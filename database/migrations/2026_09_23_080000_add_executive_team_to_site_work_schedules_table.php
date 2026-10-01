<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Assigns an Executive Team directly to a scheduled work item, so the
     * Work Schedule Planner can show which team is doing what, where, and
     * when - independent of WorkOrderExecutiveTeam, which assigns a team to
     * an entire Work Order rather than one specific work item on a site's
     * schedule.
     */
    public function up(): void
    {
        Schema::table('site_work_schedules', function (Blueprint $table) {
            $table->foreignId('executive_team_id')->nullable()->after('work_details')
                ->constrained('executive_teams')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('site_work_schedules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('executive_team_id');
        });
    }
};
