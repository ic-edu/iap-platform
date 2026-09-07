@extends('organization::layouts.organization', [
    'title' => 'Purchases & Orders',
    'heading' => 'Institutional Purchases & Billing',
    'subheading' => 'Order assessment seat packages, manage invoices, and upload payment receipts'
])

@section('content')
<div class="space-y-6">
    <!-- Action Bar -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-xs">
        <div>
            <h2 class="text-base font-bold text-slate-900 dark:text-white">Purchase Orders</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400">Institutional orders made for {{ $organization->name }}</p>
        </div>

        <a href="{{ route('organization.purchases.create', $organization->slug) }}" class="w-full sm:w-auto px-4 py-2 text-sm font-semibold bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl shadow-xs transition flex items-center justify-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Purchase Seats
        </a>
    </div>

    <!-- Orders Table -->
    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 overflow-hidden shadow-xs">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
            <h3 class="font-bold text-base text-slate-900 dark:text-white">Order History ({{ $orders->total() }})</h3>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="border-b border-slate-200 dark:border-slate-800 bg-slate-50/75 dark:bg-slate-950/50 text-xs font-semibold uppercase text-slate-500">
                        <th class="py-3.5 px-6">Order #</th>
                        <th class="py-3.5 px-4">Items / Description</th>
                        <th class="py-3.5 px-4">Total Seats</th>
                        <th class="py-3.5 px-4">Total Amount</th>
                        <th class="py-3.5 px-4">Status</th>
                        <th class="py-3.5 px-4">Created Date</th>
                        <th class="py-3.5 px-6 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($orders as $order)
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition">
                            <td class="py-3.5 px-6 font-mono text-xs font-semibold text-slate-900 dark:text-white">
                                <a href="{{ route('organization.purchases.show', [$organization->slug, $order]) }}" class="hover:text-indigo-600 dark:hover:text-indigo-400">
                                    {{ $order->order_number }}
                                </a>
                            </td>
                            <td class="py-3.5 px-4">
                                <div class="text-sm font-medium text-slate-900 dark:text-white">
                                    {{ $order->items->first()?->product_name ?? 'Assessment Seats' }}
                                    @if($order->items->count() > 1)
                                        <span class="text-xs text-slate-500">+{{ $order->items->count() - 1 }} more</span>
                                    @endif
                                </div>
                            </td>
                            <td class="py-3.5 px-4 font-semibold text-slate-900 dark:text-white">
                                {{ $order->items->sum('quantity') }} seats
                            </td>
                            <td class="py-3.5 px-4 font-bold text-slate-900 dark:text-white">
                                Rp {{ number_format($order->grand_total, 0, ',', '.') }}
                            </td>
                            <td class="py-3.5 px-4">
                                @php
                                    $statusValue = is_object($order->status) ? $order->status->value : $order->status;
                                @endphp
                                @if($statusValue === 'paid' || $statusValue === 'completed')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                                        Paid &amp; Provisioned
                                    </span>
                                @elseif($statusValue === 'pending_payment' || $statusValue === 'pending')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                                        Pending Payment
                                    </span>
                                @elseif($statusValue === 'cancelled')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-rose-100 dark:bg-rose-950/60 text-rose-800 dark:text-rose-300 border border-rose-200 dark:border-rose-800">
                                        Cancelled
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                                        {{ ucfirst($statusValue) }}
                                    </span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-xs text-slate-500">
                                {{ $order->created_at->format('M d, Y H:i') }}
                            </td>
                            <td class="py-3.5 px-6 text-right">
                                <a href="{{ route('organization.purchases.show', [$organization->slug, $order]) }}" class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-semibold bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 transition">
                                    View Invoice &rarr;
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 text-center text-slate-500 dark:text-slate-400">
                                <div class="flex flex-col items-center justify-center">
                                    <svg class="w-12 h-12 text-slate-300 dark:text-slate-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                                    <p class="font-medium">No purchase orders found</p>
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

        @if($orders->hasPages())
            <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-800">
                {{ $orders->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
