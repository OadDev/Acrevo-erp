<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            // Daily work details for staff attendance (Sales/HR/Finance/
            // Executive Team Leader/QC Officer, marked via HR > Attendance).
            // Workers don't use this field - their work is logged per work
            // order instead.
            $table->text('work_details')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn('work_details');
        });
    }
};
