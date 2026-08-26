<x-candidate-layout>
    <div class="space-y-6">
        {{-- Header --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-slate-900 dark:text-white">💳 My Invoices &amp; Billing</h1>
                <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-400 mt-1">Access tax receipts, payment instructions, and official invoices.</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('candidate.payments.index') }}" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-semibold transition-colors">
                    💸 Payment History
                </a>
            </div>
        </div>

        {{-- Flash message --}}
        @if(session('status'))
        <div class="p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-700 dark:text-emerald-400 text-xs font-medium flex items-center gap-2">
            <span>✅</span> {{ session('status') }}
        </div>
        @endif

        {{-- Invoices Table --}}
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead class="bg-slate-50 dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800 text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                        <tr>
                            <th class="px-5 py-4">Invoice Number</th>
                            <th class="px-5 py-4">Order Ref</th>
                            <th class="px-5 py-4">Total Amount</th>
                            <th class="px-5 py-4">Invoice Status</th>
                            <th class="px-5 py-4">Due Date</th>
                            <th class="px-5 py-4">Paid Date</th>
                            <th class="px-5 py-4 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
                        @forelse($invoices as $invoice)
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors">
                            <td class="px-5 py-4 font-mono font-bold text-indigo-600 dark:text-indigo-400">{{ $invoice->invoice_number }}</td>
                            <td class="px-5 py-4 font-mono">
                                @if($invoice->order)
                                    <a href="{{ route('candidate.orders.show', $invoice->order->id) }}" class="text-slate-700 dark:text-slate-300 hover:underline">
                                        {{ $invoice->order->order_number }}
                                    </a>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 font-extrabold text-slate-900 dark:text-white">IDR {{ number_format($invoice->amount) }}</td>
                            <td class="px-5 py-4">
                                <x-status-badge :status="$invoice->status" />
                            </td>
                            <td class="px-5 py-4 text-slate-500 dark:text-slate-400">{{ $invoice->due_date?->format('d M Y, H:i') ?? '—' }}</td>
                            <td class="px-5 py-4 text-slate-500 dark:text-slate-400">{{ $invoice->paid_at?->format('d M Y, H:i') ?? '—' }}</td>
                            <td class="px-5 py-4 text-right">
                                <a href="{{ route('candidate.invoices.show', $invoice->id) }}" class="px-3.5 py-1.5 rounded-lg bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-950/50 dark:hover:bg-indigo-900/50 text-indigo-700 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-800/50 text-[11px] font-bold transition-colors">
                                    View Invoice &rarr;
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="p-12 text-center text-slate-500 dark:text-slate-400">
                                <span class="text-3xl block mb-2">💳</span>
                                No invoices have been issued yet.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($invoices->hasPages())
            <div class="p-4 border-t border-slate-100 dark:border-slate-800">
                {{ $invoices->links() }}
            </div>
            @endif
        </div>
    </div>
</x-candidate-layout>
