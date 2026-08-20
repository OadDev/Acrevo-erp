<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Users created with the Worker role have always gotten a linked Employee
 * record (UserController::syncLinkedEmployeeRecord), but that sync only
 * ever covered Worker - Sales/HR/Finance/Executive Team Leader/QC Officer
 * users were never linked, so HR > Attendance had no Employee row to mark
 * attendance against for any of them. The sync now covers those roles too,
 * but that only takes effect on the next save of each User; this backfills
 * an Employee record for whoever already has one of those roles today.
 */
return new class extends Migration
{
    public function up(): void
    {
        $staffRoles = ['Sales', 'HR', 'Finance', 'Executive Team Leader', 'QC Officer', 'Worker'];

        $roleIds = DB::table('roles')->whereIn('name', $staffRoles)->pluck('id', 'name');

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
