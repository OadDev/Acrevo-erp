<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Auditor users could never submit a Leave Request - LeaveRequestController
 * ::store() requires $user->employee, and only Worker/Sales/HR/Finance/
 * Executive Team Leader/QC Officer/Marketing/Management ever got a linked
 * Employee record (UserController::syncLinkedEmployeeRecord). Auditor is now
 * included in that sync, but that only takes effect on the next save of each
 * User; this backfills an Employee record for whoever already holds the
 * Auditor role today.
 *
 * Deliberately does NOT touch Employee::STAFF_ROLES - that list also drives
 * HR Attendance and the staff Payroll scope, which Auditor was not asked to
 * be added to.
 */
return new class extends Migration
{
    public function up(): void
    {
        $roleId = DB::table('roles')->where('name', 'Auditor')->value('id');

        if (! $roleId) {
            return;
        }

        $userIds = DB::table('model_has_roles')
            ->where('role_id', $roleId)
            ->where('model_type', \App\Models\User::class)
            ->pluck('model_id')
            ->unique();

        $alreadyLinked = DB::table('employees')->whereNotNull('user_id')->pluck('user_id')->unique();
        $unlinkedUserIds = $userIds->diff($alreadyLinked);

        if ($unlinkedUserIds->isEmpty()) {
            return;
        }

        $users = DB::table('users')->whereIn('id', $unlinkedUserIds)->get();
        $now = now();

        foreach ($users as $user) {
            DB::table('employees')->insert([
                'employee_code' => 'EMP-'.strtoupper(Str::random(8)),
                'user_id' => $user->id,
                'name' => $user->name,
                'phone' => $user->phone,
                'email' => $user->email,
                'department_id' => $user->department_id,
                'employment_type' => 'permanent',
                'status' => 'active',
                'salary_type' => 'monthly',
                'created_by' => $user->id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Not reversible - would risk deleting Employee records a user has
        // since edited by hand.
    }
};
