@extends('layouts.admin')

@section('content')
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Organization Approvals & Governance</h1>
            <p class="text-xs text-slate-500 dark:text-slate-400">Super Admin oversight, institutional onboarding reviews, and group approvals</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="px-3 py-1 bg-amber-500/10 border border-amber-500/20 text-amber-700 dark:text-amber-400 rounded-full text-xs font-semibold">
                {{ $pendingOrganizations->count() }} Pending Organizations
            </span>
            @if($pendingGroups->count() > 0)
                <span class="px-3 py-1 bg-indigo-500/10 border border-indigo-500/20 text-indigo-700 dark:text-indigo-400 rounded-full text-xs font-semibold">
                    {{ $pendingGroups->count() }} Pending Groups
                </span>
            @endif
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

    <!-- SECTION 1: Pending Organizations Approval Queue -->
    <div class="mb-8 bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 rounded-xl overflow-hidden shadow-sm">
        <div class="p-4 bg-slate-50 dark:bg-slate-900/60 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                Pending Organization Submissions
            </h2>
            <span class="text-xs text-slate-500 dark:text-slate-400">{{ $pendingOrganizations->count() }} waiting for review</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="border-b border-slate-200 dark:border-slate-800 bg-slate-50/80 dark:bg-slate-900/30 text-[11px] font-semibold uppercase text-slate-500 dark:text-slate-400">
                        <th class="py-3 px-4">Organization</th>
                        <th class="py-3 px-4">Type</th>
                        <th class="py-3 px-4">Submitted By</th>
                        <th class="py-3 px-4">Submitted At</th>
                        <th class="py-3 px-4 text-right">Approval Decisions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
                    @forelse($pendingOrganizations as $org)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-900/40 transition-colors">
                            <td class="py-3 px-4">
                                <div class="font-bold text-slate-900 dark:text-white">{{ $org->name }}</div>
                                <div class="text-[11px] text-slate-500 font-mono">{{ $org->email ?? 'No email' }} &bull; {{ $org->city ?? 'No city' }}</div>
                            </td>
                            <td class="py-3 px-4 text-slate-600 dark:text-slate-400 capitalize">{{ $org->organization_type->label() }}</td>
                            <td class="py-3 px-4 text-slate-700 dark:text-slate-300">
                                {{ $org->submitter?->name ?? 'Registration Admin' }}
                            </td>
                            <td class="py-3 px-4 text-slate-600 dark:text-slate-400">
                                {{ $org->submitted_at ? $org->submitted_at->diffForHumans() : $org->created_at->diffForHumans() }}
                            </td>
                            <td class="py-3 px-4 text-right space-x-2 whitespace-nowrap">
                                <!-- Approve Form -->
                                <form method="POST" action="{{ route('admin.approvals.organizations.approve', $org->id) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="px-3 py-1 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-semibold rounded shadow transition-colors">
                                        ✓ Approve
                                    </button>
                                </form>

                                <!-- Return for Revision Modal Trigger -->
                                <button type="button" onclick="document.getElementById('revision-modal-{{ $org->id }}').classList.remove('hidden')" class="px-3 py-1 bg-amber-600/80 hover:bg-amber-500 text-white text-xs font-semibold rounded shadow transition-colors">
                                    Return for Revision
                                </button>

                                <!-- Reject Modal Trigger -->
                                <button type="button" onclick="document.getElementById('reject-modal-{{ $org->id }}').classList.remove('hidden')" class="px-3 py-1 bg-rose-600/80 hover:bg-rose-500 text-white text-xs font-semibold rounded shadow transition-colors">
                                    Reject
                                </button>

                                <!-- Revision Modal -->
                                <div id="revision-modal-{{ $org->id }}" class="hidden fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4 text-left">
                                    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-6 max-w-md w-full shadow-2xl">
                                        <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-1">Return {{ $org->name }} for Revision</h3>
                                        <p class="text-xs text-slate-500 dark:text-slate-400 mb-4">Provide clear instructions to Registration Admin on what information or documentation is needed.</p>
                                        <form method="POST" action="{{ route('admin.approvals.organizations.return-revision', $org->id) }}">
                                            @csrf
                                            <div class="mb-4">
                                                <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Revision Note *</label>
                                                <textarea name="revision_note" rows="3" required placeholder="e.g. Please clarify campus address and official institutional domain..." class="w-full p-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-lg text-xs text-slate-900 dark:text-white focus:border-amber-500 focus:outline-none"></textarea>
                                            </div>
                                            <div class="flex justify-end gap-2">
                                                <button type="button" onclick="document.getElementById('revision-modal-{{ $org->id }}').classList.add('hidden')" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs rounded-lg">Cancel</button>
                                                <button type="submit" class="px-4 py-1.5 bg-amber-600 hover:bg-amber-500 text-white text-xs font-semibold rounded-lg shadow">Send Revision Request</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>

                                <!-- Reject Modal -->
                                <div id="reject-modal-{{ $org->id }}" class="hidden fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4 text-left">
                                    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-6 max-w-md w-full shadow-2xl">
                                        <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-1">Reject {{ $org->name }}</h3>
                                        <p class="text-xs text-slate-500 dark:text-slate-400 mb-4">Specify the governance or policy reason for rejecting this organization.</p>
                                        <form method="POST" action="{{ route('admin.approvals.organizations.reject', $org->id) }}">
                                            @csrf
                                            <div class="mb-4">
                                                <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Rejection Reason *</label>
                                                <textarea name="rejection_reason" rows="3" required placeholder="e.g. Institution does not meet platform accreditation criteria..." class="w-full p-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-lg text-xs text-slate-900 dark:text-white focus:border-rose-500 focus:outline-none"></textarea>
                                            </div>
                                            <div class="flex justify-end gap-2">
                                                <button type="button" onclick="document.getElementById('reject-modal-{{ $org->id }}').classList.add('hidden')" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs rounded-lg">Cancel</button>
                                                <button type="submit" class="px-4 py-1.5 bg-rose-600 hover:bg-rose-500 text-white text-xs font-semibold rounded-lg shadow">Confirm Rejection</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-8 text-center text-slate-500 text-xs">No pending organization submissions awaiting approval.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- SECTION 2: Pending Institutional Groups Queue (if any) -->
    @if($pendingGroups->count() > 0)
        <div class="mb-8 bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 rounded-xl overflow-hidden shadow-sm">
            <div class="p-4 bg-slate-50 dark:bg-slate-900/60 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-indigo-500"></span>
                    Pending Institutional Groups
                </h2>
                <span class="text-xs text-slate-500 dark:text-slate-400">{{ $pendingGroups->count() }} awaiting approval</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-slate-200 dark:border-slate-800 bg-slate-50/80 dark:bg-slate-900/30 text-[11px] font-semibold uppercase text-slate-500 dark:text-slate-400">
                            <th class="py-3 px-4">Group Name</th>
                            <th class="py-3 px-4">Organization</th>
                            <th class="py-3 px-4">Type</th>
                            <th class="py-3 px-4">Submitted By</th>
                            <th class="py-3 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
                        @foreach($pendingGroups as $grp)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-900/40 transition-colors">
                                <td class="py-3 px-4 font-bold text-slate-900 dark:text-white">{{ $grp->name }}</td>
                                <td class="py-3 px-4 text-slate-700 dark:text-slate-300">{{ $grp->organization?->name }}</td>
                                <td class="py-3 px-4 text-slate-600 dark:text-slate-400 capitalize">{{ $grp->group_type ?? 'Standard' }}</td>
                                <td class="py-3 px-4 text-slate-600 dark:text-slate-400">{{ $grp->submitter?->name ?? 'Registration Admin' }}</td>
                                <td class="py-3 px-4 text-right">
                                    <form method="POST" action="{{ route('admin.approvals.organizations.groups.approve', $grp->id) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="px-3 py-1 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-semibold rounded shadow">
                                            ✓ Approve Group
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <!-- SECTION 3: Active & Managed Organizations Overview -->
    <div class="bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 rounded-xl overflow-hidden shadow-sm">
        <div class="p-4 bg-slate-50 dark:bg-slate-900/60 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                Active &amp; Governed Institutions
            </h2>
            <span class="text-xs text-slate-500 dark:text-slate-400">{{ $activeOrganizations->total() }} total</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="border-b border-slate-200 dark:border-slate-800 bg-slate-50/80 dark:bg-slate-900/30 text-[11px] font-semibold uppercase text-slate-500 dark:text-slate-400">
                        <th class="py-3 px-4">Organization</th>
                        <th class="py-3 px-4">Type</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4">Reviewed By</th>
                        <th class="py-3 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
                    @forelse($activeOrganizations as $org)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-900/40 transition-colors">
                            <td class="py-3 px-4">
                                <div class="font-bold text-slate-900 dark:text-white">{{ $org->name }}</div>
                                <div class="text-[11px] text-slate-500 font-mono">/organization/{{ $org->slug }}</div>
                            </td>
                            <td class="py-3 px-4 text-slate-600 dark:text-slate-400 capitalize">{{ $org->organization_type->label() }}</td>
                            <td class="py-3 px-4">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $org->isActive() ? 'bg-emerald-500/20 text-emerald-700 dark:text-emerald-400 border border-emerald-500/30' : 'bg-slate-500/20 text-slate-700 dark:text-slate-400 border border-slate-500/30' }}">
                                    {{ $org->status->label() }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-slate-600 dark:text-slate-400">
                                {{ $org->reviewer?->name ?? 'System' }}
                            </td>
                            <td class="py-3 px-4 text-right space-x-2">
                                <a href="{{ route('organization.dashboard', $org->slug) }}" class="text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:text-indigo-500" target="_blank">Portal ↗</a>
                                @if(!$org->isArchived())
                                    <form method="POST" action="{{ route('admin.approvals.organizations.archive', $org->id) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="text-xs font-medium text-slate-500 dark:text-slate-400 hover:text-rose-600">Archive</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-8 text-center text-slate-500 text-xs">No active organizations recorded.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($activeOrganizations->hasPages())
            <div class="p-4 border-t border-slate-200 dark:border-slate-800">
                {{ $activeOrganizations->links() }}
            </div>
        @endif
    </div>
@endsection
