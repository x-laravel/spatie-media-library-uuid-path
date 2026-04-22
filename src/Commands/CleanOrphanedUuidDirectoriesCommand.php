<?php

namespace XLaravel\SpatieMediaLibraryUuidPath\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Contracts\Filesystem\Factory;
use Spatie\MediaLibrary\MediaCollections\Exceptions\DiskDoesNotExist;
use Spatie\MediaLibrary\MediaCollections\MediaRepository;

class CleanOrphanedUuidDirectoriesCommand extends Command
{
    use ConfirmableTrait;

    protected $signature = 'media-library:clean-uuid
        {disk? : The disk to clean}
        {--dry-run : List directories that will be removed without removing them}
        {--force : Force the operation to run when in production}';

    protected $description = 'Clean orphaned UUID media directories left behind by UuidPathGenerator.';

    public function handle(MediaRepository $mediaRepository, Factory $fileSystem): void
    {
        if (! $this->confirmToProceed()) {
            return;
        }

        $diskName = $this->argument('disk') ?: config('media-library.disk_name');

        if (is_null(config("filesystems.disks.{$diskName}"))) {
            throw DiskDoesNotExist::create($diskName);
        }

        $isDryRun = $this->option('dry-run');

        $prefix = config('media-library.prefix', '');
        $prefix = $prefix !== '' ? trim($prefix, '/') . '/' : '';

        $existingUuids = $mediaRepository->all()
            ->pluck('uuid')
            ->filter()
            ->flip();

        $allDirectories = $fileSystem->disk($diskName)->allDirectories($prefix ?: null);

        $orphaned = collect($allDirectories)
            ->filter(fn (string $dir) => (bool) preg_match(
                '/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
                $dir
            ))
            ->reject(fn (string $dir) => $existingUuids->has(basename($dir)));

        if ($orphaned->isEmpty()) {
            $this->info('No orphaned UUID directories found.');

            return;
        }

        $orphaned->each(function (string $dir) use ($diskName, $isDryRun, $fileSystem, $prefix) {
            if (! $isDryRun) {
                $fileSystem->disk($diskName)->deleteDirectory($dir);
                $this->cleanEmptyShardParents($dir, $diskName, $prefix, $fileSystem);
            }

            $this->info("Orphaned UUID directory `{$dir}` " . ($isDryRun ? 'found' : 'has been removed'));
        });

        $this->info('All done!');
    }

    protected function cleanEmptyShardParents(string $uuidDir, string $disk, string $prefix, Factory $fileSystem): void
    {
        $relative = $prefix !== '' ? substr($uuidDir, strlen($prefix)) : $uuidDir;
        $parts = explode('/', $relative);

        // Expected structure: shard1/shard2/shard3/shard4/uuid
        if (count($parts) < 5) {
            return;
        }

        [$s1, $s2, $s3, $s4] = $parts;

        $shards = [
            $prefix . $s1 . '/' . $s2 . '/' . $s3 . '/' . $s4,
            $prefix . $s1 . '/' . $s2 . '/' . $s3,
            $prefix . $s1 . '/' . $s2,
            $prefix . $s1,
        ];

        foreach ($shards as $shard) {
            if (! $fileSystem->disk($disk)->allFiles($shard)) {
                $fileSystem->disk($disk)->deleteDirectory($shard);
            }
        }
    }
}
