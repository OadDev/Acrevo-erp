<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('work_order_id')->constrained()->cascadeOnDelete();
            $table->date('entry_date');
            $table->string('type');
            $table->string('category')->nullable();
            $table->text('description')->nullable();
            $table->text('remark')->nullable();
            $table->decimal('amount', 14, 2);
            $table->decimal('balance', 14, 2)->default(0);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_ledgers');
    }
};
