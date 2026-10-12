<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Manual status updates are now scoped to a specific AssetStock bucket
     * and quantity (see AssetController::updateStatus()) instead of
     * flipping the whole asset's legacy status field - these columns let
     * the audit trail say "2 units at WO-202609-0001 changed from
     * available to ready_for_return" instead of implying a full-asset
     * change. Nullable so older rows (recorded before this fix) stay as
     * "whole asset, quantity unknown" rather than claiming a false 0/1.
     */
    public function up(): void
    {
        Schema::table('asset_status_logs', function (Blueprint $table) {
            $table->string('location')->nullable()->after('work_order_id');
            $table->unsignedInteger('quantity')->nullable()->after('location');
        });
    }

    public function down(): void
    {
        Schema::table('asset_status_logs', function (Blueprint $table) {
            $table->dropColumn(['location', 'quantity']);
        });
    }
};
