<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_order_budget_items', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('work_order_id')->constrained()->cascadeOnDelete();
            $table->string('category', 20);
            $table->string('name');
            $table->string('brand')->nullable();
            $table->string('size')->nullable();
            $table->string('unit')->nullable();
            $table->decimal('quantity', 12, 2)->nullable();
            $table->decimal('hours', 12, 2)->nullable();
            $table->decimal('rate', 12, 2)->nullable();
            $table->string('vendor')->nullable();
            $table->decimal('amount', 14, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_budget_items');
    }
};
