<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserCreationRequest;
use App\Models\UserDeletionRequest;
use App\Notifications\SystemAlertNotification;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    /**
     * Enforce hierarchical role protection (Baseline v1.1 UAC-001 / UAC-002 / UAC-003).
     *
     * Hierarchy: Super Admin > Admin > Teacher > Student (Finance is independent)
     * Admin MUST NOT manage or request deletion of Super Admin accounts.
     */
    private function checkHierarchicalProtection(Request $request, User $targetUser, string $actionName): void
    {
        $actor = $request->user();
        if (!$actor) {
            abort(401);
        }

        // Regular Admin attempting to manage Super Admin account
        if ($actor->hasRole('admin') && !$actor->hasRole('super-admin') && $targetUser->hasRole('super-admin')) {
            ActivityLogger::log(
                action: 'FORBIDDEN_USER_MANAGEMENT',
                description: "Hierarchical Role Protection: Admin {$actor->email} attempted forbidden action '{$actionName}' on Super Admin account {$targetUser->email}",
                subject: $targetUser,
                properties: [
                    'action_attempted' => $actionName,
                    'actor_id' => $actor->id,
                    'actor_email' => $actor->email,
                    'target_user_id' => $targetUser->id,
                    'target_user_email' => $targetUser->email,
                    'result' => 'Forbidden',
                    'reason' => 'Hierarchical Role Protection',
                ]
            );

            abort(403, 'Hierarchical Role Protection: Admins are not permitted to manage, modify, or request deletion of Super Admin accounts.');
        }
    }

    /**
     * Role Population Classifications (IA Separation).
     */
    public const STAFF_ROLES = ['super-admin', 'admin', 'teacher', 'repository-manager', 'finance'];
    public const CANDIDATE_ROLES = ['student'];

    /**
     * Display Canonical Universal All Users Directory (/admin/all-users).
     * Represents all App\Models\User accounts under Super Admin governance:
     * Internal Staff, Candidates, Organization Users, and Unassigned accounts.
     */
    public function allUsers(Request $request): View
    {
        $query = User::query()->with(['roles', 'organizationMemberships.organization']);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($category = $request->input('category')) {
            if ($category === 'internal_staff') {
                $query->whereHas('roles', fn($q) => $q->whereIn('name', self::STAFF_ROLES));
            } elseif ($category === 'candidate') {
                $query->whereHas('roles', fn($q) => $q->where('name', 'student'));
            } elseif ($category === 'organization_user') {
                $query->where(function ($q) {
                    $q->whereHas('roles', fn($sub) => $sub->where('name', 'organization-coordinator'))
                        ->orWhereHas('organizationMemberships');
                });
            } elseif ($category === 'unassigned') {
                $query->doesntHave('roles');
            }
        }

        if ($role = $request->input('role')) {
            if ($role === 'unassigned') {
                $query->doesntHave('roles');
            } else {
                $query->role($role);
            }
        }

        if ($status = $request->input('status')) {
            if ($status !== 'all') {
                $query->where('status', $status);
            }
        }

        $users = $query->latest()->paginate(15)->withQueryString();

        return view('admin.users.all', compact('users'));
    }

    /**
     * Display Candidate Management Workspace (/admin/candidates).
     * Strictly scopes query to candidate/student population.
     */
    public function candidates(Request $request): View
    {
        $query = User::role(self::CANDIDATE_ROLES)->with(['roles']);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($filter = $request->input('filter')) {
            if ($filter === 'paid-eligible' || $filter === 'eligible') {
                $query->whereHas('orders', function ($oq) {
                    $oq->whereHas('invoice.payments', fn($pq) => $pq->whereIn('status', [\App\Modules\Commerce\Domain\Enums\PaymentStatus::Success, \App\Modules\Commerce\Domain\Enums\PaymentStatus::Paid]));
                });
            }
        }

        $candidates = $query->latest()->paginate(15)->withQueryString();

        return view('admin.candidates.index', compact('candidates'));
    }

    /**
     * Register a new candidate directly with immediate active status.
     */
    public function storeCandidate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8'],
            'phone_number' => ['nullable', 'string', 'max:50'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'status' => 'active',
            'phone_number' => $validated['phone_number'] ?? null,
        ]);

        $user->assignRole('student');

        ActivityLogger::log(
            'CANDIDATE_CREATED',
            "Created candidate account {$user->name} ({$user->email}) with student role",
            $user
        );

        return redirect()->route('admin.candidates.index')->with('status', "Candidate {$user->name} registered and activated successfully.");
    }

    /**
     * Display Institutional Staff & Access Control Workspace (/admin/users & /admin/staff).
     * Strictly scopes query to institutional staff population only (excludes candidates).
     */
    public function index(Request $request): View
    {
        $query = User::whereHas('roles', fn($q) => $q->whereIn('name', self::STAFF_ROLES))
            ->with(['roles', 'deletionRequests', 'creationRequests']);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($role = $request->input('role')) {
            if (in_array($role, self::STAFF_ROLES, true)) {
                $query->role($role);
            }
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $users = $query->latest()->paginate(15)->withQueryString();

        return view('admin.users.index', compact('users'));
    }

    /**
     * Store a new user account with role-based workflow (UAC-003).
     * Students = Immediate Active.
     * Staff (Teacher, Finance, Admin) by Regular Admin = Pending Approval.
     */
    public function store(Request $request): RedirectResponse
    {
        $actor = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'string', 'in:super-admin,admin,teacher,student,finance'],
            'phone_number' => ['nullable', 'string', 'max:50'],
        ]);

        // Regular Admin assigning Super Admin is forbidden
        if ($actor && $actor->hasRole('admin') && !$actor->hasRole('super-admin') && $validated['role'] === 'super-admin') {
            ActivityLogger::log(
                action: 'FORBIDDEN_USER_MANAGEMENT',
                description: "Hierarchical Role Protection: Admin {$actor->email} attempted to assign Super Admin role to new account {$validated['email']}",
                subject: null,
                properties: [
                    'action_attempted' => 'create_super_admin',
                    'actor_id' => $actor->id,
                    'actor_email' => $actor->email,
                    'target_email' => $validated['email'],
                    'result' => 'Forbidden',
                    'reason' => 'Hierarchical Role Protection',
                ]
            );

            abort(403, 'Hierarchical Role Protection: Admins are not permitted to assign the Super Admin role.');
        }

        // Determine initial status based on UAC-003 rules
        $role = $validated['role'];
        $isStudent = ($role === 'student');
        $isSuperAdminActor = ($actor && $actor->hasRole('super-admin'));

        if ($isStudent || $isSuperAdminActor) {
            $initialStatus = 'active';
        } else {
            // Regular Admin creating Staff requires Super Admin approval
            $initialStatus = 'pending_approval';
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'status' => $initialStatus,
            'phone_number' => $validated['phone_number'] ?? null,
        ]);

        $user->assignRole($role);

        ActivityLogger::log(
            'USER_CREATED',
            "Created user account {$user->name} ({$user->email}) with role {$role} and status {$initialStatus}",
            $user
        );

        ActivityLogger::log(
            'ROLE_ASSIGNED',
            "Assigned role {$role} to user {$user->email}",
            $user
        );

        if ($initialStatus === 'active') {
            ActivityLogger::log(
                'ACCOUNT_ACTIVATED',
                "Activated user account {$user->name} ({$user->email}) directly",
                $user
            );

            return redirect()->route('admin.users.index')->with('status', "User {$user->name} created and activated successfully.");
        }

        // Handle Staff Creation Approval Request for Regular Admin
        UserCreationRequest::create([
            'user_id' => $user->id,
            'requested_by' => $actor ? $actor->id : Auth::id(),
            'requested_role' => $role,
            'status' => 'pending',
        ]);

        ActivityLogger::log(
            'APPROVAL_SUBMITTED',
            "Submitted user creation approval request for {$user->name} ({$user->email}) with role {$role}",
            $user
        );

        // Notify Super Admins of new user approval pending
        $superAdmins = User::role('super-admin')->get();
        foreach ($superAdmins as $sa) {
            try {
                $sa->notify(new SystemAlertNotification(
                    'New User Creation Approval Pending',
                    "Admin {$actor?->name} created a staff account for {$user->name} ({$user->email}) with role {$role}. Super Admin approval is required before login."
                ));
            } catch (\Throwable $e) {
                // Silently handle notification errors in dev
            }
        }

        return redirect()->route('admin.users.index')->with('status', "Staff account for {$user->name} created successfully and is now Pending Approval by Super Admin.");
    }

    /**
     * Update user details and role with audit log and hierarchical role protection.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $this->checkHierarchicalProtection($request, $user, 'update');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'role' => ['required', 'string', 'in:super-admin,admin,teacher,student,finance'],
            'status' => ['required', 'string', 'in:active,inactive,pending_approval,pending_delete_approval,archived'],
            'phone_number' => ['nullable', 'string', 'max:50'],
        ]);

        // Self protection
        if ($user->id === Auth::id()) {
            if ($validated['status'] === 'inactive') {
                return redirect()->back()->with('error', 'Cannot deactivate your own active session.');
            }
            $oldRole = $user->roles->first()?->name;
            if ($oldRole !== $validated['role']) {
                return redirect()->back()->with('error', 'Cannot demote or change your own active role.');
            }
        }

        $oldRole = $user->roles->first()?->name;

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'status' => $validated['status'],
            'phone_number' => $validated['phone_number'] ?? null,
        ]);

        if ($oldRole !== $validated['role']) {
            $user->syncRoles([$validated['role']]);

            if ($oldRole) {
                ActivityLogger::log(
                    'ROLE_REMOVED',
                    "Removed role {$oldRole} from user {$user->email}",
                    $user
                );
            }

            ActivityLogger::log(
                'ROLE_ASSIGNED',
                "Assigned role {$validated['role']} to user {$user->email}",
                $user
            );
        }

        ActivityLogger::log(
            'USER_UPDATED',
            "Updated user account profile for {$user->name} ({$user->email})",
            $user
        );

        return redirect()->route('admin.users.index')->with('status', "User {$user->name} updated successfully.");
    }

    /**
     * Submit a user deletion request for Super Admin approval (UAC-002).
     */
    public function requestDelete(Request $request, User $user): RedirectResponse
    {
        $this->checkHierarchicalProtection($request, $user, 'request_delete');

        if ($user->id === Auth::id()) {
            return redirect()->back()->with('error', 'Cannot request deletion of your own active session.');
        }

        if ($user->hasRole('super-admin')) {
            return redirect()->back()->with('error', 'Super Admin accounts are permanently protected from deletion requests.');
        }

        if ($user->hasPendingDeletionRequest()) {
            return redirect()->back()->with('error', 'A deletion request for this user is already pending Super Admin approval.');
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $actor = $request->user();

        UserDeletionRequest::create([
            'user_id' => $user->id,
            'requested_by' => $actor ? $actor->id : Auth::id(),
            'reason' => $validated['reason'],
            'status' => 'pending',
        ]);

        $user->update(['status' => 'pending_delete_approval']);

        ActivityLogger::log(
            'DELETE_REQUEST_SUBMITTED',
            "Submitted user deletion request for {$user->name} ({$user->email}). Reason: {$validated['reason']}",
            $user,
            [
                'target_user_id' => $user->id,
                'target_user_email' => $user->email,
                'reason' => $validated['reason'],
                'result' => 'Success',
            ]
        );

        // Notify Super Admins of new approval pending
        $superAdmins = User::role('super-admin')->get();
        foreach ($superAdmins as $sa) {
            try {
                $sa->notify(new SystemAlertNotification(
                    'New User Deletion Approval Pending',
                    "Admin {$actor?->name} requested deletion of user {$user->name} ({$user->email}). Reason: {$validated['reason']}"
                ));
            } catch (\Throwable $e) {
                // Silently handle notification errors in dev
            }
        }

        return redirect()->route('admin.users.index')->with('status', "Deletion request for user {$user->name} submitted for Super Admin approval successfully.");
    }

    /**
     * Reset user password with audit log and hierarchical role protection.
     */
    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $this->checkHierarchicalProtection($request, $user, 'reset_password');

        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8'],
        ]);

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        ActivityLogger::log(
            'PASSWORD_RESET',
            "Reset password for user account {$user->name} ({$user->email})",
            $user
        );

        return redirect()->route('admin.users.index')->with('status', "Password reset for {$user->name} successfully.");
    }

    /**
     * Toggle active/inactive status of a user with audit log and hierarchical role protection.
     */
    public function toggleStatus(Request $request, User $user): RedirectResponse
    {
        $this->checkHierarchicalProtection($request, $user, 'toggle_status');

        if ($user->id === Auth::id()) {
            return redirect()->back()->with('error', 'Cannot deactivate your own active session.');
        }

        $newStatus = ($user->status === 'inactive') ? 'active' : 'inactive';
        $user->update(['status' => $newStatus]);

        $actionCode = ($newStatus === 'active') ? 'USER_ACTIVATED' : 'USER_DEACTIVATED';

        ActivityLogger::log(
            $actionCode,
            "Changed account status of {$user->name} ({$user->email}) to {$newStatus}",
            $user
        );

        return redirect()->route('admin.users.index')->with('status', "User {$user->name} status changed to {$newStatus}.");
    }

    /**
     * Delete user account (Super Admin Only / Soft Delete according to UAC-002).
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();

        $this->checkHierarchicalProtection($request, $user, 'delete');

        // Regular Admin MUST NOT delete user directly (Must use Request Delete)
        if ($actor && $actor->hasRole('admin') && !$actor->hasRole('super-admin')) {
            abort(403, 'Regular Admins cannot directly delete users. Please submit a deletion request for Super Admin approval.');
        }

        if ($user->id === Auth::id()) {
            return redirect()->back()->with('error', 'Cannot delete your own active session.');
        }

        if ($user->hasRole('super-admin') && User::role('super-admin')->count() <= 1) {
            return redirect()->back()->with('error', 'Cannot delete the sole Super Admin account.');
        }

        $name = $user->name;
        $email = $user->email;

        ActivityLogger::log(
            'USER_SOFT_DELETED',
            "Soft deleted user account {$name} ({$email})",
            $user
        );

        $user->update(['status' => 'deleted']);
        $user->delete();

        return redirect()->route('admin.users.index')->with('status', "User {$name} soft-deleted successfully.");
    }

    /**
     * Restore soft deleted user account (Super Admin Only).
     */
    public function restore(Request $request, string $id): RedirectResponse
    {
        $actor = $request->user();
        if (!$actor || !$actor->hasRole('super-admin')) {
            abort(403, 'Only Super Admin can restore soft-deleted users.');
        }

        $user = User::withTrashed()->findOrFail($id);
        $user->restore();
        $user->update(['status' => 'active']);

        ActivityLogger::log(
            'USER_RESTORED',
            "Restored soft deleted user account {$user->name} ({$user->email})",
            $user
        );

        return redirect()->route('admin.users.index')->with('status', "User {$user->name} restored successfully.");
    }
}
