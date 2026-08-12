<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * activity_log.subject_id was created via nullableMorphs(), which defaults to
 * an unsignedBigInteger - fine for auto-increment models like User, but every
 * other model that logs activity (WorkOrder, Client, Quotation, Enquiry,
 * Ticket) uses a UUID primary key. Writing a UUID string into that integer
 * column truncates it, which MySQL raises as a query exception - breaking
 * every create/update on those models via LogsActivity's automatic logging.
 * Switching to nullableUuidMorphs() makes subject_id a string column wide
 * enough for a UUID, while still holding plain integer ids as text for User.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropIndex('subject');
            $table->dropColumn(['subject_id', 'subject_type']);
        });

        Schema::table('activity_log', function (Blueprint $table) {
            $table->nullableUuidMorphs('subject', 'subject');
        });
    }

    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropIndex('subject');
            $table->dropColumn(['subject_id', 'subject_type']);
        });

        Schema::table('activity_log', function (Blueprint $table) {
            $table->nullableMorphs('subject', 'subject');
        });
    }
};
