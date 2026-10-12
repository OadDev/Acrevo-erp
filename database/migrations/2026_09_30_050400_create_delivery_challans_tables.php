<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_challans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignUuid('client_id')->constrained('clients');
            $table->string('challan_no')->unique();
            $table->date('challan_date');
            $table->string('delivery_time')->nullable();
            // "Shipping To" is often a different address than the client's
            // own (e.g. a site) - manual entry, not auto-copied from
            // Client, defaulting blank unless staff fill it in.
            $table->string('shipping_name')->nullable();
            $table->text('shipping_address')->nullable();
            $table->string('shipping_phone')->nullable();
            $table->string('shipping_email')->nullable();
            $table->string('shipping_tax_id')->nullable();
            $table->text('terms_and_conditions')->nullable();
            $table->string('status')->default('draft');
            $table->string('received_by_name')->nullable();
            $table->text('received_by_comment')->nullable();
            $table->date('received_by_date')->nullable();
            $table->string('delivered_by_name')->nullable();
            $table->text('delivered_by_comment')->nullable();
            $table->date('delivered_by_date')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('delivery_challan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_challan_id')->constrained('delivery_challans')->cascadeOnDelete();
            $table->foreignId('item_id')->nullable()->constrained('items')->nullOnDelete();
            $table->string('name');
            $table->string('hsn_sac_code')->nullable();
            $table->decimal('quantity', 12, 2);
            $table->string('unit')->default('Nos');
            $table->unsignedInteger('sort_order')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_challan_items');
        Schema::dropIfExists('delivery_challans');
    }
};
