<?php

namespace XLaravel\SpatieMediaLibraryUuidPath\Tests\Commands;

use Illuminate\Support\Facades\Storage;
use Mockery;
use Spatie\MediaLibrary\MediaCollections\MediaRepository;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use XLaravel\SpatieMediaLibraryUuidPath\Tests\TestCase;

class CleanOrphanedUuidDirectoriesCommandTest extends TestCase
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

    private function mockRepository(array $uuids): void
    {
        $media = collect($uuids)->map(function (string $uuid) {
            $m = Mockery::mock(Media::class)->makePartial();
            $m->uuid = $uuid;

            return $m;
        });

        $repository = Mockery::mock(MediaRepository::class);
        $repository->shouldReceive('all')->andReturn($media->lazy());

        $this->app->instance(MediaRepository::class, $repository);
    }

    private function createUuidDirectory(string $uuid, ?string $file = null): void
    {
        $path = substr($uuid, 0, 2) . '/' . substr($uuid, 2, 2) . '/' . substr($uuid, 4, 2) . '/' . substr($uuid, 6, 2) . '/' . $uuid;

        if ($file) {
            Storage::disk($this->disk)->put("{$path}/{$file}", 'content');
        } else {
            Storage::disk($this->disk)->makeDirectory($path);
        }
    }

    public function test_deletes_orphaned_uuid_directories(): void
    {
        $orphanedUuid = '550e8400-e29b-41d4-a716-446655440000';
        $this->createUuidDirectory($orphanedUuid, 'photo.jpg');

        $this->mockRepository([]);

        $this->artisan('media-library:clean-uuid')
            ->expectsOutput("Orphaned UUID directory `55/0e/84/00/{$orphanedUuid}` has been removed")
            ->expectsOutput('All done!')
            ->assertSuccessful();

        $this->assertFalse(Storage::disk($this->disk)->directoryExists("55/0e/84/00/{$orphanedUuid}"));
    }

    public function test_does_not_delete_directories_with_existing_media(): void
    {
        $existingUuid = '550e8400-e29b-41d4-a716-446655440000';
        $this->createUuidDirectory($existingUuid, 'photo.jpg');

        $this->mockRepository([$existingUuid]);

        $this->artisan('media-library:clean-uuid')
            ->expectsOutput('No orphaned UUID directories found.')
            ->assertSuccessful();

        $this->assertTrue(Storage::disk($this->disk)->directoryExists("55/0e/84/00/{$existingUuid}"));
    }

    public function test_dry_run_does_not_delete_directories(): void
    {
        $orphanedUuid = '550e8400-e29b-41d4-a716-446655440000';
        $this->createUuidDirectory($orphanedUuid, 'photo.jpg');

        $this->mockRepository([]);

        $this->artisan('media-library:clean-uuid', ['--dry-run' => true])
            ->expectsOutput("Orphaned UUID directory `55/0e/84/00/{$orphanedUuid}` found")
            ->assertSuccessful();

        $this->assertTrue(Storage::disk($this->disk)->directoryExists("55/0e/84/00/{$orphanedUuid}"));
    }

    public function test_cleans_empty_shard_directories_after_deletion(): void
    {
        $orphanedUuid = '550e8400-e29b-41d4-a716-446655440000';
        $this->createUuidDirectory($orphanedUuid, 'photo.jpg');

        $this->mockRepository([]);

        $this->artisan('media-library:clean-uuid')->assertSuccessful();

        $this->assertFalse(Storage::disk($this->disk)->directoryExists('55/0e/84/00'));
        $this->assertFalse(Storage::disk($this->disk)->directoryExists('55/0e/84'));
        $this->assertFalse(Storage::disk($this->disk)->directoryExists('55/0e'));
        $this->assertFalse(Storage::disk($this->disk)->directoryExists('55'));
    }

    public function test_reports_no_orphaned_directories_when_disk_is_empty(): void
    {
        $this->mockRepository([]);

        $this->artisan('media-library:clean-uuid')
            ->expectsOutput('No orphaned UUID directories found.')
            ->assertSuccessful();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
