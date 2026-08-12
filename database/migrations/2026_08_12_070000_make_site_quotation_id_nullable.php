<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A client can have more than one physical site, but not every site comes
 * from an approved quotation (some are added directly by Admin/Sales so the
 * client record has somewhere to attach future work). quotation_id must be
 * nullable to allow those manually-created sites.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->uuid('quotation_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->uuid('quotation_id')->nullable(false)->change();
        });
    }
};
