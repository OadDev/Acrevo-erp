<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_repairs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();

            $table->string('repair_type');
            $table->text('issue_description');
            $table->string('technician_vendor')->nullable();
            $table->boolean('is_warranty_repair')->default(false);
            $table->decimal('cost', 12, 2)->nullable();

            $table->string('status')->default('reported');
            // The asset's operational status right before this repair
            // started, so cancelling the repair can put it back exactly
            // where it was instead of guessing.
            $table->string('asset_status_before');

            // work_orders.id is a UUID (WorkOrder uses HasUuids) - see the
            // matching note in the assets table migration. Snapshot of the
            // asset's site at the time this repair was logged, so the
            // history stays accurate even if the asset moves later.
            $table->foreignUuid('work_order_id')->nullable()->constrained('work_orders')->nullOnDelete();

            $table->date('reported_date');
            $table->date('completed_date')->nullable();
            $table->text('remarks')->nullable();

            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_repairs');
    }
};
