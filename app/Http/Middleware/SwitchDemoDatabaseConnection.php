<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * The actual guarantee behind the Demo login: for the duration of a
 * Demo-role request, every business model (which doesn't pin its own
 * connection) reads and writes the isolated "demo" database instead of
 * production - so a shared demo login can freely create/edit/delete sample
 * data with no way to reach real data. User, Role, and Permission pin
 * themselves to database.main_connection so auth/permission checks are
 * unaffected by the switch below.
 *
 * The try/finally guarantees the connection is always restored before this
 * middleware returns - including when the inner request throws - so a
 * later step in the same response cycle (notably session save, which reads
 * whatever connection is "default" at that moment) never sees "demo".
 */
class SwitchDemoDatabaseConnection
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasRole('Demo')) {
            return $next($request);
        }

        DB::setDefaultConnection('demo');

        try {
            return $next($request);
        } finally {
            DB::setDefaultConnection(config('database.main_connection'));
        }
    }
}
