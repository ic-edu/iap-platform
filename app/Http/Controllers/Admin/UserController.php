<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
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
     * Enforce hierarchical role protection (Baseline v1.1 UAC-001).
     *
     * Hierarchy: Super Admin > Admin > Teacher > Student (Finance is independent)
     * Admin MUST NOT manage Super Admin accounts.
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

            abort(403, 'Hierarchical Role Protection: Admins are not permitted to manage or modify Super Admin accounts.');
        }
    }

    /**
     * Display listing of platform users with combined search and filtering.
     */
    public function index(Request $request): View
    {
        $query = User::with('roles');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($role = $request->input('role')) {
            $query->role($role);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $users = $query->latest()->paginate(15)->withQueryString();

        return view('admin.users.index', compact('users'));
    }

    /**
     * Store a new user account with role, status, and audit log.
     */
    public function store(Request $request): RedirectResponse
    {
        $actor = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'string', 'in:super-admin,admin,teacher,student,finance'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
            'phone_number' => ['nullable', 'string', 'max:50'],
        ]);

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

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'status' => $validated['status'] ?? 'active',
            'phone_number' => $validated['phone_number'] ?? null,
        ]);

        $user->assignRole($validated['role']);

        ActivityLogger::log(
            'USER_CREATED',
            "Created user account {$user->name} ({$user->email}) with role {$validated['role']}",
            $user
        );

        ActivityLogger::log(
            'ROLE_ASSIGNED',
            "Assigned role {$validated['role']} to user {$user->email}",
            $user
        );

        return redirect()->route('admin.users.index')->with('status', "User {$user->name} created successfully.");
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
            'status' => ['required', 'string', 'in:active,inactive'],
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
     * Delete user account with audit log, hierarchical role protection, and self-deletion protection.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->checkHierarchicalProtection($request, $user, 'delete');

        if ($user->id === Auth::id()) {
            return redirect()->back()->with('error', 'Cannot delete your own active session.');
        }

        if ($user->hasRole('super-admin') && User::role('super-admin')->count() <= 1) {
            return redirect()->back()->with('error', 'Cannot delete the sole Super Admin account.');
        }

        $name = $user->name;
        $email = $user->email;

        ActivityLogger::log(
            'USER_DELETED',
            "Deleted user account {$name} ({$email})"
        );

        $user->delete();

        return redirect()->route('admin.users.index')->with('status', "User {$name} deleted successfully.");
    }
}
