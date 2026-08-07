<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('employee_code')->nullable()->unique()->after('id');
            $table->string('phone')->nullable()->after('email');
            $table->string('avatar_path')->nullable()->after('phone');
            $table->foreignId('department_id')->nullable()->after('avatar_path')->constrained()->nullOnDelete();
            $table->string('designation')->nullable()->after('department_id');
            $table->boolean('is_active')->default(true)->after('designation');
            $table->timestamp('last_login_at')->nullable()->after('is_active');
            $table->boolean('must_change_password')->default(false)->after('last_login_at');
            $table->string('two_factor_secret')->nullable()->after('must_change_password');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
            $table->foreignId('created_by')->nullable()->after('two_factor_confirmed_at')->constrained('users')->nullOnDelete();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('department_id');
            $table->dropColumn([
                'employee_code', 'phone', 'avatar_path', 'designation',
                'is_active', 'last_login_at', 'must_change_password',
                'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at',
            ]);
            $table->dropSoftDeletes();
        });
    }
};
