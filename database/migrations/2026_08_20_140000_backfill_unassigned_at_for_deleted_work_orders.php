<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Data backfill: any work_order_executive_team / work_order_sub_contractor
 * row still marked "active" (unassigned_at null) whose work order was
 * already soft-deleted before this fix is a dead end - there's no page
 * left to unassign it from, and it silently blocks removing the executive
 * team entirely. Clears unassigned_at for exactly those rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('work_order_executive_team')
            ->whereNull('unassigned_at')
            ->whereIn('work_order_id', function ($query) {
                $query->select('id')->from('work_orders')->whereNotNull('deleted_at');
            })
            ->update(['unassigned_at' => now()]);

        DB::table('work_order_sub_contractors')
            ->whereNull('unassigned_at')
            ->whereIn('work_order_id', function ($query) {
                $query->select('id')->from('work_orders')->whereNotNull('deleted_at');
            })
            ->update(['unassigned_at' => now()]);
    }

    public function down(): void
    {
        // Not reversible - the original unassigned_at values weren't recorded anywhere.
    }
};
