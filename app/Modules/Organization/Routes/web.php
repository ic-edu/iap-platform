<?php

use App\Modules\Organization\Controllers\AdminOrganizationController;
use App\Modules\Organization\Controllers\InvitationController;
use App\Modules\Organization\Controllers\OrganizationPortalController;
use App\Modules\Organization\Controllers\SuperAdminOrganizationApprovalController;
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

// 4. Registration Admin & Super Admin Operational Organization Administration
Route::middleware(['web', 'auth', 'role:admin|super-admin'])
    ->prefix('admin/organizations')
    ->name('admin.organizations.')
    ->group(function () {
        Route::get('/', [AdminOrganizationController::class, 'index'])->name('index');
        Route::get('/create', [AdminOrganizationController::class, 'create'])->name('create');
        Route::post('/', [AdminOrganizationController::class, 'store'])->name('store');
        Route::get('/{organization}/edit', [AdminOrganizationController::class, 'edit'])->name('edit');
        Route::put('/{organization}', [AdminOrganizationController::class, 'update'])->name('update');
        Route::post('/{organization}/submit', [AdminOrganizationController::class, 'submitForApproval'])->name('submit');
        Route::post('/{organization}/invite-coordinator', [AdminOrganizationController::class, 'inviteCoordinator'])->name('invite-coordinator');
        Route::post('/{organization}/invitations/{invitation}/resend', [AdminOrganizationController::class, 'resendCoordinatorInvitation'])->name('invitations.resend');
        Route::post('/{organization}/invitations/{invitation}/revoke', [AdminOrganizationController::class, 'revokeCoordinatorInvitation'])->name('invitations.revoke');
        Route::post('/{organization}/toggle-status', [AdminOrganizationController::class, 'toggleStatus'])->name('toggle-status');
        Route::post('/{organization}/suspend', [AdminOrganizationController::class, 'suspend'])->name('suspend');
        Route::post('/{organization}/activate', [AdminOrganizationController::class, 'activate'])->name('activate');
    });

// 5. Super Admin Organization Governance & Approvals
Route::middleware(['web', 'auth', 'role:super-admin'])
    ->prefix('admin/approvals/organizations')
    ->group(function () {
        Route::get('/', [SuperAdminOrganizationApprovalController::class, 'index'])->name('admin.approvals.organizations');
        Route::post('/{organization}/approve', [SuperAdminOrganizationApprovalController::class, 'approveOrganization'])->name('admin.approvals.organizations.approve');
        Route::post('/{organization}/return-revision', [SuperAdminOrganizationApprovalController::class, 'returnRevision'])->name('admin.approvals.organizations.return-revision');
        Route::post('/{organization}/reject', [SuperAdminOrganizationApprovalController::class, 'rejectOrganization'])->name('admin.approvals.organizations.reject');
        Route::post('/{organization}/archive', [SuperAdminOrganizationApprovalController::class, 'approveArchive'])->name('admin.approvals.organizations.archive');
        Route::post('/groups/{group}/approve', [SuperAdminOrganizationApprovalController::class, 'approveGroup'])->name('admin.approvals.organizations.groups.approve');
    });
