@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-slate-900 dark:text-white">💳 Payment &amp; Invoice Reports</h1>
            <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-400 mt-1">Review candidate bank transfer receipts, monitor invoice transaction history, and confirm payment records.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('finance.dashboard') }}" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-semibold transition-colors">
                &larr; Finance Dashboard
            </a>
        </div>
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

    {{-- Filter Tabs --}}
    <div class="flex items-center gap-2 border-b border-slate-200 dark:border-slate-800 pb-3 overflow-x-auto text-xs">
        <a href="{{ route('finance.payments.pending', ['status' => 'pending']) }}" class="px-4 py-2 rounded-xl font-bold transition-all {{ ($statusFilter ?? 'pending') === 'pending' ? 'bg-amber-500 text-white shadow-md shadow-amber-500/20' : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700' }}">
            ⏳ Pending Review ({{ $pendingCount }})
        </a>
        <a href="{{ route('finance.payments.pending', ['status' => 'success']) }}" class="px-4 py-2 rounded-xl font-bold transition-all {{ ($statusFilter ?? '') === 'success' ? 'bg-emerald-600 text-white shadow-md shadow-emerald-600/20' : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700' }}">
            ✓ Confirmed / Paid ({{ $successCount }})
        </a>
        <a href="{{ route('finance.payments.pending', ['status' => 'failed']) }}" class="px-4 py-2 rounded-xl font-bold transition-all {{ ($statusFilter ?? '') === 'failed' ? 'bg-rose-600 text-white shadow-md shadow-rose-600/20' : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700' }}">
            ✗ Cancelled / Rejected ({{ $failedCount }})
        </a>
        <a href="{{ route('finance.payments.pending', ['status' => 'all']) }}" class="px-4 py-2 rounded-xl font-bold transition-all {{ ($statusFilter ?? '') === 'all' ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20' : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700' }}">
            All Transactions
        </a>
    </div>

    {{-- Payments Queue Table --}}
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead class="bg-slate-50 dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800 text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                    <tr>
                        <th class="px-5 py-4">Payment Ref</th>
                        <th class="px-5 py-4">Candidate / Student</th>
                        <th class="px-5 py-4">Package / Items</th>
                        <th class="px-5 py-4">Amount</th>
                        <th class="px-5 py-4">Invoice Ref</th>
                        <th class="px-5 py-4">Proof File</th>
                        <th class="px-5 py-4">Status</th>
                        <th class="px-5 py-4">Date</th>
                        <th class="px-5 py-4 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
                    @forelse($payments as $payment)
                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors">
                        <td class="px-5 py-4 font-mono font-bold text-indigo-600 dark:text-indigo-400">
                            <a href="{{ route('finance.payments.show', $payment->id) }}" class="hover:underline">
                                {{ $payment->reference_number }}
                            </a>
                        </td>
                        <td class="px-5 py-4">
                            <div class="font-bold text-slate-900 dark:text-white">{{ $payment->user?->name ?? 'Candidate User' }}</div>
                            <span class="text-[11px] text-slate-500 dark:text-slate-400 font-mono">{{ $payment->user?->email }}</span>
                        </td>
                        <td class="px-5 py-4">
                            @if($payment->invoice?->order)
                                @foreach($payment->invoice->order->items as $item)
                                    <div class="font-semibold text-slate-900 dark:text-white">{{ $item->product?->title ?? 'Package Item' }}</div>
                                    @if($item->product?->test)
                                        <span class="text-[10px] font-medium text-emerald-600 dark:text-emerald-400">🎯 Mock Test Product</span>
                                    @endif
                                @endforeach
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="px-5 py-4 font-extrabold text-slate-900 dark:text-white">IDR {{ number_format($payment->amount) }}</td>
                        <td class="px-5 py-4 font-mono text-slate-600 dark:text-slate-400">{{ $payment->invoice?->invoice_number ?? '—' }}</td>
                        <td class="px-5 py-4">
                            @if($payment->proof_path)
                                <a href="{{ route('finance.payments.proof', $payment->id) }}" target="_blank" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[10px] font-bold bg-emerald-100 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-900/30 hover:underline">
                                    <span>📎 View Proof</span>
                                </a>
                            @else
                                <span class="px-2.5 py-1 rounded-lg text-[10px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400">
                                    No File
                                </span>
                            @endif
                        </td>
                        <td class="px-5 py-4">
                            <x-status-badge :status="$payment->status" />
                        </td>
                        <td class="px-5 py-4 text-slate-500 dark:text-slate-400">{{ $payment->created_at?->format('d M Y, H:i') }}</td>
                        <td class="px-5 py-4 text-right">
                            <a href="{{ route('finance.payments.show', $payment->id) }}" class="px-3.5 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-[11px] font-bold transition-all shadow-md shadow-indigo-600/20">
                                Review &rarr;
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="p-12 text-center text-slate-500 dark:text-slate-400">
                            <span class="text-3xl block mb-2">🎉</span>
                            No payment records found for the selected status filter.
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
@endsection
