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
            $pattern = "{$prefix}-{$period}-";

            // A plain COUNT of existing rows recomputes an already-used number
            // the moment any row with this prefix/period is deleted (soft or
            // hard) - COUNT drops but the row that used the higher number is
            // still there, so "count + 1" collides with it and the insert
            // fails on the column's unique constraint. Take the highest
            // number actually in use instead, so a deletion's gap never gets
            // reissued, then defensively step past any number somehow still
            // taken (e.g. a near-simultaneous request).
            $next = DB::table($model->getTable())
                ->where($column, 'like', "{$pattern}%")
                ->pluck($column)
                ->map(fn ($value) => (int) substr($value, strlen($pattern)))
                ->max() ?? 0;

            do {
                $next++;
                $candidate = sprintf('%s%04d', $pattern, $next);
            } while (DB::table($model->getTable())->where($column, $candidate)->exists());

            $model->{$column} = $candidate;
        });
    }
}
