<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A running discussion on an Approval Request, same shape as
     * ticket_comments - no is_internal split, since an Approval Request is
     * already either company_to_client or client_to_company and both
     * sides see the same thread.
     */
    public function up(): void
    {
        Schema::create('approval_request_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('approval_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('comment');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_request_comments');
    }
};
