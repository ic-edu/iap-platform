<?php

namespace App\Modules\Authentication\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Authentication\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;

class ProfileController extends Controller
{
    /**
     * Redirect standalone profile view requests to the in-dashboard Profile Drawer.
     */
    public function edit(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user?->hasRole('super-admin')) {
            return redirect()->route('super-admin.dashboard', ['open_profile' => 1]);
        }

        if ($user?->hasRole('admin')) {
            return redirect()->route('admin.dashboard', ['open_profile' => 1]);
        }

        if ($user?->hasRole('teacher')) {
            return redirect()->route('teacher.dashboard', ['open_profile' => 1]);
        }

        if ($user?->hasRole('finance')) {
            return redirect()->route('finance.dashboard', ['open_profile' => 1]);
        }

        return redirect()->route('candidate.portal', ['open_profile' => 1]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::back()->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
