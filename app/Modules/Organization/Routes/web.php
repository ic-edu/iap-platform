<?php

use App\Modules\Organization\Controllers\AdminOrganizationController;
use App\Modules\Organization\Controllers\InvitationController;
use App\Modules\Organization\Controllers\OrganizationPortalController;
use Illuminate\Support\Facades\Route;

// 1. Public / Guest Invitation Acceptance Routes
Route::middleware(['web'])->prefix('invitations')->name('invitations.')->group(function () {
    Route::get('/{token}', [InvitationController::class, 'showAccept'])->name('accept');
    Route::post('/{token}/accept', [InvitationController::class, 'processAccept'])->name('process');
});

// 2. Organization Selector & Safe Landing Routes
Route::middleware(['web', 'auth'])->prefix('organization')->name('organization.')->group(function () {
    Route::get('/select', [OrganizationPortalController::class, 'selectOrganization'])->name('select');
    Route::get('/no-access', [OrganizationPortalController::class, 'noAccess'])->name('no-access');
});

// 3. Tenant-Scoped Organization Portal Routes
Route::middleware(['web', 'auth', 'org.context'])
    ->prefix('organization/{organization}')
    ->name('organization.')
    ->group(function () {
        Route::get('/dashboard', [OrganizationPortalController::class, 'dashboard'])->name('dashboard');

        // Member Roster & Invitations
        Route::get('/candidates', [OrganizationPortalController::class, 'candidates'])->name('candidates');
        Route::post('/candidates/invite', [OrganizationPortalController::class, 'invite'])->name('candidates.invite');
        Route::post('/invitations/{invitation}/resend', [OrganizationPortalController::class, 'resendInvitation'])->name('invitations.resend');
        Route::post('/invitations/{invitation}/revoke', [OrganizationPortalController::class, 'revokeInvitation'])->name('invitations.revoke');

        // Groups / Cohorts
        Route::get('/groups', [OrganizationPortalController::class, 'groups'])->name('groups');
        Route::post('/groups', [OrganizationPortalController::class, 'storeGroup'])->name('groups.store');
        Route::get('/groups/{group}', [OrganizationPortalController::class, 'showGroup'])->name('groups.show');
        Route::put('/groups/{group}', [OrganizationPortalController::class, 'updateGroup'])->name('groups.update');
        Route::post('/groups/{group}/toggle', [OrganizationPortalController::class, 'toggleGroupStatus'])->name('groups.toggle');
        Route::post('/groups/{group}/members', [OrganizationPortalController::class, 'addGroupMember'])->name('groups.members.add');
        Route::delete('/groups/{group}/members/{membership}', [OrganizationPortalController::class, 'removeGroupMember'])->name('groups.members.remove');

        // Profile
        Route::get('/profile', [OrganizationPortalController::class, 'profile'])->name('profile');
        Route::put('/profile', [OrganizationPortalController::class, 'updateProfile'])->name('profile.update');
    });

// 4. Super Admin Internal Organization Administration
Route::middleware(['web', 'auth', 'role:super-admin'])
    ->prefix('admin/organizations')
    ->name('admin.organizations.')
    ->group(function () {
        Route::get('/', [AdminOrganizationController::class, 'index'])->name('index');
        Route::get('/create', [AdminOrganizationController::class, 'create'])->name('create');
        Route::post('/', [AdminOrganizationController::class, 'store'])->name('store');
        Route::get('/{organization}/edit', [AdminOrganizationController::class, 'edit'])->name('edit');
        Route::put('/{organization}', [AdminOrganizationController::class, 'update'])->name('update');
        Route::post('/{organization}/toggle-status', [AdminOrganizationController::class, 'toggleStatus'])->name('toggle-status');
    });
