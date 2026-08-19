<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->string('type')->default('debit')->after('work_order_id');
            $table->text('remark')->nullable()->after('description');
            $table->decimal('balance', 14, 2)->default(0)->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn(['type', 'remark', 'balance']);
        });
    }
};
