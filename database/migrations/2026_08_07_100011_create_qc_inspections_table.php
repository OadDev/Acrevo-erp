<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qc_inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('work_order_id')->constrained()->cascadeOnDelete();
            $table->enum('inspection_type', ['daily', 'final'])->default('daily');
            $table->foreignId('inspected_by')->constrained('users');
            $table->date('inspection_date');
            $table->json('checklist')->nullable();
            $table->enum('status', ['pending', 'passed', 'failed', 'rework_required'])->default('pending');
            $table->text('remarks')->nullable();
            $table->string('signature_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qc_inspections');
    }
};
