<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Same numbering scheme as HasSequenceNumber (see that trait's own
 * comments for why MAX-not-COUNT and the defensive collision loop), but
 * the prefix also carries the issuing Company's code - e.g. "PI-ACME" -
 * so each company's documents number independently even though they
 * share one table. Requires $model->company_id to already be set (it
 * will be, since it's filled before creating() fires) and the related
 * Company to have a `code`.
 */
trait HasCompanySequenceNumber
{
    public static function bootHasCompanySequenceNumber(): void
    {
        static::creating(function ($model) {
            $column = $model->sequenceColumn ?? 'number';

            if (! empty($model->{$column})) {
                return;
            }

            $companyCode = \App\Models\Company::where('id', $model->company_id)->value('code') ?? 'CO';
            $prefix = ($model->sequencePrefix ?? 'DOC').'-'.$companyCode;
            $period = now()->format('Ym');
            $pattern = "{$prefix}-{$period}-";

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
