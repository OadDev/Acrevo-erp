<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['direct', 'group', 'discussion']);
            $table->string('name')->nullable();
            // Plain string columns (not nullableMorphs/nullableUuidMorphs) -
            // subject models have mixed key types (Enquiry/Quotation/Client/
            // Site/WorkOrder use UUIDs, Task/Employee use integers), so the
            // key is stored as its string representation either way.
            $table->string('subject_type')->nullable();
            $table->string('subject_id')->nullable();
            $table->index(['subject_type', 'subject_id']);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
