@extends('layouts.admin')

@section('content')
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-white">Commerce &amp; Finance Management</h1>
            <p class="text-xs text-slate-400">Manage assessment packages, product catalog, vouchers, payment transactions, and financial analytics.</p>
        </div>
        <div class="flex items-center gap-3">
            <button onclick="document.getElementById('create-product-modal').classList.remove('hidden')" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-lg shadow transition-colors flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                <span>+ Create Assessment Package</span>
            </button>
            <button onclick="document.getElementById('create-voucher-modal').classList.remove('hidden')" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold rounded-lg shadow transition-colors flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                </svg>
                <span>+ Create Voucher</span>
            </button>
        </div>
    </div>

    <!-- Status Alert -->
    @if (session('status'))
        <div class="mb-6 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-medium flex items-center gap-2">
            <svg class="w-4 h-4 text-emerald-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <!-- Validation Errors Alert -->
    @if ($errors->any())
        <div class="mb-6 p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-400 text-xs font-medium space-y-1">
            <span class="font-bold block">Please resolve the following errors:</span>
            <ul class="list-disc pl-5 space-y-0.5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Financial Reports & Key Metrics Hub -->
    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 mb-8">
        <div class="p-4 bg-slate-900 border border-slate-800 rounded-xl">
            <span class="text-[10px] font-bold uppercase text-slate-400 block">Gross Revenue Report</span>
            <span class="text-2xl font-extrabold text-emerald-400 mt-1 block">Rp {{ number_format($grossRevenue, 0, ',', '.') }}</span>
            <span class="text-xs text-slate-500 mt-1 block">Confirmed paid transactions</span>
        </div>
        <div class="p-4 bg-slate-900 border border-slate-800 rounded-xl">
            <span class="text-[10px] font-bold uppercase text-slate-400 block">Payment Transactions</span>
            <span class="text-2xl font-extrabold text-white mt-1 block">{{ number_format($paymentTransactionsCount) }}</span>
            <span class="text-xs text-slate-500 mt-1 block">Total transactions recorded</span>
        </div>
        <div class="p-4 bg-slate-900 border border-slate-800 rounded-xl">
            <span class="text-[10px] font-bold uppercase text-slate-400 block">Invoices Issued</span>
            <span class="text-2xl font-extrabold text-indigo-400 mt-1 block">{{ number_format($invoicesCount) }}</span>
            <span class="text-xs text-slate-500 mt-1 block">Billing invoices generated</span>
        </div>
        <div class="p-4 bg-slate-900 border border-slate-800 rounded-xl">
            <span class="text-[10px] font-bold uppercase text-slate-400 block">Active Vouchers</span>
            <span class="text-2xl font-extrabold text-amber-400 mt-1 block">{{ number_format($couponsCount) }}</span>
            <span class="text-xs text-slate-500 mt-1 block">Promotional codes available</span>
        </div>
    </div>

    <!-- Assessment Package & Product Catalog Section -->
    <div class="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden shadow-sm mb-8">
        <div class="p-4 border-b border-slate-800 flex items-center justify-between">
            <div>
                <h2 class="text-sm font-bold text-white">Assessment Package &amp; Product Catalog</h2>
                <p class="text-xs text-slate-400 mt-0.5">Manage purchasable assessment packages, pricing tiers, and optional test instance bindings.</p>
            </div>
            <span class="text-xs text-slate-400 font-mono">Total Products: {{ $products->total() }}</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-300">
                <thead class="bg-slate-950 text-xs uppercase text-slate-400 border-b border-slate-800">
                    <tr>
                        <th class="p-4">Package / Product Name</th>
                        <th class="p-4">Product Type</th>
                        <th class="p-4">Assessment Family</th>
                        <th class="p-4">Base Price</th>
                        <th class="p-4">Linked Test</th>
                        <th class="p-4">Status</th>
                        <th class="p-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60 font-sans text-xs">
                    @forelse($products as $product)
                        <tr>
                            <td class="p-4">
                                <span class="font-bold text-white block text-sm">{{ $product->title }}</span>
                                <span class="text-[11px] text-slate-500 font-mono">{{ $product->slug }}</span>
                            </td>
                            <td class="p-4">
                                <span class="px-2 py-0.5 text-[10px] font-extrabold uppercase rounded bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                                    {{ strtoupper($product->product_type) }}
                                </span>
                            </td>
                            <td class="p-4">
                                @if($product->getEffectiveFamily())
                                    <span class="px-2 py-0.5 text-[10px] font-extrabold uppercase rounded bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                        {{ strtoupper($product->getEffectiveFamily()) }}
                                    </span>
                                @else
                                    <span class="text-slate-500 italic">None</span>
                                @endif
                            </td>
                            <td class="p-4 font-mono font-bold text-emerald-400 text-sm">
                                Rp {{ number_format($product->price, 0, ',', '.') }}
                            </td>
                            <td class="p-4">
                                @if($product->test)
                                    <span class="font-medium text-slate-200 block">{{ $product->test->title }}</span>
                                    <span class="text-[10px] text-emerald-400 font-mono">Linked Test ({{ strtoupper(is_object($product->test->assessment_mode) ? $product->test->assessment_mode->value : $product->test->assessment_mode) }})</span>
                                @else
                                    <span class="text-slate-400 italic">Not yet assigned (Package)</span>
                                @endif
                            </td>
                            <td class="p-4">
                                <span class="px-2.5 py-0.5 text-xs font-bold rounded {{ $product->is_active ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-slate-800 text-slate-400 border border-slate-700' }}">
                                    {{ $product->is_active ? 'ACTIVE' : 'INACTIVE' }}
                                </span>
                            </td>
                            <td class="p-4 text-right">
                                <div class="inline-flex items-center gap-2">
                                    <form action="{{ route('admin.commerce.products.toggle', $product->id) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="px-2.5 py-1 text-xs font-semibold rounded {{ $product->is_active ? 'bg-amber-500/10 text-amber-400 hover:bg-amber-500/20 border border-amber-500/20' : 'bg-emerald-500/10 text-emerald-400 hover:bg-emerald-500/20 border border-emerald-500/20' }} transition-colors">
                                            {{ $product->is_active ? 'Deactivate' : 'Activate' }}
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-8 text-center text-slate-500">No assessment packages or products currently found. Use the "+ Create Assessment Package" button above to add one.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($products->hasPages())
            <div class="p-4 border-t border-slate-800">
                {{ $products->links() }}
            </div>
        @endif
    </div>

    <!-- Active Vouchers Grid -->
    <div class="mb-8">
        <h2 class="text-sm font-bold text-white mb-3">Active Promotional Vouchers</h2>
        @if($coupons->isEmpty())
        <div class="p-6 bg-slate-900 border border-dashed border-slate-800 rounded-xl text-center">
            <p class="text-xs text-slate-400">No active promotional vouchers found. Use the button above to create one.</p>
        </div>
        @else
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            @foreach($coupons as $v)
                <div class="p-4 bg-slate-900 border border-slate-800 rounded-xl flex items-center justify-between">
                    <div>
                        <span class="font-mono font-bold text-indigo-400 text-sm block">{{ $v->code }}</span>
                        <span class="text-xs text-slate-400 mt-0.5 block">{{ $v->value }}% OFF &bull; {{ $v->used_count }} redemptions</span>
                    </div>
                    <span class="px-2 py-0.5 text-xs font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 rounded">
                        {{ $v->is_active ? 'ACTIVE' : 'INACTIVE' }}
                    </span>
                </div>
            @endforeach
        </div>
        @endif
    </div>

    <!-- Payment Reports & Transactions Table -->
    <div class="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden shadow-sm">
        <div class="p-4 border-b border-slate-800 flex items-center justify-between">
            <h2 class="text-sm font-bold text-white">Payment &amp; Invoice Transaction Reports</h2>
            <span class="text-xs text-slate-400 font-mono">Gateway: Live Webhooks</span>
        </div>
        <div class="overflow-x-auto">
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
                    @forelse($transactions as $txn)
                        @php
                            $userEmail = $txn->user?->email ?? $txn->invoice?->order?->user?->email ?? 'N/A';
                            $productTitle = $txn->invoice?->order?->items?->first()?->product?->title ?? 'Assessment Order';
                            $statusValue = $txn->status instanceof \BackedEnum ? $txn->status->value : (string) $txn->status;
                        @endphp
                        <tr>
                            <td class="p-4 font-bold text-slate-400">{{ $txn->reference_number ?? $txn->id }}</td>
                            <td class="p-4 font-semibold text-white font-sans text-xs">{{ $userEmail }}</td>
                            <td class="p-4 text-xs text-indigo-400 font-medium font-sans">{{ $productTitle }}</td>
                            <td class="p-4 font-bold text-emerald-400">Rp {{ number_format($txn->amount, 0, ',', '.') }}</td>
                            <td class="p-4">
                                <span class="px-2.5 py-0.5 text-xs font-bold rounded {{ in_array(strtolower($statusValue), ['success', 'paid']) ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-amber-500/10 text-amber-400 border border-amber-500/20' }}">
                                    {{ strtoupper($statusValue) }}
                                </span>
                            </td>
                            <td class="p-4 text-right text-xs text-slate-400 font-sans">{{ $txn->confirmed_at ? \Carbon\Carbon::parse($txn->confirmed_at)->format('d M Y, H:i') : $txn->created_at?->format('d M Y, H:i') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-8 text-center text-slate-500 font-sans">No live payment transactions on record.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Create Assessment Package Modal -->
    <div id="create-product-modal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm hidden flex items-center justify-center p-4 z-50 overflow-y-auto">
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-6 max-w-lg w-full shadow-2xl my-8">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-bold text-white">Create Assessment Package</h2>
                <button type="button" onclick="document.getElementById('create-product-modal').classList.add('hidden')" class="text-slate-400 hover:text-white">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <form action="{{ route('admin.commerce.products.store') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">Package Title *</label>
                    <input type="text" name="title" required placeholder="e.g. TOEIC Institutional Mock Test Package" class="w-full p-2.5 bg-slate-950 border border-slate-800 rounded-lg text-white text-xs">
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">Product Type *</label>
                        <select name="product_type" required class="w-full p-2.5 bg-slate-950 border border-slate-800 rounded-lg text-white text-xs">
                            <option value="assessment" selected>Assessment Package</option>
                            <option value="course">Course</option>
                            <option value="membership">Membership</option>
                            <option value="placement_test">Placement Test</option>
                            <option value="corporate_training">Corporate Training</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">Assessment Family *</label>
                        <select name="assessment_family" class="w-full p-2.5 bg-slate-950 border border-slate-800 rounded-lg text-white text-xs">
                            <option value="">Select Family...</option>
                            @foreach($assessmentFamilies as $family)
                                <option value="{{ $family->value }}">{{ $family->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">Base Price (IDR) *</label>
                        <input type="number" name="price" min="0" step="1000" required placeholder="e.g. 750000" class="w-full p-2.5 bg-slate-950 border border-slate-800 rounded-lg text-white text-xs font-mono">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">Optional Linked Test</label>
                        <select name="test_id" class="w-full p-2.5 bg-slate-950 border border-slate-800 rounded-lg text-white text-xs">
                            <option value="">-- None (Abstract Package) --</option>
                            @foreach($tests as $test)
                                <option value="{{ $test->id }}">{{ $test->title }} ({{ strtoupper(is_object($test->test_type) ? $test->test_type->value : $test->test_type) }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">Description</label>
                    <textarea name="description" rows="3" placeholder="Institutional assessment package details..." class="w-full p-2.5 bg-slate-950 border border-slate-800 rounded-lg text-white text-xs"></textarea>
                </div>
                <div class="flex items-center gap-6 pt-2">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="is_active" value="1" checked class="rounded bg-slate-950 border-slate-800 text-indigo-600">
                        <span class="text-xs text-slate-300 font-medium">Active (Purchasable in Store)</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="is_featured" value="1" class="rounded bg-slate-950 border-slate-800 text-indigo-600">
                        <span class="text-xs text-slate-300 font-medium">Featured</span>
                    </label>
                </div>
                <div class="flex justify-end gap-3 pt-4 border-t border-slate-800">
                    <button type="button" onclick="document.getElementById('create-product-modal').classList.add('hidden')" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs rounded-lg">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs rounded-lg shadow">Save Package</button>
                </div>
            </form>
        </div>
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
                    <label class="block text-xs font-medium text-slate-300 mb-1">Discount Percentage (%) *</label>
                    <input type="number" name="discount" min="1" max="100" required placeholder="e.g. 50" class="w-full p-2.5 bg-slate-950 border border-slate-800 rounded-lg text-white text-xs">
                </div>
                <div class="flex justify-end gap-3 pt-4 border-t border-slate-800">
                    <button type="button" onclick="document.getElementById('create-voucher-modal').classList.add('hidden')" class="px-4 py-2 bg-slate-800 text-slate-300 text-xs rounded-lg">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white font-semibold text-xs rounded-lg shadow">Create Voucher</button>
                </div>
            </form>
        </div>
    </div>
@endsection
