<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignUuid('client_id')->constrained('clients');
            $table->string('tax_invoice_no')->unique();
            $table->date('document_date');
            $table->string('delivery_note')->nullable();
            $table->string('payment_terms')->nullable();
            $table->string('supplier_ref')->nullable();
            $table->string('other_reference')->nullable();
            $table->string('buyer_order_no')->nullable();
            $table->date('buyer_order_date')->nullable();
            $table->string('dispatch_doc_no')->nullable();
            $table->string('dispatch_through')->nullable();
            $table->string('destination')->nullable();
            $table->text('terms_of_delivery')->nullable();
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('tax_percent', 5, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->string('status')->default('draft');
            $table->text('notes')->nullable();
            // Set only when this Tax Invoice was created by converting a
            // Proforma Invoice (see ProformaInvoiceController::convert());
            // null for one created directly.
            $table->foreignId('proforma_invoice_id')->nullable()->constrained('proforma_invoices')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('tax_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tax_invoice_id')->constrained('tax_invoices')->cascadeOnDelete();
            $table->foreignId('item_id')->nullable()->constrained('items')->nullOnDelete();
            $table->string('name');
            $table->string('hsn_sac_code')->nullable();
            $table->string('unit')->default('Nos');
            $table->decimal('quantity', 12, 2);
            $table->decimal('rate', 14, 2);
            $table->decimal('total', 14, 2);
            $table->unsignedInteger('sort_order')->default(0);
        });

        // Now that tax_invoices exists, complete the other half of the
        // circular reference from proforma_invoices.
        Schema::table('proforma_invoices', function (Blueprint $table) {
            $table->foreign('converted_to_tax_invoice_id')->references('id')->on('tax_invoices')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('proforma_invoices', function (Blueprint $table) {
            $table->dropForeign(['converted_to_tax_invoice_id']);
        });

        Schema::dropIfExists('tax_invoice_items');
        Schema::dropIfExists('tax_invoices');
    }
};
