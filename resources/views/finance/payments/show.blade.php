@extends('layouts.admin')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    {{-- Breadcrumb --}}
    <div class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
        <a href="{{ route('finance.payments.pending') }}" class="hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">&larr; Back to Payment Queue</a>
        <span>/</span>
        <span class="text-slate-700 dark:text-slate-200 font-mono font-medium truncate">{{ $payment->reference_number }}</span>
    </div>

    {{-- Flash message --}}
    @if(session('status'))
    <div class="p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-700 dark:text-emerald-400 text-xs font-medium flex items-center gap-2">
        <span>✅</span> {{ session('status') }}
    </div>
    @endif

    @if($errors->any())
    <div class="p-4 rounded-2xl bg-rose-500/10 border border-rose-500/20 text-rose-700 dark:text-rose-400 text-xs space-y-1">
        @foreach($errors->all() as $error)
            <p>⚠️ {{ $error }}</p>
        @endforeach
    </div>
    @endif

    {{-- Payment Review Card --}}
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 sm:p-8 shadow-sm space-y-8">
        {{-- Header --}}
        <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4 pb-6 border-b border-slate-100 dark:border-slate-800">
            <div class="space-y-1">
                <span class="text-[10px] uppercase font-bold text-slate-400 block tracking-wider">Payment Transaction Audit</span>
                <h1 class="text-2xl font-black font-mono text-slate-900 dark:text-white">{{ $payment->reference_number }}</h1>
                <p class="text-xs text-slate-500 dark:text-slate-400">Gateway: {{ strtoupper(str_replace('_', ' ', $payment->payment_gateway)) }} &bull; Initiated {{ $payment->created_at?->format('d M Y, H:i') }}</p>
            </div>
            <div class="flex items-center gap-3">
                <x-status-badge :status="$payment->status" />
            </div>
        </div>

        {{-- Metadata Grid --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 text-xs p-5 rounded-2xl bg-slate-50 dark:bg-slate-950/60 border border-slate-100 dark:border-slate-800">
            <div>
                <span class="text-slate-400 uppercase font-bold text-[10px] block tracking-wider">Candidate / Payer</span>
                @if($payment->invoice?->order?->organization)
                    <div class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-[10px] font-bold bg-indigo-100 dark:bg-indigo-950/60 text-indigo-800 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 mt-1 mb-0.5">
                        <span>Institution: {{ $payment->invoice->order->organization->name }}</span>
                    </div>
                @endif
                <span class="font-bold text-slate-900 dark:text-white text-sm block mt-1">{{ $payment->user?->name ?? 'Candidate' }}</span>
                <span class="text-slate-500 dark:text-slate-400 font-mono">{{ $payment->user?->email }}</span>
            </div>
            <div>
                <span class="text-slate-400 uppercase font-bold text-[10px] block tracking-wider">Total Amount</span>
                <span class="font-black text-indigo-600 dark:text-indigo-400 text-lg block mt-1">IDR {{ number_format($payment->amount) }}</span>
                @if($payment->invoice)
                <span class="text-slate-500 dark:text-slate-400 font-mono">Invoice: {{ $payment->invoice->invoice_number }}</span>
                @endif
            </div>
            <div>
                <span class="text-slate-400 uppercase font-bold text-[10px] block tracking-wider">Transaction ID</span>
                <span class="font-mono font-bold text-slate-900 dark:text-white text-sm block mt-1">{{ $payment->transaction_id ?? 'Awaiting Confirmation' }}</span>
                <span class="text-slate-500 dark:text-slate-400">Confirmed: {{ $payment->confirmed_at?->format('d M Y, H:i') ?? 'Pending' }}</span>
            </div>
        </div>

        {{-- Purchased Items Summary --}}
        <div class="space-y-3">
            <h2 class="text-xs font-bold uppercase tracking-wider text-slate-400">Order Package Inclusions &amp; Seat Quantities</h2>
            <div class="border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-950 text-slate-500 dark:text-slate-400 font-bold border-b border-slate-200 dark:border-slate-800">
                        <tr>
                            <th class="p-4">Item Title</th>
                            <th class="p-4 text-center">Type</th>
                            <th class="p-4 text-center">Quantity / Seats</th>
                            <th class="p-4 text-right">Price</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
                        @if($payment->invoice?->order)
                            @foreach($payment->invoice->order->items as $item)
                            <tr>
                                <td class="p-4">
                                    <span class="font-bold text-slate-900 dark:text-white block">{{ $item->product?->title ?? $item->product_name ?? 'Package Item' }}</span>
                                    <span class="text-[11px] text-slate-500 dark:text-slate-400 font-mono">{{ $item->product?->slug ?? $item->product_id }}</span>
                                </td>
                                <td class="p-4 text-center">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-indigo-50 dark:bg-indigo-950/50 text-indigo-700 dark:text-indigo-300">
                                        {{ $item->product?->product_type ?? 'Package' }}
                                    </span>
                                </td>
                                <td class="p-4 text-center font-bold text-slate-900 dark:text-white font-mono">
                                    {{ $item->quantity }}
                                </td>
                                <td class="p-4 text-right font-bold text-slate-900 dark:text-white">IDR {{ number_format($item->total) }}</td>
                            </tr>
                            @endforeach
                        @endif
                    </tbody>
                    @if($payment->invoice?->order)
                    <tfoot class="bg-slate-50 dark:bg-slate-950 border-t border-slate-200 dark:border-slate-800 text-xs">
                        <tr>
                            <td colspan="3" class="p-3.5 text-right font-bold text-slate-500 dark:text-slate-400">Subtotal</td>
                            <td class="p-3.5 text-right font-bold text-slate-900 dark:text-white font-mono">IDR {{ number_format($payment->invoice->order->subtotal) }}</td>
                        </tr>
                        @if($payment->invoice->order->discount > 0 || $payment->invoice->order->coupon)
                        <tr class="bg-emerald-50/50 dark:bg-emerald-950/20 text-emerald-800 dark:text-emerald-300">
                            <td colspan="3" class="p-3.5 text-right font-bold">
                                <div class="flex items-center justify-end gap-2">
                                    <span>Promotional Voucher Discount</span>
                                    @if($payment->invoice->order->coupon)
                                    <span class="px-2 py-0.5 rounded bg-emerald-100 dark:bg-emerald-900/60 font-mono font-bold text-[11px] text-emerald-900 dark:text-emerald-200 border border-emerald-300 dark:border-emerald-800">
                                        {{ $payment->invoice->order->coupon->code }}
                                        @if($payment->invoice->order->coupon->campaign)
                                        ({{ $payment->invoice->order->coupon->campaign->name }})
                                        @endif
                                    </span>
                                    @endif
                                </div>
                            </td>
                            <td class="p-3.5 text-right font-bold font-mono">- IDR {{ number_format($payment->invoice->order->discount) }}</td>
                        </tr>
                        @endif
                        <tr>
                            <td colspan="3" class="p-3.5 text-right font-bold text-slate-500 dark:text-slate-400">VAT (11%)</td>
                            <td class="p-3.5 text-right font-bold text-slate-900 dark:text-white font-mono">IDR {{ number_format($payment->invoice->order->tax) }}</td>
                        </tr>
                        <tr class="border-t border-slate-200 dark:border-slate-800 font-bold bg-slate-100/50 dark:bg-slate-900/50">
                            <td colspan="3" class="p-4 text-right text-slate-900 dark:text-white text-sm">Grand Total (Settlement)</td>
                            <td class="p-4 text-right text-indigo-600 dark:text-indigo-400 font-black text-sm font-mono">IDR {{ number_format($payment->invoice->order->grand_total) }}</td>
                        </tr>
                    </tfoot>
                    @endif
                </table>
            </div>
        </div>

        {{-- Payment Evidence Verification Section --}}
        <div class="space-y-3">
            <h2 class="text-xs font-bold uppercase tracking-wider text-slate-400">Payment Evidence &amp; Transfer Slip</h2>
            @if($payment->proof_path)
            <div class="p-5 rounded-2xl bg-emerald-50/50 dark:bg-emerald-950/20 border border-emerald-200 dark:border-emerald-900/40 space-y-3">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2 text-emerald-800 dark:text-emerald-300 font-bold text-xs">
                            <span>✓</span>
                            <span>Proof Document Attached: {{ $payment->proof_original_name ?? 'proof.pdf' }}</span>
                        </div>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400">Uploaded on {{ $payment->proof_uploaded_at?->format('d M Y, H:i') }}</p>
                    </div>
                    <a href="{{ route('finance.payments.proof', $payment->id) }}" target="_blank" class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs transition-all shadow-md shadow-emerald-600/20 flex items-center gap-1.5 self-start sm:self-auto">
                        <span>🔍 Open Document</span>
                        <span>&rarr;</span>
                    </a>
                </div>
                @if($payment->proof_notes)
                <div class="p-3 bg-white dark:bg-slate-900 rounded-xl border border-emerald-200/60 dark:border-emerald-900/40 text-xs text-slate-700 dark:text-slate-300">
                    <span class="font-bold text-slate-900 dark:text-white block mb-0.5">Candidate Notes:</span>
                    <p>{{ $payment->proof_notes }}</p>
                </div>
                @endif
            </div>
            @else
            <div class="p-5 rounded-2xl bg-amber-50/50 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-900/40 text-xs text-amber-800 dark:text-amber-300 flex items-center gap-2">
                <span>⚠️</span>
                <span>No bank transfer receipt has been uploaded by the candidate yet. Verification can proceed if transfer was verified directly via bank mutations.</span>
            </div>
            @endif
        </div>

        {{-- Finance Action Decision Panel --}}
        @if((is_object($payment->status) ? $payment->status->value === 'pending' : $payment->status === 'pending'))
        <div class="pt-6 border-t border-slate-200 dark:border-slate-800 space-y-4">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider">Finance Decision Actions</h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                {{-- Approve Form --}}
                <div class="p-5 rounded-2xl bg-emerald-50/50 dark:bg-emerald-950/20 border border-emerald-200 dark:border-emerald-900/40 space-y-3">
                    <div class="flex items-center gap-2">
                        <span class="text-base">✅</span>
                        <h3 class="text-xs font-bold uppercase tracking-wider text-emerald-900 dark:text-emerald-300">Confirm &amp; Approve Payment</h3>
                    </div>
                    <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
                        Approving sets Payment to <strong>Success</strong>, Invoice to <strong>Paid</strong>, Order to <strong>Completed</strong>, and activates the candidate as <strong>Paid &amp; Eligible</strong>.
                    </p>

                    <form action="{{ route('finance.payments.approve', $payment->id) }}" method="POST" class="space-y-3 pt-2">
                        @csrf
                        <div>
                            <label for="transaction_id" class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 mb-1">Bank Reference / TXN ID (Optional)</label>
                            <input type="text" name="transaction_id" id="transaction_id" placeholder="e.g. BCA-9821387" class="w-full text-xs bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-800 rounded-xl p-2.5 text-slate-900 dark:text-white placeholder:text-slate-400 focus:outline-none focus:border-emerald-500">
                        </div>

                        <button type="submit" class="w-full py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs transition-all shadow-md shadow-emerald-600/20 cursor-pointer">
                            ✓ Approve Payment &amp; Grant Eligibility
                        </button>
                    </form>
                </div>

                {{-- Reject Form --}}
                <div class="p-5 rounded-2xl bg-rose-50/50 dark:bg-rose-950/20 border border-rose-200 dark:border-rose-900/40 space-y-3">
                    <div class="flex items-center gap-2">
                        <span class="text-base">❌</span>
                        <h3 class="text-xs font-bold uppercase tracking-wider text-rose-900 dark:text-rose-300">Reject / Cancel Payment</h3>
                    </div>
                    <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
                        Rejecting marks the Payment as <strong>Failed</strong> and cancels the invoice and order. A clear audit reason is required.
                    </p>

                    <form action="{{ route('finance.payments.reject', $payment->id) }}" method="POST" class="space-y-3 pt-2">
                        @csrf
                        <div>
                            <label for="reason" class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 mb-1">Rejection Reason (Required)</label>
                            <input type="text" name="reason" id="reason" required placeholder="e.g. Invalid transfer slip / amount mismatch" class="w-full text-xs bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-800 rounded-xl p-2.5 text-slate-900 dark:text-white placeholder:text-slate-400 focus:outline-none focus:border-rose-500">
                        </div>

                        <button type="submit" class="w-full py-2.5 rounded-xl bg-rose-600 hover:bg-rose-500 text-white font-bold text-xs transition-all shadow-md shadow-rose-600/20 cursor-pointer">
                            ✗ Reject &amp; Cancel Payment
                        </button>
                    </form>
                </div>
            </div>
        </div>
        @else
        <div class="pt-6 border-t border-slate-200 dark:border-slate-800 p-5 rounded-2xl bg-slate-50 dark:bg-slate-950/60 border border-slate-100 dark:border-slate-800 flex items-center justify-between gap-4">
            <div>
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400">Settlement Status</h3>
                <p class="text-sm font-bold text-slate-900 dark:text-white mt-0.5">
                    This transaction was settled on {{ $payment->confirmed_at?->format('d M Y, H:i') ?? $payment->updated_at?->format('d M Y, H:i') }}.
                </p>
            </div>
            <x-status-badge :status="$payment->status" />
        </div>
        @endif
    </div>
</div>
@endsection
