<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('work_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('client_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['agreement', 'contract', 'notice', 'other'])->default('agreement');
            $table->string('title');
            $table->string('reference_no')->nullable();
            $table->date('issued_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('audits', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['internal', 'financial', 'project'])->default('internal');
            $table->foreignUuid('work_order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->foreignId('auditor_id')->constrained('users');
            $table->date('audit_date');
            $table->text('findings')->nullable();
            $table->enum('status', ['scheduled', 'in_progress', 'completed'])->default('scheduled');
            $table->string('report_path')->nullable();
            $table->timestamps();
        });

        Schema::create('company_records', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['gst', 'msme', 'insurance', 'license', 'patent']);
            $table->string('name');
            $table->string('number')->nullable();
            $table->date('issued_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_records');
        Schema::dropIfExists('audits');
        Schema::dropIfExists('legal_documents');
    }
};
