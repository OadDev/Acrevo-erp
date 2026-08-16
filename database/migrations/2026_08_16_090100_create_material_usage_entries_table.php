<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_usage_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('work_order_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('material_name');
            $table->decimal('quantity', 12, 2);
            $table->string('unit');
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_usage_entries');
    }
};
