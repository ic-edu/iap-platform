@extends('layouts.admin')

@section('title', 'Operational Dashboard — Candidate & Assessment Operations')

@section('content')
<div class="p-6 space-y-6 max-w-7xl mx-auto">

    {{-- Page Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-800">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="px-2.5 py-0.5 rounded text-[11px] font-bold tracking-wider uppercase bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                    OPERATIONAL WORKSPACE
                </span>
                @if($unreadNotificationsCount > 0)
                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-500/20 text-rose-300 border border-rose-500/30">
                    🔔 {{ $unreadNotificationsCount }} New Alerts
                </span>
                @endif
            </div>
            <h1 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight">Operational Dashboard</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Candidate Operations, Payment Eligibility &amp; Test Assignments — {{ now()->format('l, d F Y') }}</p>
        </div>
    </div>

    @if(session('status'))
    <div class="p-4 rounded-xl bg-emerald-950/60 border border-emerald-500/30 text-emerald-300 text-sm flex items-center justify-between shadow-lg">
        <span class="flex items-center gap-2">✅ {{ session('status') }}</span>
    </div>
    @endif

    @if(session('error'))
    <div class="p-4 rounded-xl bg-rose-950/60 border border-rose-500/30 text-rose-300 text-sm flex items-center justify-between shadow-lg">
        <span class="flex items-center gap-2">⚠️ {{ session('error') }}</span>
    </div>
    @endif

    {{-- Commercial & Voucher Operations Snapshot --}}
    <section id="commercial-voucher-snapshot" class="space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-1">
            <h2 class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 flex items-center gap-2">
                <span>Commercial &amp; Voucher Snapshot</span>
                @if($inactiveVouchersCount > 0)
                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-500/10 text-slate-500 dark:text-slate-400 border border-slate-500/20">
                    Inactive: {{ $inactiveVouchersCount }}
                </span>
                @endif
            </h2>
            <a href="{{ route('admin.commerce.index') }}" class="text-xs text-indigo-600 dark:text-indigo-400 hover:text-indigo-500 font-semibold flex items-center gap-1">
                <span>View Commercial Catalog</span>
                <span>&rarr;</span>
            </a>
        </div>

        {{-- Voucher KPI Cards Grid (Informational Non-Link Cards) --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

            {{-- 1. Active Vouchers --}}
            <div class="p-5 rounded-xl bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 shadow-sm relative overflow-hidden">
                <div class="absolute top-0 left-0 right-0 h-1 bg-emerald-500"></div>
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-xs font-semibold text-slate-500 dark:text-slate-400">Active Vouchers</p>
                        <p class="text-3xl font-black text-slate-900 dark:text-white mt-1">{{ number_format($activeVouchersCount) }}</p>
                    </div>
                    <div class="p-2.5 rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 text-xl">
                        🎟️
                    </div>
                </div>
                <div class="mt-3 text-[11px] font-semibold text-slate-500 dark:text-slate-400">
                    <span>Currently redeemable</span>
                </div>
            </div>

            {{-- 2. Scheduled Vouchers --}}
            <div class="p-5 rounded-xl bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 shadow-sm relative overflow-hidden">
                <div class="absolute top-0 left-0 right-0 h-1 bg-sky-500"></div>
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-xs font-semibold text-slate-500 dark:text-slate-400">Scheduled Vouchers</p>
                        <p class="text-3xl font-black text-slate-900 dark:text-white mt-1">{{ number_format($scheduledVouchersCount) }}</p>
                    </div>
                    <div class="p-2.5 rounded-lg bg-sky-500/10 text-sky-600 dark:text-sky-400 border border-sky-500/20 text-xl">
                        ⏳
                    </div>
                </div>
                <div class="mt-3 text-[11px] font-semibold text-slate-500 dark:text-slate-400">
                    <span>Not active yet</span>
                </div>
            </div>

            {{-- 3. Expired Vouchers --}}
            <div class="p-5 rounded-xl bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 shadow-sm relative overflow-hidden">
                <div class="absolute top-0 left-0 right-0 h-1 bg-rose-500"></div>
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-xs font-semibold text-slate-500 dark:text-slate-400">Expired Vouchers</p>
                        <p class="text-3xl font-black text-slate-900 dark:text-white mt-1">{{ number_format($expiredVouchersCount) }}</p>
                    </div>
                    <div class="p-2.5 rounded-lg bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20 text-xl">
                        ⌛
                    </div>
                </div>
                <div class="mt-3 text-[11px] font-semibold text-slate-500 dark:text-slate-400">
                    <span>Validity ended</span>
                </div>
            </div>

            {{-- 4. Total Redemptions --}}
            <div class="p-5 rounded-xl bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 shadow-sm relative overflow-hidden">
                <div class="absolute top-0 left-0 right-0 h-1 bg-indigo-500"></div>
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-xs font-semibold text-slate-500 dark:text-slate-400">Total Redemptions</p>
                        <p class="text-3xl font-black text-slate-900 dark:text-white mt-1">{{ number_format($totalVoucherRedemptions) }}</p>
                    </div>
                    <div class="p-2.5 rounded-lg bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border border-indigo-500/20 text-xl">
                        📊
                    </div>
                </div>
                <div class="mt-3 text-[11px] font-semibold text-slate-500 dark:text-slate-400">
                    <span>Across all vouchers</span>
                </div>
            </div>

        </div>

        {{-- Current Vouchers Subsection --}}
        <div class="pt-1">
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2.5 flex items-center gap-1.5">
                <span>🎟️</span>
                <span>Current Vouchers</span>
            </h3>

            @if($recentVouchers->isEmpty())
            <div class="py-6 text-center border border-dashed border-slate-200 dark:border-slate-800/80 rounded-xl bg-slate-50 dark:bg-slate-900/40">
                <p class="text-xs text-slate-500 dark:text-slate-400">No currently redeemable vouchers.</p>
            </div>
            @else
            <div class="space-y-2.5">
                @foreach($recentVouchers as $voucher)
                    @php
                        $vState = $voucher->getEffectiveState();
                        $badgeClasses = match($vState) {
                            'ACTIVE'    => 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20',
                            'SCHEDULED' => 'bg-sky-500/10 text-sky-600 dark:text-sky-400 border-sky-500/20',
                            'EXPIRED'   => 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border-rose-500/20',
                            default     => 'bg-slate-500/10 text-slate-600 dark:text-slate-400 border-slate-500/20',
                        };
                    @endphp
                    <div class="p-3.5 rounded-lg bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800/80 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-sm">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="font-mono font-bold text-indigo-600 dark:text-indigo-400 text-xs">{{ $voucher->code }}</span>
                                <span class="text-xs font-bold text-slate-800 dark:text-slate-200">{{ $voucher->value }}% OFF</span>
                                <span class="px-2 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider border {{ $badgeClasses }}">
                                    {{ $vState }}
                                </span>
                            </div>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">
                                @if($vState === 'ACTIVE')
                                    Valid until {{ $voucher->valid_until ? $voucher->valid_until->format('d M Y') : 'Open' }}
                                @elseif($vState === 'SCHEDULED')
                                    Starts {{ $voucher->valid_from ? $voucher->valid_from->format('d M Y, H:i') : '-' }}
                                @elseif($vState === 'EXPIRED')
                                    Expired {{ $voucher->valid_until ? $voucher->valid_until->format('d M Y') : '-' }}
                                @else
                                    {{ $voucher->valid_until ? 'Valid until ' . $voucher->valid_until->format('d M Y') : 'Inactive' }}
                                @endif
                                &bull;
                                <span class="font-medium text-slate-700 dark:text-slate-300">{{ $voucher->used_count }} {{ \Illuminate\Support\Str::plural('redemption', $voucher->used_count) }}</span>
                            </p>
                        </div>
                    </div>
                @endforeach
            </div>
            @endif
        </div>
    </section>

    {{-- Operational KPI Grid --}}
    <section>
        <h2 class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-3 flex items-center gap-2">
            <span>Platform Candidate &amp; Testing Metrics</span>
        </h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

            {{-- 1. Total Candidates --}}
            <a href="{{ route('admin.candidates.index') }}" class="group block p-5 rounded-xl bg-slate-950/80 border border-slate-800 hover:border-indigo-500/50 hover:bg-slate-900/90 transition-all shadow-md relative overflow-hidden">
                <div class="absolute top-0 left-0 right-0 h-1 bg-indigo-500"></div>
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-xs font-semibold text-slate-500 dark:text-slate-400">Total Registered Candidates</p>
                        <p class="text-3xl font-black text-slate-900 dark:text-white mt-1 group-hover:text-indigo-600 dark:group-hover:text-indigo-300 transition-colors">{{ number_format($totalCandidates) }}</p>
                    </div>
                    <div class="p-2.5 rounded-lg bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border border-indigo-500/20 text-xl">
                        👥
                    </div>
                </div>
                <div class="mt-3 flex items-center gap-1.5 text-[11px] font-semibold text-slate-500 dark:text-slate-400">
                    <span class="text-indigo-600 dark:text-indigo-400 group-hover:translate-x-0.5 transition-transform">View Candidates &rarr;</span>
                </div>
            </a>

            {{-- 2. Paid / Eligible Candidates --}}
            <a href="{{ route('admin.candidates.index', ['filter' => 'paid-eligible']) }}" class="group block p-5 rounded-xl bg-slate-950/80 border border-slate-800 hover:border-emerald-500/50 hover:bg-slate-900/90 transition-all shadow-md relative overflow-hidden">
                <div class="absolute top-0 left-0 right-0 h-1 bg-emerald-500"></div>
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-xs font-semibold text-slate-500 dark:text-slate-400">Paid &amp; Eligible Candidates</p>
                        <p class="text-3xl font-black text-slate-900 dark:text-white mt-1 group-hover:text-emerald-600 dark:group-hover:text-emerald-300 transition-colors">{{ number_format($paidEligibleCandidatesCount) }}</p>
                    </div>
                    <div class="p-2.5 rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 text-xl">
                        💳
                    </div>
                </div>
                <div class="mt-3 flex items-center gap-1.5 text-[11px] font-semibold text-slate-500 dark:text-slate-400">
                    <span class="text-emerald-600 dark:text-emerald-400 group-hover:translate-x-0.5 transition-transform">View Eligible Candidates &rarr;</span>
                </div>
            </a>

            {{-- 3. Active Test Assignments --}}
            <a href="{{ route('admin.tests.index', ['filter' => 'active-assignments']) }}" class="group block p-5 rounded-xl bg-slate-950/80 border border-slate-800 hover:border-amber-500/50 hover:bg-slate-900/90 transition-all shadow-md relative overflow-hidden">
                <div class="absolute top-0 left-0 right-0 h-1 bg-amber-500"></div>
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-xs font-semibold text-slate-500 dark:text-slate-400">Active Test Assignments</p>
                        <p class="text-3xl font-black text-slate-900 dark:text-white mt-1 group-hover:text-amber-600 dark:group-hover:text-amber-300 transition-colors">{{ number_format($activeAssignmentsCount) }}</p>
                    </div>
                    <div class="p-2.5 rounded-lg bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20 text-xl">
                        🎯
                    </div>
                </div>
                <div class="mt-3 flex items-center gap-1.5 text-[11px] font-semibold text-slate-500 dark:text-slate-400">
                    <span class="text-amber-600 dark:text-amber-400 group-hover:translate-x-0.5 transition-transform">View Active Assignments &rarr;</span>
                </div>
            </a>

            {{-- 4. Completed Tests --}}
            <a href="{{ route('admin.reporting.index') }}" class="group block p-5 rounded-xl bg-slate-950/80 border border-slate-800 hover:border-sky-500/50 hover:bg-slate-900/90 transition-all shadow-md relative overflow-hidden">
                <div class="absolute top-0 left-0 right-0 h-1 bg-sky-500"></div>
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-xs font-semibold text-slate-500 dark:text-slate-400">Completed Assessments</p>
                        <p class="text-3xl font-black text-slate-900 dark:text-white mt-1 group-hover:text-sky-600 dark:group-hover:text-sky-300 transition-colors">{{ number_format($completedAttemptsCount) }}</p>
                    </div>
                    <div class="p-2.5 rounded-lg bg-sky-500/10 text-sky-600 dark:text-sky-400 border border-sky-500/20 text-xl">
                        ✅
                    </div>
                </div>
                <div class="mt-3 flex items-center gap-1.5 text-[11px] font-semibold text-slate-500 dark:text-slate-400">
                    <span class="text-sky-600 dark:text-sky-400 group-hover:translate-x-0.5 transition-transform">View Completed Results &rarr;</span>
                </div>
            </a>

        </div>
    </section>

    {{-- Institutional Operations Snapshot --}}
    <section id="institutional-operations" class="space-y-3">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-1">
            <h2 class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 flex items-center gap-2">
                <span>Institutional Operations</span>
            </h2>
            <a href="{{ route('admin.organizations.index') }}" class="text-xs text-indigo-600 dark:text-indigo-400 hover:text-indigo-500 font-semibold flex items-center gap-1">
                <span>View All Organizations</span>
                <span>&rarr;</span>
            </a>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            {{-- 1. Pending Organization Approvals --}}
            <a href="{{ route('admin.organizations.index', ['status' => 'pending']) }}" class="group block p-5 rounded-xl bg-slate-950/80 border border-slate-800 hover:border-amber-500/50 hover:bg-slate-900/90 transition-all shadow-md relative overflow-hidden">
                <div class="absolute top-0 left-0 right-0 h-1 bg-amber-500"></div>
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-xs font-semibold text-slate-500 dark:text-slate-400">Pending Organization Approvals</p>
                        <p class="text-3xl font-black text-slate-900 dark:text-white mt-1 group-hover:text-amber-600 dark:group-hover:text-amber-300 transition-colors">{{ number_format($pendingOrganizationsCount) }}</p>
                    </div>
                    <div class="p-2.5 rounded-lg bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20 text-xl">
                        🏛️
                    </div>
                </div>
                <div class="mt-3 flex items-center justify-between text-[11px] font-semibold text-slate-500 dark:text-slate-400">
                    <span>Awaiting Super Admin review</span>
                    <span class="text-amber-600 dark:text-amber-400 group-hover:translate-x-0.5 transition-transform">View Pending Organizations &rarr;</span>
                </div>
            </a>

            {{-- 2. Active Organizations --}}
            <a href="{{ route('admin.organizations.index', ['status' => 'active']) }}" class="group block p-5 rounded-xl bg-slate-950/80 border border-slate-800 hover:border-emerald-500/50 hover:bg-slate-900/90 transition-all shadow-md relative overflow-hidden">
                <div class="absolute top-0 left-0 right-0 h-1 bg-emerald-500"></div>
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-xs font-semibold text-slate-500 dark:text-slate-400">Active Organizations</p>
                        <p class="text-3xl font-black text-slate-900 dark:text-white mt-1 group-hover:text-emerald-600 dark:group-hover:text-emerald-300 transition-colors">{{ number_format($activeOrganizationsCount) }}</p>
                    </div>
                    <div class="p-2.5 rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 text-xl">
                        🏢
                    </div>
                </div>
                <div class="mt-3 flex items-center justify-between text-[11px] font-semibold text-slate-500 dark:text-slate-400">
                    <span>Approved &amp; operational institutions</span>
                    <span class="text-emerald-600 dark:text-emerald-400 group-hover:translate-x-0.5 transition-transform">View Active Organizations &rarr;</span>
                </div>
            </a>
        </div>
    </section>

    {{-- Action Panel: Candidates Requiring Action --}}
    <section id="action-queue" class="rounded-xl bg-slate-950/80 border border-slate-800 shadow-lg p-5">
        <div class="flex items-center justify-between pb-3 border-b border-slate-800 mb-4">
            <div class="flex items-center gap-2.5">
                <span class="w-2.5 h-2.5 rounded-full bg-rose-500 animate-pulse"></span>
                <h2 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider">Candidates Requiring Action</h2>
                <span class="ra-status-badge ra-status--awaiting-assignment">
                    {{ count($actionRequiredCandidates) }} Awaiting Assignment
                </span>
            </div>
            <span class="text-xs text-slate-500 dark:text-slate-400 font-medium">Mock Test Payment Verification</span>
        </div>

        @if(count($actionRequiredCandidates) === 0)
        <div class="py-3.5 px-4 text-center border border-dashed border-slate-800/80 rounded-xl bg-slate-900/40 flex items-center justify-center gap-2 text-xs text-slate-400">
            <span class="text-base">🎉</span>
            <strong class="text-slate-700 dark:text-slate-300 font-semibold">All Clear:</strong>
            <span>No paid candidates are currently waiting for Mock Test assignment.</span>
        </div>
        @else
        <div class="divide-y divide-slate-800/80">
            @foreach($actionRequiredCandidates as $item)
            <div class="py-3.5 flex flex-col sm:flex-row sm:items-center justify-between gap-3 hover:bg-slate-900/40 px-3 rounded-lg transition-colors">
                <div class="flex items-start gap-3 min-w-0">
                    <div class="w-9 h-9 rounded-full bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border border-indigo-500/20 flex items-center justify-center font-bold text-xs flex-shrink-0">
                        {{ strtoupper(substr($item['user']->name, 0, 2)) }}
                    </div>
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <p class="text-sm font-bold text-slate-900 dark:text-white truncate">{{ $item['user']->name }}</p>
                            <span class="ra-status-badge ra-status--approved text-[9px] py-0.5 px-2">
                                PAID
                            </span>
                            <span class="ra-status-badge ra-status--rejected text-[9px] py-0.5 px-2">
                                MOCK TEST
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                            Target: <span class="text-slate-800 dark:text-slate-200 font-semibold">{{ $item['test']->title }}</span> &bull; Paid: {{ \Carbon\Carbon::parse($item['paid_at'])->diffForHumans() }}
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0">
                    <form action="{{ route('admin.tests.assign-candidate', $item['test']->id) }}" method="POST" class="inline-flex">
                        @csrf
                        <input type="hidden" name="candidate_id" value="{{ $item['user']->id }}">
                        <button type="submit" class="px-3.5 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold transition-all shadow-md shadow-emerald-600/20 flex items-center gap-1.5">
                            <span>✓ Assign Mock Test</span>
                        </button>
                    </form>
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </section>

    {{-- Institutional Action Panel: Active Organization Seats Awaiting RA Assessment Assignment (O3) --}}
    <section id="institutional-action-queue" class="rounded-xl bg-slate-950/80 border border-slate-800 shadow-lg p-5">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-3 border-b border-slate-800 mb-4 gap-2">
            <div class="flex items-center gap-2.5">
                <span class="w-2.5 h-2.5 rounded-full bg-indigo-500 animate-pulse"></span>
                <h2 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider">Institutional Seats Awaiting Assessment Assignment</h2>
                <span class="ra-status-badge bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 text-xs px-2.5 py-0.5">
                    {{ count($institutionalSeatsAwaitingAssignment) }} Active Seat(s)
                </span>
            </div>
            <span class="text-xs text-slate-500 dark:text-slate-400 font-medium">B2B Institutional Seat Pool &bull; RA Controlled</span>
        </div>

        @if(count($institutionalSeatsAwaitingAssignment) === 0)
        <div class="py-3.5 px-4 text-center border border-dashed border-slate-800/80 rounded-xl bg-slate-900/40 flex items-center justify-center gap-2 text-xs text-slate-400">
            <span class="text-base">🎉</span>
            <strong class="text-slate-700 dark:text-slate-300 font-semibold">All Clear:</strong>
            <span>No active institutional seats are currently waiting for assessment assignment.</span>
        </div>
        @else
        <div class="divide-y divide-slate-800/80">
            @foreach($institutionalSeatsAwaitingAssignment as $allocation)
                @php
                    $candidateUser = $allocation->membership?->user;
                    $org = $allocation->entitlement?->organization;
                    $product = $allocation->entitlement?->product;
                    $order = $allocation->entitlement?->orderItem?->order;
                    $groups = $allocation->membership?->groups ?? collect();
                    $eligibleTests = $eligibleTestsByProduct[$product?->id] ?? collect();
                @endphp
                <div class="py-4 flex flex-col lg:flex-row lg:items-center justify-between gap-4 hover:bg-slate-900/40 px-3 rounded-lg transition-colors">
                    <div class="flex items-start gap-3 min-w-0">
                        <div class="w-10 h-10 rounded-full bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 flex items-center justify-center font-bold text-xs flex-shrink-0">
                            {{ strtoupper(substr($candidateUser?->name ?? 'CA', 0, 2)) }}
                        </div>
                        <div class="min-w-0 space-y-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-sm font-bold text-slate-900 dark:text-white">{{ $candidateUser?->name ?? 'Candidate' }}</p>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-800 text-slate-300 border border-slate-700">
                                    {{ $allocation->membership?->member_id ?? 'No NIM' }}
                                </span>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-indigo-500/20 text-indigo-300 border border-indigo-500/30">
                                    {{ $org?->name ?? 'Organization' }}
                                </span>
                                @foreach($groups as $grp)
                                <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-slate-800/80 text-slate-400 border border-slate-700">
                                    👥 {{ $grp->name }}
                                </span>
                                @endforeach
                            </div>
                            <div class="text-xs text-slate-400 flex flex-wrap items-center gap-x-3 gap-y-1">
                                <span>Email: <strong class="text-slate-300 font-medium">{{ $candidateUser?->email }}</strong></span>
                                <span>&bull;</span>
                                <span>Package: <strong class="text-emerald-400 font-medium">{{ $product?->name }}</strong></span>
                                @if($product?->assessment_family)
                                <span class="px-1.5 py-0.5 rounded text-[9px] font-black uppercase bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                    {{ strtoupper($product->assessment_family) }}
                                </span>
                                @endif
                                <span>&bull;</span>
                                <span>Allocated: <strong class="text-slate-300 font-medium">{{ \Carbon\Carbon::parse($allocation->allocated_at)->diffForHumans() }}</strong></span>
                                @if($order)
                                <span>&bull;</span>
                                <span>Order: <strong class="text-indigo-400 font-mono text-[11px]">{{ $order->order_number }}</strong></span>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 flex-shrink-0">
                        @if($eligibleTests->isEmpty())
                            @php
                                $familyCode = is_object($product?->assessment_family) ? $product->assessment_family->value : ($product?->assessment_family ?? 'toeic');
                                $groupContext = $groups->isNotEmpty() ? ' — ' . $groups->pluck('name')->join(', ') : '';
                                $progContext = trim(($org?->name ?? 'Organization') . $groupContext);
                                $defaultReqTitle = strtoupper($familyCode) . ' Mock Test';
                            @endphp
                            <div class="flex items-center gap-2 flex-wrap">
                                <div class="px-3 py-1.5 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-300 text-xs flex items-center gap-1.5">
                                    <span>⚠️</span>
                                    <span>No published {{ strtoupper($familyCode) }} tests</span>
                                </div>
                                <a href="{{ route('admin.assessment-requests.index', [
                                    'candidate_id'    => $candidateUser?->id,
                                    'test_type'       => $familyCode,
                                    'program_context' => $progContext,
                                    'title'           => $defaultReqTitle,
                                ]) }}" class="px-3 py-1.5 rounded-lg bg-indigo-600/20 hover:bg-indigo-600/30 text-indigo-300 text-xs font-bold border border-indigo-500/30 transition-colors flex items-center gap-1.5 shadow-sm">
                                    <span>📋 Request Mock Test</span>
                                </a>
                            </div>
                        @else
                            <form action="{{ route('admin.institutional-seats.assign', $allocation->id) }}" method="POST" class="flex items-center gap-2">
                                @csrf
                                <select name="test_id" required class="px-3 py-1.5 rounded-lg bg-slate-900 border border-slate-700 text-xs text-white focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 min-w-[200px]">
                                    <option value="" disabled selected>Select Published Assessment...</option>
                                    @foreach($eligibleTests as $test)
                                        <option value="{{ $test->id }}">
                                            {{ $test->title }} ({{ strtoupper(is_object($test->test_type) ? $test->test_type->value : (string)$test->test_type) }})
                                        </option>
                                    @endforeach
                                </select>
                                <button type="submit" class="px-3.5 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold transition-all shadow-md shadow-indigo-600/20 flex items-center gap-1.5">
                                    <span>🎯 Assign Assessment</span>
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
        @endif
    </section>

    {{-- Two Column Layout: Recent Active Assignments & Assessment Inventory --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {{-- Column 1: Recent Active Candidate Assignments --}}
        <section class="rounded-xl bg-slate-950/80 border border-slate-800 shadow-lg p-5">
            <div class="flex items-center justify-between pb-3 border-b border-slate-800 mb-4">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider flex items-center gap-2">
                    <span>🎯</span> Recent Active Assignments
                </h2>
                <a href="{{ route('admin.tests.index', ['filter' => 'active-assignments']) }}" class="text-xs text-indigo-600 dark:text-indigo-400 hover:text-indigo-500 font-semibold">View Active Assignments &rarr;</a>
            </div>

            @if($recentAssignments->isEmpty())
            <div class="py-8 text-center border border-dashed border-slate-800/80 rounded-xl bg-slate-900/40">
                <p class="text-xs text-slate-500 dark:text-slate-400">No active candidate assignments on record.</p>
            </div>
            @else
            <div class="space-y-2.5">
                @foreach($recentAssignments as $assignment)
                <div class="p-3 rounded-lg bg-slate-900/60 border border-slate-800/80 flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-xs font-bold text-slate-900 dark:text-white truncate">{{ $assignment->user?->name ?? 'Candidate' }}</p>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400 truncate">{{ $assignment->test?->title ?? 'Test' }}</p>
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0">
                        <span class="ra-status-badge {{ $assignment->test?->isRealTest() ? 'ra-status--rejected' : 'ra-status--placement-required' }} text-[9px] py-0.5 px-2">
                            {{ $assignment->test?->assessment_mode?->label() ?? 'Assessment' }}
                        </span>
                        <span class="ra-status-badge ra-status--active text-[9px] py-0.5 px-2">
                            ACTIVE
                        </span>
                    </div>
                </div>
                @endforeach
            </div>
            @endif
        </section>

        {{-- Column 2: Assessment Inventory --}}
        <section class="rounded-xl bg-slate-950/80 border border-slate-800 shadow-lg p-5">
            <div class="flex items-center justify-between pb-3 border-b border-slate-800 mb-4">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider flex items-center gap-2">
                    <span>📋</span> Assessment Inventory
                </h2>
            </div>

            @if($availableTests->isEmpty())
            <div class="py-8 text-center border border-dashed border-slate-800/80 rounded-xl bg-slate-900/40">
                <p class="text-xs text-slate-500 dark:text-slate-400">No published assessments available.</p>
            </div>
            @else
            <div class="space-y-2.5">
                @foreach($availableTests->take(6) as $test)
                <div class="p-3 rounded-lg bg-slate-900/60 border border-slate-800/80 flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <p class="text-xs font-bold text-slate-900 dark:text-white truncate">{{ $test->title }}</p>
                            <span class="px-1.5 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider {{ $test->isRealTest() ? 'bg-rose-500/20 text-rose-600 dark:text-rose-300 border border-rose-500/30' : 'bg-amber-500/20 text-amber-600 dark:text-amber-300 border border-amber-500/30' }}">
                                {{ $test->assessment_mode?->label() ?? 'Assessment' }}
                            </span>
                        </div>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                            @if($test->isSimulator())
                                Automatic / Open Candidate Access
                            @else
                                {{ $test->active_assignments_count }} Active Candidate(s) Assigned
                            @endif
                        </p>
                    </div>
                    <a href="{{ route('admin.tests.show', $test->id) }}" class="px-2.5 py-1 rounded bg-slate-800 hover:bg-slate-700 text-slate-200 hover:text-white text-xs font-semibold border border-slate-700 transition-colors flex-shrink-0">
                        Details &rarr;
                    </a>
                </div>
                @endforeach
            </div>
            @endif
        </section>

    </div>

</div>
@endsection
