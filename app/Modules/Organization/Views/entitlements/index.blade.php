@extends('organization::layouts.organization', [
    'title' => 'Seat Entitlements',
    'heading' => 'Institutional Seat Entitlements',
    'subheading' => 'Manage purchased assessment seat pools and allocate seats to candidate members'
])

@section('content')
<div class="space-y-6">
    <!-- Stat Cards -->
    @php
        $totalSeats = $entitlements->sum('total_seats');
        $allocatedSeats = $entitlements->sum(fn($e) => $e->allocatedSeatsCount());
        $availableSeats = $totalSeats - $allocatedSeats;
    @endphp
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Total Seats Owned</span>
            <div class="text-2xl font-black text-slate-900 dark:text-white mt-1">{{ $totalSeats }}</div>
            <span class="text-[11px] text-slate-400 mt-0.5 block">Across all paid assessment packages</span>
        </div>

        <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Allocated Seats</span>
            <div class="text-2xl font-black text-indigo-600 dark:text-indigo-400 mt-1">{{ $allocatedSeats }}</div>
            <span class="text-[11px] text-slate-400 mt-0.5 block">Assigned to candidate members</span>
        </div>

        <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Available Pool</span>
            <div class="text-2xl font-black text-emerald-600 dark:text-emerald-400 mt-1">{{ $availableSeats }}</div>
            <span class="text-[11px] text-slate-400 mt-0.5 block">Ready for candidate assignment</span>
        </div>
    </div>

    <!-- Action Bar -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-xs">
        <div>
            <h2 class="text-base font-bold text-slate-900 dark:text-white">Seat Pools</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400">Assessment packages provisioned for {{ $organization->name }}</p>
        </div>

        <a href="{{ route('organization.purchases.create', $organization->slug) }}" class="w-full sm:w-auto px-4 py-2 text-sm font-semibold bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl shadow-xs transition flex items-center justify-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Purchase More Seats
        </a>
    </div>

    <!-- Entitlements Table -->
    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 overflow-hidden shadow-xs">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
            <h3 class="font-bold text-base text-slate-900 dark:text-white">Active Entitlement Pools ({{ $entitlements->total() }})</h3>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="border-b border-slate-200 dark:border-slate-800 bg-slate-50/75 dark:bg-slate-950/50 text-xs font-semibold uppercase text-slate-500">
                        <th class="py-3.5 px-6">Assessment Package</th>
                        <th class="py-3.5 px-4 text-center">Total Seats</th>
                        <th class="py-3.5 px-4 text-center">Allocated</th>
                        <th class="py-3.5 px-4 text-center">Available</th>
                        <th class="py-3.5 px-4">Status</th>
                        <th class="py-3.5 px-4">Order Ref</th>
                        <th class="py-3.5 px-6 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($entitlements as $entitlement)
                        @php
                            $allocCount = $entitlement->allocatedSeatsCount();
                            $availCount = $entitlement->availableSeatsCount();
                        @endphp
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition">
                            <td class="py-3.5 px-6">
                                <a href="{{ route('organization.entitlements.show', [$organization->slug, $entitlement->id]) }}" class="font-bold text-slate-900 dark:text-white hover:text-indigo-600 dark:hover:text-indigo-400 block">
                                    {{ $entitlement->product?->title ?? 'Assessment Entitlement' }}
                                </a>
                                <span class="text-xs text-slate-500 font-mono">{{ $entitlement->product?->slug }}</span>
                            </td>
                            <td class="py-3.5 px-4 text-center font-bold text-slate-900 dark:text-white font-mono">
                                {{ $entitlement->total_seats }}
                            </td>
                            <td class="py-3.5 px-4 text-center font-semibold text-indigo-600 dark:text-indigo-400 font-mono">
                                {{ $allocCount }}
                            </td>
                            <td class="py-3.5 px-4 text-center font-bold {{ $availCount > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400' }} font-mono">
                                {{ $availCount }}
                            </td>
                            <td class="py-3.5 px-4">
                                @if($entitlement->status->value === 'active')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                                        Active Pool
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                                        {{ ucfirst($entitlement->status->value) }}
                                    </span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-xs font-mono text-slate-500">
                                {{ $entitlement->order?->order_number ?? '—' }}
                            </td>
                            <td class="py-3.5 px-6 text-right">
                                <a href="{{ route('organization.entitlements.show', [$organization->slug, $entitlement->id]) }}" class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-semibold bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300 hover:bg-indigo-100 dark:hover:bg-indigo-900/60 transition">
                                    Manage Allocations &rarr;
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 text-center text-slate-500 dark:text-slate-400">
                                <div class="flex flex-col items-center justify-center">
                                    <svg class="w-12 h-12 text-slate-300 dark:text-slate-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>
                                    <p class="font-medium">No seat entitlements found</p>
                                    <p class="text-xs mt-1">Purchase package seats and allocate them to eligible candidate members. Assessment access is assigned separately.</p>
                                    <a href="{{ route('organization.purchases.create', $organization->slug) }}" class="mt-4 px-4 py-2 text-xs font-semibold bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl shadow-xs transition">
                                        Purchase Seats
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($entitlements->hasPages())
            <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-800">
                {{ $entitlements->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
