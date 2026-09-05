@extends('layouts.admin')

@section('content')
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">All Platform Users</h1>
            <p class="text-xs text-slate-500 dark:text-slate-400">Universal human identity directory across internal staff, candidates, and organization members</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.users.index') }}" class="px-3 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-medium rounded-lg text-center transition-colors">
                Staff & Access Control &rarr;
            </a>
            <a href="{{ route('admin.candidates.index') }}" class="px-3 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-medium rounded-lg text-center transition-colors">
                Candidates Workspace &rarr;
            </a>
        </div>
    </div>

    <!-- System Feedback Alerts -->
    @if(session('status'))
        <div class="mb-6 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-400 text-xs font-medium flex items-center gap-2">
            <span>✅</span> {{ session('status') }}
        </div>
    @endif
    @if(session('error'))
        <div class="mb-6 p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-600 dark:text-rose-400 text-xs font-medium flex items-center gap-2">
            <span>⚠️</span> {{ session('error') }}
        </div>
    @endif

    <!-- Search & Filter Controls -->
    <div class="mb-6 p-4 bg-slate-950/80 border border-slate-800 rounded-xl">
        <form action="{{ route('admin.all-users.index') }}" method="GET" class="flex flex-col sm:flex-row gap-3">
            <div class="flex-1 relative">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by name, email..."
                       class="w-full pl-9 pr-4 py-2 bg-slate-900 border border-slate-800 rounded-lg text-xs text-slate-900 dark:text-white placeholder-slate-500 focus:border-indigo-500 focus:outline-none transition-colors">
                <svg class="w-4 h-4 text-slate-500 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
            </div>

            <select name="category" class="px-4 py-2 bg-slate-900 border border-slate-800 rounded-lg text-xs text-slate-900 dark:text-white focus:border-indigo-500 focus:outline-none">
                <option value="">All Categories</option>
                <option value="internal_staff" {{ request('category') === 'internal_staff' ? 'selected' : '' }}>Internal Staff</option>
                <option value="candidate" {{ request('category') === 'candidate' ? 'selected' : '' }}>Candidate</option>
                <option value="organization_user" {{ request('category') === 'organization_user' ? 'selected' : '' }}>Organization User</option>
                <option value="unassigned" {{ request('category') === 'unassigned' ? 'selected' : '' }}>Unassigned</option>
            </select>

            <select name="role" class="px-4 py-2 bg-slate-900 border border-slate-800 rounded-lg text-xs text-slate-900 dark:text-white focus:border-indigo-500 focus:outline-none">
                <option value="">All Roles</option>
                <option value="super-admin" {{ request('role') === 'super-admin' ? 'selected' : '' }}>Super Admin</option>
                <option value="admin" {{ request('role') === 'admin' ? 'selected' : '' }}>Admin / RA</option>
                <option value="repository-manager" {{ request('role') === 'repository-manager' ? 'selected' : '' }}>Repository Manager</option>
                <option value="teacher" {{ request('role') === 'teacher' ? 'selected' : '' }}>Teacher</option>
                <option value="finance" {{ request('role') === 'finance' ? 'selected' : '' }}>Finance</option>
                <option value="student" {{ request('role') === 'student' ? 'selected' : '' }}>Student / Candidate</option>
                <option value="organization-coordinator" {{ request('role') === 'organization-coordinator' ? 'selected' : '' }}>Organization Coordinator</option>
                <option value="unassigned" {{ request('role') === 'unassigned' ? 'selected' : '' }}>Unassigned</option>
            </select>

            <select name="status" class="px-4 py-2 bg-slate-900 border border-slate-800 rounded-lg text-xs text-slate-900 dark:text-white focus:border-indigo-500 focus:outline-none">
                <option value="all">All Statuses</option>
                <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                <option value="pending_approval" {{ request('status') === 'pending_approval' ? 'selected' : '' }}>Pending Approval</option>
            </select>

            <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-lg shadow transition-colors">
                Apply Filters
            </button>
            @if(request('search') || request('category') || request('role') || (request('status') && request('status') !== 'all'))
                <a href="{{ route('admin.all-users.index') }}" class="px-3 py-2 bg-slate-800 hover:bg-slate-700 text-slate-400 text-xs font-medium rounded-lg text-center transition-colors">
                    Reset
                </a>
            @endif
        </form>
    </div>

    <!-- Users Table -->
    <div class="bg-slate-950/80 border border-slate-800 rounded-xl overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="border-b border-slate-800 bg-slate-900/50 text-[11px] font-semibold uppercase text-slate-400">
                        <th class="py-3 px-4">User</th>
                        <th class="py-3 px-4">Account Category</th>
                        <th class="py-3 px-4">Role</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4">Organization Context</th>
                        <th class="py-3 px-4">Created</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                    @forelse($users as $user)
                        @php
                            $rolesList = $user->roles->pluck('name')->toArray();
                            $hasStaffRole = count(array_intersect($rolesList, ['super-admin', 'admin', 'teacher', 'repository-manager', 'finance'])) > 0;
                            $hasOrgRole = in_array('organization-coordinator', $rolesList, true) || $user->organizationMemberships->count() > 0;
                            $hasStudentRole = in_array('student', $rolesList, true);

                            if ($hasStaffRole) {
                                $categoryLabel = 'Internal Staff';
                                $categoryBadge = 'bg-purple-500/15 text-purple-400 border-purple-500/30';
                            } elseif ($hasOrgRole) {
                                $categoryLabel = 'Organization User';
                                $categoryBadge = 'bg-teal-500/15 text-teal-400 border-teal-500/30';
                            } elseif ($hasStudentRole) {
                                $categoryLabel = 'Candidate';
                                $categoryBadge = 'bg-sky-500/15 text-sky-400 border-sky-500/30';
                            } else {
                                $categoryLabel = 'Unassigned';
                                $categoryBadge = 'bg-slate-500/15 text-slate-400 border-slate-500/30';
                            }

                            $primaryRole = $rolesList[0] ?? 'unassigned';
                            $orgNames = $user->organizationMemberships->map(fn($m) => $m->organization?->name)->filter()->unique()->implode(', ');
                        @endphp
                        <tr class="hover:bg-slate-900/40 transition-colors">
                            <td class="py-3 px-4">
                                <div class="font-bold text-slate-900 dark:text-white">{{ $user->name }}</div>
                                <div class="text-[11px] text-slate-500 font-mono">{{ $user->email }}</div>
                            </td>
                            <td class="py-3 px-4">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase border {{ $categoryBadge }}">
                                    {{ $categoryLabel }}
                                </span>
                            </td>
                            <td class="py-3 px-4">
                                <x-role-badge :role="$primaryRole" />
                            </td>
                            <td class="py-3 px-4">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $user->status === 'active' ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' : 'bg-slate-500/20 text-slate-400 border border-slate-500/30' }}">
                                    {{ ucfirst(str_replace('_', ' ', $user->status ?? 'active')) }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-slate-400">
                                {{ $orgNames ?: '—' }}
                            </td>
                            <td class="py-3 px-4 text-slate-400">
                                {{ $user->created_at ? $user->created_at->format('M d, Y') : '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-slate-500 text-xs">No users found matching query.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($users->hasPages())
            <div class="p-4 border-t border-slate-800">
                {{ $users->links() }}
            </div>
        @endif
    </div>
@endsection
