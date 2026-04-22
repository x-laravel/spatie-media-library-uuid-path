<?php

namespace XLaravel\SpatieMediaLibraryUuidPath\Tests\Commands;

use Illuminate\Support\Facades\Storage;
use Mockery;
use Spatie\MediaLibrary\MediaCollections\MediaRepository;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use XLaravel\SpatieMediaLibraryUuidPath\Tests\TestCase;

class MigrateToUuidPathCommandTest extends TestCase
{
    private string $disk = 'public';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake($this->disk);

        config([
            'media-library.disk_name' => $this->disk,
            'media-library.prefix' => '',
            'filesystems.disks.' . $this->disk => ['driver' => 'local', 'root' => storage_path('app/public')],
        ]);
    }

    private function makeMedia(int $id, string $uuid, string $filename, ?string $conversionsDisk = null): Media
    {
        $media = Mockery::mock(Media::class)->makePartial();
        $media->id = $id;
        $media->uuid = $uuid;
        $media->file_name = $filename;
        $media->disk = $this->disk;
        $media->conversions_disk = $conversionsDisk;

        return $media;
    }

    private function mockRepository(array $items): void
    {
        $repository = Mockery::mock(MediaRepository::class);
        $repository->shouldReceive('all')->andReturn(collect($items)->lazy());

        $this->app->instance(MediaRepository::class, $repository);
    }

    private function uuidPath(string $uuid, string $filename): string
    {
        return substr($uuid, 0, 2) . '/' . substr($uuid, 2, 2) . '/' . substr($uuid, 4, 2) . '/' . substr($uuid, 6, 2) . '/' . $uuid . '/' . $filename;
    }

    public function test_moves_main_file_from_id_path_to_uuid_path(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $media = $this->makeMedia(1, $uuid, 'photo.jpg');

        Storage::disk($this->disk)->put('1/photo.jpg', 'content');

        $this->mockRepository([$media]);

        $this->artisan('media:migrate-to-uuid')->assertSuccessful();

        Storage::disk($this->disk)->assertExists($this->uuidPath($uuid, 'photo.jpg'));
        Storage::disk($this->disk)->assertMissing('1/photo.jpg');
    }

    public function test_moves_conversion_files(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $media = $this->makeMedia(1, $uuid, 'photo.jpg');

        Storage::disk($this->disk)->put('1/photo.jpg', 'content');
        Storage::disk($this->disk)->put('1/conversions/photo-thumb.jpg', 'thumb');

        $this->mockRepository([$media]);

        $this->artisan('media:migrate-to-uuid')->assertSuccessful();

        Storage::disk($this->disk)->assertExists($this->uuidPath($uuid, 'conversions/photo-thumb.jpg'));
        Storage::disk($this->disk)->assertMissing('1/conversions/photo-thumb.jpg');
    }

    public function test_moves_responsive_image_files(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $media = $this->makeMedia(1, $uuid, 'photo.jpg');

        Storage::disk($this->disk)->put('1/photo.jpg', 'content');
        Storage::disk($this->disk)->put('1/responsive-images/photo___media_library_original_340_280.jpg', 'resp');

        $this->mockRepository([$media]);

        $this->artisan('media:migrate-to-uuid')->assertSuccessful();

        Storage::disk($this->disk)->assertExists($this->uuidPath($uuid, 'responsive-images/photo___media_library_original_340_280.jpg'));
        Storage::disk($this->disk)->assertMissing('1/responsive-images/photo___media_library_original_340_280.jpg');
    }

    public function test_deletes_old_id_directory_after_migration(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $media = $this->makeMedia(1, $uuid, 'photo.jpg');

        Storage::disk($this->disk)->put('1/photo.jpg', 'content');

        $this->mockRepository([$media]);

        $this->artisan('media:migrate-to-uuid')->assertSuccessful();

        $this->assertFalse(Storage::disk($this->disk)->directoryExists('1'));
    }

    public function test_dry_run_does_not_move_files(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $media = $this->makeMedia(1, $uuid, 'photo.jpg');

        Storage::disk($this->disk)->put('1/photo.jpg', 'content');

        $this->mockRepository([$media]);

        $this->artisan('media:migrate-to-uuid', ['--dry-run' => true])
            ->expectsOutput('Would move: 1/ → ' . substr($uuid, 0, 2) . '/' . substr($uuid, 2, 2) . '/' . substr($uuid, 4, 2) . '/' . substr($uuid, 6, 2) . '/' . $uuid . '/')
            ->assertSuccessful();

        Storage::disk($this->disk)->assertExists('1/photo.jpg');
        Storage::disk($this->disk)->assertMissing($this->uuidPath($uuid, 'photo.jpg'));
    }

    public function test_skips_media_without_uuid(): void
    {
        $media = $this->makeMedia(1, '', 'photo.jpg');
        $media->uuid = null;

        Storage::disk($this->disk)->put('1/photo.jpg', 'content');

        $this->mockRepository([$media]);

        $this->artisan('media:migrate-to-uuid')
            ->expectsOutput('Skipping Media[id=1]: no UUID.')
            ->assertSuccessful();

        Storage::disk($this->disk)->assertExists('1/photo.jpg');
    }

    public function test_skips_already_migrated_media(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $media = $this->makeMedia(1, $uuid, 'photo.jpg');

        // Only new path exists, old path is gone
        Storage::disk($this->disk)->put($this->uuidPath($uuid, 'photo.jpg'), 'content');

        $this->mockRepository([$media]);

        $this->artisan('media:migrate-to-uuid')
            ->expectsOutput('Done! Migrated: 0, Skipped: 1.')
            ->assertSuccessful();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
