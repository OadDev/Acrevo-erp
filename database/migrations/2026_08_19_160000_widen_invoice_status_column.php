<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * invoices.status was a MySQL ENUM('draft','sent','paid','partial','overdue')
 * that never included 'cancelled', even though the app validates and offers
 * it as a status option - saving that status would fail at the DB level.
 * Widened to a plain string, validated at the app level instead, matching
 * how ledgers.type was already loosened for the same reason.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('status')->default('draft')->change();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->enum('status', ['draft', 'sent', 'paid', 'partial', 'overdue'])->default('draft')->change();
        });
    }
};
