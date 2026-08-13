<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A site holds many work orders over its life. It should only be marked
 * complete/handed-over once as an explicit decision - not implied just
 * because its current work orders happen to all be done - so it needs its
 * own status distinct from any individual work order's status.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->string('status')->default('active')->after('site_contact_phone');
            $table->timestamp('completed_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn(['status', 'completed_at']);
        });
    }
};
