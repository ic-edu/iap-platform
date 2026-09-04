@extends('organization::layouts.organization', [
    'title' => 'Members & Candidates',
    'heading' => 'Organization Members & Roster',
    'subheading' => 'Manage candidates, coordinators, and institutional invitations'
])

@section('content')
<div class="space-y-6" x-data="{ showInviteModal: false }">
    <!-- Action Bar -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-xs">
        <form method="GET" action="{{ route('organization.candidates', $organization->slug) }}" class="flex flex-wrap items-center gap-3 w-full sm:w-auto">
            <input type="text" name="search" value="{{ $search }}" placeholder="Search by name, email, ID..." class="px-3.5 py-2 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-white focus:outline-hidden focus:ring-2 focus:ring-indigo-500 w-64">

            <select name="role" class="px-3 py-2 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-white">
                <option value="all">All Roles</option>
                <option value="member" {{ $roleFilter === 'member' ? 'selected' : '' }}>Candidate Members</option>
                <option value="coordinator" {{ $roleFilter === 'coordinator' ? 'selected' : '' }}>Coordinators</option>
                <option value="admin" {{ $roleFilter === 'admin' ? 'selected' : '' }}>Admins</option>
                <option value="owner" {{ $roleFilter === 'owner' ? 'selected' : '' }}>Owners</option>
            </select>

            <button type="submit" class="px-4 py-2 text-sm font-medium bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 rounded-xl transition">
                Filter
            </button>
        </form>

        <button @click="showInviteModal = true" class="w-full sm:w-auto px-4 py-2 text-sm font-semibold bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl shadow-xs transition flex items-center justify-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Invite Member
        </button>
    </div>

    <!-- Active Roster Table -->
    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 overflow-hidden shadow-xs">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
            <h2 class="font-bold text-base text-slate-900 dark:text-white">Active Roster ({{ $memberships->total() }})</h2>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="border-b border-slate-200 dark:border-slate-800 bg-slate-50/75 dark:bg-slate-950/50 text-xs font-semibold uppercase text-slate-500">
                        <th class="py-3.5 px-6">Name & Email</th>
                        <th class="py-3.5 px-4">Member ID</th>
                        <th class="py-3.5 px-4">Role</th>
                        <th class="py-3.5 px-4">Department / Division</th>
                        <th class="py-3.5 px-4">Status</th>
                        <th class="py-3.5 px-6">Joined Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($memberships as $m)
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition">
                            <td class="py-3.5 px-6">
                                <div class="font-semibold text-slate-900 dark:text-white">{{ $m->user->name ?? '-' }}</div>
                                <div class="text-xs text-slate-500">{{ $m->user->email ?? '-' }}</div>
                            </td>
                            <td class="py-3.5 px-4 font-mono text-xs text-slate-600 dark:text-slate-400">
                                {{ $m->member_identifier ?? '—' }}
                            </td>
                            <td class="py-3.5 px-4">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium capitalize {{ $m->role->value === 'owner' ? 'bg-purple-100 text-purple-800 dark:bg-purple-950/60 dark:text-purple-300' : ($m->role->value === 'coordinator' ? 'bg-indigo-100 text-indigo-800 dark:bg-indigo-950/60 dark:text-indigo-300' : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300') }}">
                                    {{ $m->role->label() }}
                                </span>
                            </td>
                            <td class="py-3.5 px-4 text-xs text-slate-600 dark:text-slate-400">
                                {{ $m->department ?? '—' }}
                            </td>
                            <td class="py-3.5 px-4">
                                <span class="inline-flex items-center gap-1.5 text-xs font-medium text-emerald-600 dark:text-emerald-400">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                    {{ ucfirst($m->status->value) }}
                                </span>
                            </td>
                            <td class="py-3.5 px-6 text-xs text-slate-500">
                                {{ $m->joined_at ? $m->joined_at->format('M d, Y') : ($m->created_at ? $m->created_at->format('M d, Y') : '—') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-slate-400 text-sm">
                                No members found matching your search.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($memberships->hasPages())
            <div class="p-4 border-t border-slate-200 dark:border-slate-800">
                {{ $memberships->links() }}
            </div>
        @endif
    </div>

    <!-- Pending Invitations Section -->
    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 overflow-hidden shadow-xs">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
            <h2 class="font-bold text-base text-slate-900 dark:text-white">Recent Invitations</h2>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="border-b border-slate-200 dark:border-slate-800 bg-slate-50/75 dark:bg-slate-950/50 text-xs font-semibold uppercase text-slate-500">
                        <th class="py-3 px-6">Invited Email</th>
                        <th class="py-3 px-4">Intended Role</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4">Expires</th>
                        <th class="py-3 px-4">Invited By</th>
                        <th class="py-3 px-6 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($invitations as $inv)
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition text-xs">
                            <td class="py-3 px-6 font-medium text-slate-900 dark:text-white">{{ $inv->email }}</td>
                            <td class="py-3 px-4 capitalize">{{ $inv->intended_role->label() }}</td>
                            <td class="py-3 px-4">
                                <span class="px-2 py-0.5 rounded text-[11px] font-semibold uppercase {{ $inv->status->value === 'pending' ? 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300' : ($inv->status->value === 'accepted' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600') }}">
                                    {{ $inv->status->label() }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-slate-500">{{ $inv->expires_at->diffForHumans() }}</td>
                            <td class="py-3 px-4 text-slate-500">{{ $inv->inviter->name ?? 'Admin' }}</td>
                            <td class="py-3 px-6 text-right space-x-2">
                                @if($inv->isPending())
                                    <form method="POST" action="{{ route('organization.invitations.resend', [$organization->slug, $inv->id]) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 font-medium">Resend</button>
                                    </form>
                                    <form method="POST" action="{{ route('organization.invitations.revoke', [$organization->slug, $inv->id]) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="text-rose-600 hover:text-rose-800 dark:text-rose-400 font-medium">Revoke</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-6 text-center text-slate-400 text-xs">
                                No active or recent invitations.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal: Invite Member -->
    <div x-show="showInviteModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs" style="display: none;">
        <div @click.away="showInviteModal = false" class="bg-white dark:bg-slate-900 rounded-2xl max-w-md w-full p-6 border border-slate-200 dark:border-slate-800 shadow-xl space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                <h3 class="font-bold text-lg text-slate-900 dark:text-white">Invite New Member</h3>
                <button @click="showInviteModal = false" class="text-slate-400 hover:text-slate-600">✕</button>
            </div>

            <form method="POST" action="{{ route('organization.candidates.invite', $organization->slug) }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">Email Address *</label>
                    <input type="email" name="email" required placeholder="candidate@example.com" class="w-full px-3.5 py-2 text-sm rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">Intended Role *</label>
                    <select name="intended_role" class="w-full px-3 py-2 text-sm rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white">
                        <option value="member">Candidate Member (Default)</option>
                        <option value="coordinator">Organization Coordinator</option>
                        <option value="admin">Organization Admin</option>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">Student / Member ID</label>
                        <input type="text" name="member_identifier" placeholder="e.g. NIM12345" class="w-full px-3.5 py-2 text-sm rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">Department / Class</label>
                        <input type="text" name="department" placeholder="e.g. Grade 12A" class="w-full px-3.5 py-2 text-sm rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white">
                    </div>
                </div>

                <div class="pt-4 flex items-center justify-end gap-3 border-t border-slate-100 dark:border-slate-800">
                    <button type="button" @click="showInviteModal = false" class="px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-xl">Cancel</button>
                    <button type="submit" class="px-5 py-2 text-sm font-semibold bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl shadow-xs">Send Invitation</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
