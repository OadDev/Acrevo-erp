<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_entries', function (Blueprint $table) {
            $table->string('brand')->nullable()->after('material_name');
            $table->string('size')->nullable()->after('brand');
        });

        Schema::table('daily_checklists', function (Blueprint $table) {
            $table->string('title')->nullable()->after('date');
        });

        Schema::create('daily_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_checklist_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->boolean('is_done')->default(false);
            $table->timestamp('done_at')->nullable();
            $table->foreignId('done_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_checklist_items');

        Schema::table('daily_checklists', function (Blueprint $table) {
            $table->dropColumn('title');
        });

        Schema::table('material_entries', function (Blueprint $table) {
            $table->dropColumn(['brand', 'size']);
        });
    }
};
