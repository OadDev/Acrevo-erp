<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Repairs/verifications only ever recorded a work_order_id (null meant
     * "at the company store"). Now that an asset's quantity can sit at
     * several locations at once, each entry needs an explicit location -
     * matching Asset::LOCATIONS - so it's clear which stock bucket the
     * repaired/verified quantity comes out of.
     */
    public function up(): void
    {
        Schema::table('asset_repairs', function (Blueprint $table) {
            $table->string('location')->default('company_store')->after('asset_id');
        });
        Schema::table('asset_verifications', function (Blueprint $table) {
            $table->string('location')->default('company_store')->after('asset_id');
        });

        DB::table('asset_repairs')->whereNotNull('work_order_id')->update(['location' => 'work_order']);
        DB::table('asset_verifications')->whereNotNull('work_order_id')->update(['location' => 'work_order']);
    }

    public function down(): void
    {
        Schema::table('asset_repairs', function (Blueprint $table) {
            $table->dropColumn('location');
        });
        Schema::table('asset_verifications', function (Blueprint $table) {
            $table->dropColumn('location');
        });
    }
};
