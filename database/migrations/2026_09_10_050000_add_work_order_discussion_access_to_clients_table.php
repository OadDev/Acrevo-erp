<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Deliberately separate from visible_sections (which defaults to
     * "everything visible" when null): this one lets a client send
     * messages into a two-way thread with staff, so it defaults to off
     * for every client - including existing "unrestricted" ones - until
     * an Admin explicitly turns it on for that client.
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->boolean('work_order_discussion_access')->default(false)->after('visible_sections');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('work_order_discussion_access');
        });
    }
};
