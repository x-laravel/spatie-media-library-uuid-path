<?php

namespace XLaravel\SpatieMediaLibraryUuidPath\Tests;

use Illuminate\Contracts\Filesystem\Factory;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Spatie\MediaLibrary\MediaCollections\Filesystem as SpatieFilesystem;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use XLaravel\SpatieMediaLibraryUuidPath\UuidFileRemover;

class UuidFileRemoverTest extends TestCase
{
    private string $disk = 'test-disk';

    private UuidFileRemover $remover;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake($this->disk);

        $this->remover = new class(
            Mockery::mock(SpatieFilesystem::class),
            app(Factory::class)
        ) extends UuidFileRemover {
            public function cleanShards(Media $media, string $disk): void
            {
                $this->cleanEmptyShardDirectories($media, $disk);
            }
        };
    }

    private function makeMedia(string $uuid): Media
    {
        $media = Mockery::mock(Media::class)->makePartial();
        $media->uuid = $uuid;

        return $media;
    }

    public function test_deletes_all_empty_shard_directories(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';

        Storage::disk($this->disk)->makeDirectory("55/0e/84/00/{$uuid}");

        $this->remover->cleanShards($this->makeMedia($uuid), $this->disk);

        $this->assertFalse(Storage::disk($this->disk)->directoryExists('55/0e/84/00'));
        $this->assertFalse(Storage::disk($this->disk)->directoryExists('55/0e/84'));
        $this->assertFalse(Storage::disk($this->disk)->directoryExists('55/0e'));
        $this->assertFalse(Storage::disk($this->disk)->directoryExists('55'));
    }

    public function test_does_not_delete_shard_directory_that_still_has_files(): void
    {
        $uuid1 = '550e8400-e29b-41d4-a716-446655440000';
        $uuid2 = '550e8401-e29b-41d4-a716-446655440001';

        Storage::disk($this->disk)->put("55/0e/84/00/{$uuid2}/photo.jpg", 'content');
        Storage::disk($this->disk)->makeDirectory("55/0e/84/00/{$uuid1}");

        $this->remover->cleanShards($this->makeMedia($uuid1), $this->disk);

        $this->assertTrue(Storage::disk($this->disk)->directoryExists('55/0e/84/00'));
        $this->assertTrue(Storage::disk($this->disk)->exists("55/0e/84/00/{$uuid2}/photo.jpg"));
    }

    public function test_stops_cascade_at_non_empty_shard_level(): void
    {
        $uuid1 = '550e8400-e29b-41d4-a716-446655440000';
        $uuid2 = '550effff-e29b-41d4-a716-446655440001'; // same 55/ but different 0e/ shard

        Storage::disk($this->disk)->put("55/ef/ff/ff/{$uuid2}/photo.jpg", 'content');
        Storage::disk($this->disk)->makeDirectory("55/0e/84/00/{$uuid1}");

        $this->remover->cleanShards($this->makeMedia($uuid1), $this->disk);

        $this->assertFalse(Storage::disk($this->disk)->directoryExists('55/0e'));
        $this->assertTrue(Storage::disk($this->disk)->directoryExists('55'));
        $this->assertTrue(Storage::disk($this->disk)->exists("55/ef/ff/ff/{$uuid2}/photo.jpg"));
    }

    public function test_does_nothing_when_uuid_is_null(): void
    {
        $media = Mockery::mock(Media::class)->makePartial();
        $media->uuid = null;

        $this->remover->cleanShards($media, $this->disk);

        $this->addToAssertionCount(1);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
