<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('work_order_no')->unique();
            $table->foreignUuid('quotation_id')->nullable()->constrained();
            $table->foreignUuid('enquiry_id')->nullable()->constrained();
            $table->foreignUuid('client_id')->constrained();
            $table->uuid('parent_work_order_id')->nullable();
            $table->enum('type', ['new', 'rework', 'next'])->default('new');
            $table->string('title');
            $table->text('scope')->nullable();
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->date('start_date')->nullable();
            $table->date('deadline')->nullable();
            $table->decimal('budget_amount', 14, 2)->nullable();
            $table->enum('status', [
                'pending_hr_assignment',
                'team_assigned',
                'in_progress',
                'qc_pending',
                'qc_passed',
                'qc_failed',
                'client_review',
                'ticket_raised',
                'rework_in_progress',
                'final_qc',
                'completed',
                'cancelled',
            ])->default('pending_hr_assignment');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('parent_work_order_id')->references('id')->on('work_orders')->nullOnDelete();
        });

        Schema::create('work_order_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('work_order_id')->constrained()->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamp('changed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_status_logs');
        Schema::dropIfExists('work_orders');
    }
};
