<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Turns the previously-orphaned Company model into the "issuing
     * company" profile for Proforma Invoice / Tax Invoice / Delivery
     * Challan documents - code drives per-company document numbering
     * (see HasCompanySequenceNumber), tax_regime/vatin drive whether a
     * document's PDF shows "GSTIN"+"GST" or "VATIN"+"VAT" labels.
     */
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('code', 20)->unique()->nullable()->after('name');
            $table->string('country')->default('India')->after('pincode');
            $table->string('vatin')->nullable()->after('gstin');
            $table->string('tax_regime', 10)->default('gst')->after('vatin');
            $table->string('bank_name')->nullable()->after('settings');
            $table->string('bank_account_name')->nullable()->after('bank_name');
            $table->string('bank_account_no')->nullable()->after('bank_account_name');
            $table->string('bank_ifsc_code')->nullable()->after('bank_account_no');
            $table->string('bank_swift_code')->nullable()->after('bank_ifsc_code');
            $table->string('bank_branch')->nullable()->after('bank_swift_code');
            $table->boolean('is_active')->default(true)->after('bank_branch');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'code', 'country', 'vatin', 'tax_regime', 'bank_name', 'bank_account_name',
                'bank_account_no', 'bank_ifsc_code', 'bank_swift_code', 'bank_branch', 'is_active',
            ]);
        });
    }
};
