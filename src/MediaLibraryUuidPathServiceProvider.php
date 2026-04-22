<?php

namespace XLaravel\SpatieMediaLibraryUuidPath;

use Illuminate\Support\ServiceProvider;
use XLaravel\SpatieMediaLibraryUuidPath\Commands\CleanOrphanedUuidDirectoriesCommand;
use XLaravel\SpatieMediaLibraryUuidPath\Commands\MigrateToUuidPathCommand;

class MediaLibraryUuidPathServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                CleanOrphanedUuidDirectoriesCommand::class,
                MigrateToUuidPathCommand::class,
            ]);
        }
    }
}
