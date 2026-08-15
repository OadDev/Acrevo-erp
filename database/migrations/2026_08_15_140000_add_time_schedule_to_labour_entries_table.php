<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('labour_entries', function (Blueprint $table) {
            $table->string('total_time_to_finish')->nullable()->after('hours');
            $table->text('remark')->nullable()->after('total_time_to_finish');
        });
    }

    public function down(): void
    {
        Schema::table('labour_entries', function (Blueprint $table) {
            $table->dropColumn(['total_time_to_finish', 'remark']);
        });
    }
};
