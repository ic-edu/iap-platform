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

    <!-- Flash message -->
    @if(session('status'))
    <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-700 dark:text-emerald-400 text-xs font-medium flex items-center gap-2">
        <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
        </svg>
        <span>{{ session('status') }}</span>
    </div>
    @endif

    <!-- Validation Errors -->
    @if($errors->any())
    <div class="p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-700 dark:text-rose-400 text-xs space-y-1">
        @foreach($errors->all() as $error)
            <p class="flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5 text-rose-600 dark:text-rose-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <span>{{ $error }}</span>
            </p>
        @endforeach
    </div>
    @endif

    @php
        $orderStatus = is_object($order->status) ? $order->status : \App\Modules\Commerce\Domain\Enums\OrderStatus::tryFrom((string) $order->status);
        $statusValue = is_object($order->status) ? $order->status->value : (string) $order->status;
        $isPaid = $statusValue === 'paid' || $statusValue === 'completed' || $orderStatus === \App\Modules\Commerce\Domain\Enums\OrderStatus::Completed;
        $isPending = $statusValue === 'pending' || $statusValue === 'pending_payment' || $orderStatus === \App\Modules\Commerce\Domain\Enums\OrderStatus::Pending;
        $payment = $payment ?? $order->invoice?->payments()->latest()->first();
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
                @if($isPaid)
                    <span class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-xs font-bold bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 shadow-xs">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        PAID &amp; PROVISIONED
                    </span>
                @elseif($isPending)
                    <span class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-xs font-bold bg-amber-100 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300 border border-amber-200 dark:border-amber-800 shadow-xs">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        PENDING PAYMENT
                    </span>
                @elseif($statusValue === 'cancelled' || $orderStatus === \App\Modules\Commerce\Domain\Enums\OrderStatus::Cancelled)
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
                            <th class="p-4">Package</th>
                            <th class="p-4 text-center">Seat Quantity</th>
                            <th class="p-4 text-right">Unit Price</th>
                            <th class="p-4 text-right">Line Subtotal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach($order->items as $item)
                            <tr>
                                <td class="p-4">
                                    <span class="font-bold text-slate-900 dark:text-white block text-sm">{{ $item->product?->title ?? 'Package Item' }}</span>
                                    @if($item->product?->product_type)
                                        <span class="inline-block text-[10px] font-bold uppercase px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 mt-1">
                                            {{ ucfirst(str_replace('_', ' ', $item->product->product_type)) }}
                                        </span>
                                    @endif
                                </td>
                                <td class="p-4 text-center">
                                    <span class="font-bold text-slate-900 dark:text-white text-sm">{{ $item->quantity }}</span>
                                    <span class="text-[11px] text-slate-500 block">seats</span>
                                </td>
                                <td class="p-4 text-right text-slate-600 dark:text-slate-300 font-mono">
                                    Rp {{ number_format($item->price, 0, ',', '.') }}
                                </td>
                                <td class="p-4 text-right font-bold text-slate-900 dark:text-white font-mono">
                                    Rp {{ number_format($item->quantity * $item->price, 0, ',', '.') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Order Summary & Financial Calculation -->
        <div class="p-5 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-100 dark:border-slate-800 space-y-2.5 text-xs">
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-2">Order Summary</h3>

            <div class="flex justify-between text-slate-600 dark:text-slate-400">
                <span>Subtotal ({{ $order->items->sum('quantity') }} seats)</span>
                <span class="font-mono font-semibold text-slate-900 dark:text-white">Rp {{ number_format($order->subtotal, 0, ',', '.') }}</span>
            </div>

            @if($order->discount > 0 || $order->coupon)
                <div class="flex justify-between items-start text-emerald-700 dark:text-emerald-400">
                    <div>
                        <div class="font-semibold flex items-center gap-1.5">
                            <span>Voucher {{ $order->coupon?->code }}</span>
                            @if($order->coupon && $order->coupon->type === 'percentage')
                                <span>({{ $order->coupon->value }}% OFF)</span>
                            @endif
                        </div>
                        @if($order->coupon?->campaign)
                            <span class="text-[11px] text-slate-500 dark:text-slate-400 block mt-0.5">
                                Campaign: {{ $order->coupon->campaign->name }}
                            </span>
                        @endif
                    </div>
                    <span class="font-mono font-bold">-Rp {{ number_format($order->discount, 0, ',', '.') }}</span>
                </div>

                <div class="flex justify-between text-slate-600 dark:text-slate-400 pt-1 border-t border-dashed border-slate-200 dark:border-slate-800">
                    <span>Taxable Subtotal</span>
                    <span class="font-mono font-semibold text-slate-900 dark:text-white">Rp {{ number_format($order->subtotal - $order->discount, 0, ',', '.') }}</span>
                </div>
            @endif

            <div class="flex justify-between text-slate-600 dark:text-slate-400">
                <span>VAT / PPN (11%)</span>
                <span class="font-mono font-semibold text-slate-900 dark:text-white">Rp {{ number_format($order->tax, 0, ',', '.') }}</span>
            </div>

            <div class="flex justify-between pt-2.5 border-t border-slate-200 dark:border-slate-800 text-base font-black text-slate-900 dark:text-white">
                <span>Total Amount Due</span>
                <span class="font-mono text-indigo-600 dark:text-indigo-400">Rp {{ number_format($order->grand_total, 0, ',', '.') }}</span>
            </div>
        </div>

        @if($isPaid)
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
        @elseif($isPending)
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

                    <div class="p-3 bg-indigo-50/50 dark:bg-indigo-950/20 rounded-lg border border-indigo-100 dark:border-indigo-900/30 text-[11px] text-slate-600 dark:text-slate-400 space-y-1">
                        <span class="font-bold text-indigo-900 dark:text-indigo-300 block">Payment Instructions:</span>
                        <p>1. Transfer the exact amount of <strong class="text-indigo-600 dark:text-indigo-400 font-mono">Rp {{ number_format($order->grand_total, 0, ',', '.') }}</strong>.</p>
                        <p>2. Upload your transfer receipt below so Finance can verify and unlock seats.</p>
                    </div>
                </div>

                <!-- Proof Section -->
                <div class="p-5 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 space-y-4">
                    @if($payment && $payment->proof_path)
                        <!-- Post-Upload State -->
                        <div>
                            <h3 class="font-bold text-sm text-slate-900 dark:text-white">Payment Proof Uploaded</h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Evidence file is submitted for Finance verification</p>
                        </div>

                        <div class="p-4 rounded-xl bg-emerald-50/60 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-900/40 space-y-2 text-xs">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2 text-emerald-800 dark:text-emerald-300 font-bold">
                                    <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                    <span>Awaiting Finance Verification</span>
                                </div>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 dark:bg-amber-950 text-amber-800 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                                    Pending Approval
                                </span>
                            </div>
                            <p class="text-slate-600 dark:text-slate-300 font-mono text-[11px] truncate">
                                File: {{ $payment->proof_original_name ?? 'payment_proof.pdf' }}
                            </p>
                            <p class="text-slate-500 dark:text-slate-400 text-[11px]">
                                Uploaded: {{ $payment->proof_uploaded_at?->format('d M Y, H:i') ?? 'Recently' }}
                            </p>
                            @if($payment->proof_notes)
                                <p class="text-slate-700 dark:text-slate-300 mt-1.5 p-2.5 bg-white dark:bg-slate-900 rounded-lg border border-emerald-200/50 dark:border-emerald-900/30 text-[11px]">
                                    Notes: {{ $payment->proof_notes }}
                                </p>
                            @endif
                        </div>

                        <div class="flex flex-col sm:flex-row items-center gap-2 pt-1">
                            <a href="{{ route('organization.purchases.proof.view', [$organization->slug, $order]) }}" target="_blank" class="w-full sm:w-auto px-4 py-2 rounded-xl bg-slate-200 dark:bg-slate-800 hover:bg-slate-300 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 text-xs font-bold transition flex items-center justify-center gap-1.5 cursor-pointer">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                View Uploaded Proof
                            </a>

                            <button type="button" onclick="document.getElementById('replace-proof-container').classList.toggle('hidden')" class="w-full sm:w-auto px-4 py-2 rounded-xl border border-slate-300 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 text-xs font-bold transition flex items-center justify-center gap-1.5 cursor-pointer">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                Replace Proof
                            </button>
                        </div>

                        <!-- Replace Proof Collapsible Form -->
                        <div id="replace-proof-container" class="hidden pt-3 border-t border-slate-200 dark:border-slate-800 space-y-3">
                            <form method="POST" action="{{ route('organization.purchases.proof', [$organization->slug, $order]) }}" enctype="multipart/form-data" class="space-y-3">
                                @csrf
                                <div>
                                    <label for="proof_replace" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                        Select New Payment Receipt <span class="text-rose-500">*</span>
                                    </label>
                                    <input type="file" id="proof_replace" name="proof" accept=".jpeg,.png,.jpg,.pdf" required
                                           class="w-full text-xs text-slate-600 dark:text-slate-400 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 cursor-pointer">
                                    <span class="text-[11px] text-slate-400 dark:text-slate-500 block mt-1">Accepted: JPG, PNG, PDF up to 5MB</span>
                                </div>

                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label for="sender_bank_rep" class="block text-[11px] font-semibold text-slate-600 dark:text-slate-400 mb-0.5">Sender Bank</label>
                                        <input type="text" id="sender_bank_rep" name="sender_bank" placeholder="e.g. BCA / Mandiri" class="w-full px-2.5 py-1.5 text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white">
                                    </div>
                                    <div>
                                        <label for="sender_name_rep" class="block text-[11px] font-semibold text-slate-600 dark:text-slate-400 mb-0.5">Sender Name</label>
                                        <input type="text" id="sender_name_rep" name="sender_name" placeholder="e.g. Finance Team" class="w-full px-2.5 py-1.5 text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white">
                                    </div>
                                </div>

                                <div>
                                    <label for="payment_ref_rep" class="block text-[11px] font-semibold text-slate-600 dark:text-slate-400 mb-0.5">Transfer Ref / Transaction ID</label>
                                    <input type="text" id="payment_ref_rep" name="payment_reference" placeholder="e.g. TRX-928374" class="w-full px-2.5 py-1.5 text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white">
                                </div>

                                <button type="submit" class="w-full py-2.5 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-xs transition flex items-center justify-center gap-2 cursor-pointer">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                                    Update Payment Proof
                                </button>
                            </form>
                        </div>
                    @else
                        <!-- Pre-Upload State Form -->
                        <div>
                            <h3 class="font-bold text-sm text-slate-900 dark:text-white">Upload Payment Receipt</h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">No payment proof uploaded yet. JPG, PNG, or PDF up to 5MB</p>
                        </div>

                        <form method="POST" action="{{ route('organization.purchases.proof', [$organization->slug, $order]) }}" enctype="multipart/form-data" class="space-y-3">
                            @csrf

                            <div>
                                <label for="proof" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    Proof of Transfer <span class="text-rose-500">*</span>
                                </label>
                                <input type="file" id="proof" name="proof" accept=".jpeg,.png,.jpg,.pdf,image/*,application/pdf" required
                                       class="w-full text-xs text-slate-600 dark:text-slate-400 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 cursor-pointer">
                                <span class="text-[11px] text-slate-400 dark:text-slate-500 block mt-1">Accepted: JPG, PNG, PDF up to 5MB</span>
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
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
