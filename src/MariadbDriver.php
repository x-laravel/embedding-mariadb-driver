<?php

namespace XLaravel\Embedding\Driver\Mariadb;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use InvalidArgumentException;
use XLaravel\Embedding\Contracts\SearchRequest;
use XLaravel\Embedding\Contracts\SimilarityDriver;
use XLaravel\Embedding\Models\Embeddable as EmbeddableRecord;

class MariadbDriver implements SimilarityDriver
{
    /**
     * Search for models similar to the request's query vector using VEC_Distance_Cosine.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, \Illuminate\Database\Eloquent\Model>
     */
    public function search(Model $prototype, SearchRequest $request): Collection
    {
        $morphClass = $prototype->getMorphClass();
        $embeddingClass = config('embedding.model');
        $vectorJson = json_encode($request->vector);

        $query = app($embeddingClass)
            ->where('embeddable_type', $morphClass)
            ->where('slot', $request->slot)
            ->selectRaw(
                'embeddable_id, 1 - VEC_Distance_Cosine(`vector`, Vec_FromText(?)) AS similarity_score',
                [$vectorJson]
            )
            ->orderByDesc('similarity_score')
            ->limit($request->limit);

        if ($request->threshold > 0.0) {
            $query->whereRaw(
                '1 - VEC_Distance_Cosine(`vector`, Vec_FromText(?)) >= ?',
                [$vectorJson, $request->threshold]
            );
        }

        if ($request->ids !== null) {
            $query->whereIn('embeddable_id', $request->ids);
        }

        if (! empty($request->filter)) {
            $this->applyPayloadFilter($query, $request->filter);
        }

        $results = $query->get();

        $matchedIds = $results->pluck('embeddable_id')->all();
        $scores = $results->pluck('similarity_score', 'embeddable_id')->all();

        $modelQuery = in_array(SoftDeletes::class, class_uses_recursive($prototype), true)
            ? $prototype::query()->withTrashed()
            : $prototype::query();

        return $modelQuery->findMany($matchedIds)
            ->each(fn ($m) => $m->setAttribute('similarity_score', (float) ($scores[$m->getKey()] ?? 0.0)))
            ->sortByDesc(fn ($m) => $m->getAttribute('similarity_score'))
            ->values();
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @param  array<string, mixed>  $filter
     */
    protected function applyPayloadFilter($query, array $filter): void
    {
        $embeddingsTable = $query->getModel()->getTable();
        $embeddablesTable = (new EmbeddableRecord())->getTable();

        $query->whereExists(function ($exists) use ($filter, $embeddingsTable, $embeddablesTable) {
            $exists->from($embeddablesTable)
                ->whereColumn("{$embeddablesTable}.embeddable_type", "{$embeddingsTable}.embeddable_type")
                ->whereColumn("{$embeddablesTable}.embeddable_id", "{$embeddingsTable}.embeddable_id");

            foreach ($filter as $key => $value) {
                $this->applyPayloadCondition($exists, $key, $value);
            }
        });
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     */
    protected function applyPayloadCondition($query, string $key, mixed $value): void
    {
        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key)) {
            throw new InvalidArgumentException("Invalid payload filter key [{$key}].");
        }

        if (! is_array($value)) {
            $this->wherePayloadEquals($query, $key, $value);

            return;
        }

        if ($value === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(function ($group) use ($key, $value) {
            foreach ($value as $candidate) {
                $group->orWhere(fn ($branch) => $this->wherePayloadEquals($branch, $key, $candidate));
            }
        });
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     */
    protected function wherePayloadEquals($query, string $key, mixed $value): void
    {
        $path = "'$.{$key}'";
        $typeExpr = "JSON_TYPE(JSON_EXTRACT(payload, {$path}))";

        if (is_int($value) || is_float($value)) {
            $query->whereRaw("{$typeExpr} IN ('INTEGER', 'DOUBLE')")
                ->whereRaw("CAST(JSON_VALUE(payload, {$path}) AS DOUBLE) = CAST(? AS DOUBLE)", [$value]);
        } elseif (is_bool($value)) {
            $literal = $value ? 'true' : 'false';
            $query->whereRaw("{$typeExpr} = 'BOOLEAN'")
                ->whereRaw("JSON_EXTRACT(payload, {$path}) = '{$literal}'");
        } elseif (is_string($value)) {
            $query->whereRaw("{$typeExpr} = 'STRING'")
                ->whereRaw("CAST(JSON_VALUE(payload, {$path}) AS BINARY) = CAST(? AS BINARY)", [$value]);
        } elseif ($value === null) {
            $query->whereRaw("{$typeExpr} = 'NULL'");
        } else {
            throw new InvalidArgumentException("Payload filter values must be scalar or arrays of scalars [{$key}].");
        }
    }
}
