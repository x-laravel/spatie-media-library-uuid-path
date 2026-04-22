# spatie-media-library-uuid-path

[![Tests](https://github.com/x-laravel/spatie-media-library-uuid-path/actions/workflows/tests.yml/badge.svg)](https://github.com/x-laravel/spatie-media-library-uuid-path/actions/workflows/tests.yml)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-blue)](https://www.php.net)
[![Laravel](https://img.shields.io/badge/Laravel-12%20|%2013-red)](https://laravel.com)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE.md)

A UUID-based path generator for [spatie/laravel-medialibrary](https://github.com/spatie/laravel-medialibrary).

## The Problem

spatie/laravel-medialibrary's default `DefaultPathGenerator` stores media files in a flat structure based on the primary key (ID):

```
1/photo.jpg
2/photo.jpg
3/photo.jpg
...
```

This works fine for small applications, but causes serious issues as the number of media files grows:

- **File system performance degradation:** File systems like ext4 and NTFS slow down directory listings when thousands of subdirectories exist under a single parent.
- **Predictable URLs:** The sequential ID-based structure makes media file URLs trivially easy to enumerate.
- **Operational overhead:** Bulk-moving, backing up, or migrating files to a CDN becomes harder with a flat layout.

## The Solution

This package distributes files evenly by turning the first 8 characters of each media UUID into a four-level directory hierarchy:

```
55/0e/84/00/550e8400-e29b-41d4-a716-446655440000/photo.jpg
```

Conversions and responsive images are placed in dedicated subdirectories under the UUID folder:

```
55/0e/84/00/550e8400-e29b-41d4-a716-446655440000/conversions/
55/0e/84/00/550e8400-e29b-41d4-a716-446655440000/responsive-images/
```

Benefits:

- **Performance:** Each directory holds at most 256 subdirectories, spreading the file system load evenly.
- **Security:** The UUID-based random structure makes file paths unpredictable and resistant to enumeration.
- **Uniqueness:** Every media file gets its own UUID directory, eliminating any risk of path collisions.

## Requirements

- PHP ^8.2
- spatie/laravel-medialibrary ^11.0

## Installation

```bash
composer require x-laravel/spatie-media-library-uuid-path
```

## Setup

Publish the spatie/laravel-medialibrary config (if you haven't already) and set the `path_generator` option:

```bash
php artisan vendor:publish --provider="Spatie\MediaLibrary\MediaLibraryServiceProvider" --tag="config"
```

In `config/media-library.php`:

```php
'path_generator' => \XLaravel\SpatieMediaLibraryUuidPath\UuidPathGenerator::class,

'file_remover_class' => \XLaravel\SpatieMediaLibraryUuidPath\UuidFileRemover::class,
```

> **Why `UuidFileRemover`?**
> The default file remover deletes the UUID directory but leaves the empty shard parent directories (`55/0e/84/00/`) behind. `UuidFileRemover` cascades upward and removes each shard level when it becomes empty.

## Cleaning Orphaned Directories

spatie's built-in `media-library:clean` command identifies orphaned directories using an `is_numeric()` check, which only works for the default ID-based path structure. This package ships a UUID-aware replacement:

```bash
php artisan media-library:clean-uuid
```

Options:

| Option | Description |
|---|---|
| `disk` | Disk to clean (defaults to `media-library.disk_name` config) |
| `--dry-run` | List orphaned directories without deleting them |
| `--force` | Skip the production confirmation prompt |

## Running Tests

```bash
# Locally
vendor/bin/phpunit

# Via Docker (specific PHP version)
docker compose --profile php82 run --rm php82
docker compose --profile php83 run --rm php83
docker compose --profile php84 run --rm php84
docker compose --profile php85 run --rm php85
```
