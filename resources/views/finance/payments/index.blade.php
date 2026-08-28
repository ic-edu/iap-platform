@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-slate-900 dark:text-white flex items-center gap-2">
                <svg class="w-7 h-7 text-indigo-600 dark:text-indigo-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <span>{{ $pageTitle ?? 'Payment & Invoice Reports' }}</span>
            </h1>
            <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-400 mt-1">{{ $pageSubtitle ?? 'Review transaction history, invoice records, and financial breakdowns.' }}</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('finance.dashboard') }}" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 text-xs font-bold transition-colors border border-slate-300 dark:border-slate-700">
                &larr; Finance Dashboard
            </a>
        </div>
    </div>

    {{-- Flash messages --}}
    @if(session('status'))
    <div class="p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-800 dark:text-emerald-400 text-xs font-bold flex items-center gap-2">
        <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
        </svg>
        <span>{{ session('status') }}</span>
    </div>
    @endif

    @if($errors->any())
    <div class="p-4 rounded-2xl bg-rose-500/10 border border-rose-500/20 text-rose-800 dark:text-rose-400 text-xs font-semibold space-y-1">
        @foreach($errors->all() as $error)
            <p>⚠️ {{ $error }}</p>
        @endforeach
    </div>
    @endif

    {{-- Filter Status Tabs --}}
    <div class="flex items-center gap-2 border-b border-slate-300 dark:border-slate-800 pb-3 overflow-x-auto text-xs">
        @php
            $currentQuery = request()->except(['status', 'page']);
        @endphp
        <a href="{{ route('finance.payments.index', array_merge($currentQuery, ['status' => 'pending'])) }}" class="px-4 py-2 rounded-xl font-bold transition-all flex items-center gap-1.5 {{ ($statusFilter ?? '') === 'pending' ? 'bg-amber-500 text-white shadow-md shadow-amber-500/20' : 'bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 border border-slate-300 dark:border-slate-700' }}">
            <span class="w-2 h-2 rounded-full bg-amber-400"></span>
            <span>Pending Review ({{ $pendingCount }})</span>
        </a>
        <a href="{{ route('finance.payments.index', array_merge($currentQuery, ['status' => 'success'])) }}" class="px-4 py-2 rounded-xl font-bold transition-all flex items-center gap-1.5 {{ ($statusFilter ?? '') === 'success' ? 'bg-emerald-600 text-white shadow-md shadow-emerald-600/20' : 'bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 border border-slate-300 dark:border-slate-700' }}">
            <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
            <span>Confirmed / Paid ({{ $successCount }})</span>
        </a>
        <a href="{{ route('finance.payments.index', array_merge($currentQuery, ['status' => 'failed'])) }}" class="px-4 py-2 rounded-xl font-bold transition-all flex items-center gap-1.5 {{ ($statusFilter ?? '') === 'failed' ? 'bg-rose-600 text-white shadow-md shadow-rose-600/20' : 'bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 border border-slate-300 dark:border-slate-700' }}">
            <span class="w-2 h-2 rounded-full bg-rose-400"></span>
            <span>Cancelled / Rejected ({{ $failedCount }})</span>
        </a>
        <a href="{{ route('finance.payments.index', array_merge($currentQuery, ['status' => 'all'])) }}" class="px-4 py-2 rounded-xl font-bold transition-all {{ ($statusFilter ?? 'all') === 'all' ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20' : 'bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 border border-slate-300 dark:border-slate-700' }}">
            All Transactions
        </a>
    </div>

    {{-- Multi-Factor Filter Bar & Export Actions --}}
    <div class="bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-800 rounded-2xl p-4 shadow-sm space-y-4">
        <form method="GET" action="{{ route('finance.payments.index') }}" autocomplete="off" class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end">
            {{-- Preserve active status tab --}}
            <input type="hidden" name="status" value="{{ $statusFilter ?? 'all' }}">

            {{-- Search input --}}
            <div class="sm:col-span-4">
                <label for="search" class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">Search Identifier</label>
                <div class="relative">
                    <input type="text" name="search" id="search" value="{{ $search ?? '' }}" placeholder="Candidate, Ref #, Invoice, TXN ID..." class="w-full text-xs bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl p-2.5 pl-8 text-slate-900 dark:text-white placeholder:text-slate-400 focus:outline-none focus:border-indigo-500">
                    <svg class="w-4 h-4 text-slate-400 absolute left-2.5 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
            </div>

            {{-- Start Date --}}
            <div class="sm:col-span-2">
                <div class="flex items-center justify-between mb-1">
                    <label for="start_date" class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider">Start Date</label>
                    <span class="text-[10px] text-slate-500 dark:text-slate-400 font-mono font-medium">dd/mm/yyyy</span>
                </div>
                <input type="date" name="start_date" id="start_date" value="{{ !empty($startDate) ? $startDate : '' }}" placeholder="dd/mm/yyyy" autocomplete="off" class="w-full text-xs bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl p-2 text-slate-900 dark:text-white placeholder:text-slate-400 focus:outline-none focus:border-indigo-500">
            </div>

            {{-- End Date --}}
            <div class="sm:col-span-2">
                <div class="flex items-center justify-between mb-1">
                    <label for="end_date" class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider">End Date</label>
                    <span class="text-[10px] text-slate-500 dark:text-slate-400 font-mono font-medium">dd/mm/yyyy</span>
                </div>
                <input type="date" name="end_date" id="end_date" value="{{ !empty($endDate) ? $endDate : '' }}" placeholder="dd/mm/yyyy" autocomplete="off" class="w-full text-xs bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl p-2 text-slate-900 dark:text-white placeholder:text-slate-400 focus:outline-none focus:border-indigo-500">
            </div>

            {{-- Product / Package Filter --}}
            <div class="sm:col-span-2">
                <label for="product_id" class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">Product Package</label>
                <select name="product_id" id="product_id" class="w-full text-xs bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl p-2 text-slate-900 dark:text-white focus:outline-none focus:border-indigo-500">
                    <option value="">All Products</option>
                    @foreach($products as $prod)
                        <option value="{{ $prod->id }}" {{ ($productId ?? '') == $prod->id ? 'selected' : '' }}>
                            {{ $prod->title }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Submit & Clear Buttons --}}
            <div class="sm:col-span-2 flex items-center gap-2">
                <button type="submit" class="w-full py-2.5 px-3 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs transition-all shadow-sm flex items-center justify-center gap-1.5 cursor-pointer">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                    </svg>
                    <span>Filter</span>
                </button>
                <a href="{{ route('finance.payments.index') }}" class="py-2.5 px-3 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-bold text-xs transition-all border border-slate-300 dark:border-slate-700 whitespace-nowrap text-center" title="Clear all filters">
                    Clear
                </a>
            </div>
        </form>

        {{-- Export Action Strip --}}
        <div class="pt-3 border-t border-slate-200 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
            <span class="text-slate-600 dark:text-slate-400 font-semibold">
                Showing <strong class="text-slate-900 dark:text-white">{{ $summary['total_count'] }}</strong> filtered transaction(s)
            </span>
            <div class="flex items-center gap-2">
                <a href="{{ route('finance.payments.export.csv', request()->query()) }}" class="px-3.5 py-1.5 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 text-emerald-800 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800 hover:bg-emerald-100 dark:hover:bg-emerald-900/50 font-bold transition-all flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <span>Export CSV</span>
                </a>
                <a href="{{ route('finance.payments.export.xlsx', request()->query()) }}" class="px-3.5 py-1.5 rounded-xl bg-indigo-50 dark:bg-indigo-950/40 text-indigo-800 dark:text-indigo-300 border border-indigo-300 dark:border-indigo-800 hover:bg-indigo-100 dark:hover:bg-indigo-900/50 font-bold transition-all flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    <span>Export Excel (.xlsx)</span>
                </a>
            </div>
        </div>
    </div>

    {{-- Financial Summary Strip --}}
    <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
        <div class="p-4 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-800 rounded-2xl">
            <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-600 dark:text-slate-400 block">Filtered Count</span>
            <span class="text-xl font-black text-slate-900 dark:text-white mt-0.5 block">{{ number_format($summary['total_count']) }}</span>
            <span class="text-[11px] text-slate-500 dark:text-slate-400">Transactions</span>
        </div>
        <div class="p-4 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-800 rounded-2xl">
            <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-600 dark:text-slate-400 block">Confirmed Amount</span>
            <span class="text-xl font-black text-emerald-600 dark:text-emerald-400 mt-0.5 block">IDR {{ number_format($summary['confirmed_amount']) }}</span>
            <span class="text-[11px] text-slate-500 dark:text-slate-400">Settled collections</span>
        </div>
        <div class="p-4 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-800 rounded-2xl">
            <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-600 dark:text-slate-400 block">Pending Amount</span>
            <span class="text-xl font-black text-amber-600 dark:text-amber-400 mt-0.5 block">IDR {{ number_format($summary['pending_amount']) }}</span>
            <span class="text-[11px] text-slate-500 dark:text-slate-400">Awaiting verification</span>
        </div>
        <div class="p-4 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-800 rounded-2xl">
            <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-600 dark:text-slate-400 block">Cancelled Amount</span>
            <span class="text-xl font-black text-rose-600 dark:text-rose-400 mt-0.5 block">IDR {{ number_format($summary['cancelled_amount']) }}</span>
            <span class="text-[11px] text-slate-500 dark:text-slate-400">Rejected payments</span>
        </div>
        <div class="p-4 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-800 rounded-2xl col-span-2 sm:col-span-1">
            <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-600 dark:text-slate-400 block">Tax / PPN (11%)</span>
            <span class="text-xl font-black text-indigo-600 dark:text-indigo-400 mt-0.5 block">IDR {{ number_format($summary['total_tax_amount']) }}</span>
            <span class="text-[11px] text-slate-500 dark:text-slate-400">VAT component</span>
        </div>
    </div>

    {{-- Payments Queue Table --}}
    <div class="bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-800 rounded-2xl overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead class="bg-slate-100 dark:bg-slate-950 border-b border-slate-300 dark:border-slate-800 text-[10px] font-bold uppercase tracking-wider text-slate-700 dark:text-slate-400">
                    <tr>
                        <th class="px-5 py-4">Payment Ref</th>
                        <th class="px-5 py-4">Candidate / Student</th>
                        <th class="px-5 py-4">Package / Items</th>
                        <th class="px-5 py-4">Financial Breakdown</th>
                        <th class="px-5 py-4">Invoice Ref</th>
                        <th class="px-5 py-4">Proof File</th>
                        <th class="px-5 py-4">Status</th>
                        <th class="px-5 py-4">Initiated</th>
                        <th class="px-5 py-4 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800/80">
                    @forelse($payments as $payment)
                    @php
                        $order = $payment->invoice?->order;
                        $basePrice = $order ? (float) $order->subtotal : (float) $payment->amount;
                        $discount = $order ? (float) $order->discount : 0.0;
                        $tax = $order ? (float) $order->tax : 0.0;
                    @endphp
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/30 transition-colors">
                        <td class="px-5 py-4 font-mono font-bold text-indigo-600 dark:text-indigo-400">
                            <a href="{{ route('finance.payments.show', $payment->id) }}" class="hover:underline block">
                                {{ $payment->reference_number }}
                            </a>
                            @if($payment->transaction_id)
                                <span class="text-[10px] text-slate-500 dark:text-slate-400 block font-normal">TXN: {{ $payment->transaction_id }}</span>
                            @endif
                        </td>
                        <td class="px-5 py-4">
                            <div class="font-bold text-slate-900 dark:text-white">{{ $payment->user?->name ?? 'Candidate User' }}</div>
                            <span class="text-[11px] text-slate-600 dark:text-slate-400 font-mono">{{ $payment->user?->email }}</span>
                        </td>
                        <td class="px-5 py-4">
                            @if($order)
                                @foreach($order->items as $item)
                                    <div class="font-semibold text-slate-900 dark:text-white">{{ $item->product?->title ?? 'Package Item' }}</div>
                                    @if($item->product?->test)
                                        <span class="text-[10px] font-semibold text-emerald-700 dark:text-emerald-400">🎯 Mock Test Product</span>
                                    @endif
                                @endforeach
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="px-5 py-4 space-y-0.5">
                            <div class="font-black text-slate-900 dark:text-white text-xs">
                                IDR {{ number_format($payment->amount) }}
                            </div>
                            <div class="text-[10px] text-slate-600 dark:text-slate-400">
                                Base: IDR {{ number_format($basePrice) }}
                                @if($discount > 0)
                                    &bull; Disc: -{{ number_format($discount) }}
                                @endif
                                &bull; Tax: +{{ number_format($tax) }}
                            </div>
                        </td>
                        <td class="px-5 py-4 font-mono text-slate-700 dark:text-slate-400">{{ $payment->invoice?->invoice_number ?? '—' }}</td>
                        <td class="px-5 py-4">
                            @if($payment->proof_path)
                                <a href="{{ route('finance.payments.proof', $payment->id) }}" target="_blank" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[10px] font-bold bg-emerald-100 dark:bg-emerald-950/50 text-emerald-800 dark:text-emerald-400 border border-emerald-300 dark:border-emerald-900/30 hover:underline">
                                    <svg class="w-3 h-3 text-emerald-700 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                                    </svg>
                                    <span>View Proof</span>
                                </a>
                            @else
                                <span class="px-2.5 py-1 rounded-lg text-[10px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border border-slate-300 dark:border-slate-700">
                                    No File
                                </span>
                            @endif
                        </td>
                        <td class="px-5 py-4">
                            <x-status-badge :status="$payment->status" />
                        </td>
                        <td class="px-5 py-4 text-slate-600 dark:text-slate-400">{{ $payment->created_at?->format('d M Y, H:i') }}</td>
                        <td class="px-5 py-4 text-right">
                            <a href="{{ route('finance.payments.show', $payment->id) }}" class="px-3.5 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-[11px] font-bold transition-all shadow-md shadow-indigo-600/20">
                                Review &rarr;
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="p-12 text-center text-slate-600 dark:text-slate-400">
                            <svg class="w-10 h-10 mx-auto mb-2 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <p class="font-bold text-slate-800 dark:text-slate-200 text-sm">No payment records found matching the selected filters.</p>
                            <p class="text-xs text-slate-500 mt-1">No transactions match the selected filters. Try clearing or adjusting search, status, date range, or package filters.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($payments->hasPages())
        <div class="p-4 border-t border-slate-200 dark:border-slate-800">
            {{ $payments->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
