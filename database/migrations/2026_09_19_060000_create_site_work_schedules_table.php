<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per work item on a site's schedule. sequence_order is the
     * chain position used to recalculate dates - each item starts after
     * the running end date of everything before it, unless is_parallel
     * makes it start alongside the previous non-parallel "anchor" item
     * instead. original_* is captured once at creation and never touched
     * again; revised_* is what WorkScheduleRecalculator rewrites whenever
     * anything in the chain changes. See app/Services/WorkScheduleRecalculator.php.
     */
    public function up(): void
    {
        Schema::create('site_work_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('site_id')->constrained('sites')->cascadeOnDelete();
            $table->unsignedInteger('sequence_order');
            $table->string('work_name');
            $table->text('work_details')->nullable();
            $table->boolean('is_parallel')->default(false);
            $table->unsignedInteger('lag_days')->default(0);

            $table->date('original_start_date');
            $table->unsignedInteger('original_duration_days');
            $table->date('original_end_date');

            $table->date('revised_start_date');
            $table->unsignedInteger('revised_duration_days');
            $table->date('revised_end_date');

            $table->date('actual_start_date')->nullable();
            $table->date('actual_end_date')->nullable();
            $table->unsignedTinyInteger('actual_progress_percent')->nullable();
            $table->string('status')->default('not_started');
            $table->text('delay_reason')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['site_id', 'sequence_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_work_schedules');
    }
};
