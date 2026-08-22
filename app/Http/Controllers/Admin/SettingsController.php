<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\MailSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function editMail(): View
    {
        $settings = [
            'mailer' => Setting::get('mail.mailer', 'log'),
            'host' => Setting::get('mail.host'),
            'port' => Setting::get('mail.port'),
            'username' => Setting::get('mail.username'),
            'has_password' => filled(Setting::get('mail.password')),
            'encryption' => Setting::get('mail.encryption'),
            'from_address' => Setting::get('mail.from_address', config('mail.from.address')),
            'from_name' => Setting::get('mail.from_name', config('mail.from.name')),
        ];

        return view('admin.settings.mail', compact('settings'));
    }

    public function updateMail(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'mailer' => ['required', 'in:smtp,log'],
            'host' => ['required_if:mailer,smtp', 'nullable', 'string', 'max:255'],
            'port' => ['required_if:mailer,smtp', 'nullable', 'integer', 'min:1', 'max:65535'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'encryption' => ['nullable', 'in:tls,ssl,'],
            'from_address' => ['required', 'email', 'max:255'],
            'from_name' => ['required', 'string', 'max:255'],
        ]);

        Setting::set('mail.mailer', $data['mailer']);
        Setting::set('mail.host', $data['host'] ?? null);
        Setting::set('mail.port', $data['port'] ?? null);
        Setting::set('mail.username', $data['username'] ?? null);
        Setting::set('mail.encryption', $data['encryption'] ?? null);
        Setting::set('mail.from_address', $data['from_address']);
        Setting::set('mail.from_name', $data['from_name']);

        if (filled($data['password'] ?? null)) {
            Setting::set('mail.password', Crypt::encryptString($data['password']));
        }

        MailSettings::apply();

        return redirect()->route('admin.settings.mail.edit')->with('success', 'Mail settings saved.');
    }

    public function sendTest(Request $request): RedirectResponse
    {
        MailSettings::apply();

        try {
            Mail::raw('This is a test email from '.config('app.name').'. If you received this, your mail settings are working correctly.', function ($message) use ($request) {
                $message->to($request->user()->email)->subject('Test Email — '.config('app.name'));
            });

            return redirect()->route('admin.settings.mail.edit')->with('success', 'Test email sent to '.$request->user()->email.'. Check the inbox (and spam folder).');
        } catch (\Throwable $e) {
            return redirect()->route('admin.settings.mail.edit')->with('error', 'Test email failed: '.$e->getMessage());
        }
    }
}
