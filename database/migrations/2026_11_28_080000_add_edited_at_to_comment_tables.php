<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Explicit "edited" marker, set only when the comment's own text is
     * corrected - deliberately not inferred from updated_at vs created_at,
     * since those can land in the same second (or diverge for other
     * reasons, e.g. an attachment-only change) and would misreport either
     * way.
     */
    public function up(): void
    {
        Schema::table('ticket_comments', function (Blueprint $table) {
            $table->timestamp('edited_at')->nullable()->after('comment');
        });

        Schema::table('approval_request_comments', function (Blueprint $table) {
            $table->timestamp('edited_at')->nullable()->after('comment');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_comments', function (Blueprint $table) {
            $table->dropColumn('edited_at');
        });

        Schema::table('approval_request_comments', function (Blueprint $table) {
            $table->dropColumn('edited_at');
        });
    }
};
