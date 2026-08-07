<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('measurement_books', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('work_order_id')->constrained()->cascadeOnDelete();
            $table->string('mb_no')->unique();
            $table->text('description')->nullable();
            $table->date('date');
            $table->foreignId('recorded_by')->constrained('users');
            $table->enum('status', ['draft', 'submitted', 'verified'])->default('draft');
            $table->timestamps();
        });

        Schema::create('measurement_book_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('measurement_book_id')->constrained()->cascadeOnDelete();
            $table->string('item_description');
            $table->string('unit')->default('Sqft');
            $table->decimal('length', 10, 2)->nullable();
            $table->decimal('breadth', 10, 2)->nullable();
            $table->decimal('height', 10, 2)->nullable();
            $table->decimal('quantity', 12, 2)->default(0);
            $table->decimal('rate', 12, 2)->default(0);
            $table->decimal('amount', 14, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('work_order_id')->constrained()->cascadeOnDelete();
            $table->date('entry_date');
            $table->enum('type', ['credit', 'debit']);
            $table->string('category')->nullable();
            $table->text('description')->nullable();
            $table->decimal('amount', 14, 2);
            $table->decimal('balance', 14, 2)->default(0);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledgers');
        Schema::dropIfExists('measurement_book_items');
        Schema::dropIfExists('measurement_books');
    }
};
