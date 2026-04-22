<?php

namespace XLaravel\SpatieMediaLibraryUuidPath;

use Illuminate\Support\ServiceProvider;
use XLaravel\SpatieMediaLibraryUuidPath\Commands\CleanOrphanedUuidDirectoriesCommand;

class MediaLibraryUuidPathServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                CleanOrphanedUuidDirectoriesCommand::class,
            ]);
        }
    }
}
