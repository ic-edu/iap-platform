@extends('organization::layouts.organization', [
    'title' => 'Dashboard',
    'heading' => 'Organization Overview',
    'subheading' => 'Management console for ' . $organization->name
])

@section('content')
<div class="space-y-6">
    <!-- 4 KPI Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <!-- KPI 1: Active Members -->
        <div class="bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Active Members</span>
                <span class="p-2 rounded-xl bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                </span>
            </div>
            <div class="mt-4 flex items-baseline gap-2">
                <span class="text-3xl font-bold text-slate-900 dark:text-white">{{ $activeMembersCount }}</span>
                <span class="text-xs text-slate-500 dark:text-slate-400">enrolled</span>
            </div>
        </div>

        <!-- KPI 2: Active Groups -->
        <div class="bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Active Groups</span>
                <span class="p-2 rounded-xl bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                </span>
            </div>
            <div class="mt-4 flex items-baseline gap-2">
                <span class="text-3xl font-bold text-slate-900 dark:text-white">{{ $activeGroupsCount }}</span>
                <span class="text-xs text-slate-500 dark:text-slate-400">classes / cohorts</span>
            </div>
        </div>

        <!-- KPI 3: Pending Invitations -->
        <div class="bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Pending Invites</span>
                <span class="p-2 rounded-xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                </span>
            </div>
            <div class="mt-4 flex items-baseline gap-2">
                <span class="text-3xl font-bold text-slate-900 dark:text-white">{{ $pendingInvitationsCount }}</span>
                <span class="text-xs text-slate-500 dark:text-slate-400">awaiting response</span>
            </div>
        </div>

        <!-- KPI 4: Organization Status -->
        <div class="bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Account Status</span>
                <span class="p-2 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                </span>
            </div>
            <div class="mt-4 flex items-center gap-2">
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold uppercase tracking-wider {{ $organizationStatus->value === 'active' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300' }}">
                    {{ $organizationStatus->label() }}
                </span>
            </div>
        </div>
    </div>

    <!-- Quick Navigation Panels -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Recent Members Panel -->
        <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 p-6 shadow-xs">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-bold text-base text-slate-900 dark:text-white">Recent Members</h2>
                <a href="{{ route('organization.candidates', $organization->slug) }}" class="text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:underline">View All →</a>
            </div>

            @if($recentMembers->isEmpty())
                <div class="text-center py-8 text-slate-400 text-sm">
                    No active members enrolled yet. Invite candidates to build your roster.
                </div>
            @else
                <div class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach($recentMembers as $membership)
                        <div class="py-3 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-xs font-bold text-slate-700 dark:text-slate-300">
                                    {{ strtoupper(substr($membership->user->name ?? 'U', 0, 1)) }}
                                </div>
                                <div>
                                    <div class="text-sm font-medium text-slate-900 dark:text-white">{{ $membership->user->name ?? 'Candidate' }}</div>
                                    <div class="text-xs text-slate-500">{{ $membership->user->email ?? '-' }}</div>
                                </div>
                            </div>
                            <span class="text-xs px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 capitalize">
                                {{ $membership->role->label() }}
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- Groups Overview Panel -->
        <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 p-6 shadow-xs">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-bold text-base text-slate-900 dark:text-white">Active Groups</h2>
                <a href="{{ route('organization.groups', $organization->slug) }}" class="text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:underline">Manage Groups →</a>
            </div>

            @if($recentGroups->isEmpty())
                <div class="text-center py-8 text-slate-400 text-sm">
                    No groups created yet. Create cohorts, classes, or departments to organize members.
                </div>
            @else
                <div class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach($recentGroups as $group)
                        <div class="py-3 flex items-center justify-between">
                            <div>
                                <div class="text-sm font-medium text-slate-900 dark:text-white">{{ $group->name }}</div>
                                <div class="text-xs text-slate-500 capitalize">{{ $group->group_type ?? 'Cohort' }}</div>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-medium text-slate-600 dark:text-slate-400">{{ $group->group_members_count }} members</span>
                                <a href="{{ route('organization.groups.show', [$organization->slug, $group->id]) }}" class="px-2.5 py-1 text-xs font-medium bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 rounded text-slate-700 dark:text-slate-300">View</a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
