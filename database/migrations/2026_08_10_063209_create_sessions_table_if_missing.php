<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The sessions table is normally created by 0001_01_01_000000_create_users_table.php,
 * but that migration was already recorded as run on production before this table
 * existed there (state mismatch from manual DB changes outside of migrations) -
 * so `artisan migrate` alone won't re-create it. This creates it if still missing.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sessions')) {
            Schema::create('sessions', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->foreignId('user_id')->nullable()->index();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->longText('payload');
                $table->integer('last_activity')->index();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
    }
};
