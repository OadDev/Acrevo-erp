<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            // Total quantity this asset record represents (a single tool is
            // 1; a batch like "Steel Sheets" can be many). Kept in sync with
            // the sum of its asset_stocks rows - see AssetStock::adjust().
            $table->unsignedInteger('quantity')->default(1)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn('quantity');
        });
    }
};
