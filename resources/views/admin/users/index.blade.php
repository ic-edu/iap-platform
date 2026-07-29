<x-admin-layout>
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-white">Enterprise User &amp; Access Control Management Workspace</h1>
            <p class="text-xs text-slate-400">Manage user lifecycles, assign role-based security permissions, reset credentials, and audit access.</p>
        </div>
        <button onclick="document.getElementById('create-user-modal').classList.remove('hidden')" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-lg shadow transition-colors flex items-center gap-1.5">
            <span>+ Create New User</span>
        </button>
    </div>

    <!-- System Feedback Alerts -->
    @if (session('status'))
        <div class="mb-6 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-medium flex items-center gap-2">
            <span>✅</span> {{ session('status') }}
        </div>
    @endif
    @if (session('error'))
        <div class="mb-6 p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-400 text-xs font-medium flex items-center gap-2">
            <span>⚠️</span> {{ session('error') }}
        </div>
    @endif

    <!-- Search & Filter Controls -->
    <div class="mb-6 p-4 bg-slate-900 border border-slate-800 rounded-xl">
        <form action="{{ route('admin.users.index') }}" method="GET" class="flex flex-col sm:flex-row gap-3">
            <div class="flex-1 relative">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by user name, email address..." 
                       class="w-full pl-9 pr-4 py-2 bg-slate-950 border border-slate-800 rounded-lg text-xs text-white placeholder-slate-500 focus:border-indigo-500 focus:outline-none transition-colors">
                <svg class="w-4 h-4 text-slate-500 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
            </div>
            
            <select name="role" class="px-4 py-2 bg-slate-950 border border-slate-800 rounded-lg text-xs text-white focus:border-indigo-500 focus:outline-none">
                <option value="">All Security Roles</option>
                <option value="super-admin" {{ request('role') === 'super-admin' ? 'selected' : '' }}>Super Admin</option>
                <option value="admin" {{ request('role') === 'admin' ? 'selected' : '' }}>Admin</option>
                <option value="teacher" {{ request('role') === 'teacher' ? 'selected' : '' }}>Teacher</option>
                <option value="student" {{ request('role') === 'student' ? 'selected' : '' }}>Student</option>
                <option value="finance" {{ request('role') === 'finance' ? 'selected' : '' }}>Finance</option>
            </select>

            <select name="status" class="px-4 py-2 bg-slate-950 border border-slate-800 rounded-lg text-xs text-white focus:border-indigo-500 focus:outline-none">
                <option value="">All Account Statuses</option>
                <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                <option value="pending_delete_approval" {{ request('status') === 'pending_delete_approval' ? 'selected' : '' }}>Pending Delete Approval</option>
            </select>

            <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-lg shadow transition-colors">
                Apply Filters
            </button>
            @if (request()->hasAny(['search', 'role', 'status']))
                <a href="{{ route('admin.users.index') }}" class="px-3 py-2 bg-slate-800 hover:bg-slate-700 text-slate-400 text-xs font-medium rounded-lg text-center transition-colors">
                    Reset
                </a>
            @endif
        </form>
    </div>

    <!-- Users Table -->
    <div class="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden shadow-sm mb-6">
        <table class="w-full text-left text-sm text-slate-300">
            <thead class="bg-slate-950 text-xs uppercase text-slate-400 border-b border-slate-800">
                <tr>
                    <th class="p-4">User Details</th>
                    <th class="p-4">Email</th>
                    <th class="p-4">Security Role</th>
                    <th class="p-4">Status</th>
                    <th class="p-4">Created Date</th>
                    <th class="p-4">Last Activity</th>
                    <th class="p-4 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60">
                @forelse ($users as $user)
                    @php
                        $roleName = $user->roles->first()?->name ?? 'student';
                        $roleBadge = match($roleName) {
                            'super-admin' => 'bg-purple-500/10 text-purple-400 border-purple-500/20',
                            'admin' => 'bg-indigo-500/10 text-indigo-400 border-indigo-500/20',
                            'teacher' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
                            'finance' => 'bg-amber-500/10 text-amber-400 border-amber-500/20',
                            default => 'bg-slate-800 text-slate-400 border-slate-700',
                        };
                        $isSelf = Auth::id() === $user->id;
                        $actorIsSuperAdmin = Auth::user()?->hasRole('super-admin');
                        $actorIsRegularAdmin = Auth::user()?->hasRole('admin') && !$actorIsSuperAdmin;
                        $targetIsSuperAdmin = $user->hasRole('super-admin');
                        $isProtectedFromActor = $actorIsRegularAdmin && $targetIsSuperAdmin;
                        $hasPendingDeletion = $user->status === 'pending_delete_approval' || $user->hasPendingDeletionRequest();
                    @endphp
                    <tr class="hover:bg-slate-950/40 transition-colors">
                        <td class="p-4">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-lg bg-indigo-600/30 border border-indigo-500/30 text-indigo-300 font-bold text-xs flex items-center justify-center flex-shrink-0">
                                    {{ strtoupper(substr($user->name, 0, 2)) }}
                                </div>
                                <div>
                                    <span class="block font-semibold text-white text-xs">{{ $user->name }}</span>
                                    @if ($user->phone_number)
                                        <span class="block text-[10px] text-slate-500">{{ $user->phone_number }}</span>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="p-4 text-slate-300 text-xs font-mono">{{ $user->email }}</td>
                        <td class="p-4">
                            <span class="px-2.5 py-0.5 text-[10px] font-bold rounded border uppercase {{ $roleBadge }}">
                                {{ $roleName }}
                            </span>
                        </td>
                        <td class="p-4">
                            @if ($hasPendingDeletion)
                                <span class="px-2.5 py-0.5 text-[10px] font-bold rounded bg-amber-500/10 text-amber-400 border border-amber-500/20 uppercase flex items-center gap-1 w-max">
                                    ⏳ Pending Approval
                                </span>
                            @elseif ($user->status === 'inactive')
                                <span class="px-2.5 py-0.5 text-[10px] font-bold rounded bg-rose-500/10 text-rose-400 border border-rose-500/20 uppercase">
                                    INACTIVE
                                </span>
                            @else
                                <span class="px-2.5 py-0.5 text-[10px] font-bold rounded bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 uppercase">
                                    ACTIVE
                                </span>
                            @endif
                        </td>
                        <td class="p-4 text-slate-400 text-xs">{{ $user->created_at?->format('Y-m-d') }}</td>
                        <td class="p-4 text-slate-400 text-xs">{{ $user->updated_at?->diffForHumans() }}</td>
                        <td class="p-4 text-right">
                            <div class="flex items-center justify-end gap-2 text-xs">
                                <!-- View User (Always Allowed) -->
                                <button type="button" 
                                        onclick='openViewUserModal({{ json_encode($user) }}, "{{ $roleName }}")'
                                        class="px-2 py-1 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded transition-colors" title="View Details">
                                    👁 View
                                </button>

                                @if ($isProtectedFromActor)
                                    <!-- Hierarchical Protection Badge for Admin viewing Super Admin -->
                                    <span class="px-2.5 py-1 bg-purple-500/20 text-purple-300 border border-purple-500/30 text-[10px] font-bold rounded uppercase flex items-center gap-1" title="Protected Account (Hierarchical Role Protection)">
                                        🔒 Protected Account
                                    </span>
                                @else
                                    <!-- Edit User -->
                                    <button type="button" 
                                            onclick='openEditUserModal({{ json_encode($user) }}, "{{ $roleName }}")'
                                            class="px-2 py-1 bg-indigo-600/20 hover:bg-indigo-600/30 text-indigo-300 border border-indigo-500/30 rounded transition-colors" title="Edit User">
                                        ✏ Edit
                                    </button>

                                    <!-- Reset Password -->
                                    <button type="button" 
                                            onclick='openResetPasswordModal({{ json_encode($user) }})'
                                            class="px-2 py-1 bg-amber-500/20 hover:bg-amber-500/30 text-amber-300 border border-amber-500/30 rounded transition-colors" title="Reset Password">
                                        🔑 Reset
                                    </button>

                                    <!-- Toggle Status (Disabled for self) -->
                                    @if (! $isSelf)
                                        <form action="{{ route('admin.users.toggle-status', $user->id) }}" method="POST" class="inline" 
                                              onsubmit="return confirm('Are you sure you want to {{ $user->status === 'inactive' ? 'ACTIVATE' : 'DEACTIVATE' }} {{ $user->name }}?')">
                                            @csrf
                                            <button type="submit" class="px-2 py-1 {{ $user->status === 'inactive' ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 hover:bg-emerald-500/30' : 'bg-rose-500/20 text-rose-300 border border-rose-500/30 hover:bg-rose-500/30' }} rounded transition-colors">
                                                {{ $user->status === 'inactive' ? '⚡ Activate' : '⏸ Deactivate' }}
                                            </button>
                                        </form>
                                    @endif

                                    <!-- Deletion Workflow Buttons (UAC-002) -->
                                    @if (! $isSelf)
                                        @if ($actorIsRegularAdmin)
                                            <!-- Regular Admin: Must Request Delete -->
                                            @if ($hasPendingDeletion)
                                                <span class="px-2 py-1 bg-amber-500/10 text-amber-400 border border-amber-500/20 text-[10px] font-bold rounded cursor-not-allowed" title="Deletion Request Pending Approval">
                                                    ⏳ Pending Approval
                                                </span>
                                            @else
                                                <button type="button" 
                                                        onclick='openRequestDeleteModal({{ json_encode($user) }})'
                                                        class="px-2 py-1 bg-rose-500/20 hover:bg-rose-500/30 text-rose-300 border border-rose-500/30 rounded transition-colors" title="Request User Deletion">
                                                    📩 Request Delete
                                                </button>
                                            @endif
                                        @elseif ($actorIsSuperAdmin)
                                            <!-- Super Admin: Direct Soft Delete or Request Delete -->
                                            <form action="{{ route('admin.users.destroy', $user->id) }}" method="POST" class="inline" 
                                                  onsubmit="return confirm('⚠️ WARNING: Soft delete user {{ $user->email }}?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="px-2 py-1 bg-rose-500/10 text-rose-400 hover:text-rose-300 hover:bg-rose-500/20 border border-rose-500/20 rounded transition-colors">
                                                    🗑 Delete
                                                </button>
                                            </form>
                                        @endif
                                    @else
                                        <span class="px-2 py-1 bg-slate-800 text-slate-500 text-[10px] font-bold rounded cursor-not-allowed" title="Current Active Session">
                                            Self
                                        </span>
                                    @endif
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="p-8 text-center text-slate-500 text-xs">No users match your criteria.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $users->links() }}</div>

    <!-- Create User Modal -->
    <div id="create-user-modal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm hidden flex items-center justify-center p-4 z-50">
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-6 max-w-md w-full shadow-2xl">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-base font-bold text-white">Create New Platform User</h2>
                <button type="button" onclick="document.getElementById('create-user-modal').classList.add('hidden')" class="text-slate-400 hover:text-white">&times;</button>
            </div>
            <form action="{{ route('admin.users.store') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">Full Name *</label>
                    <input type="text" name="name" required class="w-full p-2.5 bg-slate-950 border border-slate-800 rounded-lg text-white text-xs focus:border-indigo-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">Email Address *</label>
                    <input type="email" name="email" required class="w-full p-2.5 bg-slate-950 border border-slate-800 rounded-lg text-white text-xs focus:border-indigo-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">Initial Password *</label>
                    <input type="password" name="password" required minlength="8" class="w-full p-2.5 bg-slate-950 border border-slate-800 rounded-lg text-white text-xs focus:border-indigo-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">Phone Number (Optional)</label>
                    <input type="text" name="phone_number" class="w-full p-2.5 bg-slate-950 border border-slate-800 rounded-lg text-white text-xs focus:border-indigo-500 focus:outline-none" placeholder="+1234567890">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">Security Role *</label>
                        <select name="role" required class="w-full p-2.5 bg-slate-950 border border-slate-800 rounded-lg text-white text-xs focus:border-indigo-500 focus:outline-none">
                            <option value="student">Student / Candidate</option>
                            <option value="teacher">Teacher / Author</option>
                            <option value="admin">Administrator</option>
                            <option value="finance">Finance Admin</option>
                            @if (Auth::user()?->hasRole('super-admin'))
                                <option value="super-admin">Super Admin</option>
                            @endif
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">Initial Status *</label>
                        <select name="status" required class="w-full p-2.5 bg-slate-950 border border-slate-800 rounded-lg text-white text-xs focus:border-indigo-500 focus:outline-none">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="flex justify-end gap-3 pt-4 border-t border-slate-800">
                    <button type="button" onclick="document.getElementById('create-user-modal').classList.add('hidden')" class="px-4 py-2 bg-slate-800 text-slate-300 text-xs rounded-lg">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs rounded-lg shadow">Create User</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Request User Deletion Modal (UAC-002) -->
    <div id="request-delete-modal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm hidden flex items-center justify-center p-4 z-50">
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-6 max-w-md w-full shadow-2xl">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-base font-bold text-white flex items-center gap-2">
                    <span>📩 Request User Deletion Approval</span>
                </h2>
                <button type="button" onclick="document.getElementById('request-delete-modal').classList.add('hidden')" class="text-slate-400 hover:text-white">&times;</button>
            </div>
            <p class="text-xs text-slate-400 mb-4">
                Submit user deletion request for <strong id="request-delete-user-name" class="text-white"></strong> (<span id="request-delete-user-email" class="text-indigo-400 font-mono"></span>).
                This request will be sent to the Super Admin Approval Center.
            </p>
            
            <form id="request-delete-form" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">Reason for Deletion Request *</label>
                    <textarea name="reason" required minlength="5" rows="3" placeholder="Provide clear business or security justification for deleting this user account..." 
                              class="w-full p-2.5 bg-slate-950 border border-slate-800 rounded-lg text-white text-xs focus:border-rose-500 focus:outline-none"></textarea>
                </div>
                <div class="p-3 bg-amber-500/10 border border-amber-500/20 rounded-lg text-[11px] text-amber-300">
                    ⚠️ <strong>Governance Notice:</strong> Permanent user deletion requires Super Admin approval. Account status will change to <em>Pending Delete Approval</em>.
                </div>
                <div class="flex justify-end gap-3 pt-4 border-t border-slate-800">
                    <button type="button" onclick="document.getElementById('request-delete-modal').classList.add('hidden')" class="px-4 py-2 bg-slate-800 text-slate-300 text-xs rounded-lg">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-rose-600 hover:bg-rose-500 text-white font-semibold text-xs rounded-lg shadow">Submit Request</button>
                </div>
            </form>
        </div>
    </div>

    <!-- View User Read-Only Modal -->
    <div id="view-user-modal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm hidden flex items-center justify-center p-4 z-50">
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-6 max-w-md w-full shadow-2xl">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-base font-bold text-white flex items-center gap-2">
                    <span>👁 User Context &amp; Details</span>
                </h2>
                <button type="button" onclick="document.getElementById('view-user-modal').classList.add('hidden')" class="text-slate-400 hover:text-white">&times;</button>
            </div>
            
            <div class="space-y-3 text-xs bg-slate-950 p-4 rounded-xl border border-slate-800 mb-4">
                <div class="flex justify-between py-1 border-b border-slate-800">
                    <span class="text-slate-400 font-medium">Full Name</span>
                    <span id="view-user-name" class="font-bold text-white"></span>
                </div>
                <div class="flex justify-between py-1 border-b border-slate-800">
                    <span class="text-slate-400 font-medium">Email Address</span>
                    <span id="view-user-email" class="font-mono text-indigo-400"></span>
                </div>
                <div class="flex justify-between py-1 border-b border-slate-800">
                    <span class="text-slate-400 font-medium">Assigned Role</span>
                    <span id="view-user-role" class="font-bold uppercase text-white"></span>
                </div>
                <div class="flex justify-between py-1 border-b border-slate-800">
                    <span class="text-slate-400 font-medium">Account Status</span>
                    <span id="view-user-status" class="font-bold uppercase"></span>
                </div>
                <div class="flex justify-between py-1 border-b border-slate-800">
                    <span class="text-slate-400 font-medium">Phone Number</span>
                    <span id="view-user-phone" class="text-slate-300"></span>
                </div>
                <div class="flex justify-between py-1 border-b border-slate-800">
                    <span class="text-slate-400 font-medium">Created Date</span>
                    <span id="view-user-created" class="text-slate-400"></span>
                </div>
                <div class="flex justify-between py-1">
                    <span class="text-slate-400 font-medium">Last Updated</span>
                    <span id="view-user-updated" class="text-slate-400"></span>
                </div>
            </div>

            <div class="flex justify-end">
                <button type="button" onclick="document.getElementById('view-user-modal').classList.add('hidden')" class="px-4 py-2 bg-slate-800 text-slate-300 text-xs rounded-lg font-medium">Close</button>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div id="edit-user-modal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm hidden flex items-center justify-center p-4 z-50">
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-6 max-w-md w-full shadow-2xl">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-base font-bold text-white">Edit User Profile &amp; Role</h2>
                <button type="button" onclick="document.getElementById('edit-user-modal').classList.add('hidden')" class="text-slate-400 hover:text-white">&times;</button>
            </div>
            <form id="edit-user-form" method="POST" class="space-y-4">
                @csrf
                @method('PUT')
                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">Full Name *</label>
                    <input type="text" id="edit-user-name" name="name" required class="w-full p-2.5 bg-slate-950 border border-slate-800 rounded-lg text-white text-xs focus:border-indigo-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">Email Address *</label>
                    <input type="email" id="edit-user-email" name="email" required class="w-full p-2.5 bg-slate-950 border border-slate-800 rounded-lg text-white text-xs focus:border-indigo-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">Phone Number</label>
                    <input type="text" id="edit-user-phone" name="phone_number" class="w-full p-2.5 bg-slate-950 border border-slate-800 rounded-lg text-white text-xs focus:border-indigo-500 focus:outline-none">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">Security Role *</label>
                        <select id="edit-user-role" name="role" required class="w-full p-2.5 bg-slate-950 border border-slate-800 rounded-lg text-white text-xs focus:border-indigo-500 focus:outline-none">
                            <option value="student">Student / Candidate</option>
                            <option value="teacher">Teacher / Author</option>
                            <option value="admin">Administrator</option>
                            <option value="finance">Finance Admin</option>
                            @if (Auth::user()?->hasRole('super-admin'))
                                <option value="super-admin">Super Admin</option>
                            @endif
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">Account Status *</label>
                        <select id="edit-user-status" name="status" required class="w-full p-2.5 bg-slate-950 border border-slate-800 rounded-lg text-white text-xs focus:border-indigo-500 focus:outline-none">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="pending_delete_approval">Pending Delete Approval</option>
                        </select>
                    </div>
                </div>
                <div class="flex justify-end gap-3 pt-4 border-t border-slate-800">
                    <button type="button" onclick="document.getElementById('edit-user-modal').classList.add('hidden')" class="px-4 py-2 bg-slate-800 text-slate-300 text-xs rounded-lg">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs rounded-lg shadow">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Reset Password Modal -->
    <div id="reset-password-modal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm hidden flex items-center justify-center p-4 z-50">
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-6 max-w-md w-full shadow-2xl">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-base font-bold text-white flex items-center gap-2">
                    <span>🔑 Reset Credentials</span>
                </h2>
                <button type="button" onclick="document.getElementById('reset-password-modal').classList.add('hidden')" class="text-slate-400 hover:text-white">&times;</button>
            </div>
            <p class="text-xs text-slate-400 mb-4">Reset password for <strong id="reset-password-user-name" class="text-white"></strong> (<span id="reset-password-user-email" class="text-indigo-400 font-mono"></span>).</p>
            
            <form id="reset-password-form" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">New Password (Min 8 characters) *</label>
                    <div class="flex gap-2">
                        <input type="password" id="reset-new-password" name="password" required minlength="8" class="flex-1 p-2.5 bg-slate-950 border border-slate-800 rounded-lg text-white text-xs focus:border-indigo-500 focus:outline-none">
                        <button type="button" onclick="generateTempPassword()" class="px-3 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold rounded-lg">Generate</button>
                    </div>
                </div>
                <div class="flex justify-end gap-3 pt-4 border-t border-slate-800">
                    <button type="button" onclick="document.getElementById('reset-password-modal').classList.add('hidden')" class="px-4 py-2 bg-slate-800 text-slate-300 text-xs rounded-lg">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-amber-600 hover:bg-amber-500 text-white font-semibold text-xs rounded-lg shadow">Confirm Reset</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openViewUserModal(user, role) {
            document.getElementById('view-user-name').innerText = user.name;
            document.getElementById('view-user-email').innerText = user.email;
            document.getElementById('view-user-role').innerText = role;
            document.getElementById('view-user-status').innerText = user.status || 'active';
            document.getElementById('view-user-status').className = (user.status === 'inactive') ? 'font-bold uppercase text-rose-400' : ((user.status === 'pending_delete_approval') ? 'font-bold uppercase text-amber-400' : 'font-bold uppercase text-emerald-400');
            document.getElementById('view-user-phone').innerText = user.phone_number || 'N/A';
            document.getElementById('view-user-created').innerText = user.created_at ? new Date(user.created_at).toLocaleDateString() : 'N/A';
            document.getElementById('view-user-updated').innerText = user.updated_at ? new Date(user.updated_at).toLocaleDateString() : 'N/A';
            document.getElementById('view-user-modal').classList.remove('hidden');
        }

        function openEditUserModal(user, role) {
            document.getElementById('edit-user-form').action = "/admin/users/" + user.id;
            document.getElementById('edit-user-name').value = user.name;
            document.getElementById('edit-user-email').value = user.email;
            document.getElementById('edit-user-phone').value = user.phone_number || '';
            document.getElementById('edit-user-role').value = role;
            document.getElementById('edit-user-status').value = user.status || 'active';
            document.getElementById('edit-user-modal').classList.remove('hidden');
        }

        function openRequestDeleteModal(user) {
            document.getElementById('request-delete-form').action = "/admin/users/" + user.id + "/request-delete";
            document.getElementById('request-delete-user-name').innerText = user.name;
            document.getElementById('request-delete-user-email').innerText = user.email;
            document.getElementById('request-delete-modal').classList.remove('hidden');
        }

        function openResetPasswordModal(user) {
            document.getElementById('reset-password-form').action = "/admin/users/" + user.id + "/reset-password";
            document.getElementById('reset-password-user-name').innerText = user.name;
            document.getElementById('reset-password-user-email').innerText = user.email;
            document.getElementById('reset-new-password').value = '';
            document.getElementById('reset-password-modal').classList.remove('hidden');
        }

        function generateTempPassword() {
            const chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%";
            let pass = "";
            for (let i = 0; i < 10; i++) {
                pass += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            const input = document.getElementById('reset-new-password');
            input.type = 'text';
            input.value = pass;
        }
    </script>
</x-admin-layout>
