@extends('organization::layouts.organization', [
    'title' => 'Entitlement Pool — ' . ($entitlement->product?->title ?? 'Seats'),
    'heading' => 'Seat Entitlement Allocation Pool',
    'subheading' => 'Manage candidate seat allocations for ' . ($entitlement->product?->title ?? 'Assessment')
])

@section('content')
<div class="space-y-6" x-data="{ showAllocateModal: false }">
    <!-- Breadcrumb & Back -->
    <div>
        <a href="{{ route('organization.entitlements', $organization->slug) }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-slate-900 dark:hover:text-white transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Back to Entitlements
        </a>
    </div>

    @php
        $allocCount = $entitlement->allocatedSeatsCount();
        $availCount = $entitlement->availableSeatsCount();
        $hasCapacity = $entitlement->hasAvailableSeats();
    @endphp

    <!-- Pool Summary Card -->
    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 p-6 shadow-xs space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-slate-100 dark:border-slate-800">
            <div>
                <div class="flex items-center gap-2">
                    <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Entitlement Pool</span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300">
                        {{ strtoupper($entitlement->status->value) }}
                    </span>
                </div>
                <h1 class="text-xl font-bold text-slate-900 dark:text-white mt-1">
                    {{ $entitlement->product?->title ?? 'Assessment Product' }}
                </h1>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                    Order Ref: <span class="font-mono">{{ $entitlement->order?->order_number ?? '—' }}</span> &bull; Provisioned: {{ $entitlement->created_at->format('d M Y, H:i') }}
                </p>
            </div>

            <div class="flex items-center gap-3">
                <button type="button" @click="showAllocateModal = true"
                        @if(!$hasCapacity || !$entitlement->isActive()) disabled @endif
                        class="px-4 py-2 text-sm font-semibold bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-xl shadow-xs transition flex items-center gap-2 cursor-pointer">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Allocate Seat to Candidate
                </button>
            </div>
        </div>

        <!-- Metric Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800">
                <span class="text-xs text-slate-500 dark:text-slate-400 font-medium">Total Pool Capacity</span>
                <div class="text-2xl font-black text-slate-900 dark:text-white mt-1 font-mono">{{ $entitlement->total_seats }}</div>
                <span class="text-[11px] text-slate-400">seats purchased</span>
            </div>

            <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800">
                <span class="text-xs text-slate-500 dark:text-slate-400 font-medium">Allocated Seats</span>
                <div class="text-2xl font-black text-indigo-600 dark:text-indigo-400 mt-1 font-mono">{{ $allocCount }}</div>
                <span class="text-[11px] text-slate-400">currently assigned</span>
            </div>

            <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800">
                <span class="text-xs text-slate-500 dark:text-slate-400 font-medium">Available Seats</span>
                <div class="text-2xl font-black {{ $availCount > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600' }} mt-1 font-mono">{{ $availCount }}</div>
                <span class="text-[11px] text-slate-400">seats remaining</span>
            </div>
        </div>
    </div>

    <!-- Allocations List Table -->
    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 overflow-hidden shadow-xs">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
            <h3 class="font-bold text-base text-slate-900 dark:text-white">Seat Allocations History ({{ $allocations->total() }})</h3>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="border-b border-slate-200 dark:border-slate-800 bg-slate-50/75 dark:bg-slate-950/50 text-xs font-semibold uppercase text-slate-500">
                        <th class="py-3.5 px-6">Candidate Member</th>
                        <th class="py-3.5 px-4">Member ID</th>
                        <th class="py-3.5 px-4">Status</th>
                        <th class="py-3.5 px-4">Allocated By</th>
                        <th class="py-3.5 px-4">Allocated Date</th>
                        <th class="py-3.5 px-6 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($allocations as $alloc)
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition">
                            <td class="py-3.5 px-6">
                                <div class="font-bold text-slate-900 dark:text-white">
                                    {{ $alloc->membership?->user?->name ?? 'Candidate Member' }}
                                </div>
                                <div class="text-xs text-slate-500">
                                    {{ $alloc->membership?->user?->email ?? '-' }}
                                </div>
                            </td>
                            <td class="py-3.5 px-4 font-mono text-xs text-slate-600 dark:text-slate-400">
                                {{ $alloc->membership?->member_identifier ?? '—' }}
                            </td>
                            <td class="py-3.5 px-4">
                                @if($alloc->status->value === 'active')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                                        Active Seat
                                    </span>
                                @elseif($alloc->status->value === 'released')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400">
                                        Released
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                                        {{ ucfirst($alloc->status->value) }}
                                    </span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-xs text-slate-600 dark:text-slate-400">
                                {{ $alloc->allocator?->name ?? 'Coordinator' }}
                            </td>
                            <td class="py-3.5 px-4 text-xs text-slate-500">
                                <div>{{ $alloc->allocated_at?->format('d M Y, H:i') ?? $alloc->created_at->format('d M Y, H:i') }}</div>
                                @if($alloc->released_at)
                                    <div class="text-[11px] text-slate-400">Released: {{ $alloc->released_at->format('d M Y, H:i') }}</div>
                                @endif
                            </td>
                            <td class="py-3.5 px-6 text-right">
                                @if($alloc->isActive())
                                    <form method="POST" action="{{ route('organization.entitlements.release', [$organization->slug, $entitlement->id, $alloc->id]) }}"
                                          onsubmit="event.preventDefault(); iapConfirm({ title: 'Release Seat Allocation?', message: 'Are you sure you want to release this seat allocation? The seat will be returned to the available pool.', confirmText: 'Release Seat', variant: 'danger', form: this });">
                                        @csrf
                                        <button type="submit" class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-semibold bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300 hover:bg-rose-100 dark:hover:bg-rose-900/40 transition cursor-pointer">
                                            Release Seat
                                        </button>
                                    </form>
                                @else
                                    <span class="text-xs text-slate-400 italic">Seat Returned</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-12 text-center text-slate-500 dark:text-slate-400">
                                <div class="flex flex-col items-center justify-center">
                                    <svg class="w-10 h-10 text-slate-300 dark:text-slate-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                    <p class="font-medium">No candidate seat allocations yet</p>
                                    <p class="text-xs mt-1">Click "Allocate Seat to Candidate" above to assign seats from this pool.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($allocations->hasPages())
            <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-800">
                {{ $allocations->links() }}
            </div>
        @endif
    </div>

    <!-- Allocate Modal -->
    <div x-show="showAllocateModal" style="display: none;"
         class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
        <div class="bg-white dark:bg-slate-900 rounded-2xl max-w-lg w-full p-6 space-y-6 shadow-xl border border-slate-200 dark:border-slate-800"
             @click.away="showAllocateModal = false">
            <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800">
                <div>
                    <h3 class="font-bold text-base text-slate-900 dark:text-white">Allocate Seat</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Assign 1 seat from {{ $entitlement->product?->title }}</p>
                </div>
                <button type="button" @click="showAllocateModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            @if($eligibleCandidates->isEmpty())
                <div class="p-4 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 text-amber-800 dark:text-amber-200 text-xs">
                    No eligible candidate members found. Either all candidates currently have active seats in this pool, or no active Candidate Members exist in your organization roster.
                </div>
            @else
                <form method="POST" action="{{ route('organization.entitlements.allocate', [$organization->slug, $entitlement->id]) }}" class="space-y-4">
                    @csrf

                    <div>
                        <label for="membership_id" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                            Select Candidate Member <span class="text-rose-500">*</span>
                        </label>
                        <select id="membership_id" name="membership_id" required
                                class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-white text-xs focus:outline-hidden focus:ring-2 focus:ring-indigo-500">
                            <option value="">-- Choose Candidate --</option>
                            @foreach($eligibleCandidates as $cand)
                                <option value="{{ $cand->id }}">
                                    {{ $cand->user?->name ?? 'Candidate' }} ({{ $cand->user?->email }}) {{ $cand->member_identifier ? '[' . $cand->member_identifier . ']' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="notes" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                            Allocation Notes (Optional)
                        </label>
                        <input type="text" id="notes" name="notes" placeholder="e.g. Assigned for Batch 1 testing"
                               class="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-white text-xs focus:outline-hidden focus:ring-2 focus:ring-indigo-500">
                    </div>

                    <div class="p-3.5 rounded-xl bg-indigo-50/60 dark:bg-indigo-950/30 border border-indigo-100 dark:border-indigo-900/40 text-[11px] text-indigo-900 dark:text-indigo-300">
                        ℹ️ Allocating a seat assigns 1 seat entitlement to the candidate in your organization. (O2 seat pool entitlement).
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-2">
                        <button type="button" @click="showAllocateModal = false" class="px-4 py-2 text-xs font-semibold text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition">
                            Cancel
                        </button>
                        <button type="submit" class="px-5 py-2 text-xs font-bold bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl shadow-xs transition">
                            Confirm Allocation
                        </button>
                    </div>
                </form>
            @endif
        </div>
    </div>
</div>
@endsection
