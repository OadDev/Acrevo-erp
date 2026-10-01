<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Every business-model insert a Demo-role request makes sets created_by
 * (or a similar user_id column) to auth()->id() - the demo account's real
 * id in the main users table. Those same columns are foreign keys on the
 * isolated demo database's own (structurally identical, but otherwise
 * unused) users table, so without a row there sharing that exact id, every
 * such insert would fail its foreign key constraint. This mirrors just
 * enough of the demo account into the demo database to satisfy that -
 * never used for authentication, since App\Models\User stays pinned to
 * the main connection.
 */
class DemoDatabase
{
    public static function mirrorDemoUser(?User $demoUser = null): ?int
    {
        $demoUser ??= User::where('email', 'demo@geethanworks.in')->first();

        if (! $demoUser) {
            return null;
        }

        DB::connection('demo')->table('users')->updateOrInsert(
            ['id' => $demoUser->id],
            ['name' => $demoUser->name, 'email' => $demoUser->email, 'password' => $demoUser->password,
                'is_active' => true, 'created_at' => now(), 'updated_at' => now()]
        );

        return $demoUser->id;
    }
}
