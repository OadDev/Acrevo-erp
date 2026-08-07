<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_no')->unique();
            $table->foreignUuid('work_order_id')->constrained();
            $table->foreignUuid('client_id')->constrained();
            $table->decimal('amount', 14, 2);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2);
            $table->date('due_date')->nullable();
            $table->enum('status', ['draft', 'sent', 'paid', 'partial', 'overdue'])->default('draft');
            $table->foreignId('issued_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('work_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('client_id')->constrained();
            $table->decimal('amount', 14, 2);
            $table->date('payment_date');
            $table->enum('mode', ['cash', 'bank_transfer', 'upi', 'cheque', 'card'])->default('bank_transfer');
            $table->string('reference_no')->nullable();
            $table->foreignId('received_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('vendor_payments', function (Blueprint $table) {
            $table->id();
            $table->string('vendor_name');
            $table->foreignUuid('work_order_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 14, 2);
            $table->date('payment_date');
            $table->enum('mode', ['cash', 'bank_transfer', 'upi', 'cheque', 'card'])->default('bank_transfer');
            $table->string('reference_no')->nullable();
            $table->string('category')->nullable();
            $table->foreignId('paid_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('work_order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category');
            $table->text('description')->nullable();
            $table->decimal('amount', 14, 2);
            $table->date('expense_date');
            $table->foreignId('paid_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('vendor_payments');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoices');
    }
};
