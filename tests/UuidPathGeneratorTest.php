<?php

namespace XLaravel\SpatieMediaLibraryUuidPath\Tests;

use Mockery;
use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use XLaravel\SpatieMediaLibraryUuidPath\UuidPathGenerator;

class UuidPathGeneratorTest extends TestCase
{
    private UuidPathGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->generator = new UuidPathGenerator();
    }

    private function makeMedia(string $uuid): Media
    {
        $media = Mockery::mock(Media::class)->makePartial();
        $media->uuid = $uuid;

        return $media;
    }

    public function test_getPath_returns_nested_uuid_path(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $media = $this->makeMedia($uuid);

        $path = $this->generator->getPath($media);

        $this->assertSame("55/0e/84/00/{$uuid}/", $path);
    }

    public function test_getPath_throws_when_uuid_is_null(): void
    {
        $media = Mockery::mock(Media::class)->makePartial();
        $media->uuid = null;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Media UUID is not set.');

        $this->generator->getPath($media);
    }

    public function test_getPathForConversions_appends_conversions_directory(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $media = $this->makeMedia($uuid);

        $path = $this->generator->getPathForConversions($media);

        $this->assertSame("55/0e/84/00/{$uuid}/conversions/", $path);
    }

    public function test_getPathForResponsiveImages_appends_responsive_images_directory(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $media = $this->makeMedia($uuid);

        $path = $this->generator->getPathForResponsiveImages($media);

        $this->assertSame("55/0e/84/00/{$uuid}/responsive-images/", $path);
    }

    public function test_getPath_segments_are_derived_from_uuid_first_eight_chars(): void
    {
        $uuid = 'abcdef12-0000-0000-0000-000000000000';
        $media = $this->makeMedia($uuid);

        $path = $this->generator->getPath($media);

        $this->assertStringStartsWith('ab/cd/ef/12/', $path);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
