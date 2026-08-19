<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_schedule_id')->nullable()->constrained('task_schedules')->nullOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to')->constrained('users')->cascadeOnDelete();
            $table->foreignId('verifier_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('due_date');
            $table->string('status')->default('pending');
            $table->timestamp('completed_at')->nullable();
            $table->text('completion_notes')->nullable();
            $table->text('delay_reason')->nullable();
            $table->timestamp('delay_reported_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('retask_note')->nullable();
            $table->foreignId('parent_task_id')->nullable()->constrained('tasks')->nullOnDelete();
            $table->string('period_key')->nullable();
            $table->timestamps();

            $table->unique(['task_schedule_id', 'assigned_to', 'period_key'], 'tasks_schedule_assignee_period_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
