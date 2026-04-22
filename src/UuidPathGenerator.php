<?php

namespace XLaravel\SpatieMediaLibraryUuidPath;

use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

class UuidPathGenerator implements PathGenerator
{
    public function getPath(Media $media): string
    {
        $uuid = $media->uuid ?? throw new \RuntimeException('Media UUID is not set.');

        return substr($uuid, 0, 2) . '/' .
            substr($uuid, 2, 2) . '/' .
            substr($uuid, 4, 2) . '/' .
            substr($uuid, 6, 2) . '/' .
            $uuid . '/';
    }

    public function getPathForConversions(Media $media): string
    {
        return $this->getPath($media) . 'conversions/';
    }

    public function getPathForResponsiveImages(Media $media): string
    {
        return $this->getPath($media) . 'responsive-images/';
    }
}
