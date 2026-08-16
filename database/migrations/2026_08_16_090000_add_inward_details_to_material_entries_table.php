<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_entries', function (Blueprint $table) {
            $table->enum('scope', ['client', 'company'])->nullable()->after('vendor');
            $table->string('delivery_vehicle_details')->nullable()->after('scope');
        });
    }

    public function down(): void
    {
        Schema::table('material_entries', function (Blueprint $table) {
            $table->dropColumn(['scope', 'delivery_vehicle_details']);
        });
    }
};
