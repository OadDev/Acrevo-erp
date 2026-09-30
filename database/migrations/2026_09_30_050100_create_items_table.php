<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reusable catalog for autofilling Proforma Invoice / Tax Invoice /
     * Delivery Challan line items - not required, a document can also
     * have free-typed items.
     */
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('hsn_sac_code')->nullable();
            $table->string('unit')->default('Nos');
            $table->decimal('default_rate', 14, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
