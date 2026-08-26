<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The predefined list an Admin maintains for the Site Ledger's Category
 * field. Deliberately not a foreign key from Ledger - a ledger entry's
 * category is stored as plain text, so removing a category here never
 * touches historical entries that already used it.
 */
class LedgerCategory extends Model
{
    protected $fillable = ['name', 'created_by'];
}
