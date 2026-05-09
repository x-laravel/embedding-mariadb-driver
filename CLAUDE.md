# CLAUDE.md — embedding-mariadb-driver

This file provides guidance to Claude Code (claude.ai/code) when working with this repository.

## Overview

MariaDB 11.7 native vector driver for `x-laravel/embedding`. Handles both similarity search and vector storage using MariaDB's vector functions.

- **Package name:** `x-laravel/embedding-mariadb-driver` — **Namespace:** `XLaravel\Embedding\Driver\Mariadb`
- PHP `^8.3`, Laravel (illuminate) `^12.0|^13.0`, `x-laravel/embedding ^1.2`
- MariaDB 11.7+
- Dev: Orchestra Testbench `^10.0|^11.0`, PHPUnit `^11.0|^12.0`

## Running Tests

```bash
# Build once per PHP version
DOCKER_BUILDKIT=0 docker compose --profile php83 build

# Run all tests
docker compose --profile php83 up   # PHP 8.3
docker compose --profile php84 up   # PHP 8.4
docker compose --profile php85 up   # PHP 8.5

# Run a single test class or method
docker compose --profile php83 run --rm php83 vendor/bin/phpunit --filter MariadbDriverTest
docker compose --profile php83 run --rm php83 vendor/bin/phpunit --filter test_identical_vector_returns_score_of_one
```

Tests require a live MariaDB 11.7 instance — the `mariadb` service in `docker-compose.yml` provides it. CI runs PHP 8.3–8.5 via `.github/workflows/tests.yml`.

## Source Files (`src/`)

| File | Responsibility |
|------|----------------|
| `MariadbDriver.php` | Implements `SimilarityDriver`. Builds `1 - VEC_Distance_Cosine(vector, Vec_FromText(?))` query, loads models via `findMany()`, sets `similarity_score` on each. |
| `MariadbVectorStore.php` | Implements `VectorStore`. Writes embeddings via `INSERT ... ON DUPLICATE KEY UPDATE` with `Vec_FromText(?)`. |
| `MariadbVectorStoreMetrics.php` | Implements `VectorStoreMetrics`. Returns `Embedding::count()` for `rows`; reads `data_length` / `index_length` / `(data_length + index_length)` from `information_schema.TABLES`. Falls back to `null` byte fields if the user lacks `information_schema` access. |
| `MariadbEmbeddingServiceProvider.php` | `register()` binds `VectorStore` → `MariadbVectorStore` and `VectorStoreMetrics` → `MariadbVectorStoreMetrics`. `boot()` registers `mariadb` similarity driver, adds `VEC_ToText` global scope to `Embedding` model, loads migration, publishes under `embedding-mariadb-migrations` tag. |

## Test Structure (`tests/`)

| Path | Purpose |
|------|---------|
| `TestCase.php` | Base test case. Boots `EmbeddingServiceProvider` + `MariadbEmbeddingServiceProvider`, sets up MariaDB connection from env vars, calls `Embeddings::fake()`. |
| `Models/Post.php` | Fixture model using `#[EmbedOn]` and `Embeddable` trait. |
| `database/migrations/` | Creates `posts` table for tests. |
| `Feature/MariadbDriverTest.php` | Tests similarity search: sort order, threshold, limit, `where` filter, empty results. Uses `setVector()` helper to write known vectors via `Vec_FromText()`. |
| `Feature/MariadbEmbeddingServiceProviderTest.php` | Tests driver registration, `VectorStore` binding, and embedding read/write via native vector storage. |

## Driver Lifecycle

```
register()
  ├─► app->bind(VectorStore::class, MariadbVectorStore::class)
  └─► app->bind(VectorStoreMetrics::class, MariadbVectorStoreMetrics::class)

boot()
  ├─► loadMigrationsFrom(...)
  ├─► publishes([...], 'embedding-mariadb-migrations')
  ├─► SimilarityManager::extend('mariadb', fn() => new MariadbDriver())
  └─► Embedding::addGlobalScope('mariadb_vector_read', VEC_ToText scope)
```

`VectorStore` must be bound in `register()` — before `EmbeddingGenerator` is first resolved by the container.

## Key Design Decisions

**Write — `Vec_FromText`:** MariaDB stores vectors as `BLOB`. `Vec_FromText('[1.0,2.0,...]')` converts the JSON string to MariaDB's binary vector format. `MariadbVectorStore` uses a raw `INSERT ... ON DUPLICATE KEY UPDATE` instead of Eloquent's `updateOrCreate`.

**Read — `VEC_ToText` global scope:** MariaDB returns the `BLOB` vector in binary format. The global scope wraps the column in `VEC_ToText()` so it arrives as a JSON-parseable string (`[1.0,2.0,...]`) and the `json` cast on `Embedding` model works correctly.

**BLOB column:** MariaDB 11.7 does not have a dedicated VECTOR column type — vectors are stored as `BLOB`. The migration uses `$table->binary('vector')` which creates a `BLOB` column.

**Driver name:** Registered as `mariadb` to match Laravel 11+'s database driver name for MariaDB connections (`getDriverName()` returns `mariadb`), enabling auto-detection.

**Migration:** Uses Laravel's Blueprint `binary()` method which creates a `BLOB` column on MariaDB.

## Migration

Publish and run the MariaDB migration **instead of** the core `embedding-migrations`:

```bash
composer require x-laravel/embedding-mariadb-driver
php artisan vendor:publish --tag=embedding-mariadb-migrations
php artisan migrate
```

## Git Commits

Never create a commit unless the user explicitly requests it.
