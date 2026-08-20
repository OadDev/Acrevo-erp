<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            // Null = every portal section visible (default, matches existing
            // behavior for clients an Admin hasn't restricted yet). Once set,
            // only the listed section keys show on that client's portal.
            $table->json('visible_sections')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('visible_sections');
        });
    }
};
