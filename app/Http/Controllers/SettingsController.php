<?php

namespace App\Http\Controllers;

use App\Models\AISetting;
use App\Models\PanelSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;

class SettingsController extends Controller
{
    public function index(Request $request)
    {
        $tab = $request->query('tab', 'ai');

        $setting = AISetting::where('user_id', Auth::id())->first() ?? new AISetting();
        $panelSetting = PanelSetting::where('user_id', Auth::id())->first() ?? new PanelSetting();
        $timezones = PanelSetting::timezones();

        return view('settings.index', compact('setting', 'panelSetting', 'timezones', 'tab'));
    }

    /**
     * Update AI provider settings
     */
    public function update(Request $request)
    {
        $request->validate([
            'provider' => 'required|in:claude,gemini,openai,groq',
            'model' => 'nullable|string|max:100',
        ]);

        $data = [
            'provider' => $request->provider,
            'model' => $request->model,
        ];

        // Only update API key if a new one is provided (not the masked dots)
        if ($request->api_key && !str_starts_with($request->api_key, '••')) {
            $data['api_key'] = Crypt::encryptString($request->api_key);
        }

        AISetting::updateOrCreate(
            ['user_id' => Auth::id()],
            $data
        );

        return redirect()->route('settings.index', ['tab' => 'ai'])->with('success', 'AI provider settings saved!');
    }

    /**
     * Update panel settings
     */
    public function updatePanel(Request $request)
    {
        $request->validate([
            'panel_name' => 'required|string|max:50',
            'timezone' => 'required|string|max:50',
            'session_timeout' => 'required|integer|min:5|max:1440',
            'language' => 'required|in:en',
        ]);

        PanelSetting::updateOrCreate(
            ['user_id' => Auth::id()],
            [
                'panel_name' => $request->panel_name,
                'timezone' => $request->timezone,
                'session_timeout' => $request->session_timeout,
                'language' => $request->language,
            ]
        );

        return redirect()->route('settings.index', ['tab' => 'panel'])->with('success', 'Panel settings saved!');
    }

    /**
     * Update password (Security tab)
     */
    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        // Check current password
        if (!Hash::check($request->current_password, Auth::user()->password)) {
            return redirect()->route('settings.index', ['tab' => 'security'])
                ->withErrors(['current_password' => 'Current password is incorrect.']);
        }

        Auth::user()->update([
            'password' => Hash::make($request->password),
        ]);

        return redirect()->route('settings.index', ['tab' => 'security'])->with('success', 'Password changed successfully!');
    }
}
