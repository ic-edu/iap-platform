<x-candidate-layout>
    <div class="max-w-4xl mx-auto space-y-6">
        {{-- Breadcrumb --}}
        <div class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
            <a href="{{ route('candidate.invoices.index') }}" class="hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">&larr; Back to Invoices</a>
            <span>/</span>
            <span class="text-slate-700 dark:text-slate-200 font-mono font-medium truncate">{{ $invoice->invoice_number }}</span>
        </div>

        {{-- Flash message --}}
        @if(session('status'))
        <div class="p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-700 dark:text-emerald-400 text-xs font-medium flex items-center gap-2">
            <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            <span>{{ session('status') }}</span>
        </div>
        @endif

        {{-- Invoice Receipt Card --}}
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 sm:p-10 shadow-sm space-y-8">
            {{-- Header --}}
            <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-6 pb-6 border-b border-slate-100 dark:border-slate-800">
                <div class="space-y-2">
                    <div class="flex items-center gap-2">
                        <span class="w-7 h-7 rounded-lg bg-indigo-600 flex items-center justify-center font-bold text-white text-xs">iC</span>
                        <span class="font-black text-slate-900 dark:text-white tracking-wider text-base">iC.edu Institute</span>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Assessment Platform &amp; Certification Services</p>
                </div>
                <div class="sm:text-right space-y-1">
                    <span class="text-[10px] uppercase font-bold text-slate-400 block tracking-wider">Tax Invoice</span>
                    <h1 class="text-xl sm:text-2xl font-black font-mono text-slate-900 dark:text-white">{{ $invoice->invoice_number }}</h1>
                    <div class="pt-1">
                        <x-status-badge :status="$invoice->status" />
                    </div>
                </div>
            </div>

            {{-- Metadata Grid --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 text-xs p-5 rounded-2xl bg-slate-50 dark:bg-slate-950/60 border border-slate-100 dark:border-slate-800">
                <div>
                    <span class="text-slate-400 uppercase font-bold text-[10px] block tracking-wider">Billed To</span>
                    <span class="font-bold text-slate-900 dark:text-white text-sm block mt-1">{{ Auth::user()->name }}</span>
                    <span class="text-slate-500 dark:text-slate-400 font-mono">{{ Auth::user()->email }}</span>
                </div>
                <div>
                    <span class="text-slate-400 uppercase font-bold text-[10px] block tracking-wider">Order Reference</span>
                    @if($invoice->order)
                    <a href="{{ route('candidate.orders.show', $invoice->order->id) }}" class="font-bold font-mono text-indigo-600 dark:text-indigo-400 text-sm block mt-1 hover:underline">
                        {{ $invoice->order->order_number }}
                    </a>
                    <span class="text-slate-500 dark:text-slate-400">{{ $invoice->order->created_at?->format('d M Y') }}</span>
                    @endif
                </div>
                <div>
                    <span class="text-slate-400 uppercase font-bold text-[10px] block tracking-wider">Payment Due</span>
                    <span class="font-bold text-slate-900 dark:text-white text-sm block mt-1">{{ $invoice->due_date?->format('d F Y, H:i') ?? 'Immediate' }}</span>
                    <span class="text-slate-500 dark:text-slate-400">Gateway: Manual Bank Transfer</span>
                </div>
            </div>

            {{-- Line Items --}}
            <div class="space-y-3">
                <span class="text-[10px] uppercase font-bold text-slate-400 block tracking-wider">Invoice Line Items</span>
                <div class="border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 dark:bg-slate-950 text-slate-500 dark:text-slate-400 font-bold border-b border-slate-200 dark:border-slate-800">
                            <tr>
                                <th class="p-4">Description</th>
                                <th class="p-4 text-center">Qty</th>
                                <th class="p-4 text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
                            @if($invoice->order)
                                @foreach($invoice->order->items as $item)
                                <tr>
                                    <td class="p-4">
                                        <span class="font-bold text-slate-900 dark:text-white block">{{ $item->product?->title ?? 'Assessment Item' }}</span>
                                        <span class="text-[11px] text-slate-500 dark:text-slate-400">{{ strtoupper($item->product?->product_type ?? 'assessment') }} Package</span>
                                    </td>
                                    <td class="p-4 text-center font-bold text-slate-900 dark:text-white">{{ $item->quantity }}</td>
                                    <td class="p-4 text-right font-bold text-slate-900 dark:text-white">IDR {{ number_format($item->total) }}</td>
                                </tr>
                                @endforeach
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Total Summary --}}
            <div class="flex justify-end">
                <div class="w-full sm:w-80 p-5 rounded-2xl bg-slate-50 dark:bg-slate-950/60 border border-slate-100 dark:border-slate-800 space-y-2 text-xs">
                    <div class="flex justify-between text-slate-600 dark:text-slate-400">
                        <span>Subtotal:</span>
                        <span class="font-semibold text-slate-900 dark:text-white">IDR {{ number_format($invoice->order?->subtotal ?? ($invoice->amount / 1.11)) }}</span>
                    </div>
                    <div class="flex justify-between text-slate-600 dark:text-slate-400">
                        <span>Tax (11% VAT):</span>
                        <span class="font-semibold text-slate-900 dark:text-white">IDR {{ number_format($invoice->order?->tax ?? ($invoice->amount - ($invoice->amount / 1.11))) }}</span>
                    </div>
                    <div class="flex justify-between pt-2 border-t border-slate-200 dark:border-slate-800 text-base font-black text-slate-900 dark:text-white">
                        <span>Total Due:</span>
                        <span class="text-indigo-600 dark:text-indigo-400">IDR {{ number_format($invoice->amount) }}</span>
                    </div>
                </div>
            </div>

            {{-- Payment Instructions Section --}}
            @if($invoice->status !== 'paid')
            <div class="p-6 rounded-3xl bg-amber-50/50 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-900/40 space-y-4">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-amber-700 dark:text-amber-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z" />
                    </svg>
                    <h3 class="text-sm font-bold text-amber-900 dark:text-amber-300 uppercase tracking-wider">Manual Bank Transfer Payment Instructions</h3>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs text-slate-700 dark:text-slate-300">
                    <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-amber-200/60 dark:border-amber-900/40 space-y-1">
                        <span class="text-[10px] uppercase font-bold text-slate-400 block">Bank Account</span>
                        <p class="font-bold text-slate-900 dark:text-white text-sm">Bank Central Asia (BCA)</p>
                        <p class="font-mono text-indigo-600 dark:text-indigo-400 text-base font-extrabold tracking-wider">8830-1928-3001</p>
                        <p class="text-slate-500 dark:text-slate-400">A/N: Yayasan iC.edu International</p>
                    </div>
                    <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-amber-200/60 dark:border-amber-900/40 space-y-1">
                        <span class="text-[10px] uppercase font-bold text-slate-400 block">Transfer Reference Code</span>
                        <p class="font-mono text-amber-700 dark:text-amber-300 text-base font-extrabold">{{ $payment?->reference_number ?? $invoice->invoice_number }}</p>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400">Include this reference code in your transfer description notes.</p>
                    </div>
                </div>

                @if($payment)
                <div class="pt-2 flex flex-col sm:flex-row items-center justify-between gap-4">
                    <span class="text-xs text-slate-500 dark:text-slate-400">Payment Status: <x-status-badge :status="$payment->status" /></span>
                    <a href="{{ route('candidate.payments.show', $payment->id) }}" class="w-full sm:w-auto px-6 py-2.5 rounded-xl bg-amber-600 hover:bg-amber-500 text-white text-xs font-bold transition-all shadow-md shadow-amber-600/20 text-center flex items-center justify-center gap-2">
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                        </svg>
                        <span>Upload Transfer Evidence &rarr;</span>
                    </a>
                </div>
                @endif
            </div>
            @else
            <div class="p-6 rounded-3xl bg-emerald-50/50 dark:bg-emerald-950/20 border border-emerald-200 dark:border-emerald-900/40 flex items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <span class="text-2xl">✅</span>
                    <div>
                        <h3 class="text-sm font-bold text-emerald-900 dark:text-emerald-300">Payment Confirmed</h3>
                        <p class="text-xs text-emerald-700 dark:text-emerald-400 mt-0.5">This invoice has been verified and settled by the Finance Officer on {{ $invoice->paid_at?->format('d M Y, H:i') }}.</p>
                    </div>
                </div>
                <a href="{{ route('candidate.available-tests') }}" class="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold transition-all shadow-md shadow-emerald-600/20 flex-shrink-0">
                    Go to Available Tests &rarr;
                </a>
            </div>
            @endif
        </div>
    </div>
</x-candidate-layout>
