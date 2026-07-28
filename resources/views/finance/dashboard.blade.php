<x-admin-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs font-semibold text-emerald-400 uppercase tracking-wider">Financial &amp; Revenue Analytics</p>
                <h1 class="text-2xl font-bold tracking-tight text-white mt-1">Finance Workspace Dashboard</h1>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.commerce.index') }}" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-semibold rounded-lg shadow transition-colors">
                    🛍️ Commerce &amp; Billing
                </a>
            </div>
        </div>
    </x-slot>

    <!-- Financial KPI Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 mb-8">
        <div class="p-5 bg-slate-900 border border-slate-800 rounded-xl">
            <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 block">Gross Revenue Report</span>
            <span class="text-3xl font-black text-emerald-400 mt-1 block">{{ $grossRevenue }}</span>
            <span class="text-xs text-slate-500 mt-1 block">↑ 12.4% vs last month</span>
        </div>
        <div class="p-5 bg-slate-900 border border-slate-800 rounded-xl">
            <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 block">Payment Transactions</span>
            <span class="text-3xl font-black text-white mt-1 block">{{ $completedTransactionsCount }}</span>
            <span class="text-xs text-slate-500 mt-1 block">Completed online payments</span>
        </div>
        <div class="p-5 bg-slate-900 border border-slate-800 rounded-xl">
            <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 block">Invoices Issued</span>
            <span class="text-3xl font-black text-indigo-400 mt-1 block">{{ $invoicesIssuedCount }}</span>
            <span class="text-xs text-slate-500 mt-1 block">Tax receipts generated</span>
        </div>
        <div class="p-5 bg-slate-900 border border-slate-800 rounded-xl">
            <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 block">Active Promotional Vouchers</span>
            <span class="text-3xl font-black text-amber-400 mt-1 block">{{ count($vouchers) }}</span>
            <span class="text-xs text-slate-500 mt-1 block">Promotions live</span>
        </div>
    </div>

    <!-- Payment & Invoice Transactions Table -->
    <div class="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden shadow-sm">
        <div class="p-4 border-b border-slate-800 flex items-center justify-between">
            <h3 class="text-sm font-bold text-white">Recent Payment Transactions &amp; Invoices</h3>
            <a href="{{ route('admin.commerce.index') }}" class="text-xs text-emerald-400 font-semibold hover:underline">View All Transactions →</a>
        </div>
        <table class="w-full text-left text-sm text-slate-300">
            <thead class="bg-slate-950 text-xs uppercase text-slate-400 border-b border-slate-800">
                <tr>
                    <th class="p-4">Txn Ref</th>
                    <th class="p-4">Customer Email</th>
                    <th class="p-4">Package</th>
                    <th class="p-4">Amount</th>
                    <th class="p-4">Payment Status</th>
                    <th class="p-4 text-right">Date</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60 font-mono text-xs">
                @foreach ($transactions as $txn)
                    <tr>
                        <td class="p-4 font-bold text-slate-400">{{ $txn['id'] }}</td>
                        <td class="p-4 font-semibold text-white font-sans text-xs">{{ $txn['user'] }}</td>
                        <td class="p-4 text-xs text-indigo-400 font-medium font-sans">{{ $txn['package'] }}</td>
                        <td class="p-4 font-bold text-emerald-400">{{ $txn['amount'] }}</td>
                        <td class="p-4">
                            <span class="px-2.5 py-0.5 text-xs font-bold rounded bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                {{ $txn['status'] }}
                            </span>
                        </td>
                        <td class="p-4 text-right text-xs text-slate-400 font-sans">{{ $txn['date'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-admin-layout>
