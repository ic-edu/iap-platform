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
        $redirectUrl = $request->input('redirect') ?? $request->query('redirect');

        // Flush previous session completely to avoid context bleed
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $request->authenticate();

        $request->session()->regenerate();

        // Clear Spatie permission memory cache
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        /** @var User|null $user */
        $user = $request->user();

        // Check if account status is pending approval (UAC-003)
        if ($user && $user->status === 'pending_approval') {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Your account is awaiting approval.',
            ]);
        }

        if ($user) {
            event(new UserLoggedIn($user));
        }

        // Return to redirect/intended URL if present and safe (e.g. invitation acceptance)
        if ($redirectUrl) {
            $parsedHost = parse_url($redirectUrl, PHP_URL_HOST);
            $appHost = parse_url(config('app.url'), PHP_URL_HOST);
            if (!$parsedHost || $parsedHost === $appHost || in_array($parsedHost, ['localhost', '127.0.0.1'], true)) {
                return redirect()->to($redirectUrl);
            }
        }

        // Redirect strictly based on active role
        if ($user?->hasRole('super-admin')) {
            return redirect()->route('super-admin.dashboard');
        }

        if ($user?->hasRole('admin')) {
            return redirect()->route('admin.dashboard');
        }

        if ($user?->hasRole('repository-manager')) {
            return redirect()->route('admin.repository-manager.dashboard');
        }

        if ($user?->hasRole('teacher')) {
            return redirect()->route('teacher.dashboard');
        }

        if ($user?->hasRole('finance')) {
            return redirect()->route('finance.dashboard');
        }

        if ($user?->hasRole('organization-coordinator')) {
            $orgSlug = $user->organizationMemberships()->first()?->organization?->slug;
            if ($orgSlug) {
                return redirect()->route('organization.dashboard', $orgSlug);
            }
        }

        if ($user?->hasRole('student')) {
            return redirect()->route('candidate.portal');
        }

        return redirect()->route('candidate.portal');
    }

    /**
     * Destroy an authenticated session and clear all cached credentials.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();
        $returnUrl = $request->input('return_url') ?? $request->query('return_url') ?? $request->session()->get('url.intended');

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        if ($user) {
            event(new UserLoggedOut($user));
        }

        if ($returnUrl) {
            $parsedHost = parse_url($returnUrl, PHP_URL_HOST);
            $appHost = parse_url(config('app.url'), PHP_URL_HOST);
            if (!$parsedHost || $parsedHost === $appHost || in_array($parsedHost, ['localhost', '127.0.0.1'], true)) {
                return redirect()->route('login', ['redirect' => $returnUrl]);
            }
        }

        return redirect('/login');
    }
}
