<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enquiries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('enquiry_no')->unique();
            $table->foreignUuid('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('contact_name');
            $table->string('contact_phone');
            $table->string('contact_email')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->enum('source', ['website', 'referral', 'call', 'walk_in', 'social_media', 'advertisement', 'other'])->default('other');
            $table->string('service_type')->nullable();
            $table->text('description')->nullable();
            $table->enum('status', ['new', 'contacted', 'site_visit_scheduled', 'quoted', 'converted', 'lost'])->default('new');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->date('follow_up_date')->nullable();
            $table->string('lost_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('enquiry_follow_ups', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('enquiry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->text('note');
            $table->date('next_follow_up_date')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enquiry_follow_ups');
        Schema::dropIfExists('enquiries');
    }
};
