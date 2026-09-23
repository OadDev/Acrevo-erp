<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A hard, permission-independent guarantee for the Demo role: no matter
 * what a future permission grant gives it, it can never submit a
 * non-GET request against this live production database. Logout is the
 * one exception - a read-only account must still be able to sign out.
 */
class BlockDemoWrites
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasRole('Demo') || $request->isMethodSafe()) {
            return $next($request);
        }

        if ($request->route()?->getName() === 'logout') {
            return $next($request);
        }

        $message = 'This is a read-only demo account - adding, editing, and deleting is disabled.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 403);
        }

        return back()->with('error', $message);
    }
}
