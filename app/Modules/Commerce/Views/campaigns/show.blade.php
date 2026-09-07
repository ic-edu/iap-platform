@extends('layouts.admin')

@section('content')
<div class="max-w-6xl mx-auto space-y-6">
    <!-- Breadcrumbs -->
    <div class="flex items-center gap-2 text-xs text-slate-400">
        <a href="{{ route('admin.commerce.index') }}" class="hover:text-indigo-400 transition">&larr; Commercial Catalog</a>
        <span>/</span>
        <span class="text-white font-medium">{{ $campaign->name }}</span>
    </div>

    <!-- Status Alert -->
    @if (session('status'))
        <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-medium flex items-center gap-2">
            <svg class="w-4 h-4 text-emerald-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <!-- Campaign Header & Actions -->
    <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 space-y-6 shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4 pb-6 border-b border-slate-800">
            <div class="space-y-1.5">
                <div class="flex items-center gap-2">
                    <span class="px-2.5 py-0.5 rounded text-[10px] font-extrabold uppercase bg-indigo-500/20 text-indigo-300 border border-indigo-500/30">
                        {{ strtoupper($campaign->assessment_family) }} Family
                    </span>
                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $campaign->is_active ? 'bg-emerald-500/20 text-emerald-300' : 'bg-rose-500/20 text-rose-300' }}">
                        {{ $campaign->getEffectiveState() }}
                    </span>
                </div>
                <h1 class="text-2xl font-black text-white">{{ $campaign->name }}</h1>
                <p class="text-xs text-slate-400">Created by {{ $campaign->creator?->name ?? 'System' }} on {{ $campaign->created_at->format('d M Y, H:i') }}</p>
            </div>

            <div class="flex items-center gap-3">
                <form action="{{ route('admin.commerce.campaigns.toggle', $campaign->id) }}" method="POST">
                    @csrf
                    <button type="submit" class="px-4 py-2 rounded-xl text-xs font-bold transition {{ $campaign->is_active ? 'bg-amber-600/20 text-amber-300 hover:bg-amber-600/30' : 'bg-emerald-600 text-white hover:bg-emerald-500' }} cursor-pointer">
                        {{ $campaign->is_active ? 'Deactivate Campaign' : 'Activate Campaign' }}
                    </button>
                </form>

                <form id="archive-campaign-form" action="{{ route('admin.commerce.campaigns.destroy', $campaign->id) }}" method="POST">
                    @csrf
                    @method('DELETE')
                    <button type="button" onclick="confirmArchiveCampaign()" class="px-4 py-2 rounded-xl text-xs font-bold bg-rose-600/20 text-rose-300 hover:bg-rose-600/30 transition cursor-pointer">
                        Archive Campaign
                    </button>
                </form>
            </div>
        </div>

        <!-- Metric Cards Grid -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs">
            <div class="p-4 rounded-2xl bg-slate-950 border border-slate-800 space-y-1">
                <span class="text-slate-400 uppercase font-bold text-[10px]">Generated Codes</span>
                <span class="text-xl font-black text-white block">{{ number_format($totalCodes) }}</span>
                <span class="text-[11px] text-slate-500">{{ $activeCodes }} currently active</span>
            </div>

            <div class="p-4 rounded-2xl bg-slate-950 border border-slate-800 space-y-1">
                <span class="text-slate-400 uppercase font-bold text-[10px]">Discount Policy</span>
                <span class="text-xl font-black text-indigo-400 block">
                    {{ $campaign->discount_type === 'percentage' ? number_format($campaign->discount_value, 0) . '%' : 'IDR ' . number_format($campaign->discount_value) }} OFF
                </span>
                <span class="text-[11px] text-slate-500">{{ ucfirst($campaign->discount_type) }} discount</span>
            </div>

            <div class="p-4 rounded-2xl bg-slate-950 border border-slate-800 space-y-1">
                <span class="text-slate-400 uppercase font-bold text-[10px]">Consumed Uses</span>
                <span class="text-xl font-black text-emerald-400 block">{{ number_format($totalConsumed) }}</span>
                <span class="text-[11px] text-slate-500">Confirmed redemptions</span>
            </div>

            <div class="p-4 rounded-2xl bg-slate-950 border border-slate-800 space-y-1">
                <span class="text-slate-400 uppercase font-bold text-[10px]">Active Reservations</span>
                <span class="text-xl font-black text-amber-400 block">{{ number_format($totalReserved) }}</span>
                <span class="text-[11px] text-slate-500">Pending checkout hold</span>
            </div>
        </div>

        <!-- Scope & Validity Information -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs p-4 rounded-2xl bg-slate-950 border border-slate-800">
            <div>
                <span class="text-[10px] uppercase font-bold text-slate-400 block tracking-wider">Product Scope</span>
                <span class="font-bold text-slate-200 mt-1 block">
                    {{ $campaign->scope_mode === 'all_products_in_family' ? 'All Packages in ' . strtoupper($campaign->assessment_family) . ' Family' : 'Selected Packages (' . $campaign->products->count() . ' items)' }}
                </span>
                @if($campaign->scope_mode === 'selected_products')
                    <div class="flex flex-wrap gap-1.5 mt-2">
                        @foreach($campaign->products as $p)
                            <span class="px-2 py-0.5 rounded bg-slate-900 border border-slate-800 text-[11px] text-slate-300">{{ $p->title }}</span>
                        @endforeach
                    </div>
                @endif
            </div>

            <div>
                <span class="text-[10px] uppercase font-bold text-slate-400 block tracking-wider">Validity Period</span>
                <span class="font-bold text-slate-200 mt-1 block font-mono">
                    {{ $campaign->valid_from ? $campaign->valid_from->format('d M Y, H:i') : 'Immediate' }} &rarr; {{ $campaign->valid_until ? $campaign->valid_until->format('d M Y, H:i') : 'Unlimited' }}
                </span>
            </div>
        </div>
    </div>

    <!-- Generated Voucher Codes Table -->
    <div class="bg-slate-900 border border-slate-800 rounded-3xl overflow-hidden shadow-sm space-y-4 p-6">
        <div class="flex items-center justify-between pb-4 border-b border-slate-800">
            <div>
                <h2 class="text-base font-bold text-white">Generated Voucher Codes</h2>
                <p class="text-xs text-slate-400 mt-0.5">Authoritative codes generated for this campaign available for candidate and institutional checkout.</p>
            </div>
            <span class="text-xs text-slate-400 font-mono">Count: {{ $coupons->total() }}</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-300">
                <thead class="bg-slate-950 uppercase text-slate-400 border-b border-slate-800">
                    <tr>
                        <th class="p-4 font-bold">Voucher Code</th>
                        <th class="p-4 text-center font-bold">Status</th>
                        <th class="p-4 text-center font-bold">Consumed</th>
                        <th class="p-4 text-center font-bold">Reserved</th>
                        <th class="p-4 text-center font-bold">Capacity Limit</th>
                        <th class="p-4 text-center font-bold">Available Uses</th>
                        <th class="p-4 text-right font-bold">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/80 font-mono">
                    @forelse($coupons as $coupon)
                        <tr>
                            <td class="p-4 font-bold text-white text-sm">
                                <span>{{ $coupon->code }}</span>
                            </td>
                            <td class="p-4 text-center font-sans">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $coupon->is_active ? 'bg-emerald-500/20 text-emerald-300' : 'bg-rose-500/20 text-rose-300' }}">
                                    {{ $coupon->getEffectiveState() }}
                                </span>
                            </td>
                            <td class="p-4 text-center font-bold text-emerald-400">
                                {{ $coupon->used_count }}
                            </td>
                            <td class="p-4 text-center font-bold text-amber-400">
                                {{ $coupon->reserved_count ?? 0 }}
                            </td>
                            <td class="p-4 text-center text-slate-400">
                                {{ $coupon->usage_limit }}
                            </td>
                            <td class="p-4 text-center font-bold text-indigo-400">
                                {{ $coupon->getAvailableUses() }}
                            </td>
                            <td class="p-4 text-right font-sans">
                                <form action="{{ route('admin.commerce.vouchers.toggle', $coupon->id) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" class="text-xs font-semibold {{ $coupon->is_active ? 'text-amber-400 hover:text-amber-300' : 'text-emerald-400 hover:text-emerald-300' }} cursor-pointer">
                                        {{ $coupon->is_active ? 'Disable' : 'Enable' }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-6 text-center text-slate-500 font-sans">
                                No codes generated for this campaign.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($coupons->hasPages())
            <div class="pt-4 border-t border-slate-800">
                {{ $coupons->links() }}
            </div>
        @endif
    </div>
</div>

<script>
    function confirmArchiveCampaign() {
        const form = document.getElementById('archive-campaign-form');
        if (!form) return;

        if (typeof window.iapConfirm === 'function') {
            window.iapConfirm({
                title: 'Archive Promotional Campaign',
                message: 'Are you sure you want to archive campaign "{{ $campaign->name }}"? All generated codes will be deactivated, but historical redemptions will be preserved for audit.',
                confirmText: 'Archive Campaign',
                cancelText: 'Cancel',
                variant: 'danger',
                form: form
            });
        } else {
            form.submit();
        }
    }
</script>
@endsection
