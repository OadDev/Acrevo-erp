<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Replaces the old is_parallel flag (which only ever let a work ride
     * alongside "the previous list item's anchor") with an explicit
     * dependency model: every work is either 'independent' (its own
     * admin-set start date, letting two or more works start on the same
     * day with no relationship to each other at all) or 'depends_on'
     * another specific work on the same site (picked explicitly, not
     * inferred from list order).
     *
     * depends_on_schedule_id is only ever allowed to reference a row with
     * a smaller id (see SiteWorkScheduleController's validation) - that
     * ordering is what lets WorkScheduleRecalculator resolve the whole
     * chain in one ascending-id pass with no cycle risk.
     */
    public function up(): void
    {
        Schema::table('site_work_schedules', function (Blueprint $table) {
            $table->string('schedule_mode')->default('independent')->after('is_parallel');
            $table->foreignId('depends_on_schedule_id')->nullable()->after('schedule_mode')
                ->constrained('site_work_schedules')->nullOnDelete();
        });

        // Backfill from the old sequence_order chain: each site's first
        // work becomes independent (it always had its own explicit start
        // date); every later work depends on whichever work immediately
        // preceded it in that site's sequence, regardless of what
        // is_parallel used to say - a reasonable one-time approximation,
        // this feature having only just shipped with no real usage yet.
        DB::table('sites')->orderBy('id')->pluck('id')->each(function ($siteId) {
            $rows = DB::table('site_work_schedules')->where('site_id', $siteId)->orderBy('sequence_order')->get(['id']);
            $previousId = null;

            foreach ($rows as $row) {
                DB::table('site_work_schedules')->where('id', $row->id)->update([
                    'schedule_mode' => $previousId === null ? 'independent' : 'depends_on',
                    'depends_on_schedule_id' => $previousId,
                ]);
                $previousId = $row->id;
            }
        });

        Schema::table('site_work_schedules', function (Blueprint $table) {
            $table->dropColumn('is_parallel');
        });
    }

    public function down(): void
    {
        Schema::table('site_work_schedules', function (Blueprint $table) {
            $table->boolean('is_parallel')->default(false)->after('work_details');
        });

        Schema::table('site_work_schedules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('depends_on_schedule_id');
            $table->dropColumn('schedule_mode');
        });
    }
};
