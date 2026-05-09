<?php

namespace XLaravel\Embedding\Driver\Mariadb;

use Illuminate\Support\Facades\DB;
use Throwable;
use XLaravel\Embedding\Contracts\VectorStoreMetrics;
use XLaravel\Embedding\Models\Embedding;

class MariadbVectorStoreMetrics implements VectorStoreMetrics
{
    public function snapshot(): array
    {
        $rows = Embedding::query()->count();
        $bytes = null;
        $dataBytes = null;
        $indexBytes = null;

        try {
            $row = DB::connection(config('embedding.database.connection'))
                ->selectOne(
                    'SELECT
                        data_length AS data_bytes,
                        index_length AS index_bytes,
                        (data_length + index_length) AS total_bytes
                     FROM information_schema.TABLES
                     WHERE table_schema = DATABASE()
                       AND table_name = ?',
                    [config('embedding.database.table')]
                );

            if ($row !== null) {
                $bytes = isset($row->total_bytes) ? (int) $row->total_bytes : null;
                $dataBytes = isset($row->data_bytes) ? (int) $row->data_bytes : null;
                $indexBytes = isset($row->index_bytes) ? (int) $row->index_bytes : null;
            }
        } catch (Throwable) {
            // information_schema is normally readable, but a restricted
            // user may lack the SELECT privilege. Leave the byte fields
            // null so embedding:status renders them as "n/a".
        }

        return [
            'rows' => $rows,
            'bytes' => $bytes,
            'data_bytes' => $dataBytes,
            'index_bytes' => $indexBytes,
        ];
    }
}
