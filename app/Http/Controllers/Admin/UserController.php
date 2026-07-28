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
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'string', 'in:super-admin,admin,teacher,student,finance'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
            'phone_number' => ['nullable', 'string', 'max:50'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'status' => $validated['status'] ?? 'active',
            'phone_number' => $validated['phone_number'] ?? null,
        ]);

        $user->assignRole($validated['role']);

        ActivityLogger::log(
            'user.created',
            "Created user account {$user->name} ({$user->email}) with role {$validated['role']}",
            $user
        );

        return redirect()->route('admin.users.index')->with('status', "User {$user->name} created successfully.");
    }

    /**
     * Update user details and role with audit log.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'role' => ['required', 'string', 'in:super-admin,admin,teacher,student,finance'],
            'status' => ['required', 'string', 'in:active,inactive'],
            'phone_number' => ['nullable', 'string', 'max:50'],
        ]);

        if ($user->id === Auth::id() && $validated['status'] === 'inactive') {
            return redirect()->back()->with('error', 'Cannot deactivate your own active session.');
        }

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'status' => $validated['status'],
            'phone_number' => $validated['phone_number'] ?? null,
        ]);

        $user->syncRoles([$validated['role']]);

        ActivityLogger::log(
            'user.updated',
            "Updated user account {$user->name} ({$user->email})",
            $user
        );

        return redirect()->route('admin.users.index')->with('status', "User {$user->name} updated successfully.");
    }

    /**
     * Reset user password with audit log.
     */
    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8'],
        ]);

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        ActivityLogger::log(
            'user.password_reset',
            "Reset password for user account {$user->name} ({$user->email})",
            $user
        );

        return redirect()->route('admin.users.index')->with('status', "Password reset for {$user->name} successfully.");
    }

    /**
     * Toggle active/inactive status of a user with audit log.
     */
    public function toggleStatus(User $user): RedirectResponse
    {
        if ($user->id === Auth::id()) {
            return redirect()->back()->with('error', 'Cannot deactivate your own active session.');
        }

        $newStatus = ($user->status === 'inactive') ? 'active' : 'inactive';
        $user->update(['status' => $newStatus]);

        ActivityLogger::log(
            'user.status_toggled',
            "Changed account status of {$user->name} ({$user->email}) to {$newStatus}",
            $user
        );

        return redirect()->route('admin.users.index')->with('status', "User {$user->name} status changed to {$newStatus}.");
    }

    /**
     * Delete user account with audit log and self-deletion protection.
     */
    public function destroy(User $user): RedirectResponse
    {
        if ($user->id === Auth::id()) {
            return redirect()->back()->with('error', 'Cannot delete your own active session.');
        }

        if ($user->hasRole('super-admin') && User::role('super-admin')->count() <= 1) {
            return redirect()->back()->with('error', 'Cannot delete the sole Super Admin account.');
        }

        $name = $user->name;
        $email = $user->email;

        ActivityLogger::log(
            'user.deleted',
            "Deleted user account {$name} ({$email})",
            $user
        );

        $user->delete();

        return redirect()->route('admin.users.index')->with('status', "User {$name} deleted successfully.");
    }
}
