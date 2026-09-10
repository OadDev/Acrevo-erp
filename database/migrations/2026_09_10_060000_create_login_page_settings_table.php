<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Single-row settings table for the login page's background image and
     * promotional content - both editable by Admin whenever needed, no
     * code change required. Row is created lazily on first use rather
     * than seeded here, matching LoginPageSetting::current().
     */
    public function up(): void
    {
        Schema::create('login_page_settings', function (Blueprint $table) {
            $table->id();
            $table->string('offer_title')->nullable();
            $table->text('offer_body')->nullable();
            $table->boolean('is_offer_active')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_page_settings');
    }
};
