<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LoginPageSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;

class LoginPageSettingController extends Controller
{
    public function edit(): View
    {
        $setting = LoginPageSetting::current();

        return view('admin.settings.login-page', compact('setting'));
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'offer_title' => ['nullable', 'string', 'max:150'],
            'offer_body' => ['nullable', 'string', 'max:2000'],
            'is_offer_active' => ['nullable', 'boolean'],
            'background' => ['nullable', 'image', 'max:8192'],
            'remove_background' => ['nullable', 'boolean'],
        ]);

        $setting = LoginPageSetting::current();

        $setting->update([
            'offer_title' => $data['offer_title'] ?? null,
            'offer_body' => $data['offer_body'] ?? null,
            'is_offer_active' => $request->boolean('is_offer_active'),
        ]);

        if ($request->boolean('remove_background')) {
            $setting->clearMediaCollection('background');
        }

        if ($request->hasFile('background')) {
            try {
                $setting->addMedia($request->file('background'))->toMediaCollection('background');
            } catch (FileIsTooBig $e) {
                return back()->withErrors(['background' => 'That image is too large (max 8MB).']);
            }
        }

        return redirect()->route('admin.settings.login-page.edit')->with('success', 'Login page settings saved.');
    }
}
