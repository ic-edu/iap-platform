<?php

use App\Models\User;
use App\Modules\Authentication\Controllers\Auth\AuthenticatedSessionController;
use App\Modules\Authentication\Controllers\Auth\ConfirmablePasswordController;
use App\Modules\Authentication\Controllers\Auth\EmailVerificationNotificationController;
use App\Modules\Authentication\Controllers\Auth\EmailVerificationPromptController;
use App\Modules\Authentication\Controllers\Auth\NewPasswordController;
use App\Modules\Authentication\Controllers\Auth\PasswordController;
use App\Modules\Authentication\Controllers\Auth\PasswordResetLinkController;
use App\Modules\Authentication\Controllers\Auth\RegisteredUserController;
use App\Modules\Authentication\Controllers\Auth\VerifyEmailController;
use App\Modules\Authentication\Controllers\ProfileController;
use App\Modules\Reporting\Services\DashboardMetricsService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/dashboard', function () {
    /** @var User|null $user */
    $user = Auth::user();

    if ($user?->hasRole('super-admin')) {
        return redirect()->route('super-admin.dashboard');
    }

    if ($user?->hasRole('repository-manager')) {
        return redirect()->route('admin.repository-manager.dashboard');
    }

    if ($user?->hasRole('admin')) {
        return redirect()->route('admin.dashboard');
    }

    if ($user?->hasRole('teacher')) {
        return redirect()->route('teacher.dashboard');
    }

    if ($user?->hasRole('finance')) {
        return redirect()->route('finance.dashboard');
    }

    return redirect()->route('candidate.portal');
})->middleware(['auth'])->name('dashboard');

Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])
        ->name('register');

    Route::post('register', [RegisteredUserController::class, 'store']);

    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->name('password.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
