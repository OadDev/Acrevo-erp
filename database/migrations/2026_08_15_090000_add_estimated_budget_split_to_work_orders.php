<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The single "Budget Amount" field on work order creation is split into the
 * same two components as the Budget tab (Material Specifications / Man
 * Power Schedule) so the estimate entered up front matches how the actual
 * budget gets tracked later. budget_amount itself is kept as the computed
 * total, since reports/API/overview already read it directly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->decimal('estimated_material_budget', 14, 2)->nullable()->after('budget_amount');
            $table->decimal('estimated_labour_budget', 14, 2)->nullable()->after('estimated_material_budget');
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropColumn(['estimated_material_budget', 'estimated_labour_budget']);
        });
    }
};
