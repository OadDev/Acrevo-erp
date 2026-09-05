<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('asset_code')->unique();
            $table->string('name');
            $table->string('category')->nullable();
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->string('serial_number')->nullable();
            $table->string('status')->default('available');
            $table->string('condition')->nullable();

            // 'company_store' | 'work_order' | 'in_transit' | 'other' - paired
            // with current_work_order_id when the asset is at a specific site,
            // so "Location" filters (Company Store / a specific WO / In
            // Transit / Other) don't depend on free-text matching.
            $table->string('current_location')->default('company_store');
            // work_orders.id is a UUID (WorkOrder uses HasUuids), not an
            // auto-increment bigint - foreignId() here would create a
            // mismatched-type FK that MySQL rejects at CREATE TABLE time
            // (errno 150), even though SQLite (used by the test suite)
            // lets it slide.
            $table->foreignUuid('current_work_order_id')->nullable()->constrained('work_orders')->nullOnDelete();

            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_cost', 12, 2)->nullable();
            $table->string('supplier')->nullable();
            $table->string('invoice_number')->nullable();

            $table->date('warranty_start')->nullable();
            $table->date('warranty_end')->nullable();
            $table->string('warranty_provider')->nullable();
            $table->text('warranty_card_details')->nullable();

            $table->text('remarks')->nullable();

            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
