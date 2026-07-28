<?php

namespace App\Modules\Authentication\Controllers\Auth;

use App\Events\UserLoggedIn;
use App\Events\UserLoggedOut;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Authentication\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Spatie\Permission\PermissionRegistrar;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('authentication::auth.login');
    }

    /**
     * Handle an incoming authentication request with complete role context isolation.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        // Flush previous session completely to avoid context bleed
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $request->authenticate();

        $request->session()->regenerate();

        // Clear Spatie permission memory cache
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        /** @var User|null $user */
        $user = $request->user();
        if ($user) {
            event(new UserLoggedIn($user));
        }

        // Redirect strictly based on active role
        if ($user?->hasRole('student')) {
            return redirect()->route('candidate.portal');
        }

        if ($user?->hasRole('teacher')) {
            return redirect()->route('admin.question-banks.index');
        }

        if ($user?->hasRole('finance')) {
            return redirect()->route('admin.commerce.index');
        }

        if ($user?->hasRole('admin') || $user?->hasRole('super-admin')) {
            return redirect()->route('admin.dashboard');
        }

        return redirect()->route('candidate.portal');
    }

    /**
     * Destroy an authenticated session and clear all cached credentials.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        if ($user) {
            event(new UserLoggedOut($user));
        }

        return redirect('/login');
    }
}
