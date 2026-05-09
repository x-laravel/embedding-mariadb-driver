<?php

namespace XLaravel\Embedding\Driver\Mariadb\Tests\Feature;

use XLaravel\Embedding\Contracts\VectorStore;
use XLaravel\Embedding\Contracts\VectorStoreMetrics;
use XLaravel\Embedding\Driver\Mariadb\MariadbDriver;
use XLaravel\Embedding\Driver\Mariadb\MariadbVectorStore;
use XLaravel\Embedding\Driver\Mariadb\MariadbVectorStoreMetrics;
use XLaravel\Embedding\Driver\Mariadb\Tests\Fixtures\Models\Post;
use XLaravel\Embedding\Driver\Mariadb\Tests\TestCase;
use XLaravel\Embedding\SimilarityManager;

class MariadbEmbeddingServiceProviderTest extends TestCase
{
    public function test_it_registers_the_mariadb_driver(): void
    {
        $manager = app(SimilarityManager::class);

        $this->assertInstanceOf(MariadbDriver::class, $manager->driver('mariadb'));
    }

    public function test_it_can_be_set_as_the_default_driver(): void
    {
        $manager = app(SimilarityManager::class);
        $manager->forgetDrivers();

        config(['embedding.similarity.driver' => 'mariadb']);

        $this->assertInstanceOf(MariadbDriver::class, $manager->driver());
    }

    public function test_it_binds_mariadb_vector_store(): void
    {
        $this->assertInstanceOf(MariadbVectorStore::class, app(VectorStore::class));
    }

    public function test_it_stores_and_reads_embedding_via_native_vector(): void
    {
        $post = Post::create(['title' => 'Laravel', 'body' => 'PHP Framework']);

        $this->assertNotNull($post->fresh()->embedding);
        $this->assertIsArray($post->fresh()->embedding->vector);
    }

    public function test_it_binds_mariadb_vector_store_metrics(): void
    {
        $this->assertInstanceOf(MariadbVectorStoreMetrics::class, app(VectorStoreMetrics::class));
    }

    public function test_metrics_snapshot_reports_rows_and_byte_sizes(): void
    {
        Post::create(['title' => 'Laravel', 'body' => 'PHP Framework']);

        $snapshot = app(VectorStoreMetrics::class)->snapshot();

        $this->assertSame(1, $snapshot['rows']);
        $this->assertIsInt($snapshot['bytes']);
        $this->assertIsInt($snapshot['data_bytes']);
        $this->assertIsInt($snapshot['index_bytes']);
        $this->assertGreaterThan(0, $snapshot['bytes']);
    }
}
