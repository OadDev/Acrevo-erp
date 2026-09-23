<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The one piece of real data a Demo-role request can still reach: its own
 * row in the (always main-connection) users table. Blocks editing or
 * deleting it, so one client's demo session can't change the shared
 * credentials or lock out the next client. Everything else this role
 * touches is business data, already isolated by
 * SwitchDemoDatabaseConnection.
 */
class RestrictDemoOwnAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasRole('Demo')) {
            return $next($request);
        }

        if (! in_array($request->route()?->getName(), ['profile.update', 'profile.destroy'], true)) {
            return $next($request);
        }

        $message = 'This is a shared demo account - its own login details cannot be changed.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 403);
        }

        return back()->with('error', $message);
    }
}
