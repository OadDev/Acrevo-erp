<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * proforma_invoices and tax_invoices reference each other
     * (converted_to_tax_invoice_id / proforma_invoice_id), so this table
     * is created here without a FK constraint on that column yet - the
     * constraint is added once tax_invoices exists, in the next migration.
     */
    public function up(): void
    {
        Schema::create('proforma_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            // clients.id is a UUID (Client uses HasUuids).
            $table->foreignUuid('client_id')->constrained('clients');
            $table->string('proforma_no')->unique();
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
            // Set once this proforma is converted - locks it from further
            // edits/re-conversion. FK constraint added in the tax_invoices
            // migration, once that table exists. See
            // ProformaInvoiceController::convert().
            $table->unsignedBigInteger('converted_to_tax_invoice_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('proforma_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proforma_invoice_id')->constrained('proforma_invoices')->cascadeOnDelete();
            $table->foreignId('item_id')->nullable()->constrained('items')->nullOnDelete();
            $table->string('name');
            $table->string('hsn_sac_code')->nullable();
            $table->string('unit')->default('Nos');
            $table->decimal('quantity', 12, 2);
            $table->decimal('rate', 14, 2);
            $table->decimal('total', 14, 2);
            $table->unsignedInteger('sort_order')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proforma_invoice_items');
        Schema::dropIfExists('proforma_invoices');
    }
};
