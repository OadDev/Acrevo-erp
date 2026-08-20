<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Site Details needs two more location fields: the construction site's own
 * location (distinct from the formal address/city/state/pincode fields, e.g.
 * a Maps link or landmark description) and where the client currently
 * lives, since that's often a different place from the site itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->string('construction_site_location')->nullable()->after('pincode');
            $table->string('client_living_location')->nullable()->after('construction_site_location');
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn(['construction_site_location', 'client_living_location']);
        });
    }
};
