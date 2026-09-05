<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->string('result');
            $table->string('condition')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignUuid('work_order_id')->nullable()->constrained('work_orders')->nullOnDelete();
            $table->date('verified_at');
            $table->foreignId('verified_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_verifications');
    }
};
