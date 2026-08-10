<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sites', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('quotation_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignUuid('client_id')->constrained();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('pincode')->nullable();
            $table->string('site_contact_name')->nullable();
            $table->string('site_contact_phone')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('work_orders', function (Blueprint $table) {
            $table->foreignUuid('site_id')->nullable()->after('quotation_id')->constrained()->nullOnDelete();
            $table->string('execution_way')->nullable()->after('scope');
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('site_id');
            $table->dropColumn('execution_way');
        });

        Schema::dropIfExists('sites');
    }
};
