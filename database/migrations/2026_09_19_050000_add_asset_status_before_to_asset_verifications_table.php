<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mirrors asset_repairs.asset_status_before - lets destroy() put the
     * asset's legacy whole-asset status back exactly where it was before
     * this verification flipped it to missing/damaged, instead of leaving
     * it stuck there forever (and so stuck showing on Missing Assets)
     * after the verification entry itself is removed. Nullable, unlike
     * asset_repairs' version, since existing verification rows have no
     * such value to backfill - destroy() treats a null here as "unknown,
     * leave the status alone" rather than guessing.
     */
    public function up(): void
    {
        Schema::table('asset_verifications', function (Blueprint $table) {
            $table->string('asset_status_before')->nullable()->after('result');
        });
    }

    public function down(): void
    {
        Schema::table('asset_verifications', function (Blueprint $table) {
            $table->dropColumn('asset_status_before');
        });
    }
};
