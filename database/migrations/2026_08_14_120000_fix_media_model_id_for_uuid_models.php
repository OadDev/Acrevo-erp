<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * media.model_id was created via morphs('model'), which defaults to an
 * unsignedBigInteger - fine for integer-keyed models (Employee,
 * QcInspection, DailyProgressReport, LegalDocument), but WorkOrder, Ticket,
 * and ApprovalRequest all use UUID primary keys. Writing a UUID string into
 * that integer column truncates it, which MySQL raises as a query
 * exception - every media upload (progress photos, ticket attachments,
 * approval request documents) on those models has been silently failing.
 * Same root cause as the earlier activity_log.subject_id fix.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropIndex('media_model_type_model_id_index');
        });

        Schema::table('media', function (Blueprint $table) {
            $table->uuid('model_id')->change();
        });

        Schema::table('media', function (Blueprint $table) {
            $table->index(['model_type', 'model_id'], 'media_model_type_model_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropIndex('media_model_type_model_id_index');
        });

        Schema::table('media', function (Blueprint $table) {
            $table->unsignedBigInteger('model_id')->change();
        });

        Schema::table('media', function (Blueprint $table) {
            $table->index(['model_type', 'model_id'], 'media_model_type_model_id_index');
        });
    }
};
