<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('position_applied')->nullable();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('qualification')->nullable();
            $table->text('experience_summary')->nullable();
            $table->decimal('expected_salary', 12, 2)->nullable();
            $table->date('interview_date')->nullable();
            $table->text('interview_notes')->nullable();
            // pending: awaiting a decision. selected: ready to hire.
            // not_selected: kept for future reference. hired: converted.
            $table->enum('status', ['pending', 'selected', 'not_selected', 'hired'])->default('pending');
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidates');
    }
};
