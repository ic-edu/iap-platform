@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <!-- Top Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <span class="text-2xl">🛡️</span>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Commercial Governance &amp; Approvals</h1>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                Commercial oversight, catalog audit, and price change authorization command center for institutional assessment packages.
            </p>
        </div>
        <div class="flex items-center gap-2">
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold bg-indigo-500/10 text-indigo-700 dark:text-indigo-400 border border-indigo-500/20">
                <span>🛡️</span> Super Admin Governance Authority &bull; Commercial Oversight
            </span>
        </div>
    </div>

    <!-- Status Alerts -->
    @if(session('status'))
        <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-400 text-xs font-medium">
            ✅ {{ session('status') }}
        </div>
    @endif
    @if(session('error'))
        <div class="p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-600 dark:text-rose-400 text-xs font-medium">
            ⚠️ {{ session('error') }}
        </div>
    @endif
    @if($errors->any())
        <div class="p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-600 dark:text-rose-400 text-xs font-medium space-y-1">
            <span class="font-bold block">Please resolve the following errors:</span>
            <ul class="list-disc pl-5 space-y-0.5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- 4 KPI Metrics Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <!-- Total Packages -->
        <div class="gov-card p-5 flex items-center justify-between">
            <div>
                <span class="text-xs text-slate-500 dark:text-slate-400 font-bold uppercase tracking-wider block">Total Packages</span>
                <span class="text-3xl font-extrabold text-slate-900 dark:text-white my-1 block">{{ $totalProductsCount }}</span>
                <span class="text-xs text-slate-500 dark:text-slate-400">Institutional & Abstract</span>
            </div>
            <span class="text-3xl p-3 bg-slate-100 dark:bg-slate-800 rounded-xl">📦</span>
        </div>

        <!-- Active Packages -->
        <div class="gov-card p-5 flex items-center justify-between">
            <div>
                <span class="text-xs text-slate-500 dark:text-slate-400 font-bold uppercase tracking-wider block">Active Packages</span>
                <span class="text-3xl font-extrabold text-emerald-600 dark:text-emerald-400 my-1 block">{{ $activePackagesCount }}</span>
                <span class="text-xs text-emerald-600 dark:text-emerald-400">Live in Candidate Store</span>
            </div>
            <span class="text-3xl p-3 bg-emerald-500/10 rounded-xl text-emerald-600 border border-emerald-500/20">✅</span>
        </div>

        <!-- Active Campaigns -->
        <div class="gov-card p-5 flex items-center justify-between">
            <div>
                <span class="text-xs text-slate-500 dark:text-slate-400 font-bold uppercase tracking-wider block">Voucher Campaigns</span>
                <span class="text-3xl font-extrabold text-indigo-600 dark:text-indigo-400 my-1 block">{{ $campaignsCount }}</span>
                <span class="text-xs text-indigo-600 dark:text-indigo-400">Promotional Campaigns</span>
            </div>
            <span class="text-3xl p-3 bg-indigo-500/10 rounded-xl text-indigo-600 border border-indigo-500/20">🏷️</span>
        </div>

        <!-- Pending Price Proposals -->
        <div class="gov-card p-5 flex items-center justify-between">
            <div>
                <span class="text-xs text-slate-500 dark:text-slate-400 font-bold uppercase tracking-wider block">Pending Price Proposals</span>
                <span class="text-3xl font-extrabold text-amber-600 dark:text-amber-400 my-1 block">{{ $pendingPriceChangeCount }}</span>
                <span class="text-xs text-amber-600 dark:text-amber-400 font-semibold">SA Approval Required</span>
            </div>
            <span class="text-3xl p-3 bg-amber-500/10 rounded-xl text-amber-600 border border-amber-500/20">⏳</span>
        </div>
    </div>

    <!-- PRIMARY ACTIONABLE SECTION: Commercial Price Change Approval Queue -->
    <div class="gov-card p-6 shadow-sm">
        <div class="flex items-center justify-between pb-4 mb-4 border-b border-slate-200 dark:border-slate-800">
            <div>
                <h2 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                    <span>⏳</span> Commercial Price Change Approval Queue
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">
                        {{ $pendingPriceChangeCount }} Pending
                    </span>
                </h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                    Proposals submitted by Operational Admins requiring Super Admin authorization before pricing changes take effect.
                </p>
            </div>
        </div>

        @if($pendingPriceChangeRequests->isEmpty())
            <div class="py-8 text-center">
                <span class="text-3xl block mb-2">✨</span>
                <p class="text-xs text-slate-500 dark:text-slate-400 font-medium">No pending commercial price change proposals in the queue.</p>
                <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-0.5">All commercial packages are operating at authorized price levels.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-600 dark:text-slate-300">
                    <thead class="bg-slate-50 dark:bg-slate-950 uppercase text-[10px] text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
                        <tr>
                            <th class="p-3">Product / Package</th>
                            <th class="p-3">Family</th>
                            <th class="p-3">Current Price</th>
                            <th class="p-3">Proposed Price</th>
                            <th class="p-3">Requested By</th>
                            <th class="p-3">Reason / Justification</th>
                            <th class="p-3">Submission Date</th>
                            <th class="p-3 text-right">Governance Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach($pendingPriceChangeRequests as $req)
                            <tr>
                                <td class="p-3 font-bold text-slate-900 dark:text-white">{{ $req->product?->title }}</td>
                                <td class="p-3 uppercase text-[10px] font-bold text-indigo-600 dark:text-indigo-400">{{ $req->product?->getEffectiveFamily() ?? 'GENERAL' }}</td>
                                <td class="p-3 font-mono text-slate-500">IDR {{ number_format($req->current_price_snapshot) }}</td>
                                <td class="p-3 font-mono font-bold text-emerald-600 dark:text-emerald-400">IDR {{ number_format($req->proposed_price) }}</td>
                                <td class="p-3">
                                    <span class="font-medium text-slate-900 dark:text-white block">{{ $req->requester?->name }}</span>
                                    <span class="text-[10px] text-slate-400">{{ $req->requester?->email }}</span>
                                </td>
                                <td class="p-3 max-w-xs truncate text-slate-600 dark:text-slate-400" title="{{ $req->reason }}">{{ $req->reason }}</td>
                                <td class="p-3 text-slate-400">{{ $req->created_at?->format('d M Y, H:i') }}</td>
                                <td class="p-3 text-right">
                                    <div class="inline-flex items-center gap-2">
                                        <form action="{{ route('admin.approvals.price-changes.approve', $req->id) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs shadow-sm transition-all">
                                                Approve
                                            </button>
                                        </form>
                                        <button type="button" onclick="openRejectPriceChangeModal('{{ $req->id }}', '{{ addslashes($req->product?->title) }}')" class="px-3 py-1.5 rounded-lg bg-rose-600 hover:bg-rose-500 text-white font-bold text-xs shadow-sm transition-all">
                                            Reject
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <!-- READ-ONLY SECTION: Package Catalog Oversight -->
    <div class="gov-card p-6 shadow-sm">
        <div class="flex items-center justify-between pb-4 mb-4 border-b border-slate-200 dark:border-slate-800">
            <div>
                <h2 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                    <span>📦</span> Package Catalog Oversight (Read-Only)
                </h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                    Governed assessment packages published across all institutional assessment families.
                </p>
            </div>
            <span class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                {{ $products->total() }} Total Packages
            </span>
        </div>

        @if($products->isEmpty())
            <p class="text-xs text-slate-500 dark:text-slate-400 italic py-4">No assessment packages registered in the catalog.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-600 dark:text-slate-300">
                    <thead class="bg-slate-50 dark:bg-slate-950 uppercase text-[10px] text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
                        <tr>
                            <th class="p-3">Package Title</th>
                            <th class="p-3">Family</th>
                            <th class="p-3">Type</th>
                            <th class="p-3">Authorized Price</th>
                            <th class="p-3">Linked Real Test</th>
                            <th class="p-3">Status</th>
                            <th class="p-3">Pending Proposal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach($products as $product)
                            <tr>
                                <td class="p-3 font-bold text-slate-900 dark:text-white">
                                    {{ $product->title }}
                                    <span class="text-[10px] text-slate-400 font-mono block">{{ $product->slug }}</span>
                                </td>
                                <td class="p-3">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border border-indigo-500/20">
                                        {{ $product->getEffectiveFamily() ?? 'GENERAL' }}
                                    </span>
                                </td>
                                <td class="p-3 capitalize text-slate-600 dark:text-slate-400">{{ $product->product_type }}</td>
                                <td class="p-3 font-mono font-bold text-slate-900 dark:text-white">IDR {{ number_format($product->price) }}</td>
                                <td class="p-3 text-slate-500">
                                    @if($product->test)
                                        <span class="font-medium text-slate-900 dark:text-white">{{ $product->test->title }}</span>
                                    @else
                                        <span class="italic text-slate-400">Abstract (Family-wide)</span>
                                    @endif
                                </td>
                                <td class="p-3">
                                    @if($product->is_active)
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">Active</span>
                                    @else
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-200 dark:bg-slate-800 text-slate-600 dark:text-slate-400">Inactive</span>
                                    @endif
                                </td>
                                <td class="p-3">
                                    @if($product->pendingPriceChangeRequest)
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">
                                            IDR {{ number_format($product->pendingPriceChangeRequest->proposed_price) }} Pending
                                        </span>
                                    @else
                                        <span class="text-slate-400 text-[10px]">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($products->hasPages())
                <div class="mt-4 pt-4 border-t border-slate-200 dark:border-slate-800">
                    {{ $products->appends(request()->query())->links() }}
                </div>
            @endif
        @endif
    </div>

    <!-- READ-ONLY SECTION: Voucher Campaign & Standalone Voucher Oversight -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Voucher Campaigns Oversight -->
        <div class="gov-card p-6 shadow-sm">
            <div class="flex items-center justify-between pb-4 mb-4 border-b border-slate-200 dark:border-slate-800">
                <div>
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <span>🏷️</span> Promotional Campaigns Oversight
                    </h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Campaigns configured by Operational Admins.</p>
                </div>
            </div>

            @if($campaigns->isEmpty())
                <p class="text-xs text-slate-500 dark:text-slate-400 italic py-4">No voucher campaigns registered.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-600 dark:text-slate-300">
                        <thead class="bg-slate-50 dark:bg-slate-950 uppercase text-[10px] text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
                            <tr>
                                <th class="p-2.5">Campaign</th>
                                <th class="p-2.5">Family</th>
                                <th class="p-2.5">Discount</th>
                                <th class="p-2.5">Codes</th>
                                <th class="p-2.5">Status</th>
                                <th class="p-2.5 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach($campaigns as $campaign)
                                <tr>
                                    <td class="p-2.5 font-bold text-slate-900 dark:text-white">{{ $campaign->name }}</td>
                                    <td class="p-2.5 uppercase text-[10px] font-bold text-indigo-600 dark:text-indigo-400">{{ $campaign->assessment_family }}</td>
                                    <td class="p-2.5 font-mono font-bold text-emerald-600 dark:text-emerald-400">
                                        {{ $campaign->discount_value }}{{ $campaign->discount_type === 'percentage' ? '%' : ' IDR' }}
                                    </td>
                                    <td class="p-2.5 font-mono">{{ $campaign->coupons_count }}</td>
                                    <td class="p-2.5">
                                        @if($campaign->is_active)
                                            <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">Active</span>
                                        @else
                                            <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-slate-200 dark:bg-slate-800 text-slate-500">Inactive</span>
                                        @endif
                                    </td>
                                    <td class="p-2.5 text-right">
                                        <a href="{{ route('admin.commerce.campaigns.show', $campaign->id) }}" class="px-2.5 py-1 rounded bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-semibold text-[11px] transition-all no-underline">
                                            View Details &rarr;
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <!-- Standalone Vouchers Oversight -->
        <div class="gov-card p-6 shadow-sm">
            <div class="flex items-center justify-between pb-4 mb-4 border-b border-slate-200 dark:border-slate-800">
                <div>
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <span>🎟️</span> Recent Voucher Codes Oversight
                    </h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Active and historical voucher discount codes.</p>
                </div>
            </div>

            @if($coupons->isEmpty())
                <p class="text-xs text-slate-500 dark:text-slate-400 italic py-4">No vouchers generated.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-600 dark:text-slate-300">
                        <thead class="bg-slate-50 dark:bg-slate-950 uppercase text-[10px] text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
                            <tr>
                                <th class="p-2.5">Code</th>
                                <th class="p-2.5">Discount</th>
                                <th class="p-2.5">Usage</th>
                                <th class="p-2.5">Valid Until</th>
                                <th class="p-2.5">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach($coupons as $coupon)
                                <tr>
                                    <td class="p-2.5 font-mono font-bold text-indigo-600 dark:text-indigo-400">{{ $coupon->code }}</td>
                                    <td class="p-2.5 font-mono">{{ $coupon->value }}%</td>
                                    <td class="p-2.5 font-mono text-slate-500">{{ $coupon->used_count }}/{{ $coupon->usage_limit ?? '∞' }}</td>
                                    <td class="p-2.5 text-slate-400">{{ $coupon->valid_until ? $coupon->valid_until->format('d M Y') : 'Never' }}</td>
                                    <td class="p-2.5">
                                        @if($coupon->is_active)
                                            <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">Active</span>
                                        @else
                                            <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-slate-200 dark:bg-slate-800 text-slate-500">Inactive</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>

<!-- Reject Price Change Modal -->
<div id="reject-price-change-modal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm hidden flex items-center justify-center p-4 z-50">
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 max-w-md w-full shadow-2xl">
        <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-2">Reject Price Change Proposal</h3>
        <p id="reject-modal-product-title" class="text-xs text-slate-500 dark:text-slate-400 mb-4"></p>
        <form id="reject-price-change-form" method="POST" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Rejection Reason *</label>
                <textarea name="rejection_reason" required rows="3" placeholder="Provide a justification for rejecting this proposal..." class="w-full p-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-900 dark:text-white"></textarea>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="document.getElementById('reject-price-change-modal').classList.add('hidden')" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-xs font-semibold rounded-xl">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-rose-600 hover:bg-rose-500 text-white text-xs font-bold rounded-xl shadow">Confirm Rejection</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openRejectPriceChangeModal(id, title) {
        const modal = document.getElementById('reject-price-change-modal');
        const form = document.getElementById('reject-price-change-form');
        const titleEl = document.getElementById('reject-modal-product-title');
        if (modal && form) {
            form.action = '/admin/approvals/price-changes/' + id + '/reject';
            if (titleEl) titleEl.textContent = 'Package: ' + title;
            modal.classList.remove('hidden');
        }
    }
</script>
@endsection
