<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Generates human-readable sequence numbers (e.g. WO-202608-0001) for models
 * that expose a static $sequencePrefix and $sequenceColumn.
 */
trait HasSequenceNumber
{
    public static function bootHasSequenceNumber(): void
    {
        static::creating(function ($model) {
            $column = $model->sequenceColumn ?? 'number';

            if (! empty($model->{$column})) {
                return;
            }

            $prefix = $model->sequencePrefix ?? 'DOC';
            $period = now()->format('Ym');

            $count = DB::table($model->getTable())
                ->where($column, 'like', "{$prefix}-{$period}-%")
                ->count();

            $model->{$column} = sprintf('%s-%s-%04d', $prefix, $period, $count + 1);
        });
    }
}
