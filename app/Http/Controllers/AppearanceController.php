<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AppearanceController extends Controller
{
    /**
     * Show appearance settings page (available to all authenticated roles).
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $currentTheme = $user?->getThemePreference() ?? 'dark';

        return view('settings.appearance', compact('user', 'currentTheme'));
    }

    /**
     * Update user appearance preference.
     */
    public function update(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'theme' => ['required', 'string', 'in:dark,light,system'],
        ]);

        $user = $request->user();
        if ($user) {
            $user->update(['theme_preference' => $validated['theme']]);
            session(['theme_preference' => $validated['theme']]);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'theme' => $validated['theme'],
                'message' => 'Theme preference saved successfully.',
            ]);
        }

        return back()->with('status', 'theme-updated');
    }
}
