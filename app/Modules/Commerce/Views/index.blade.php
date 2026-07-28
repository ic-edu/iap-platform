<x-admin-layout>
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-white">Commerce &amp; Finance Management</h1>
            <p class="text-xs text-slate-400">Manage assessment vouchers, test pricing packages, and payment transaction logs.</p>
        </div>
        <button onclick="document.getElementById('create-voucher-modal').classList.remove('hidden')" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-lg shadow transition-colors">
            + Create Discount Voucher
        </button>
    </div>

    <!-- Status Alert -->
    @if (session('status'))
        <div class="mb-6 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-medium">
            ✅ {{ session('status') }}
        </div>
    @endif

    <!-- Active Vouchers Grid -->
    <div class="mb-8">
        <h2 class="text-sm font-bold text-white mb-3">Active Promotional Vouchers</h2>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            @foreach($vouchers as $v)
                <div class="p-4 bg-slate-900 border border-slate-800 rounded-xl flex items-center justify-between">
                    <div>
                        <span class="font-mono font-bold text-indigo-400 text-sm block">{{ $v['code'] }}</span>
                        <span class="text-xs text-slate-400 mt-0.5 block">{{ $v['discount'] }} | {{ $v['uses'] }} redemptions</span>
                    </div>
                    <span class="px-2 py-0.5 text-xs font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 rounded">
                        {{ $v['status'] }}
                    </span>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Transactions Table -->
    <div class="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden shadow-sm">
        <div class="p-4 border-b border-slate-800">
            <h2 class="text-sm font-bold text-white">Recent Payment Transactions</h2>
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
            <tbody class="divide-y divide-slate-800/60">
                @foreach($transactions as $txn)
                    <tr>
                        <td class="p-4 font-mono text-xs font-bold text-slate-400">{{ $txn['id'] }}</td>
                        <td class="p-4 font-semibold text-white text-xs">{{ $txn['user'] }}</td>
                        <td class="p-4 text-xs text-indigo-400 font-medium">{{ $txn['package'] }}</td>
                        <td class="p-4 font-bold text-emerald-400">{{ $txn['amount'] }}</td>
                        <td class="p-4">
                            <span class="px-2.5 py-0.5 text-xs font-bold rounded bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                {{ $txn['status'] }}
                            </span>
                        </td>
                        <td class="p-4 text-right text-xs text-slate-400">{{ $txn['date'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <!-- Create Voucher Modal -->
    <div id="create-voucher-modal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm hidden flex items-center justify-center p-4 z-50">
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-6 max-w-md w-full shadow-2xl">
            <h2 class="text-lg font-bold text-white mb-4">Create Voucher Code</h2>
            <form action="{{ route('admin.commerce.vouchers.store') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">Voucher Code *</label>
                    <input type="text" name="code" required placeholder="e.g. PROMO2026" class="w-full p-2.5 bg-slate-950 border border-slate-800 rounded-lg text-white text-xs font-mono uppercase">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">Discount Amount / Percentage *</label>
                    <input type="text" name="discount" required placeholder="e.g. 50% OFF or $10 OFF" class="w-full p-2.5 bg-slate-950 border border-slate-800 rounded-lg text-white text-xs">
                </div>
                <div class="flex justify-end gap-3 pt-4 border-t border-slate-800">
                    <button type="button" onclick="document.getElementById('create-voucher-modal').classList.add('hidden')" class="px-4 py-2 bg-slate-800 text-slate-300 text-xs rounded-lg">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white font-semibold text-xs rounded-lg shadow">Create Voucher</button>
                </div>
            </form>
        </div>
    </div>
</x-admin-layout>
