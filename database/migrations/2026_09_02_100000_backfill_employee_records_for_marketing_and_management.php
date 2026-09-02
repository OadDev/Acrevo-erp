<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Marketing and Management users could never submit a Leave Request -
 * LeaveRequestController::store() requires $user->employee, and only
 * Worker/Sales/HR/Finance/Executive Team Leader/QC Officer ever got a
 * linked Employee record (UserController::syncLinkedEmployeeRecord).
 * Marketing/Management are now included in that sync, but that only takes
 * effect on the next save of each User; this backfills an Employee record
 * for whoever already has one of those two roles today.
 *
 * Deliberately does NOT touch Employee::STAFF_ROLES - that list also
 * drives HR Attendance and the staff Payroll scope, which Marketing and
 * Management were not asked to be added to.
 */
return new class extends Migration
{
    public function up(): void
    {
        $roles = ['Marketing', 'Management'];

        $roleIds = DB::table('roles')->whereIn('name', $roles)->pluck('id', 'name');

        if ($roleIds->isEmpty()) {
            return;
        }

        $userIds = DB::table('model_has_roles')
            ->whereIn('role_id', $roleIds->values())
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
