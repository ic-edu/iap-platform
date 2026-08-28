@extends('layouts.admin')

@section('content')
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <svg class="w-6 h-6 text-indigo-600 dark:text-indigo-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                </svg>
                <span>Finance Overview &amp; Payment Operations Hub</span>
            </h1>
            <p class="text-xs text-slate-600 dark:text-slate-400 mt-1">Live financial metric performance, pending bank transfer verifications, and transaction reports.</p>
        </div>

        <!-- Period Selector Filter -->
        <div class="flex items-center gap-1.5 p-1 bg-slate-100 dark:bg-slate-900 border border-slate-300 dark:border-slate-800 rounded-xl text-xs font-semibold self-start sm:self-auto">
            @php $currentPeriod = $period ?? 'all_time'; @endphp
            <a href="{{ route('finance.dashboard', ['period' => 'today']) }}" class="px-3 py-1.5 rounded-lg transition-all {{ $currentPeriod === 'today' ? 'bg-indigo-600 text-white font-bold shadow-sm' : 'text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white' }}">
                Today
            </a>
            <a href="{{ route('finance.dashboard', ['period' => 'this_month']) }}" class="px-3 py-1.5 rounded-lg transition-all {{ $currentPeriod === 'this_month' ? 'bg-indigo-600 text-white font-bold shadow-sm' : 'text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white' }}">
                This Month
            </a>
            <a href="{{ route('finance.dashboard', ['period' => 'this_quarter']) }}" class="px-3 py-1.5 rounded-lg transition-all {{ $currentPeriod === 'this_quarter' ? 'bg-indigo-600 text-white font-bold shadow-sm' : 'text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white' }}">
                This Quarter
            </a>
            <a href="{{ route('finance.dashboard', ['period' => 'this_year']) }}" class="px-3 py-1.5 rounded-lg transition-all {{ $currentPeriod === 'this_year' ? 'bg-indigo-600 text-white font-bold shadow-sm' : 'text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white' }}">
                This Year
            </a>
            <a href="{{ route('finance.dashboard', ['period' => 'all_time']) }}" class="px-3 py-1.5 rounded-lg transition-all {{ $currentPeriod === 'all_time' ? 'bg-indigo-600 text-white font-bold shadow-sm' : 'text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white' }}">
                All Time
            </a>
        </div>
    </div>

    <!-- Financial KPI Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 mb-8">
        <div class="p-5 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-800 rounded-2xl shadow-sm">
            <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-600 dark:text-slate-400 block">Gross Cash Collections</span>
            <span class="text-3xl font-black text-emerald-600 dark:text-emerald-400 mt-1 block">IDR {{ number_format($grossCashCollections) }}</span>
            <span class="text-xs text-slate-600 dark:text-slate-400 mt-1 block">Including Tax &bull; Confirmed payments</span>
        </div>

        <div class="p-5 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-800 rounded-2xl shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-600 dark:text-slate-400 block">Pending Approvals</span>
                <span class="w-2.5 h-2.5 rounded-full {{ $pendingPaymentsCount > 0 ? 'bg-amber-500 animate-pulse' : 'bg-slate-300 dark:bg-slate-700' }}"></span>
            </div>
            <span class="text-3xl font-black text-amber-600 dark:text-amber-400 mt-1 block">{{ number_format($pendingPaymentsCount) }}</span>
            <span class="text-xs text-slate-600 dark:text-slate-400 mt-1 block">Candidate payments awaiting review</span>
        </div>

        <div class="p-5 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-800 rounded-2xl shadow-sm">
            <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-600 dark:text-slate-400 block">Invoices Issued</span>
            <span class="text-3xl font-black text-indigo-600 dark:text-indigo-400 mt-1 block">{{ number_format($invoicesIssuedCount) }}</span>
            <span class="text-xs text-slate-600 dark:text-slate-400 mt-1 block">Billing invoices generated</span>
        </div>

        <div class="p-5 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-800 rounded-2xl shadow-sm">
            <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-600 dark:text-slate-400 block">Unpaid Invoices</span>
            <span class="text-3xl font-black text-rose-600 dark:text-rose-400 mt-1 block">{{ number_format($unpaidInvoicesCount) }}</span>
            <span class="text-xs text-slate-600 dark:text-slate-400 mt-1 block">Awaiting candidate payment</span>
        </div>
    </div>

    <!-- Live Pending Payments Queue Section -->
    @if(isset($recentPendingPayments) && $recentPendingPayments->isNotEmpty())
    <div class="mb-8 bg-white dark:bg-slate-900 border border-amber-300 dark:border-amber-900/40 rounded-2xl overflow-hidden shadow-sm">
        <div class="p-4 border-b border-amber-200 dark:border-amber-900/30 flex items-center justify-between bg-amber-50/70 dark:bg-amber-950/20">
            <div class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-amber-500 animate-pulse"></span>
                <h2 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider">Candidate Payments Requiring Review ({{ $pendingPaymentsCount }})</h2>
            </div>
            <a href="{{ route('finance.payments.index', ['status' => 'pending']) }}" class="text-xs text-amber-800 dark:text-amber-400 font-bold hover:underline">
                View Full Queue &rarr;
            </a>
        </div>
        <div class="divide-y divide-slate-200 dark:divide-slate-800/80">
            @foreach($recentPendingPayments as $payment)
            <div class="p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 hover:bg-slate-50 dark:hover:bg-slate-800/30 transition-colors">
                <div class="space-y-0.5 min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="font-mono font-bold text-indigo-600 dark:text-indigo-400 text-xs">{{ $payment->reference_number }}</span>
                        <x-status-badge :status="$payment->status" />
                        @if($payment->proof_path)
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 dark:bg-emerald-950/50 text-emerald-800 dark:text-emerald-400 border border-emerald-300 dark:border-emerald-800">Proof Uploaded ✓</span>
                        @endif
                    </div>
                    <p class="text-sm font-bold text-slate-900 dark:text-white truncate">{{ $payment->user?->name }} <span class="text-xs text-slate-600 dark:text-slate-400 font-normal">({{ $payment->user?->email }})</span></p>
                    <p class="text-xs text-slate-600 dark:text-slate-400">
                        Amount: <strong class="text-slate-900 dark:text-white font-bold">IDR {{ number_format($payment->amount) }}</strong> &bull; Gateway: {{ strtoupper(str_replace('_', ' ', $payment->payment_gateway)) }}
                    </p>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0">
                    <a href="{{ route('finance.payments.show', $payment->id) }}" class="px-3.5 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold transition-all shadow-md shadow-indigo-600/20">
                        Review &amp; Verify &rarr;
                    </a>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    <!-- Payment & Invoice Transactions Table -->
    <div class="bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-800 rounded-2xl overflow-hidden shadow-sm">
        <div class="p-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white">Recent Payment Transactions &amp; Invoices</h2>
            <a href="{{ route('finance.payments.index', ['status' => 'all']) }}" class="text-xs text-indigo-600 dark:text-indigo-400 font-bold hover:underline">View All Transactions &rarr;</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-700 dark:text-slate-300">
                <thead class="bg-slate-100 dark:bg-slate-950 text-xs uppercase text-slate-700 dark:text-slate-400 border-b border-slate-300 dark:border-slate-800 font-bold">
                    <tr>
                        <th class="p-4">Payment Ref</th>
                        <th class="p-4">Customer Email</th>
                        <th class="p-4">Package</th>
                        <th class="p-4">Amount</th>
                        <th class="p-4">Invoice Ref</th>
                        <th class="p-4">Payment Status</th>
                        <th class="p-4 text-right">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800/60 font-mono text-xs">
                    @forelse ($latestTransactions as $txn)
                        @php
                            $userEmail = $txn->user?->email ?? $txn->invoice?->order?->user?->email ?? 'N/A';
                            $productTitle = $txn->invoice?->order?->items?->first()?->product?->title ?? 'Assessment Order';
                        @endphp
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/30 transition-colors">
                            <td class="p-4 font-bold text-slate-900 dark:text-white font-mono">{{ $txn->reference_number ?? $txn->id }}</td>
                            <td class="p-4 font-semibold text-slate-900 dark:text-white font-sans text-xs">{{ $userEmail }}</td>
                            <td class="p-4 text-xs text-indigo-600 dark:text-indigo-400 font-bold font-sans">{{ $productTitle }}</td>
                            <td class="p-4 font-extrabold text-emerald-700 dark:text-emerald-400">IDR {{ number_format($txn->amount) }}</td>
                            <td class="p-4 font-mono text-slate-700 dark:text-slate-400">{{ $txn->invoice?->invoice_number ?? '—' }}</td>
                            <td class="p-4">
                                <x-status-badge :status="$txn->status" />
                            </td>
                            <td class="p-4 text-right text-xs text-slate-600 dark:text-slate-400 font-sans">{{ $txn->created_at?->format('d M Y, H:i') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-8 text-center text-slate-500 font-sans">No recent payment transactions recorded.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
