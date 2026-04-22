<?php

namespace XLaravel\SpatieMediaLibraryUuidPath\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Contracts\Filesystem\Factory;
use Spatie\MediaLibrary\MediaCollections\MediaRepository;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\DefaultPathGenerator;
use XLaravel\SpatieMediaLibraryUuidPath\UuidPathGenerator;

class MigrateToUuidPathCommand extends Command
{
    use ConfirmableTrait;

    protected $signature = 'media:migrate-to-uuid
        {disk? : The disk to migrate}
        {--dry-run : Preview changes without moving any files}
        {--force : Force the operation to run when in production}';

    protected $description = 'Migrate media files from DefaultPathGenerator (ID-based) to UuidPathGenerator.';

    private DefaultPathGenerator $oldGenerator;

    private UuidPathGenerator $newGenerator;

    public function handle(MediaRepository $mediaRepository, Factory $fileSystem): void
    {
        if (! $this->confirmToProceed()) {
            return;
        }

        $diskName = $this->argument('disk') ?: config('media-library.disk_name');
        $isDryRun = $this->option('dry-run');

        $this->oldGenerator = new DefaultPathGenerator();
        $this->newGenerator = new UuidPathGenerator();

        $migrated = 0;
        $skipped = 0;

        $mediaRepository->all()->each(function (Media $media) use (
            $diskName, $isDryRun, $fileSystem, &$migrated, &$skipped
        ) {
            if (! $media->uuid) {
                $this->warn("Skipping Media[id={$media->id}]: no UUID.");
                $skipped++;

                return;
            }

            $disk = $media->disk ?: $diskName;
            $conversionsDisk = $media->conversions_disk ?: $disk;

            $oldPath = $this->oldGenerator->getPath($media);
            $newPath = $this->newGenerator->getPath($media);

            if (! $fileSystem->disk($disk)->directoryExists($oldPath)) {
                $skipped++;

                return;
            }

            if ($isDryRun) {
                $this->line("Would move: {$oldPath} → {$newPath}");
                $migrated++;

                return;
            }

            $this->moveFile($fileSystem, $disk, $oldPath . $media->file_name, $newPath . $media->file_name);

            $this->moveDirectory(
                $fileSystem,
                $conversionsDisk,
                $this->oldGenerator->getPathForConversions($media),
                $this->newGenerator->getPathForConversions($media),
            );

            $this->moveDirectory(
                $fileSystem,
                $conversionsDisk,
                $this->oldGenerator->getPathForResponsiveImages($media),
                $this->newGenerator->getPathForResponsiveImages($media),
            );

            if (! $fileSystem->disk($disk)->allFiles($oldPath)) {
                $fileSystem->disk($disk)->deleteDirectory($oldPath);
            }

            $this->line("Moved: {$oldPath} → {$newPath}");
            $migrated++;
        });

        $this->info("Done! Migrated: {$migrated}, Skipped: {$skipped}.");
    }

    private function moveFile(Factory $fileSystem, string $disk, string $from, string $to): void
    {
        if (! $fileSystem->disk($disk)->exists($from)) {
            return;
        }

        $stream = $fileSystem->disk($disk)->readStream($from);
        $fileSystem->disk($disk)->put($to, $stream);
        $fileSystem->disk($disk)->delete($from);
    }

    private function moveDirectory(Factory $fileSystem, string $disk, string $from, string $to): void
    {
        if (! $fileSystem->disk($disk)->directoryExists($from)) {
            return;
        }

        foreach ($fileSystem->disk($disk)->allFiles($from) as $file) {
            $relative = substr($file, strlen($from));
            $this->moveFile($fileSystem, $disk, $file, $to . $relative);
        }

        if (! $fileSystem->disk($disk)->allFiles($from)) {
            $fileSystem->disk($disk)->deleteDirectory($from);
        }
    }
}
