@extends('layouts.admin')

@section('content')
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Institutional Organizations</h1>
            <p class="text-xs text-slate-500 dark:text-slate-400">Operational management for partner schools, universities, companies, and training institutions</p>
        </div>
        <a href="{{ route('admin.organizations.create') }}" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-lg shadow transition-colors flex items-center gap-1.5">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            <span>+ Create Organization</span>
        </a>
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

    <!-- Generated Invitation URL Callout (UAT / Test link sharing) -->
    @if(session('invitation_url'))
        <div class="mb-6 p-4 rounded-xl bg-indigo-500/10 border border-indigo-500/30 text-xs shadow-sm">
            <div class="flex items-center justify-between mb-2">
                <div class="font-bold text-indigo-700 dark:text-indigo-300 flex items-center gap-1.5">
                    <span>🔗</span>
                    <span>Primary Coordinator Invitation Link Generated</span>
                </div>
                <button type="button" onclick="navigator.clipboard.writeText('{{ session('invitation_url') }}'); this.innerText='✓ Copied!';" class="px-2.5 py-1 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold rounded text-[11px] transition-colors shadow-sm">
                    Copy Invitation Link
                </button>
            </div>
            <p class="text-[11px] text-slate-600 dark:text-slate-400 mb-2">Share this canonical activation link with the designated Institutional Primary Coordinator:</p>
            <div class="p-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded font-mono text-[11px] break-all select-all text-indigo-900 dark:text-indigo-200">
                {{ session('invitation_url') }}
            </div>
        </div>
    @endif

    <!-- Search & Filter Controls -->
    <div class="mb-6 p-4 bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 rounded-xl shadow-sm">
        <form action="{{ route('admin.organizations.index') }}" method="GET" class="flex flex-col sm:flex-row gap-3">
            <div class="flex-1 relative">
                <input type="text" name="search" value="{{ $search }}" placeholder="Search by name, slug, city..."
                       class="w-full pl-9 pr-4 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-lg text-xs text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:border-indigo-500 focus:outline-none transition-colors">
                <svg class="w-4 h-4 text-slate-400 dark:text-slate-500 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
            </div>

            <select name="status" class="px-4 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-lg text-xs text-slate-900 dark:text-white focus:border-indigo-500 focus:outline-none">
                <option value="all">All Statuses</option>
                @foreach($statuses as $s)
                    <option value="{{ $s->value }}" {{ $statusFilter === $s->value ? 'selected' : '' }}>{{ $s->label() }}</option>
                @endforeach
            </select>

            <select name="type" class="px-4 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-lg text-xs text-slate-900 dark:text-white focus:border-indigo-500 focus:outline-none">
                <option value="all">All Types</option>
                @foreach($types as $t)
                    <option value="{{ $t->value }}" {{ $typeFilter === $t->value ? 'selected' : '' }}>{{ $t->label() }}</option>
                @endforeach
            </select>

            <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-lg shadow transition-colors">
                Apply Filters
            </button>
            @if($search || $statusFilter !== 'all' || $typeFilter !== 'all')
                <a href="{{ route('admin.organizations.index') }}" class="px-3 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-400 text-xs font-medium rounded-lg text-center transition-colors">
                    Reset
                </a>
            @endif
        </form>
    </div>

    <!-- Organizations Table -->
    <div class="bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 rounded-xl overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="border-b border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/50 text-[11px] font-semibold uppercase text-slate-600 dark:text-slate-400">
                        <th class="py-3 px-4">Organization</th>
                        <th class="py-3 px-4">Type</th>
                        <th class="py-3 px-4">Primary Coordinator</th>
                        <th class="py-3 px-4">Active Members</th>
                        <th class="py-3 px-4">Groups</th>
                        <th class="py-3 px-4">Status & Governance</th>
                        <th class="py-3 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800/60">
                    @forelse($organizations as $org)
                        @php
                            $activeCoord = $org->primaryCoordinatorMembership;
                            $coordInv = $org->primaryCoordinatorInvitation;
                        @endphp
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-900/40 transition-colors">
                            <td class="py-3 px-4">
                                <div class="font-bold text-slate-900 dark:text-white">{{ $org->name }}</div>
                                <div class="text-[11px] text-slate-500 font-mono">/organization/{{ $org->slug }}</div>
                                @if($org->needsRevision() && $org->revision_note)
                                    <div class="mt-1.5 p-2 bg-amber-500/10 border border-amber-500/20 rounded text-[11px] text-amber-600 dark:text-amber-400">
                                        <span class="font-semibold">Revision Note:</span> {{ $org->revision_note }}
                                    </div>
                                @elseif($org->isRejected() && $org->rejection_reason)
                                    <div class="mt-1.5 p-2 bg-rose-500/10 border border-rose-500/20 rounded text-[11px] text-rose-600 dark:text-rose-400">
                                        <span class="font-semibold">Rejection Reason:</span> {{ $org->rejection_reason }}
                                    </div>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-slate-600 dark:text-slate-400 capitalize">{{ $org->organization_type->label() }}</td>

                            <!-- Primary Coordinator Lifecycle Column -->
                            <td class="py-3 px-4">
                                @if($activeCoord && $activeCoord->user)
                                    <div>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Active
                                        </span>
                                        <div class="font-medium text-slate-900 dark:text-white text-[11px] mt-0.5">{{ $activeCoord->user->name }}</div>
                                        <div class="text-[10px] text-slate-500 font-mono truncate max-w-[150px]" title="{{ $activeCoord->user->email }}">{{ $activeCoord->user->email }}</div>
                                    </div>
                                @elseif($coordInv)
                                    @if($coordInv->isPending())
                                        @if($coordInv->isExpired())
                                            <div>
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-rose-500/20 text-rose-600 dark:text-rose-400 border border-rose-500/30">
                                                    Expired
                                                </span>
                                                <div class="text-[11px] text-slate-600 dark:text-slate-300 font-mono truncate max-w-[150px] mt-0.5" title="{{ $coordInv->email }}">{{ $coordInv->email }}</div>
                                                <div class="text-[10px] text-rose-500">Expired {{ $coordInv->expires_at ? $coordInv->expires_at->diffForHumans() : '' }}</div>
                                            </div>
                                        @else
                                            <div>
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-amber-500/20 text-amber-600 dark:text-amber-400 border border-amber-500/30">
                                                    Pending
                                                </span>
                                                <div class="text-[11px] text-slate-700 dark:text-slate-300 font-mono truncate max-w-[150px] mt-0.5" title="{{ $coordInv->email }}">{{ $coordInv->email }}</div>
                                                <div class="text-[10px] text-slate-400">Expires {{ $coordInv->expires_at ? $coordInv->expires_at->diffForHumans() : '' }}</div>
                                            </div>
                                        @endif
                                    @elseif($coordInv->isRevoked())
                                        <div>
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-slate-500/20 text-slate-600 dark:text-slate-400 border border-slate-500/30">
                                                Revoked
                                            </span>
                                            <div class="text-[11px] text-slate-400 font-mono truncate max-w-[150px] mt-0.5" title="{{ $coordInv->email }}">{{ $coordInv->email }}</div>
                                        </div>
                                    @elseif($coordInv->isAccepted())
                                        <div>
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30">
                                                Accepted
                                            </span>
                                            <div class="text-[11px] text-slate-600 dark:text-slate-300 font-mono truncate max-w-[150px] mt-0.5" title="{{ $coordInv->email }}">{{ $coordInv->email }}</div>
                                        </div>
                                    @endif
                                @else
                                    <span class="text-[11px] text-slate-400 italic">Not Invited</span>
                                @endif
                            </td>

                            <td class="py-3 px-4 font-semibold text-slate-900 dark:text-white">{{ $org->active_memberships_count }}</td>
                            <td class="py-3 px-4 text-slate-600 dark:text-slate-400">{{ $org->groups_count }}</td>
                            <td class="py-3 px-4">
                                @if($org->isActive())
                                    <span class="px-2.5 py-0.5 rounded text-[10px] font-bold uppercase bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30">
                                        Active
                                    </span>
                                @elseif($org->isDraft())
                                    <span class="px-2.5 py-0.5 rounded text-[10px] font-bold uppercase bg-slate-500/20 text-slate-600 dark:text-slate-400 border border-slate-500/30">
                                        Draft
                                    </span>
                                @elseif($org->isPending())
                                    <span class="px-2.5 py-0.5 rounded text-[10px] font-bold uppercase bg-amber-500/20 text-amber-600 dark:text-amber-400 border border-amber-500/30">
                                        Pending Approval
                                    </span>
                                @elseif($org->needsRevision())
                                    <span class="px-2.5 py-0.5 rounded text-[10px] font-bold uppercase bg-orange-500/20 text-orange-600 dark:text-orange-400 border border-orange-500/30">
                                        Needs Revision
                                    </span>
                                @elseif($org->isRejected())
                                    <span class="px-2.5 py-0.5 rounded text-[10px] font-bold uppercase bg-rose-500/20 text-rose-600 dark:text-rose-400 border border-rose-500/30">
                                        Rejected
                                    </span>
                                @elseif($org->isSuspended())
                                    <span class="px-2.5 py-0.5 rounded text-[10px] font-bold uppercase bg-slate-500/20 text-slate-600 dark:text-slate-400 border border-slate-500/30">
                                        Suspended
                                    </span>
                                @elseif($org->isArchived())
                                    <span class="px-2.5 py-0.5 rounded text-[10px] font-bold uppercase bg-zinc-500/20 text-zinc-600 dark:text-zinc-400 border border-zinc-500/30">
                                        Archived
                                    </span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-right space-x-2 whitespace-nowrap">
                                @if($org->isActive())
                                    <a href="{{ route('organization.dashboard', $org->slug) }}" class="text-xs font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300" target="_blank">Portal ↗</a>
                                    <a href="{{ route('admin.organizations.edit', $org->id) }}" class="text-xs font-semibold text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-300">Edit</a>

                                    <!-- State-Aware Primary Coordinator Action Trigger -->
                                    @if(!$activeCoord && !$coordInv)
                                        <button type="button" onclick="document.getElementById('invite-modal-{{ $org->id }}').classList.remove('hidden')" class="text-xs font-semibold text-teal-600 hover:text-teal-500 dark:text-teal-400 dark:hover:text-teal-300">
                                            + Invite Coord
                                        </button>
                                    @elseif($coordInv && $coordInv->isPending() && !$coordInv->isExpired())
                                        <button type="button" onclick="document.getElementById('coord-modal-{{ $org->id }}').classList.remove('hidden')" class="text-xs font-semibold text-amber-600 hover:text-amber-500 dark:text-amber-400 dark:hover:text-amber-300">
                                            Coord Pending
                                        </button>
                                    @elseif($coordInv && $coordInv->isExpired())
                                        <button type="button" onclick="document.getElementById('coord-modal-{{ $org->id }}').classList.remove('hidden')" class="text-xs font-semibold text-rose-600 hover:text-rose-500 dark:text-rose-400 dark:hover:text-rose-300">
                                            Coord Expired
                                        </button>
                                        <button type="button" onclick="document.getElementById('invite-modal-{{ $org->id }}').classList.remove('hidden')" class="text-xs font-semibold text-teal-600 hover:text-teal-500 dark:text-teal-400 dark:hover:text-teal-300">
                                            + Invite Coord
                                        </button>
                                    @elseif($coordInv && $coordInv->isRevoked())
                                        <button type="button" onclick="document.getElementById('coord-modal-{{ $org->id }}').classList.remove('hidden')" class="text-xs font-semibold text-slate-600 hover:text-slate-500 dark:text-slate-400 dark:hover:text-slate-300">
                                            Coord Revoked
                                        </button>
                                        <button type="button" onclick="document.getElementById('invite-modal-{{ $org->id }}').classList.remove('hidden')" class="text-xs font-semibold text-teal-600 hover:text-teal-500 dark:text-teal-400 dark:hover:text-teal-300">
                                            + Invite Coord
                                        </button>
                                    @elseif($activeCoord || ($coordInv && $coordInv->isAccepted()))
                                        <button type="button" onclick="document.getElementById('coord-modal-{{ $org->id }}').classList.remove('hidden')" class="text-xs font-semibold text-emerald-600 hover:text-emerald-500 dark:text-emerald-400 dark:hover:text-emerald-300">
                                            Coord Active
                                        </button>
                                    @endif

                                    <!-- Quick Suspend -->
                                    <form method="POST" action="{{ route('admin.organizations.suspend', $org->id) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="text-xs font-medium text-amber-600 hover:text-amber-500 dark:text-amber-400 dark:hover:text-amber-300">
                                            Suspend
                                        </button>
                                    </form>

                                    <!-- Initial Coordinator Invite Modal -->
                                    <div id="invite-modal-{{ $org->id }}" class="hidden fixed inset-0 z-50 bg-slate-900/60 dark:bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4 text-left">
                                        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-6 max-w-md w-full shadow-2xl">
                                            <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-1">Invite Primary Coordinator</h3>
                                            <p class="text-xs text-slate-500 dark:text-slate-400 mb-4">Send an onboarding invitation to the primary coordinator for <strong class="text-slate-900 dark:text-white">{{ $org->name }}</strong>.</p>
                                            <form method="POST" action="{{ route('admin.organizations.invite-coordinator', $org->id) }}">
                                                @csrf
                                                <div class="mb-4">
                                                    <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Coordinator Email *</label>
                                                    <input type="email" name="coordinator_email" required placeholder="coordinator@example.com" class="w-full p-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-lg text-xs text-slate-900 dark:text-white focus:border-indigo-500 focus:outline-none">
                                                </div>
                                                <div class="flex justify-end gap-2">
                                                    <button type="button" onclick="document.getElementById('invite-modal-{{ $org->id }}').classList.add('hidden')" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs rounded-lg">Cancel</button>
                                                    <button type="submit" class="px-4 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-lg shadow">Generate Invitation</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>

                                    <!-- Comprehensive Coordinator Lifecycle Modal -->
                                    @if($activeCoord || $coordInv)
                                        <div id="coord-modal-{{ $org->id }}" class="hidden fixed inset-0 z-50 bg-slate-900/60 dark:bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4 text-left">
                                            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-6 max-w-lg w-full shadow-2xl">
                                                <div class="flex items-center justify-between pb-3 mb-4 border-b border-slate-200 dark:border-slate-800">
                                                    <div>
                                                        <h3 class="text-sm font-bold text-slate-900 dark:text-white">Primary Coordinator Governance</h3>
                                                        <p class="text-[11px] text-slate-500 dark:text-slate-400">{{ $org->name }} ({{ $org->organization_type->label() }})</p>
                                                    </div>
                                                    <button type="button" onclick="document.getElementById('coord-modal-{{ $org->id }}').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 text-sm">✕</button>
                                                </div>

                                                @if($activeCoord && $activeCoord->user)
                                                    <div class="space-y-3 mb-6 text-xs">
                                                        <div class="p-3 bg-emerald-500/10 border border-emerald-500/20 rounded-lg flex items-center justify-between">
                                                            <div class="font-bold text-emerald-700 dark:text-emerald-400 flex items-center gap-1.5">
                                                                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                                                <span>Active Primary Coordinator</span>
                                                            </div>
                                                            <span class="text-[10px] font-mono text-emerald-600 dark:text-emerald-400">ROLE: {{ strtoupper($activeCoord->role->value) }}</span>
                                                        </div>
                                                        <div class="grid grid-cols-2 gap-3 p-3 bg-slate-50 dark:bg-slate-950/50 rounded-lg border border-slate-200 dark:border-slate-800">
                                                            <div>
                                                                <span class="text-slate-400 text-[10px] uppercase font-semibold">Coordinator Name</span>
                                                                <div class="font-semibold text-slate-900 dark:text-white">{{ $activeCoord->user->name }}</div>
                                                            </div>
                                                            <div>
                                                                <span class="text-slate-400 text-[10px] uppercase font-semibold">Coordinator Email</span>
                                                                <div class="font-mono text-slate-900 dark:text-white truncate" title="{{ $activeCoord->user->email }}">{{ $activeCoord->user->email }}</div>
                                                            </div>
                                                            <div>
                                                                <span class="text-slate-400 text-[10px] uppercase font-semibold">Joined At</span>
                                                                <div class="text-slate-700 dark:text-slate-300">{{ $activeCoord->joined_at ? $activeCoord->joined_at->format('M d, Y H:i') : $activeCoord->created_at->format('M d, Y H:i') }}</div>
                                                            </div>
                                                            <div>
                                                                <span class="text-slate-400 text-[10px] uppercase font-semibold">Active Members Total</span>
                                                                <div class="text-slate-700 dark:text-slate-300">{{ $org->active_memberships_count }}</div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                @elseif($coordInv)
                                                    <div class="space-y-3 mb-6 text-xs">
                                                        <div class="p-3 rounded-lg flex items-center justify-between {{ $coordInv->isPending() && !$coordInv->isExpired() ? 'bg-amber-500/10 border border-amber-500/20 text-amber-700 dark:text-amber-400' : ($coordInv->isExpired() ? 'bg-rose-500/10 border border-rose-500/20 text-rose-700 dark:text-rose-400' : ($coordInv->isRevoked() ? 'bg-slate-500/10 border border-slate-500/20 text-slate-700 dark:text-slate-400' : 'bg-emerald-500/10 border border-emerald-500/20 text-emerald-700 dark:text-emerald-400')) }}">
                                                            <div class="font-bold flex items-center gap-1.5">
                                                                <span>Status: {{ $coordInv->status->label() }}</span>
                                                            </div>
                                                            <span class="text-[10px] font-mono">ROLE: {{ strtoupper($coordInv->intended_role->value) }}</span>
                                                        </div>

                                                        <div class="grid grid-cols-2 gap-3 p-3 bg-slate-50 dark:bg-slate-950/50 rounded-lg border border-slate-200 dark:border-slate-800">
                                                            <div>
                                                                <span class="text-slate-400 text-[10px] uppercase font-semibold">Invited Email</span>
                                                                <div class="font-mono text-slate-900 dark:text-white truncate" title="{{ $coordInv->email }}">{{ $coordInv->email }}</div>
                                                            </div>
                                                            <div>
                                                                <span class="text-slate-400 text-[10px] uppercase font-semibold">Invited By</span>
                                                                <div class="text-slate-700 dark:text-slate-300">{{ $coordInv->inviter?->name ?? 'Registration Admin' }}</div>
                                                            </div>
                                                            <div>
                                                                <span class="text-slate-400 text-[10px] uppercase font-semibold">Sent At</span>
                                                                <div class="text-slate-700 dark:text-slate-300">{{ $coordInv->created_at->format('M d, Y H:i') }} ({{ $coordInv->created_at->diffForHumans() }})</div>
                                                            </div>
                                                            <div>
                                                                <span class="text-slate-400 text-[10px] uppercase font-semibold">Expires At</span>
                                                                <div class="{{ $coordInv->isExpired() ? 'text-rose-600 dark:text-rose-400 font-semibold' : 'text-slate-700 dark:text-slate-300' }}">
                                                                    {{ $coordInv->expires_at ? $coordInv->expires_at->format('M d, Y H:i') : 'N/A' }}
                                                                    ({{ $coordInv->expires_at ? ($coordInv->expires_at->isPast() ? 'Expired' : $coordInv->expires_at->diffForHumans()) : 'N/A' }})
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Lifecycle Management Actions -->
                                                    <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-200 dark:border-slate-800">
                                                        @if($coordInv->isPending() && !$coordInv->isExpired())
                                                            <form method="POST" action="{{ route('admin.organizations.invitations.revoke', ['organization' => $org->id, 'invitation' => $coordInv->id]) }}">
                                                                @csrf
                                                                <button type="submit" class="px-3 py-1.5 bg-rose-600 hover:bg-rose-500 text-white text-xs font-semibold rounded-lg transition-colors">
                                                                    Revoke Invitation
                                                                </button>
                                                            </form>
                                                            <form method="POST" action="{{ route('admin.organizations.invitations.resend', ['organization' => $org->id, 'invitation' => $coordInv->id]) }}">
                                                                @csrf
                                                                <button type="submit" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-lg transition-colors">
                                                                    Resend / Extend (7 Days)
                                                                </button>
                                                            </form>
                                                        @elseif($coordInv->isExpired())
                                                            <button type="button" onclick="document.getElementById('coord-modal-{{ $org->id }}').classList.add('hidden'); document.getElementById('invite-modal-{{ $org->id }}').classList.remove('hidden');" class="px-3 py-1.5 bg-teal-600 hover:bg-teal-500 text-white text-xs font-semibold rounded-lg transition-colors">
                                                                Invite New Coordinator
                                                            </button>
                                                            <form method="POST" action="{{ route('admin.organizations.invitations.resend', ['organization' => $org->id, 'invitation' => $coordInv->id]) }}">
                                                                @csrf
                                                                <button type="submit" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-lg transition-colors">
                                                                    Resend Invitation (7 Days)
                                                                </button>
                                                            </form>
                                                        @elseif($coordInv->isRevoked())
                                                            <button type="button" onclick="document.getElementById('coord-modal-{{ $org->id }}').classList.add('hidden'); document.getElementById('invite-modal-{{ $org->id }}').classList.remove('hidden');" class="px-3 py-1.5 bg-teal-600 hover:bg-teal-500 text-white text-xs font-semibold rounded-lg transition-colors">
                                                                Invite New Coordinator
                                                            </button>
                                                            <form method="POST" action="{{ route('admin.organizations.invite-coordinator', $org->id) }}">
                                                                @csrf
                                                                <input type="hidden" name="coordinator_email" value="{{ $coordInv->email }}">
                                                                <button type="submit" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-lg transition-colors">
                                                                    Re-invite {{ $coordInv->email }}
                                                                </button>
                                                            </form>
                                                        @endif
                                                        <button type="button" onclick="document.getElementById('coord-modal-{{ $org->id }}').classList.add('hidden')" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs rounded-lg">
                                                            Close
                                                        </button>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    @endif
                                @elseif($org->isDraft())
                                    <a href="{{ route('admin.organizations.edit', $org->id) }}" class="text-xs font-semibold text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-300">Edit</a>
                                    <form method="POST" action="{{ route('admin.organizations.submit', $org->id) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="px-2.5 py-1 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded shadow transition-colors">
                                            Submit for Approval
                                        </button>
                                    </form>
                                @elseif($org->needsRevision())
                                    <a href="{{ route('admin.organizations.edit', $org->id) }}" class="px-2.5 py-1 bg-amber-600 hover:bg-amber-500 text-white text-xs font-semibold rounded shadow">
                                        Edit & Resubmit
                                    </a>
                                @elseif($org->isPending())
                                    <span class="text-[11px] text-amber-600 dark:text-amber-400 font-medium italic">Awaiting Approval</span>
                                @elseif($org->isSuspended())
                                    <a href="{{ route('admin.organizations.edit', $org->id) }}" class="text-xs font-semibold text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-300">Edit</a>
                                    <form method="POST" action="{{ route('admin.organizations.activate', $org->id) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="text-xs font-medium text-emerald-600 hover:text-emerald-500 dark:text-emerald-400 dark:hover:text-emerald-300">
                                            Reactivate
                                        </button>
                                    </form>
                                @elseif($org->isRejected())
                                    <a href="{{ route('admin.organizations.edit', $org->id) }}" class="text-xs font-semibold text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-300">Edit & Reapply</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-slate-500 text-xs">No organizations found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($organizations->hasPages())
            <div class="p-4 border-t border-slate-200 dark:border-slate-800">
                {{ $organizations->links() }}
            </div>
        @endif
    </div>
@endsection
