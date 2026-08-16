<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->string('status')->default('pending')->change();
            $table->decimal('paid_amount', 12, 2)->default(0)->after('net_salary');
        });
    }

    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropColumn('paid_amount');
            $table->enum('status', ['pending', 'paid'])->default('pending')->change();
        });
    }
};
