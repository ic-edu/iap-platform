<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AppearanceController extends Controller
{
    /**
     * Redirect direct appearance GET requests to profile settings.
     */
    public function index(Request $request): RedirectResponse
    {
        return redirect()->route('profile.edit');
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
