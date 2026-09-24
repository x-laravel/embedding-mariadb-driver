# Changelog

All notable changes to `x-laravel/embedding-mariadb-driver` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/). The package's major version follows `laravel/ai`.

## 1.0.0 - 2026-09-24

Initial release. Requires PHP ^8.3, Laravel ^12.0 | ^13.0, `x-laravel/embedding` ^1.0 and MariaDB 11.7+.

### Added

- `MariadbDriver` — `mariadb` similarity driver running cosine search in MariaDB with `VEC_Distance_Cosine`. Distance is converted to `similarity_score = 1 - distance`, and the cutoff applies only when `threshold > 0.0`. Soft-deleting models are loaded with `withTrashed()`.
- Payload `filter` translation for `similarTo()` / `similarToText()` / `mostSimilar()`: a `whereExists` subquery against `embeddables` using `JSON_EXTRACT` / `JSON_VALUE`. Every condition carries a `JSON_TYPE` guard so `34` never matches `"34"`, numbers compare as `DOUBLE`, booleans are inlined as JSON literals, and filter keys are validated before being interpolated into the JSON path.
- `MariadbVectorStore` — writes embeddings with `INSERT ... ON DUPLICATE KEY UPDATE` and `Vec_FromText()`; a global scope on the embedding model reads vectors back through `VEC_ToText()`.
- `MariadbVectorStoreMetrics` and `MariadbPayloadStoreMetrics` — storage figures from `information_schema.TABLES`.
- MariaDB-native migrations with the core package's filenames: `embeddings` with a `BLOB` vector column and `embeddables` with a `JSON` payload column, published under the `embedding-mariadb-migrations` tag.
