<?php

namespace App\Modules\Settings\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Settings\Services\SettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function __construct(
        protected SettingService $settingService
    ) {}

    /**
     * Display settings index page.
     */
    public function index(): View
    {
        $settings = [
            'app_name' => $this->settingService->get('app_name', config('app.name', 'iC.edu Assessment Platform')),
            'app_locale' => $this->settingService->get('app_locale', 'en'),
            'app_timezone' => $this->settingService->get('app_timezone', 'Asia/Jakarta'),
            'maintenance_mode' => $this->settingService->get('maintenance_mode', 'false'),
            'branding_logo' => $this->settingService->get('branding_logo', ''),
        ];

        return view('settings::index', compact('settings'));
    }

    /**
     * Update settings.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'app_name' => ['required', 'string', 'max:255'],
            'app_locale' => ['required', 'string', 'max:10'],
            'app_timezone' => ['required', 'string', 'max:50'],
            'maintenance_mode' => ['required', 'in:true,false'],
            'branding_logo' => ['nullable', 'string', 'max:500'],
        ]);

        foreach ($validated as $key => $value) {
            $this->settingService->set($key, $value ?? '', 'general');
        }

        return redirect()->route('admin.settings.index')->with('status', 'settings-updated');
    }
}
