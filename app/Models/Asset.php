<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The predefined list an Admin maintains for the WO Equipment section's
 * asset picker. Deliberately not a foreign key from WorkOrderAsset - an
 * entry's asset name is stored as plain text, so removing an asset here
 * never touches historical entries that already used it.
 */
class Asset extends Model
{
    protected $fillable = ['name', 'created_by'];
}
