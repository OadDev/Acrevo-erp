<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ledgers', function (Blueprint $table) {
            $table->string('type')->change();
            $table->text('remark')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('ledgers', function (Blueprint $table) {
            $table->dropColumn('remark');
            $table->enum('type', ['credit', 'debit'])->change();
        });
    }
};
