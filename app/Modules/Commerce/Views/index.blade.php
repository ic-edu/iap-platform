@extends('layouts.admin')

@section('content')
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Assessment Package &amp; Commercial Catalog</h1>
            <p class="text-xs text-slate-500 dark:text-slate-400">Operational Admin management for institutional assessment packages, initial pricing, promotional vouchers, and SA price proposals.</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.commerce.campaigns.create') }}" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-lg shadow transition-colors flex items-center gap-1.5 cursor-pointer">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                <span>+ Generate Voucher Campaign</span>
            </a>
            <button onclick="document.getElementById('create-product-modal').classList.remove('hidden')" class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-semibold rounded-lg shadow transition-colors flex items-center gap-1.5 cursor-pointer">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                <span>+ Create Package</span>
            </button>
            <button onclick="document.getElementById('create-voucher-modal').classList.remove('hidden')" class="px-3 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs font-semibold rounded-lg shadow transition-colors flex items-center gap-1.5 cursor-pointer">
                <span>+ Legacy Voucher</span>
            </button>
        </div>
    </div>

    <!-- Status Alert -->
    @if (session('status'))
        <div class="mb-6 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-400 text-xs font-medium flex items-center gap-2">
            <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <!-- Validation Errors Alert -->
    @if ($errors->any())
        <div class="mb-6 p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-600 dark:text-rose-400 text-xs font-medium space-y-1">
            <span class="font-bold block">Please resolve the following errors:</span>
            <ul class="list-disc pl-5 space-y-0.5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Commercial Metrics Hub -->
    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 mb-8">
        <div class="p-4 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-sm">
            <span class="text-[10px] font-bold uppercase text-slate-500 dark:text-slate-400 block">Total Packages</span>
            <span class="text-2xl font-extrabold text-slate-900 dark:text-white mt-1 block">{{ number_format($totalProductsCount) }}</span>
            <span class="text-xs text-slate-400 dark:text-slate-500 mt-1 block">Commercial catalog products</span>
        </div>
        <div class="p-4 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-sm">
            <span class="text-[10px] font-bold uppercase text-slate-500 dark:text-slate-400 block">Active Packages</span>
            <span class="text-2xl font-extrabold text-emerald-600 dark:text-emerald-400 mt-1 block">{{ number_format($activePackagesCount) }}</span>
            <span class="text-xs text-slate-400 dark:text-slate-500 mt-1 block">Live in Candidate Store</span>
        </div>
        <div class="p-4 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-sm">
            <span class="text-[10px] font-bold uppercase text-slate-500 dark:text-slate-400 block">Active Vouchers</span>
            <span class="text-2xl font-extrabold text-amber-600 dark:text-amber-400 mt-1 block">{{ number_format($couponsCount) }}</span>
            <span class="text-xs text-slate-400 dark:text-slate-500 mt-1 block">Promotional codes available</span>
        </div>
        <div class="p-4 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-sm">
            <span class="text-[10px] font-bold uppercase text-slate-500 dark:text-slate-400 block">Pending Price Proposals</span>
            <span class="text-2xl font-extrabold text-indigo-600 dark:text-indigo-400 mt-1 block">{{ number_format($pendingPriceChangeCount) }}</span>
            <span class="text-xs text-slate-400 dark:text-slate-500 mt-1 block">Awaiting SA review</span>
        </div>
    </div>

    <!-- Assessment Package & Product Catalog Section -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl overflow-hidden shadow-sm mb-8">
        <div class="p-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
            <div>
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">Assessment Package &amp; Product Catalog</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Manage purchasable assessment packages, active states, and propose price changes for Super Admin approval.</p>
            </div>
            <span class="text-xs text-slate-400 dark:text-slate-500 font-mono">Total Products: {{ $products->total() }}</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-600 dark:text-slate-300">
                <thead class="bg-slate-50 dark:bg-slate-950 text-xs uppercase text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
                    <tr>
                        <th class="p-4">Package / Product Name</th>
                        <th class="p-4">Product Type</th>
                        <th class="p-4">Assessment Family</th>
                        <th class="p-4">Current Price</th>
                        <th class="p-4">Linked Test</th>
                        <th class="p-4">Status</th>
                        <th class="p-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-sans text-xs">
                    @forelse($products as $product)
                        <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-950/40 transition-colors">
                            <td class="p-4">
                                <span class="font-bold text-slate-900 dark:text-white block text-sm">{{ $product->title }}</span>
                                <span class="text-[11px] text-slate-400 dark:text-slate-500 font-mono">{{ $product->slug }}</span>
                                @if($product->pendingPriceChangeRequest)
                                    <div class="mt-1">
                                        <span class="px-2 py-0.5 text-[10px] font-bold rounded bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20 inline-flex items-center gap-1">
                                            <span>⏳</span> Price Change Pending SA Approval (Proposed: IDR {{ number_format($product->pendingPriceChangeRequest->proposed_price) }})
                                        </span>
                                    </div>
                                @endif
                            </td>
                            <td class="p-4">
                                <span class="px-2 py-0.5 text-[10px] font-extrabold uppercase rounded bg-indigo-500/10 text-indigo-700 dark:text-indigo-400 border border-indigo-500/20">
                                    {{ strtoupper($product->product_type) }}
                                </span>
                            </td>
                            <td class="p-4">
                                @if($product->getEffectiveFamily())
                                    <span class="px-2 py-0.5 text-[10px] font-extrabold uppercase rounded bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-500/20">
                                        {{ strtoupper($product->getEffectiveFamily()) }}
                                    </span>
                                @else
                                    <span class="text-slate-400 dark:text-slate-500 italic">None</span>
                                @endif
                            </td>
                            <td class="p-4 font-mono font-bold text-emerald-600 dark:text-emerald-400 text-sm">
                                IDR {{ number_format($product->price) }}
                            </td>
                            <td class="p-4">
                                @if($product->test)
                                    <span class="font-medium text-slate-700 dark:text-slate-200 block">{{ $product->test->title }}</span>
                                    <span class="text-[10px] text-emerald-600 dark:text-emerald-400 font-mono">Linked Test ({{ strtoupper(is_object($product->test->assessment_mode) ? $product->test->assessment_mode->value : $product->test->assessment_mode) }})</span>
                                @else
                                    <span class="text-slate-400 dark:text-slate-500 italic">Not yet assigned (Package)</span>
                                @endif
                            </td>
                            <td class="p-4">
                                <span class="px-2.5 py-0.5 text-xs font-bold rounded {{ $product->is_active ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20' : 'bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 border border-slate-200 dark:border-slate-700' }}">
                                    {{ $product->is_active ? 'ACTIVE' : 'INACTIVE' }}
                                </span>
                            </td>
                            <td class="p-4 text-right">
                                <div class="inline-flex items-center gap-1.5">
                                    <button type="button" onclick="openProposePriceModal('{{ $product->id }}', '{{ addslashes($product->title) }}', '{{ $product->price }}')" class="px-2.5 py-1 text-xs font-semibold rounded bg-amber-500/10 text-amber-700 dark:text-amber-400 hover:bg-amber-500/20 border border-amber-500/20 transition-colors cursor-pointer" title="Propose Price Change to Super Admin">
                                        Propose Price
                                    </button>
                                    <button type="button" onclick="openEditMetadataModal('{{ $product->id }}', '{{ addslashes($product->title) }}', '{{ $product->assessment_family }}', '{{ addslashes($product->description ?? '') }}', '{{ $product->test_id }}')" class="px-2.5 py-1 text-xs font-semibold rounded bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-500/10 dark:hover:bg-indigo-500/20 text-indigo-700 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-500/20 transition-colors cursor-pointer" title="Edit Metadata">
                                        Edit
                                    </button>
                                    <form action="{{ route('admin.commerce.products.toggle', $product->id) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="px-2.5 py-1 text-xs font-semibold rounded {{ $product->is_active ? 'bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300 dark:hover:bg-slate-700' : 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-500/20 border border-emerald-500/20' }} transition-colors cursor-pointer">
                                            {{ $product->is_active ? 'Deactivate' : 'Activate' }}
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-8 text-center text-slate-400 dark:text-slate-500">No assessment packages or products currently found. Use the "+ Create Package" button above to add one.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($products->hasPages())
            <div class="p-4 border-t border-slate-200 dark:border-slate-800">
                {{ $products->links() }}
            </div>
        @endif
    </div>

    <!-- Voucher Campaigns (Primary Generator Engine) -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl overflow-hidden shadow-sm mb-8">
        <div class="p-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
            <div>
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">Voucher Campaigns (Primary Generator Engine)</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Assessment-family governed promotional campaigns with transactional unique code generation.</p>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-600 dark:text-slate-300">
                <thead class="bg-slate-50 dark:bg-slate-950 text-xs uppercase text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
                    <tr>
                        <th class="p-4">Campaign Name</th>
                        <th class="p-4">Assessment Family</th>
                        <th class="p-4">Product Scope</th>
                        <th class="p-4">Discount</th>
                        <th class="p-4 text-center">Codes</th>
                        <th class="p-4 text-center">Status</th>
                        <th class="p-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-sans text-xs">
                    @forelse($campaigns as $camp)
                        <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-950/40 transition-colors">
                            <td class="p-4 font-bold text-slate-900 dark:text-white">
                                <a href="{{ route('admin.commerce.campaigns.show', $camp->id) }}" class="hover:text-indigo-600 dark:hover:text-indigo-400 transition">
                                    {{ $camp->name }}
                                </a>
                                <span class="text-[11px] text-slate-400 dark:text-slate-500 block font-mono">{{ $camp->valid_from ? $camp->valid_from->format('d M Y') : 'Now' }} &rarr; {{ $camp->valid_until ? $camp->valid_until->format('d M Y') : 'Finite' }}</span>
                            </td>
                            <td class="p-4">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-indigo-500/10 text-indigo-700 dark:text-indigo-300 border border-indigo-500/20">
                                    {{ strtoupper($camp->assessment_family) }}
                                </span>
                            </td>
                            <td class="p-4 text-slate-500 dark:text-slate-400">
                                {{ $camp->scope_mode === 'all_products_in_family' ? 'All Family Products' : 'Selected Products' }}
                            </td>
                            <td class="p-4 font-bold text-indigo-600 dark:text-indigo-400">
                                {{ $camp->discount_type === 'percentage' ? number_format($camp->discount_value, 0) . '%' : 'IDR ' . number_format($camp->discount_value) }} OFF
                            </td>
                            <td class="p-4 text-center font-mono font-bold text-slate-900 dark:text-white">
                                {{ $camp->coupons_count }}
                            </td>
                            <td class="p-4 text-center">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $camp->is_active ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-300 border border-emerald-500/20' : 'bg-rose-500/10 text-rose-600 dark:text-rose-300 border border-rose-500/20' }}">
                                    {{ $camp->getEffectiveState() }}
                                </span>
                            </td>
                            <td class="p-4 text-right space-x-2">
                                <a href="{{ route('admin.commerce.campaigns.show', $camp->id) }}" class="text-xs font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300">
                                    Manage &rarr;
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-6 text-center text-slate-400 dark:text-slate-500">
                                No voucher campaigns created yet. Use the "+ Generate Voucher Campaign" button above to start.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Standalone / Legacy Promotional Vouchers Grid -->
    <div class="mb-8">
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white">Standalone / Legacy Promotional Vouchers</h2>
            <span class="text-xs text-slate-500 dark:text-slate-400">{{ $coupons->count() }} configured voucher(s)</span>
        </div>
        @if($coupons->isEmpty())
        <div class="p-6 bg-white dark:bg-slate-900 border border-dashed border-slate-200 dark:border-slate-800 rounded-xl text-center">
            <p class="text-xs text-slate-400 dark:text-slate-500">No promotional vouchers found. Use the button above to create one.</p>
        </div>
        @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($coupons as $v)
                @php
                    $state = $v->getEffectiveState();
                    $badgeClasses = match($state) {
                        'ACTIVE'    => 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20',
                        'SCHEDULED' => 'bg-sky-500/10 text-sky-600 dark:text-sky-400 border-sky-500/20',
                        'EXPIRED'   => 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border-rose-500/20',
                        default     => 'bg-slate-500/10 text-slate-600 dark:text-slate-400 border-slate-500/20',
                    };
                @endphp
                <div class="p-4 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl flex flex-col justify-between space-y-3 relative group shadow-sm">
                    <div class="space-y-2">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <span class="font-mono font-black text-indigo-600 dark:text-indigo-400 text-base tracking-wider block">{{ $v->code }}</span>
                                <span class="text-xs font-bold text-slate-800 dark:text-slate-200 mt-0.5 block">{{ $v->value }}% OFF</span>
                            </div>
                            <span class="px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider rounded border {{ $badgeClasses }}">
                                {{ $state }}
                            </span>
                        </div>

                        {{-- Validity Period Display --}}
                        <div class="p-2.5 rounded-lg bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800/80 text-[11px] space-y-1">
                            <div class="flex items-center justify-between text-slate-500 dark:text-slate-400">
                                <span>Validity Period:</span>
                                <span class="font-semibold text-slate-700 dark:text-slate-300">
                                    @if($state === 'ACTIVE')
                                        {{ $v->valid_from ? $v->valid_from->format('d M Y') : 'Now' }} &rarr; {{ $v->valid_until ? $v->valid_until->format('d M Y') : 'Finite' }}
                                    @elseif($state === 'SCHEDULED')
                                        Starts {{ $v->valid_from ? $v->valid_from->format('d M Y, H:i') : '-' }}
                                    @elseif($state === 'EXPIRED')
                                        Expired {{ $v->valid_until ? $v->valid_until->format('d M Y') : '-' }}
                                    @else
                                        {{ $v->valid_until ? $v->validity_display : 'Validity not configured' }}
                                    @endif
                                </span>
                            </div>
                            <div class="flex items-center justify-between text-slate-500 dark:text-slate-400">
                                <span>Redemptions:</span>
                                <span class="font-mono font-medium text-slate-700 dark:text-slate-300">{{ $v->used_count }} / {{ $v->usage_limit }} used</span>
                            </div>
                        </div>
                    </div>

                    {{-- Actions Toolbar --}}
                    <div class="pt-2 border-t border-slate-200 dark:border-slate-800/80 flex items-center justify-between gap-2">
                        <form action="{{ route('admin.commerce.vouchers.toggle', $v->id) }}" method="POST" class="inline">
                            @csrf
                            <button type="submit" class="px-2.5 py-1 text-[11px] font-semibold rounded transition-colors {{ $v->is_active ? 'text-amber-600 dark:text-amber-400 hover:bg-amber-500/10' : 'text-emerald-600 dark:text-emerald-400 hover:bg-emerald-500/10' }}">
                                {{ $v->is_active ? 'Deactivate' : 'Activate' }}
                            </button>
                        </form>

                        <div class="flex items-center gap-1.5">
                            <button type="button" onclick="openEditVoucherModal('{{ $v->id }}', '{{ $v->code }}', {{ $v->value }}, '{{ $v->valid_from ? $v->valid_from->format('Y-m-d\TH:i') : '' }}', '{{ $v->valid_until ? $v->valid_until->format('Y-m-d\TH:i') : '' }}', {{ $v->usage_limit }}, {{ $v->is_active ? 'true' : 'false' }})" class="px-2.5 py-1 text-[11px] font-semibold text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 rounded transition-colors cursor-pointer">
                                Edit
                            </button>

                            @if($v->isDeletable())
                                <button type="button" onclick="confirmDeleteVoucher('{{ $v->id }}', '{{ $v->code }}', {{ $v->value }}, {{ $v->used_count }})" class="px-2.5 py-1 text-[11px] font-semibold text-rose-600 dark:text-rose-400 hover:text-rose-700 dark:hover:text-rose-300 hover:bg-rose-500/10 rounded transition-colors cursor-pointer">
                                    Delete
                                </button>
                                <form id="delete-voucher-form-{{ $v->id }}" action="{{ route('admin.commerce.vouchers.destroy', $v->id) }}" method="POST" class="hidden">
                                    @csrf
                                    @method('DELETE')
                                </form>
                            @else
                                <span class="px-2 py-1 text-[10px] text-slate-400 dark:text-slate-500 cursor-not-allowed" title="Voucher has redemption history and cannot be deleted.">
                                    Locked
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        @endif
    </div>

    <!-- Create Assessment Package Modal -->
    <div id="create-product-modal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm hidden flex items-center justify-center p-4 z-50 overflow-y-auto">
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 max-w-lg w-full shadow-2xl my-8">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-bold text-slate-900 dark:text-white">Create Assessment Package</h2>
                <button type="button" onclick="document.getElementById('create-product-modal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 dark:hover:text-white cursor-pointer">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <form action="{{ route('admin.commerce.products.store') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Package Title *</label>
                    <input type="text" name="title" required placeholder="e.g. TOEIC Institutional Mock Test Package" class="w-full p-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-lg text-slate-900 dark:text-white text-xs">
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Product Type *</label>
                        <select name="product_type" required class="w-full p-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-lg text-slate-900 dark:text-white text-xs">
                            <option value="assessment" selected>Assessment Package</option>
                            <option value="course">Course</option>
                            <option value="membership">Membership</option>
                            <option value="placement_test">Placement Test</option>
                            <option value="corporate_training">Corporate Training</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Assessment Family *</label>
                        <select name="assessment_family" class="w-full p-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-lg text-slate-900 dark:text-white text-xs">
                            <option value="">Select Family...</option>
                            @foreach($assessmentFamilies as $family)
                                <option value="{{ $family->value }}">{{ $family->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Initial Base Price (IDR) *</label>
                        <input type="number" name="price" min="0" step="1000" required placeholder="e.g. 750000" class="w-full p-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-lg text-slate-900 dark:text-white text-xs font-mono">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Optional Linked Test</label>
                        <select name="test_id" class="w-full p-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-lg text-slate-900 dark:text-white text-xs">
                            <option value="">-- None (Abstract Package) --</option>
                            @foreach($tests as $test)
                                <option value="{{ $test->id }}">{{ $test->title }} ({{ strtoupper(is_object($test->test_type) ? $test->test_type->value : $test->test_type) }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Description</label>
                    <textarea name="description" rows="3" placeholder="Institutional assessment package details..." class="w-full p-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-lg text-slate-900 dark:text-white text-xs"></textarea>
                </div>
                <div class="flex items-center gap-6 pt-2">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="is_active" value="1" checked class="rounded bg-slate-50 dark:bg-slate-950 border-slate-300 dark:border-slate-800 text-indigo-600">
                        <span class="text-xs text-slate-700 dark:text-slate-300 font-medium">Active (Purchasable in Store)</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="is_featured" value="1" class="rounded bg-slate-50 dark:bg-slate-950 border-slate-300 dark:border-slate-800 text-indigo-600">
                        <span class="text-xs text-slate-700 dark:text-slate-300 font-medium">Featured</span>
                    </label>
                </div>
                <div class="flex justify-end gap-3 pt-4 border-t border-slate-200 dark:border-slate-800">
                    <button type="button" onclick="document.getElementById('create-product-modal').classList.add('hidden')" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs rounded-lg cursor-pointer">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs rounded-lg shadow cursor-pointer">Save Package</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Propose Price Change Modal -->
    <div id="propose-price-modal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm hidden flex items-center justify-center p-4 z-50">
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 max-w-md w-full shadow-2xl">
            <h2 class="text-lg font-bold text-slate-900 dark:text-white mb-1">Propose Price Change</h2>
            <p id="propose-price-modal-title" class="text-xs text-slate-500 dark:text-slate-400 mb-4"></p>
            <form id="propose-price-form" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">Current Price (Snapshot)</label>
                    <input type="text" id="propose-price-current-display" readonly class="w-full p-2.5 bg-slate-100 dark:bg-slate-950/50 border border-slate-200 dark:border-slate-800 rounded-lg text-slate-500 dark:text-slate-400 text-xs font-mono font-bold cursor-not-allowed">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">New Proposed Price (IDR) *</label>
                    <input type="number" name="proposed_price" min="0" step="1000" required placeholder="e.g. 800000" class="w-full p-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-lg text-slate-900 dark:text-white text-xs font-mono">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Reason / Justification *</label>
                    <textarea name="reason" rows="3" required placeholder="Explain why this price change is requested for Super Admin review..." class="w-full p-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-lg text-slate-900 dark:text-white text-xs"></textarea>
                </div>
                <div class="flex justify-end gap-3 pt-4 border-t border-slate-200 dark:border-slate-800">
                    <button type="button" onclick="document.getElementById('propose-price-modal').classList.add('hidden')" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs rounded-lg cursor-pointer">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-amber-600 hover:bg-amber-500 text-white font-semibold text-xs rounded-lg shadow cursor-pointer">Submit Proposal</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Product Metadata Modal -->
    <div id="edit-metadata-modal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm hidden flex items-center justify-center p-4 z-50 overflow-y-auto">
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 max-w-lg w-full shadow-2xl my-8">
            <h2 class="text-lg font-bold text-slate-900 dark:text-white mb-4">Edit Package Metadata</h2>
            <form id="edit-metadata-form" method="POST" class="space-y-4">
                @csrf
                @method('PUT')
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Package Title *</label>
                    <input type="text" name="title" id="edit-metadata-title" required class="w-full p-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-lg text-slate-900 dark:text-white text-xs">
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Assessment Family</label>
                        <select name="assessment_family" id="edit-metadata-family" class="w-full p-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-lg text-slate-900 dark:text-white text-xs">
                            <option value="">Select Family...</option>
                            @foreach($assessmentFamilies as $family)
                                <option value="{{ $family->value }}">{{ $family->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Optional Linked Test</label>
                        <select name="test_id" id="edit-metadata-test-id" class="w-full p-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-lg text-slate-900 dark:text-white text-xs">
                            <option value="">-- None (Abstract Package) --</option>
                            @foreach($tests as $test)
                                <option value="{{ $test->id }}">{{ $test->title }} ({{ strtoupper(is_object($test->test_type) ? $test->test_type->value : $test->test_type) }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Description</label>
                    <textarea name="description" id="edit-metadata-desc" rows="3" class="w-full p-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-lg text-slate-900 dark:text-white text-xs"></textarea>
                </div>
                <div class="flex justify-end gap-3 pt-4 border-t border-slate-200 dark:border-slate-800">
                    <button type="button" onclick="document.getElementById('edit-metadata-modal').classList.add('hidden')" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs rounded-lg cursor-pointer">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs rounded-lg shadow cursor-pointer">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Create Voucher Modal -->
    <div id="create-voucher-modal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm hidden flex items-center justify-center p-4 z-50 overflow-y-auto">
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 max-w-md w-full shadow-2xl my-8">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-bold text-slate-900 dark:text-white">Create Promotional Voucher</h2>
                <button type="button" onclick="document.getElementById('create-voucher-modal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 dark:hover:text-white cursor-pointer">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <form action="{{ route('admin.commerce.vouchers.store') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Voucher Code *</label>
                    <input type="text" name="code" required placeholder="e.g. TOEIC2026" class="w-full p-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-lg text-slate-900 dark:text-white text-xs font-mono uppercase">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Discount (%) *</label>
                        <input type="number" name="discount" min="1" max="100" required placeholder="e.g. 20" class="w-full p-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-lg text-slate-900 dark:text-white text-xs">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Usage Limit</label>
                        <input type="number" name="usage_limit" min="1" value="100" class="w-full p-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-lg text-slate-900 dark:text-white text-xs">
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Valid From</label>
                        <input type="datetime-local" name="valid_from" value="{{ now()->format('Y-m-d\TH:i') }}" class="w-full p-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-lg text-slate-900 dark:text-white text-xs">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Valid Until *</label>
                        <input type="datetime-local" name="valid_until" required class="w-full p-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-lg text-slate-900 dark:text-white text-xs">
                    </div>
                </div>
                <div class="flex items-center gap-2 pt-1">
                    <input type="checkbox" name="is_active" id="create-voucher-active" value="1" checked class="w-4 h-4 rounded bg-slate-50 dark:bg-slate-950 border-slate-300 dark:border-slate-800 text-indigo-600 focus:ring-indigo-500">
                    <label for="create-voucher-active" class="text-xs text-slate-700 dark:text-slate-300">Activate voucher immediately upon creation</label>
                </div>
                <div class="flex justify-end gap-3 pt-4 border-t border-slate-200 dark:border-slate-800">
                    <button type="button" onclick="document.getElementById('create-voucher-modal').classList.add('hidden')" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-xs rounded-lg hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors cursor-pointer">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white font-semibold text-xs rounded-lg shadow hover:bg-indigo-500 transition-colors cursor-pointer">Create Voucher</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Edit Voucher Modal --}}
    <div id="edit-voucher-modal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm hidden flex items-center justify-center p-4 z-50 overflow-y-auto">
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 max-w-md w-full shadow-2xl my-8">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-bold text-slate-900 dark:text-white">Edit Promotional Voucher</h2>
                <button type="button" onclick="document.getElementById('edit-voucher-modal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 dark:hover:text-white cursor-pointer">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <form id="edit-voucher-form" method="POST" class="space-y-4">
                @csrf
                @method('PUT')
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Voucher Code</label>
                    <input type="text" id="edit-voucher-code" disabled class="w-full p-2.5 bg-slate-100 dark:bg-slate-950/50 border border-slate-200 dark:border-slate-800 rounded-lg text-slate-500 dark:text-slate-400 text-xs font-mono uppercase cursor-not-allowed">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Discount (%) *</label>
                        <input type="number" name="discount" id="edit-voucher-discount" min="1" max="100" required class="w-full p-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-lg text-slate-900 dark:text-white text-xs">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Usage Limit</label>
                        <input type="number" name="usage_limit" id="edit-voucher-usage-limit" min="1" class="w-full p-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-lg text-slate-900 dark:text-white text-xs">
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Valid From</label>
                        <input type="datetime-local" name="valid_from" id="edit-voucher-valid-from" class="w-full p-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-lg text-slate-900 dark:text-white text-xs">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Valid Until *</label>
                        <input type="datetime-local" name="valid_until" id="edit-voucher-valid-until" required class="w-full p-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-lg text-slate-900 dark:text-white text-xs">
                    </div>
                </div>
                <div class="flex items-center gap-2 pt-1">
                    <input type="checkbox" name="is_active" id="edit-voucher-active" value="1" class="w-4 h-4 rounded bg-slate-50 dark:bg-slate-950 border-slate-300 dark:border-slate-800 text-indigo-600 focus:ring-indigo-500">
                    <label for="edit-voucher-active" class="text-xs text-slate-700 dark:text-slate-300">Voucher Active Status</label>
                </div>
                <div class="flex justify-end gap-3 pt-4 border-t border-slate-200 dark:border-slate-800">
                    <button type="button" onclick="document.getElementById('edit-voucher-modal').classList.add('hidden')" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-xs rounded-lg hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors cursor-pointer">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white font-semibold text-xs rounded-lg shadow hover:bg-indigo-500 transition-colors cursor-pointer">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openProposePriceModal(productId, title, currentPrice) {
            const modal = document.getElementById('propose-price-modal');
            const form = document.getElementById('propose-price-form');
            const titleEl = document.getElementById('propose-price-modal-title');
            const currentDisplay = document.getElementById('propose-price-current-display');
            if (modal && form) {
                form.action = '/admin/commerce/products/' + productId + '/propose-price';
                if (titleEl) titleEl.textContent = 'Package: ' + title;
                if (currentDisplay) currentDisplay.value = 'IDR ' + Number(currentPrice).toLocaleString();
                modal.classList.remove('hidden');
            }
        }

        function openEditMetadataModal(productId, title, family, desc, testId) {
            const modal = document.getElementById('edit-metadata-modal');
            const form = document.getElementById('edit-metadata-form');
            const titleInput = document.getElementById('edit-metadata-title');
            const familySelect = document.getElementById('edit-metadata-family');
            const descInput = document.getElementById('edit-metadata-desc');
            const testSelect = document.getElementById('edit-metadata-test-id');
            if (modal && form) {
                form.action = '/admin/commerce/products/' + productId;
                if (titleInput) titleInput.value = title;
                if (familySelect) familySelect.value = family || '';
                if (descInput) descInput.value = desc || '';
                if (testSelect) testSelect.value = testId || '';
                modal.classList.remove('hidden');
            }
        }

        function openEditVoucherModal(id, code, discount, validFrom, validUntil, usageLimit, isActive) {
            const modal = document.getElementById('edit-voucher-modal');
            const form = document.getElementById('edit-voucher-form');
            const codeInput = document.getElementById('edit-voucher-code');
            const discountInput = document.getElementById('edit-voucher-discount');
            const validFromInput = document.getElementById('edit-voucher-valid-from');
            const validUntilInput = document.getElementById('edit-voucher-valid-until');
            const usageLimitInput = document.getElementById('edit-voucher-usage-limit');
            const activeCheckbox = document.getElementById('edit-voucher-active');

            if (modal && form) {
                form.action = '/admin/commerce/vouchers/' + id;
                if (codeInput) codeInput.value = code;
                if (discountInput) discountInput.value = discount;
                if (validFromInput) validFromInput.value = validFrom || '';
                if (validUntilInput) validUntilInput.value = validUntil || '';
                if (usageLimitInput) usageLimitInput.value = usageLimit || 100;
                if (activeCheckbox) activeCheckbox.checked = !!isActive;
                modal.classList.remove('hidden');
            }
        }

        function confirmDeleteVoucher(id, code, discount, usedCount) {
            const form = document.getElementById('delete-voucher-form-' + id);
            if (!form) return;

            if (typeof window.iapConfirm === 'function') {
                window.iapConfirm({
                    title: 'Delete Promotional Voucher',
                    message: 'Are you sure you want to delete voucher "' + code + '" (' + discount + '% OFF)?\n\nThis voucher has ' + usedCount + ' redemptions. Deleting it will permanently remove it from candidate access.',
                    confirmText: 'Delete Voucher',
                    cancelText: 'Cancel',
                    variant: 'danger',
                    form: form
                });
            } else {
                if (confirm('Are you sure you want to delete voucher ' + code + '?')) {
                    form.submit();
                }
            }
        }
    </script>
@endsection
