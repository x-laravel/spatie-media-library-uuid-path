<?php

namespace XLaravel\SpatieMediaLibraryUuidPath\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use XLaravel\SpatieMediaLibraryUuidPath\MediaLibraryUuidPathServiceProvider;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            MediaLibraryUuidPathServiceProvider::class,
        ];
    }
}
