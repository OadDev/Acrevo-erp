<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;

/**
 * SMTP credentials are configured by an Admin via Settings > Mail rather
 * than by editing .env on the server (which isn't even reachable from the
 * deploy pipeline). This applies whatever is stored in the settings table
 * on top of the config/mail.php defaults for the current request.
 */
class MailSettings
{
    public static function apply(): void
    {
        // Runs on every boot, including contexts with no usable database
        // connection yet (composer install's package:discover, a fresh
        // install before migrations have run, artisan commands with no
        // .env). Never let this take the app - or the deploy pipeline -
        // down; just fall back to config/mail.php's own defaults.
        try {
            if (! Schema::hasTable('settings')) {
                return;
            }
        } catch (\Throwable) {
            return;
        }

        $mailer = Setting::get('mail.mailer');

        if (! $mailer) {
            return;
        }

        config(['mail.default' => $mailer]);

        if ($mailer !== 'smtp') {
            return;
        }

        $encryptedPassword = Setting::get('mail.password');

        // Laravel's SMTP transport reads 'scheme' (not 'encryption') to decide
        // implicit TLS: 'smtps' for SSL, plain 'smtp' lets Symfony Mailer
        // negotiate STARTTLS automatically (correct for both 'tls' and 'none').
        $scheme = Setting::get('mail.encryption') === 'ssl' ? 'smtps' : 'smtp';

        config([
            'mail.mailers.smtp.scheme' => $scheme,
            'mail.mailers.smtp.host' => Setting::get('mail.host'),
            'mail.mailers.smtp.port' => Setting::get('mail.port'),
            'mail.mailers.smtp.username' => Setting::get('mail.username'),
            'mail.mailers.smtp.password' => $encryptedPassword ? Crypt::decryptString($encryptedPassword) : null,
            'mail.from.address' => Setting::get('mail.from_address', config('mail.from.address')),
            'mail.from.name' => Setting::get('mail.from_name', config('mail.from.name')),
        ]);
    }
}
