<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_checklists', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('work_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('executive_team_id')->constrained();
            $table->date('date');
            $table->json('items')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('daily_progress_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('work_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('executive_team_id')->constrained();
            $table->date('date');
            $table->text('completed_work')->nullable();
            $table->text('pending_work')->nullable();
            $table->text('problems')->nullable();
            $table->text('materials_required')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('submitted_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('material_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('work_order_id')->constrained()->cascadeOnDelete();
            $table->string('material_name');
            $table->string('unit')->default('Nos');
            $table->decimal('quantity', 12, 2);
            $table->decimal('rate', 12, 2)->default(0);
            $table->decimal('amount', 14, 2)->default(0);
            $table->string('vendor')->nullable();
            $table->date('entry_date');
            $table->foreignId('added_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('labour_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('work_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->string('labour_type')->nullable();
            $table->unsignedInteger('count')->default(1);
            $table->decimal('wage_rate', 12, 2)->default(0);
            $table->decimal('hours', 5, 2)->nullable();
            $table->decimal('amount', 14, 2)->default(0);
            $table->date('entry_date');
            $table->foreignId('added_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('labour_entries');
        Schema::dropIfExists('material_entries');
        Schema::dropIfExists('daily_progress_reports');
        Schema::dropIfExists('daily_checklists');
    }
};
