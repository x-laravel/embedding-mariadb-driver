# x-laravel/embedding — MariaDB Driver

[![Tests](https://github.com/x-laravel/embedding-mariadb-driver/actions/workflows/tests.yml/badge.svg)](https://github.com/x-laravel/embedding-mariadb-driver/actions/workflows/tests.yml)
[![PHP](https://img.shields.io/badge/PHP-8.3%2B-blue)](https://www.php.net)
[![Laravel](https://img.shields.io/badge/Laravel-12%20|%2013-red)](https://laravel.com)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE.md)

MariaDB 11.7 native vector driver for [x-laravel/embedding](https://github.com/x-laravel/embedding).

## How It Works

- Implements `SimilarityDriver` — registers as the `mariadb` driver, similarity search runs entirely in MariaDB using `VEC_Distance_Cosine`
- Implements `VectorStore` — writes embeddings via `INSERT ... ON DUPLICATE KEY UPDATE` with `Vec_FromText()`, reads via a `VEC_ToText` global scope
- Translates payload `filter:` constraints to type-strict `JSON_TYPE` / `JSON_VALUE` SQL against the `embeddables` table

## Requirements

- PHP ^8.3
- Laravel ^12.0 | ^13.0
- `x-laravel/embedding ^1.0`
- MariaDB 11.7+

## Installation

```bash
composer require x-laravel/embedding-mariadb-driver
```

The `MariadbEmbeddingServiceProvider` is auto-discovered and registers the `mariadb` driver automatically.

## Setup

### 1. Configure x-laravel/embedding

Publish the config if you haven't already:

```bash
php artisan vendor:publish --tag=embedding-config
```

Set the similarity driver and database connection in `config/embedding.php`:

```php
'database' => [
    'connection' => env('EMBEDDINGS_DATABASE_CONNECTION', env('DB_CONNECTION', 'mariadb')),
    'embeddings_table' => env('EMBEDDINGS_DB_TABLE', 'embeddings'),
    'embeddables_table' => env('EMBEDDABLES_DB_TABLE', 'embeddables'),
],

'similarity' => [
    'driver' => env('EMBEDDING_SIMILARITY_DRIVER', 'mariadb'),
],
```

### 2. Create the tables

This driver ships its own MariaDB-native migrations that **replace** the default ones from `x-laravel/embedding`: `embeddings` with a `BLOB` column for vector storage and `embeddables` with a `JSON` payload column.

Publish and run the migrations (migrations are not loaded automatically — publish the driver migrations, **not** the core ones):

```bash
php artisan vendor:publish --tag=embedding-mariadb-migrations
php artisan migrate
```

The published files are plain migrations in `database/migrations/` — customise the DDL there if needed before running `migrate`.

> **Note:** `VEC_Distance_Cosine` requires MariaDB 11.7+. For production, add a `VECTOR INDEX` on the `vector` column after publishing the migration.

### 3. Model

Follow the standard `x-laravel/embedding` setup. No MariaDB-specific changes are needed on your models.

```php
use XLaravel\Embedding\Attributes\EmbedOn;
use XLaravel\Embedding\Concerns\Embeddable;
use XLaravel\Embedding\Contracts\HasEmbeddings;

#[EmbedOn(['title', 'body'])]
class Post extends Model implements HasEmbeddings
{
    use Embeddable;

    public function toEmbeddingText(string $slot = 'default'): string
    {
        return $this->title.' '.$this->body;
    }
}
```

## Usage

The driver is transparent — use the standard `x-laravel/embedding` API:

```php
Post::similarToText('web framework', limit: 10);
Post::similarTo($vector, limit: 10, threshold: 0.8);
Post::rankByRelevance($posts, 'web framework');

$post->mostSimilar(limit: 5);
$post->similarityTo($otherPost);
```

All methods set a `similarity_score` float attribute on each returned model.

### Payload filtering

Models using `#[EmbedPayload]` can filter similarity searches at the database level. The driver translates `filter:` to a `whereExists` subquery with `JSON_EXTRACT` / `JSON_VALUE` and a `JSON_TYPE` guard on every condition, so comparisons are type-strict (`34` never matches `"34"`):

```php
use XLaravel\Embedding\Attributes\EmbedPayload;

#[EmbedOn('name')]
#[EmbedPayload(['province_id', 'category_id', 'active'])]
class Venue extends Model implements HasEmbeddings { ... }

Venue::similarTo($vector, limit: 300, filter: ['province_id' => 34]);            // equality
Venue::similarToText('kebap', filter: ['category_id' => [3, 7]]);                // IN
$venue->mostSimilar(limit: 5, filter: ['province_id' => 34, 'active' => true]);  // AND
```

> **Note:** MariaDB stores `boolean` columns as `TINYINT(1)` and returns them to PHP as integers. Add a `boolean` cast to boolean payload fields on your models — otherwise the payload stores `1` instead of `true`, and a `true` filter stops matching.

## Testing

```bash
# Build first (once per PHP version)
DOCKER_BUILDKIT=0 docker compose --profile php83 build

# Run tests
docker compose --profile php83 up
docker compose --profile php84 up
docker compose --profile php85 up
```

## License

This package is open-sourced software licensed under the [MIT license](https://opensource.org/license/MIT).
