<?php

namespace XLaravel\Embedding\Driver\Mariadb;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use XLaravel\Embedding\Contracts\VectorStore;
use XLaravel\Embedding\Models\Embedding;

class MariadbVectorStore implements VectorStore
{
    public function store(Model $model, array $vector, string $slot): Embedding
    {
        $connection = config('embedding.database.connection');
        $table = config('embedding.database.table');
        $morphClass = $model->getMorphClass();
        $key = $model->getKey();
        $vectorJson = json_encode($vector);
        $now = now()->toDateTimeString();

        DB::connection($connection)->statement(
            "INSERT INTO `{$table}` (embeddable_type, embeddable_id, slot, `vector`, created_at, updated_at)
             VALUES (?, ?, ?, Vec_FromText(?), ?, ?)
             ON DUPLICATE KEY UPDATE `vector` = Vec_FromText(?), updated_at = ?",
            [$morphClass, $key, $slot, $vectorJson, $now, $now, $vectorJson, $now]
        );

        $embeddingClass = config('embedding.model');

        return $embeddingClass::where('embeddable_type', $morphClass)
            ->where('embeddable_id', $key)
            ->where('slot', $slot)
            ->first();
    }
}
