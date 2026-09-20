<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The predefined list an Admin maintains for the Category field on Site
 * Ledger, Company Ledger, and Finance entries (Expenses, Sub Contractor
 * Payments). Deliberately not a foreign key from those entries - a category
 * is stored as plain text, so removing a category here never touches
 * historical entries that already used it.
 */
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class LedgerCategory extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    protected $fillable = ['name', 'created_by'];
}
