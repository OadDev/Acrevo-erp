<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('work_order_id')->nullable()->constrained('work_orders')->nullOnDelete();
            $table->foreignId('requested_by')->constrained('users');
            $table->string('item_name');
            $table->string('category')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->text('reason')->nullable();
            $table->string('status')->default('requested');
            $table->boolean('needs_purchase')->nullable();
            $table->foreignId('asset_id')->nullable()->constrained('assets')->nullOnDelete();
            $table->date('required_by_date')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->dateTime('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_requests');
    }
};
