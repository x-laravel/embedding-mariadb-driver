<?php

namespace XLaravel\Embedding\Driver\Mariadb;

use Illuminate\Support\ServiceProvider;
use XLaravel\Embedding\Contracts\VectorStore;
use XLaravel\Embedding\Models\Embedding;
use XLaravel\Embedding\SimilarityManager;

class MariadbEmbeddingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'embedding-mariadb-migrations');
        }

        $this->app->resolving(SimilarityManager::class, function (SimilarityManager $manager) {
            $manager->extend('mariadb', fn () => new MariadbDriver());
        });

        // MariaDB read: VEC_ToText converts the binary BLOB vector to a JSON-parseable string.
        // We explicitly list columns instead of using * to avoid returning raw binary data.
        Embedding::addGlobalScope('mariadb_vector_read', function ($query) {
            $table = config('embedding.database.table');
            $query->selectRaw(
                "`{$table}`.id, `{$table}`.embeddable_type, `{$table}`.embeddable_id, `{$table}`.slot,
                 VEC_ToText(`{$table}`.`vector`) AS `vector`,
                 `{$table}`.created_at, `{$table}`.updated_at"
            );
        });
    }

    public function register(): void
    {
        $this->app->bind(VectorStore::class, MariadbVectorStore::class);
    }
}
