<x-candidate-layout>
    <div class="space-y-6">
        {{-- Header --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-slate-900 dark:text-white">Payment Transactions</h1>
                <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-400 mt-1">Track payment references, upload bank transfer slips, and view verification statuses.</p>
            </div>
            <a href="{{ route('candidate.invoices.index') }}" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-semibold transition-colors">
                <svg class="w-3.5 h-3.5 text-slate-500 dark:text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                </svg>
                <span>Invoices</span>
            </a>
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

        {{-- Payments Table --}}
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead class="bg-slate-50 dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800 text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                        <tr>
                            <th class="px-5 py-4">Payment Ref</th>
                            <th class="px-5 py-4">Invoice</th>
                            <th class="px-5 py-4">Amount</th>
                            <th class="px-5 py-4">Gateway</th>
                            <th class="px-5 py-4">Payment Status</th>
                            <th class="px-5 py-4">Evidence</th>
                            <th class="px-5 py-4 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
                        @forelse($payments as $payment)
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors">
                            <td class="px-5 py-4 font-mono font-bold text-indigo-600 dark:text-indigo-400">{{ $payment->reference_number }}</td>
                            <td class="px-5 py-4 font-mono">
                                @if($payment->invoice)
                                    <a href="{{ route('candidate.invoices.show', $payment->invoice->id) }}" class="text-slate-700 dark:text-slate-300 hover:underline">
                                        {{ $payment->invoice->invoice_number }}
                                    </a>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 font-extrabold text-slate-900 dark:text-white">IDR {{ number_format($payment->amount) }}</td>
                            <td class="px-5 py-4 capitalize text-slate-600 dark:text-slate-400">{{ str_replace('_', ' ', $payment->payment_gateway) }}</td>
                            <td class="px-5 py-4">
                                <x-status-badge :status="$payment->status" />
                            </td>
                            <td class="px-5 py-4">
                                @if($payment->proof_path)
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-400">
                                        Uploaded ✓
                                    </span>
                                @else
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400">
                                        Pending
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-right">
                                <a href="{{ route('candidate.payments.show', $payment->id) }}" class="px-3.5 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 text-[11px] font-bold transition-colors">
                                    Details / Proof &rarr;
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="p-12 text-center text-slate-500 dark:text-slate-400">
                                <span class="text-3xl block mb-2">💸</span>
                                No payment transactions initiated yet.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($payments->hasPages())
            <div class="p-4 border-t border-slate-100 dark:border-slate-800">
                {{ $payments->links() }}
            </div>
            @endif
        </div>
    </div>
</x-candidate-layout>
