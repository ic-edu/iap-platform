<x-admin-layout>
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-white">User &amp; Access Control Management</h1>
            <p class="text-xs text-slate-400">Manage user accounts, assign security roles, and enforce system access policies.</p>
        </div>
        <button onclick="document.getElementById('create-user-modal').classList.remove('hidden')" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-lg shadow transition-colors">
            + Create New User
        </button>
    </div>

    <!-- Status Alerts -->
    @if (session('status'))
        <div class="mb-6 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-medium">
            ✅ {{ session('status') }}
        </div>
    @endif
    @if (session('error'))
        <div class="mb-6 p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-400 text-xs font-medium">
            ⚠️ {{ session('error') }}
        </div>
    @endif

    <!-- Search & Filter Bar -->
    <div class="mb-6 p-4 bg-slate-900 border border-slate-800 rounded-xl">
        <form action="{{ route('admin.users.index') }}" method="GET" class="flex flex-col sm:flex-row gap-3">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by name or email..." class="flex-1 px-4 py-2 bg-slate-950 border border-slate-800 rounded-lg text-xs text-white">
            <select name="role" class="px-4 py-2 bg-slate-950 border border-slate-800 rounded-lg text-xs text-white">
                <option value="">All Roles</option>
                <option value="super-admin" {{ request('role') === 'super-admin' ? 'selected' : '' }}>Super Admin</option>
                <option value="admin" {{ request('role') === 'admin' ? 'selected' : '' }}>Admin</option>
                <option value="teacher" {{ request('role') === 'teacher' ? 'selected' : '' }}>Teacher</option>
                <option value="student" {{ request('role') === 'student' ? 'selected' : '' }}>Student</option>
                <option value="finance" {{ request('role') === 'finance' ? 'selected' : '' }}>Finance</option>
            </select>
            <button type="submit" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold rounded-lg">Filter</button>
        </form>
    </div>

    <!-- Users Table -->
    <div class="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden shadow-sm mb-6">
        <table class="w-full text-left text-sm text-slate-300">
            <thead class="bg-slate-950 text-xs uppercase text-slate-400 border-b border-slate-800">
                <tr>
                    <th class="p-4">Name</th>
                    <th class="p-4">Email</th>
                    <th class="p-4">Assigned Role</th>
                    <th class="p-4">Joined Date</th>
                    <th class="p-4 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60">
                @forelse ($users as $user)
                    @php
                        $roleName = $user->roles->first()?->name ?? 'student';
                        $badgeStyle = match($roleName) {
                            'super-admin' => 'bg-rose-500/10 text-rose-400 border-rose-500/20',
                            'admin' => 'bg-indigo-500/10 text-indigo-400 border-indigo-500/20',
                            'teacher' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
                            'finance' => 'bg-amber-500/10 text-amber-400 border-amber-500/20',
                            default => 'bg-slate-800 text-slate-400 border-slate-700',
                        };
                    @endphp
                    <tr>
                        <td class="p-4 font-semibold text-white">{{ $user->name }}</td>
                        <td class="p-4 text-slate-400 text-xs font-mono">{{ $user->email }}</td>
                        <td class="p-4">
                            <span class="px-2.5 py-0.5 text-xs font-bold rounded border uppercase {{ $badgeStyle }}">
                                {{ $roleName }}
                            </span>
                        </td>
                        <td class="p-4 text-slate-400 text-xs">{{ $user->created_at?->format('d M Y') }}</td>
                        <td class="p-4 text-right">
                            <form action="{{ route('admin.users.destroy', $user->id) }}" method="POST" class="inline" onsubmit="return confirm('Delete user {{ $user->email }}?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs text-rose-400 hover:underline">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="p-8 text-center text-slate-500">No users found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $users->links() }}</div>

    <!-- Create User Modal -->
    <div id="create-user-modal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm hidden flex items-center justify-center p-4 z-50">
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-6 max-w-md w-full shadow-2xl">
            <h2 class="text-lg font-bold text-white mb-4">Create User Account</h2>
            <form action="{{ route('admin.users.store') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">Full Name *</label>
                    <input type="text" name="name" required class="w-full p-2.5 bg-slate-950 border border-slate-800 rounded-lg text-white text-xs">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">Email Address *</label>
                    <input type="email" name="email" required class="w-full p-2.5 bg-slate-950 border border-slate-800 rounded-lg text-white text-xs">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">Password *</label>
                    <input type="password" name="password" required class="w-full p-2.5 bg-slate-950 border border-slate-800 rounded-lg text-white text-xs">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">Security Role *</label>
                    <select name="role" required class="w-full p-2.5 bg-slate-950 border border-slate-800 rounded-lg text-white text-xs uppercase">
                        <option value="student">Student / Candidate</option>
                        <option value="teacher">Teacher / Author</option>
                        <option value="admin">Administrator</option>
                        <option value="finance">Finance Administrator</option>
                        <option value="super-admin">Super Admin</option>
                    </select>
                </div>
                <div class="flex justify-end gap-3 pt-4 border-t border-slate-800">
                    <button type="button" onclick="document.getElementById('create-user-modal').classList.add('hidden')" class="px-4 py-2 bg-slate-800 text-slate-300 text-xs rounded-lg">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white font-semibold text-xs rounded-lg shadow">Create User</button>
                </div>
            </form>
        </div>
    </div>
</x-admin-layout>
