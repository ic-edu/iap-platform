<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    /**
     * Display Platform Settings workspace.
     */
    public function index(): View
    {
        $settings = [
            'app_name' => config('app.name', 'iC.edu Assessment Platform'),
            'app_url' => config('app.url', 'https://assessment.icedu.org'),
            'maintenance_mode' => app()->isDownForMaintenance(),
            'certificate_prefix' => 'CERT',
            'security_session_lifetime' => 120,
        ];

        return view('admin.settings.index', compact('settings'));
    }

    /**
     * Update Platform Settings.
     */
    public function update(Request $request): RedirectResponse
    {
        return redirect()->route('admin.settings.index')->with('status', 'Platform settings updated successfully.');
    }
}
