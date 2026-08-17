<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_order_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('work_order_id')->constrained()->cascadeOnDelete();
            $table->date('entry_date');
            $table->string('status');
            $table->string('responsibility');
            $table->text('work_detail')->nullable();
            $table->integer('client_bear_days')->nullable();
            $table->integer('remaining_construction_days')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_summaries');
    }
};
