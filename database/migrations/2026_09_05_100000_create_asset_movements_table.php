<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The complete movement timeline for an asset. Never deleted - a
     * cancelled movement stays in the table with status=cancelled rather
     * than being removed, so the history is always complete.
     */
    public function up(): void
    {
        Schema::create('asset_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();

            $table->string('type');

            $table->string('from_location');
            // work_orders.id is a UUID (WorkOrder uses HasUuids) - see the
            // matching note in the assets table migration.
            $table->foreignUuid('from_work_order_id')->nullable()->constrained('work_orders')->nullOnDelete();

            $table->string('to_location');
            $table->foreignUuid('to_work_order_id')->nullable()->constrained('work_orders')->nullOnDelete();

            $table->string('status')->default('pending');
            $table->date('moved_at');
            $table->timestamp('confirmed_at')->nullable();
            $table->text('remarks')->nullable();

            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_movements');
    }
};
