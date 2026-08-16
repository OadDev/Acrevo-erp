<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('measurement_books', function (Blueprint $table) {
            $table->enum('type', ['schedule', 'actual'])->default('actual')->after('mb_no');
        });
    }

    public function down(): void
    {
        Schema::table('measurement_books', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
