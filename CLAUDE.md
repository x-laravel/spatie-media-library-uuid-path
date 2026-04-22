# spatie-media-library-uuid-path

## Overview
A Laravel package providing a UUID-based path generator for spatie/laravel-medialibrary. Files are sharded into a four-level directory tree (`55/0e/84/00/<uuid>/`) derived from the first 8 characters of the media UUID.

- **Package name:** `x-laravel/spatie-media-library-uuid-path`
- **Namespace:** `XLaravel\SpatieMediaLibraryUuidPath`
- **Location:** `~/Projects/x-laravel/spatie-media-library-uuid-path`

## Requirements
- PHP ^8.2
- spatie/laravel-medialibrary ^11.0
- Orchestra Testbench ^10.0 | ^11.0 (dev)
- PHPUnit ^11.0 | ^12.0 (dev)
- Mockery ^1.0 (dev)

## Source Files (`src/`)

| File | Responsibility |
|------|----------------|
| `UuidPathGenerator.php` | Implements `PathGenerator`. Derives a 4-level shard path from the UUID and appends `conversions/` or `responsive-images/` subdirectories as needed. Throws `RuntimeException` if `uuid` is null. |
| `UuidFileRemover.php` | Extends `DefaultFileRemover`. After the parent deletes the UUID directory, walks up the 4 shard levels and removes each one if it is empty (`allFiles()` returns empty). Handles separate `conversions_disk`. |
| `Commands/CleanOrphanedUuidDirectoriesCommand.php` | `php artisan media-library:clean-uuid` — lists all directories on the configured disk, filters by UUID regex, deletes ones whose UUID is absent from the `media` table, then cleans up empty shard parents. Supports `--dry-run` and `--force`. |
| `MediaLibraryUuidPathServiceProvider.php` | Registers `CleanOrphanedUuidDirectoriesCommand`. |

## Why a custom FileRemover?

`DefaultFileRemover` deletes the UUID directory (`55/0e/84/00/<uuid>/`) but leaves the shard parent directories behind when they become empty. `UuidFileRemover` adds a cascade cleanup step after parent deletion.

## Why a custom artisan command?

`media-library:clean`'s `deleteOrphanedDirectories()` uses `is_numeric()` to identify directories, which only matches the default ID-based path structure. UUID shard directories (e.g. `55/`, `0e/`) are non-numeric and invisible to it. `media-library:clean-uuid` uses a UUID regex instead.

## Git Commits

Never create a commit unless the user explicitly requests it. Always wait for a clear instruction before running `git commit`.

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

## CI/CD
`.github/workflows/tests.yml` runs a matrix of PHP 8.2–8.5 × Laravel 12–13 (7 combinations). PHP 8.2 + Laravel 13 is excluded because Laravel 13 requires PHP ^8.3.
