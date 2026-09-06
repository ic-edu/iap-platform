@extends('organization::layouts.organization', [
    'title' => 'Order ' . $order->order_number,
    'heading' => 'Institutional Order & Invoice',
    'subheading' => 'Order reference ' . $order->order_number . ' for ' . $organization->name
])

@section('content')
<div class="max-w-4xl space-y-6">
    <!-- Breadcrumb & Back -->
    <div>
        <a href="{{ route('organization.purchases', $organization->slug) }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-slate-900 dark:hover:text-white transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Back to Orders
        </a>
    </div>

    @php
        $statusValue = is_object($order->status) ? $order->status->value : $order->status;
    @endphp

    <!-- Order Header Card -->
    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 p-6 sm:p-8 shadow-xs space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-slate-100 dark:border-slate-800">
            <div>
                <span class="text-[10px] uppercase font-bold text-slate-400 block tracking-wider">Institutional Order Reference</span>
                <h1 class="text-2xl font-black font-mono text-slate-900 dark:text-white mt-0.5">{{ $order->order_number }}</h1>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                    Placed on {{ $order->created_at->format('d F Y, H:i') }} &bull; Purchaser: {{ $order->user?->name ?? 'Coordinator' }} ({{ $order->user?->email }})
                </p>
            </div>
            <div>
                @if($statusValue === 'paid')
                    <span class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-xs font-bold bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 shadow-xs">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        PAID &amp; PROVISIONED
                    </span>
                @elseif($statusValue === 'pending_payment')
                    <span class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-xs font-bold bg-amber-100 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300 border border-amber-200 dark:border-amber-800 shadow-xs">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        PENDING PAYMENT
                    </span>
                @elseif($statusValue === 'cancelled')
                    <span class="inline-flex items-center px-3.5 py-1.5 rounded-full text-xs font-bold bg-rose-100 dark:bg-rose-950/60 text-rose-800 dark:text-rose-300 border border-rose-200 dark:border-rose-800 shadow-xs">
                        CANCELLED
                    </span>
                @else
                    <span class="inline-flex items-center px-3.5 py-1.5 rounded-full text-xs font-bold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                        {{ strtoupper($statusValue) }}
                    </span>
                @endif
            </div>
        </div>

        <!-- Ordered Items Table -->
        <div class="space-y-3">
            <h2 class="text-xs font-bold uppercase tracking-wider text-slate-400">Order Items &amp; Seat Capacity</h2>
            <div class="border border-slate-200 dark:border-slate-800 rounded-xl overflow-hidden">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-950 text-slate-500 dark:text-slate-400 font-bold border-b border-slate-200 dark:border-slate-800">
                        <tr>
                            <th class="p-4">Assessment Package</th>
                            <th class="p-4 text-center">Allocated Seats</th>
                            <th class="p-4 text-right">Unit Price</th>
                            <th class="p-4 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach($order->items as $item)
                            <tr>
                                <td class="p-4">
                                    <span class="font-bold text-slate-900 dark:text-white block text-sm">{{ $item->product_name }}</span>
                                    <span class="text-[11px] text-slate-500 dark:text-slate-400 font-mono">Product ID: {{ $item->product_id }}</span>
                                </td>
                                <td class="p-4 text-center">
                                    <span class="font-bold text-slate-900 dark:text-white text-sm">{{ $item->quantity }}</span>
                                    <span class="text-[11px] text-slate-500 block">candidate seats</span>
                                </td>
                                <td class="p-4 text-right text-slate-600 dark:text-slate-300 font-mono">
                                    Rp {{ number_format($item->price, 0, ',', '.') }}
                                </td>
                                <td class="p-4 text-right font-bold text-slate-900 dark:text-white font-mono">
                                    Rp {{ number_format($item->total, 0, ',', '.') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Financial Calculation -->
        <div class="p-5 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-100 dark:border-slate-800 space-y-2 text-xs">
            <div class="flex justify-between text-slate-600 dark:text-slate-400">
                <span>Subtotal ({{ $order->items->sum('quantity') }} seats)</span>
                <span class="font-mono font-semibold text-slate-900 dark:text-white">Rp {{ number_format($order->subtotal, 0, ',', '.') }}</span>
            </div>
            @if($order->tax > 0)
                <div class="flex justify-between text-slate-600 dark:text-slate-400">
                    <span>Tax (PPN 11%)</span>
                    <span class="font-mono font-semibold text-slate-900 dark:text-white">Rp {{ number_format($order->tax, 0, ',', '.') }}</span>
                </div>
            @endif
            <div class="flex justify-between pt-2 border-t border-slate-200 dark:border-slate-800 text-base font-black text-slate-900 dark:text-white">
                <span>Total Amount Due</span>
                <span class="font-mono text-indigo-600 dark:text-indigo-400">Rp {{ number_format($order->grand_total, 0, ',', '.') }}</span>
            </div>
        </div>

        @if($statusValue === 'paid')
            <!-- Success / Entitlement Provisioned Banner -->
            <div class="p-5 rounded-xl bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800 flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-emerald-600 text-white flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <div>
                        <h3 class="font-bold text-sm text-emerald-950 dark:text-emerald-200">Seat Pools Provisioned &amp; Ready</h3>
                        <p class="text-xs text-emerald-700 dark:text-emerald-400 mt-0.5">
                            {{ $order->items->sum('quantity') }} seats have been credited to your organization's entitlement pool.
                        </p>
                    </div>
                </div>
                <a href="{{ route('organization.entitlements', $organization->slug) }}" class="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition shadow-xs shrink-0">
                    Go to Seat Entitlements &rarr;
                </a>
            </div>
        @elseif($statusValue === 'pending_payment')
            <!-- Payment Instructions & Upload Proof -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-2">
                <!-- Payment Instructions -->
                <div class="p-5 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 space-y-4">
                    <div>
                        <h3 class="font-bold text-sm text-slate-900 dark:text-white">Bank Transfer Details</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Please transfer the exact total amount to:</p>
                    </div>

                    <div class="space-y-2 text-xs">
                        <div class="p-3 bg-white dark:bg-slate-900 rounded-lg border border-slate-200 dark:border-slate-800 space-y-1">
                            <span class="text-[10px] uppercase font-bold text-slate-400 block">Bank Name</span>
                            <span class="font-bold text-slate-900 dark:text-white">Bank Mandiri (PT iC.edu Indonesia)</span>
                        </div>
                        <div class="p-3 bg-white dark:bg-slate-900 rounded-lg border border-slate-200 dark:border-slate-800 space-y-1">
                            <span class="text-[10px] uppercase font-bold text-slate-400 block">Account Number</span>
                            <span class="font-bold font-mono text-sm text-indigo-600 dark:text-indigo-400">131-00-1928374-6</span>
                        </div>
                        <div class="p-3 bg-white dark:bg-slate-900 rounded-lg border border-slate-200 dark:border-slate-800 space-y-1">
                            <span class="text-[10px] uppercase font-bold text-slate-400 block">Payment Reference / Note</span>
                            <span class="font-bold font-mono text-xs text-slate-900 dark:text-white">{{ $order->order_number }}</span>
                        </div>
                    </div>

                    <p class="text-[11px] text-slate-500 dark:text-slate-400">
                        ⚡ After transferring, upload your payment receipt below so Finance can verify and unlock seats.
                    </p>
                </div>

                <!-- Proof Upload Form -->
                <div class="p-5 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 space-y-4">
                    <div>
                        <h3 class="font-bold text-sm text-slate-900 dark:text-white">Upload Payment Receipt</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">JPG, PNG, or PDF up to 5MB</p>
                    </div>

                    <form method="POST" action="{{ route('organization.purchases.proof', [$organization->slug, $order]) }}" enctype="multipart/form-data" class="space-y-3">
                        @csrf

                        <div>
                            <label for="payment_proof" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                Proof of Transfer <span class="text-rose-500">*</span>
                            </label>
                            <input type="file" id="payment_proof" name="payment_proof" accept="image/*,application/pdf" required
                                   class="w-full text-xs text-slate-600 dark:text-slate-400 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 cursor-pointer">
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label for="sender_bank" class="block text-[11px] font-semibold text-slate-600 dark:text-slate-400 mb-0.5">Sender Bank</label>
                                <input type="text" id="sender_bank" name="sender_bank" placeholder="e.g. BCA / Mandiri" class="w-full px-2.5 py-1.5 text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white">
                            </div>
                            <div>
                                <label for="sender_name" class="block text-[11px] font-semibold text-slate-600 dark:text-slate-400 mb-0.5">Sender Name</label>
                                <input type="text" id="sender_name" name="sender_name" placeholder="e.g. Finance Team" class="w-full px-2.5 py-1.5 text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white">
                            </div>
                        </div>

                        <div>
                            <label for="payment_reference" class="block text-[11px] font-semibold text-slate-600 dark:text-slate-400 mb-0.5">Transfer Ref / Transaction ID</label>
                            <input type="text" id="payment_reference" name="payment_reference" placeholder="e.g. TRX-928374" class="w-full px-2.5 py-1.5 text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white">
                        </div>

                        <button type="submit" class="w-full py-2.5 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-xs transition flex items-center justify-center gap-2 cursor-pointer">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                            Submit Payment Proof
                        </button>
                    </form>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
