<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Budget section on work order creation only covered Material and Man
 * Power. Adding Equipment/Machinery, Transport, and Miscellaneous as their
 * own allocated categories, same pattern as the material/labour split.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->decimal('estimated_equipment_budget', 14, 2)->nullable()->after('estimated_labour_budget');
            $table->decimal('estimated_transport_budget', 14, 2)->nullable()->after('estimated_equipment_budget');
            $table->decimal('estimated_misc_budget', 14, 2)->nullable()->after('estimated_transport_budget');
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropColumn(['estimated_equipment_budget', 'estimated_transport_budget', 'estimated_misc_budget']);
        });
    }
};
