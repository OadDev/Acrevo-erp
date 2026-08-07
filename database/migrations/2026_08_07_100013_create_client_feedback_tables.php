<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('work_order_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('client_id')->constrained();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->text('comments')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('completion_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('work_order_id')->constrained()->cascadeOnDelete();
            $table->string('certificate_no')->unique();
            $table->date('issued_date');
            $table->foreignId('issued_by')->constrained('users');
            $table->string('pdf_path')->nullable();
            $table->timestamp('client_signed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('completion_certificates');
        Schema::dropIfExists('client_reviews');
    }
};
