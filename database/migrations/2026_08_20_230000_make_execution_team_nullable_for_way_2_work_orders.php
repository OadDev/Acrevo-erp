<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A Way 2 (Sub-Contractor) work order never has an executive team assigned -
 * teams and sub-contractors are mutually exclusive assignment paths - so
 * Daily Work with Checklist and Daily Progress entries on those work orders
 * must be allowed without one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_checklists', function (Blueprint $table) {
            $table->foreignId('executive_team_id')->nullable()->change();
        });

        Schema::table('daily_progress_reports', function (Blueprint $table) {
            $table->foreignId('executive_team_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('daily_checklists', function (Blueprint $table) {
            $table->foreignId('executive_team_id')->nullable(false)->change();
        });

        Schema::table('daily_progress_reports', function (Blueprint $table) {
            $table->foreignId('executive_team_id')->nullable(false)->change();
        });
    }
};
