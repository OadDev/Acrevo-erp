<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('quotation_no')->unique();
            $table->foreignUuid('enquiry_id')->constrained();
            $table->foreignUuid('client_id')->constrained();
            $table->unsignedInteger('version')->default(1);
            $table->uuid('parent_quotation_id')->nullable();
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->enum('discount_type', ['flat', 'percent'])->default('flat');
            $table->decimal('discount_value', 14, 2)->default(0);
            $table->decimal('tax_percent', 5, 2)->default(18);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->text('terms')->nullable();
            $table->enum('status', ['draft', 'sent', 'approved', 'rejected', 'expired'])->default('draft');
            $table->date('valid_until')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->string('approved_by')->nullable();
            $table->text('rejected_reason')->nullable();
            $table->string('pdf_path')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('parent_quotation_id')->references('id')->on('quotations')->nullOnDelete();
        });

        Schema::create('quotation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('quotation_id')->constrained()->cascadeOnDelete();
            $table->enum('item_type', ['product', 'service'])->default('service');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('unit')->default('Nos');
            $table->decimal('quantity', 12, 2)->default(1);
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->decimal('discount', 14, 2)->default(0);
            $table->decimal('tax_percent', 5, 2)->default(18);
            $table->decimal('total', 14, 2)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_items');
        Schema::dropIfExists('quotations');
    }
};
