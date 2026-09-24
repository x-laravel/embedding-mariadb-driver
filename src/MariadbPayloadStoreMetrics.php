<?php

namespace XLaravel\Embedding\Driver\Mariadb;

use Illuminate\Support\Facades\DB;
use Throwable;
use XLaravel\Embedding\Contracts\PayloadStoreMetrics;
use XLaravel\Embedding\Models\Embeddable;

class MariadbPayloadStoreMetrics implements PayloadStoreMetrics
{
    public function snapshot(): array
    {
        $rows = Embeddable::query()->count();
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
                    [config('embedding.database.embeddables_table', 'embeddables')]
                );

            if ($row !== null) {
                $bytes = isset($row->total_bytes) ? (int) $row->total_bytes : null;
                $dataBytes = isset($row->data_bytes) ? (int) $row->data_bytes : null;
                $indexBytes = isset($row->index_bytes) ? (int) $row->index_bytes : null;
            }
        } catch (Throwable) {
            //
        }

        return [
            'rows' => $rows,
            'bytes' => $bytes,
            'data_bytes' => $dataBytes,
            'index_bytes' => $indexBytes,
        ];
    }
}
