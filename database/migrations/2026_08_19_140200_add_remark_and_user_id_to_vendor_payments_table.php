<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_payments', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('vendor_name')->constrained('users')->nullOnDelete();
            $table->text('remark')->nullable()->after('category');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_payments', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn(['user_id', 'remark']);
        });
    }
};
