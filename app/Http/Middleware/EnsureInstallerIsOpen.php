<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates every /install/* route. The installer runs before the app has a
 * database (or possibly even an .env file), so it can't rely on sessions,
 * CSRF tokens, or auth — this is the only line of defense, and it's why
 * the routes are also stripped of EncryptCookies/StartSession/CSRF: none
 * of those can be trusted to work yet either.
 */
class EnsureInstallerIsOpen
{
    public function handle(Request $request, Closure $next): Response
    {
        if (file_exists(storage_path('installed'))) {
            return response(
                'Geethan Works ERP is already installed. Delete storage/installed on '.
                'the server (over SSH) if you really need to run the installer again.',
                403
            );
        }

        $tokenPath = storage_path('install_token.txt');

        if (! is_dir(storage_path())) {
            @mkdir(storage_path(), 0755, true);
        }

        if (! file_exists($tokenPath)) {
            file_put_contents($tokenPath, bin2hex(random_bytes(20)));
        }

        $expected = trim((string) file_get_contents($tokenPath));
        $given = (string) $request->query('token', '');

        if ($expected === '' || ! hash_equals($expected, $given)) {
            return response(
                "Missing or invalid install token.\n\n".
                "SSH into the server and run:\n  cat storage/install_token.txt\n\n".
                'Then open /install?token=<that value>',
                403
            );
        }

        return $next($request);
    }
}
