<?php

namespace XLaravel\SpatieMediaLibraryUuidPath;

use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\FileRemover\DefaultFileRemover;

class UuidFileRemover extends DefaultFileRemover
{
    public function removeAllFiles(Media $media): void
    {
        parent::removeAllFiles($media);

        $this->cleanEmptyShardDirectories($media, $media->disk);

        if ($media->conversions_disk && $media->disk !== $media->conversions_disk) {
            $this->cleanEmptyShardDirectories($media, $media->conversions_disk);
        }
    }

    protected function cleanEmptyShardDirectories(Media $media, string $disk): void
    {
        $uuid = $media->uuid;

        if (! $uuid) {
            return;
        }

        $prefix = config('media-library.prefix', '');
        $prefix = $prefix !== '' ? rtrim($prefix, '/') . '/' : '';

        $shards = [
            $prefix . substr($uuid, 0, 2) . '/' . substr($uuid, 2, 2) . '/' . substr($uuid, 4, 2) . '/' . substr($uuid, 6, 2),
            $prefix . substr($uuid, 0, 2) . '/' . substr($uuid, 2, 2) . '/' . substr($uuid, 4, 2),
            $prefix . substr($uuid, 0, 2) . '/' . substr($uuid, 2, 2),
            $prefix . substr($uuid, 0, 2),
        ];

        foreach ($shards as $shard) {
            if (! $this->filesystem->disk($disk)->allFiles($shard)) {
                $this->filesystem->disk($disk)->deleteDirectory($shard);
            }
        }
    }
}
