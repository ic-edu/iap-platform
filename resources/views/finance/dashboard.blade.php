@extends('layouts.admin')

@section('content')

    <!-- Financial KPI Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 mb-8">
        <div class="p-5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm">
            <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-500 dark:text-slate-400 block">Gross Revenue</span>
            <span class="text-3xl font-black text-emerald-600 dark:text-emerald-400 mt-1 block">IDR {{ number_format($grossRevenue) }}</span>
            <span class="text-xs text-slate-500 dark:text-slate-400 mt-1 block">Confirmed paid transactions</span>
        </div>

        <div class="p-5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-500 dark:text-slate-400 block">Pending Approvals</span>
                <span class="w-2.5 h-2.5 rounded-full {{ $pendingPaymentsCount > 0 ? 'bg-amber-500 animate-pulse' : 'bg-slate-300 dark:bg-slate-700' }}"></span>
            </div>
            <span class="text-3xl font-black text-amber-600 dark:text-amber-400 mt-1 block">{{ number_format($pendingPaymentsCount) }}</span>
            <span class="text-xs text-slate-500 dark:text-slate-400 mt-1 block">Candidate payments awaiting review</span>
        </div>

        <div class="p-5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm">
            <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-500 dark:text-slate-400 block">Invoices Issued</span>
            <span class="text-3xl font-black text-indigo-600 dark:text-indigo-400 mt-1 block">{{ number_format($invoicesIssuedCount) }}</span>
            <span class="text-xs text-slate-500 dark:text-slate-400 mt-1 block">Billing invoices generated</span>
        </div>

        <div class="p-5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm">
            <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-500 dark:text-slate-400 block">Promotions</span>
            <span class="text-3xl font-black text-slate-900 dark:text-white mt-1 block">{{ number_format($couponsCount) }}</span>
            <span class="text-xs text-slate-500 dark:text-slate-400 mt-1 block">Active vouchers</span>
        </div>
    </div>

    <!-- Live Pending Payments Queue Section -->
    @if(isset($recentPendingPayments) && $recentPendingPayments->isNotEmpty())
    <div class="mb-8 bg-white dark:bg-slate-900 border border-amber-200 dark:border-amber-900/40 rounded-2xl overflow-hidden shadow-sm">
        <div class="p-4 border-b border-amber-100 dark:border-amber-900/30 flex items-center justify-between bg-amber-50/50 dark:bg-amber-950/20">
            <div class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-amber-500 animate-pulse"></span>
                <h2 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider">Candidate Payments Requiring Review ({{ $pendingPaymentsCount }})</h2>
            </div>
            <a href="{{ route('finance.payments.pending') }}" class="text-xs text-amber-700 dark:text-amber-400 font-bold hover:underline">
                View Full Queue &rarr;
            </a>
        </div>
        <div class="divide-y divide-slate-100 dark:divide-slate-800/80">
            @foreach($recentPendingPayments as $payment)
            <div class="p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors">
                <div class="space-y-0.5 min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="font-mono font-bold text-indigo-600 dark:text-indigo-400 text-xs">{{ $payment->reference_number }}</span>
                        <x-status-badge :status="$payment->status" />
                        @if($payment->proof_path)
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-400">Proof Uploaded ✓</span>
                        @endif
                    </div>
                    <p class="text-sm font-bold text-slate-900 dark:text-white truncate">{{ $payment->user?->name }} <span class="text-xs text-slate-500 dark:text-slate-400 font-normal">({{ $payment->user?->email }})</span></p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
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
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-sm">
        <div class="p-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white">Recent Payment Transactions &amp; Invoices</h2>
            <a href="{{ route('admin.commerce.index') }}" class="text-xs text-emerald-600 dark:text-emerald-400 font-semibold hover:underline">View All Transactions →</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-600 dark:text-slate-300">
                <thead class="bg-slate-50 dark:bg-slate-950 text-xs uppercase text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
                    <tr>
                        <th class="p-4">Txn Ref</th>
                        <th class="p-4">Customer Email</th>
                        <th class="p-4">Package</th>
                        <th class="p-4">Amount</th>
                        <th class="p-4">Payment Status</th>
                        <th class="p-4 text-right">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60 font-mono text-xs">
                    @forelse ($latestTransactions as $txn)
                        @php
                            $userEmail = $txn->user?->email ?? $txn->invoice?->order?->user?->email ?? 'N/A';
                            $productTitle = $txn->invoice?->order?->items?->first()?->product?->title ?? 'Assessment Order';
                        @endphp
                        <tr>
                            <td class="p-4 font-bold text-slate-500 dark:text-slate-400">{{ $txn->reference_number ?? $txn->id }}</td>
                            <td class="p-4 font-semibold text-slate-900 dark:text-white font-sans text-xs">{{ $userEmail }}</td>
                            <td class="p-4 text-xs text-indigo-600 dark:text-indigo-400 font-medium font-sans">{{ $productTitle }}</td>
                            <td class="p-4 font-bold text-emerald-600 dark:text-emerald-400">IDR {{ number_format($txn->amount) }}</td>
                            <td class="p-4">
                                <x-status-badge :status="$txn->status" />
                            </td>
                            <td class="p-4 text-right text-xs text-slate-400 font-sans">{{ $txn->created_at?->format('d M Y, H:i') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-8 text-center text-slate-500 font-sans">No recent payment transactions recorded.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
