<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class UserController extends Controller
{
    /**
     * Display listing of platform users.
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

        $users = $query->latest()->paginate(15)->withQueryString();

        return view('admin.users.index', compact('users'));
    }

    /**
     * Store new user account.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'string', 'in:super-admin,admin,teacher,student,finance'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        $user->assignRole($validated['role']);

        return redirect()->route('admin.users.index')->with('status', 'user-created');
    }

    /**
     * Update user role / details.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'role' => ['required', 'string', 'in:super-admin,admin,teacher,student,finance'],
        ]);

        $user->update(['name' => $validated['name']]);
        $user->syncRoles([$validated['role']]);

        return redirect()->route('admin.users.index')->with('status', 'user-updated');
    }

    /**
     * Delete user account.
     */
    public function destroy(User $user): RedirectResponse
    {
        if ($user->hasRole('super-admin') && User::role('super-admin')->count() <= 1) {
            return redirect()->route('admin.users.index')->with('error', 'Cannot delete the sole Super Admin account.');
        }

        $user->delete();

        return redirect()->route('admin.users.index')->with('status', 'user-deleted');
    }
}
