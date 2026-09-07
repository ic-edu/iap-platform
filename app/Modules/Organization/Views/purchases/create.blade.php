@extends('organization::layouts.organization', [
    'title' => 'Purchase Seats',
    'heading' => 'Order Package Seats',
    'subheading' => 'Select an assessment package and specify number of seats for your organization'
])

@section('content')
<div class="max-w-4xl space-y-6" x-data="{
    selectedProductId: '{{ old('product_id', $products->first()?->id ?? '') }}',
    products: {{ json_encode($products->map(fn($p) => ['id' => $p->id, 'title' => $p->title, 'price' => (float)$p->price, 'type' => $p->product_type, 'description' => $p->description, 'family' => $p->assessment_family])) }},
    quantity: {{ old('quantity', 10) }},
    voucherCode: '{{ old('voucher_code', '') }}',
    appliedVoucher: null,
    voucherError: null,
    voucherLoading: false,
    quoteData: null,

    get selectedProduct() {
        return this.products.find(p => p.id === this.selectedProductId) || this.products[0] || null;
    },
    get unitPrice() {
        return this.selectedProduct ? this.selectedProduct.price : 0;
    },
    get baseSubtotal() {
        return this.unitPrice * (this.quantity > 0 ? this.quantity : 0);
    },
    get discountAmount() {
        return this.quoteData ? this.quoteData.discount : 0;
    },
    get taxableSubtotal() {
        return this.quoteData ? this.quoteData.taxable_amount : Math.max(0, this.baseSubtotal - this.discountAmount);
    },
    get taxAmount() {
        return this.quoteData ? this.quoteData.tax : Math.round(this.taxableSubtotal * 0.11);
    },
    get grandTotal() {
        return this.quoteData ? this.quoteData.grand_total : (this.taxableSubtotal + this.taxAmount);
    },
    formatRupiah(number) {
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(number);
    },
    async fetchQuote() {
        if (!this.selectedProduct || this.quantity < 1) return;
        this.voucherLoading = true;
        this.voucherError = null;

        try {
            const response = await fetch('{{ route('organization.purchases.quote', $organization->slug) }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    product_id: this.selectedProductId,
                    quantity: this.quantity,
                    voucher_code: this.voucherCode ? this.voucherCode.trim() : null
                })
            });

            const data = await response.json();
            if (response.ok) {
                this.quoteData = data;
                if (data.voucher && data.voucher.applied) {
                    this.appliedVoucher = data.voucher;
                    this.voucherError = null;
                } else if (data.voucher && data.voucher.reason) {
                    this.appliedVoucher = null;
                    this.voucherError = data.voucher.reason;
                } else {
                    this.appliedVoucher = null;
                    this.voucherError = null;
                }
            } else {
                this.voucherError = data.error || 'Failed to validate quote.';
                this.appliedVoucher = null;
            }
        } catch (e) {
            console.error('Quote fetch error', e);
        } finally {
            this.voucherLoading = false;
        }
    },
    applyVoucher() {
        if (!this.voucherCode || this.voucherCode.trim().length === 0) return;
        this.fetchQuote();
    },
    removeVoucher() {
        this.voucherCode = '';
        this.appliedVoucher = null;
        this.voucherError = null;
        this.fetchQuote();
    },
    init() {
        this.$watch('selectedProductId', () => this.fetchQuote());
        this.$watch('quantity', () => this.fetchQuote());
        this.fetchQuote();
    }
}">
    <!-- Back button -->
    <div>
        <a href="{{ route('organization.purchases', $organization->slug) }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-slate-900 dark:hover:text-white transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Back to Orders
        </a>
    </div>

    @if ($errors->any())
        <div class="p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-700 dark:text-rose-400 text-xs font-medium space-y-1">
            <span class="font-bold block">Please resolve the following errors:</span>
            <ul class="list-disc pl-5 space-y-0.5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('organization.purchases.store', $organization->slug) }}" class="space-y-6">
        @csrf

        <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 p-6 shadow-xs space-y-6">
            <div>
                <h2 class="text-base font-bold text-slate-900 dark:text-white">1. Select Package / Product</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Choose the commercial package you want to purchase seats for</p>
            </div>

            @if($products->isEmpty())
                <div class="p-4 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 text-amber-800 dark:text-amber-200 text-sm">
                    No active commercial packages found in the catalog. Please contact the platform administrator.
                </div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @foreach($products as $prod)
                        <label class="relative flex flex-col p-4 rounded-xl border cursor-pointer transition-all"
                               :class="selectedProductId === '{{ $prod->id }}' ? 'border-indigo-600 bg-indigo-50/40 dark:bg-indigo-950/30 ring-2 ring-indigo-500/20' : 'border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 bg-white dark:bg-slate-900'">
                            <input type="radio" name="product_id" value="{{ $prod->id }}" class="sr-only" x-model="selectedProductId" required>
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <span class="inline-block text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 mb-1.5">
                                        {{ ucfirst(str_replace('_', ' ', $prod->product_type)) }}
                                    </span>
                                    <h3 class="font-bold text-sm text-slate-900 dark:text-white">{{ $prod->title }}</h3>
                                </div>
                                <div class="text-right">
                                    <div class="font-bold text-sm text-indigo-600 dark:text-indigo-400">
                                        Rp {{ number_format($prod->price, 0, ',', '.') }}
                                    </div>
                                    <div class="text-[10px] text-slate-400">per seat</div>
                                </div>
                            </div>
                            @if($prod->description)
                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-2 line-clamp-2">{{ $prod->description }}</p>
                            @endif
                        </label>
                    @endforeach
                </div>
            @endif

            <hr class="border-slate-100 dark:border-slate-800">

            <div>
                <h2 class="text-base font-bold text-slate-900 dark:text-white">2. Seat Quantity</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Specify how many candidate seats your institution needs</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-center">
                <div>
                    <label for="quantity" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                        Number of Seats <span class="text-rose-500">*</span>
                    </label>
                    <input type="number" id="quantity" name="quantity" min="1" max="10000" x-model.number="quantity" required
                           class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-white text-sm font-semibold focus:outline-hidden focus:ring-2 focus:ring-indigo-500">
                    <p class="text-[11px] text-slate-400 mt-1">Minimum 1 seat. Each seat allows 1 candidate member allocation.</p>
                </div>

                <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 space-y-1.5">
                    <div class="text-xs text-slate-500 dark:text-slate-400">Quick Quantity Preset:</div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" @click="quantity = 5" class="px-2.5 py-1 text-xs font-semibold rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 hover:border-indigo-500 transition cursor-pointer">5 seats</button>
                        <button type="button" @click="quantity = 10" class="px-2.5 py-1 text-xs font-semibold rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 hover:border-indigo-500 transition cursor-pointer">10 seats</button>
                        <button type="button" @click="quantity = 25" class="px-2.5 py-1 text-xs font-semibold rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 hover:border-indigo-500 transition cursor-pointer">25 seats</button>
                        <button type="button" @click="quantity = 50" class="px-2.5 py-1 text-xs font-semibold rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 hover:border-indigo-500 transition cursor-pointer">50 seats</button>
                        <button type="button" @click="quantity = 100" class="px-2.5 py-1 text-xs font-semibold rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 hover:border-indigo-500 transition cursor-pointer">100 seats</button>
                    </div>
                </div>
            </div>

            <hr class="border-slate-100 dark:border-slate-800">

            <!-- 3. Promotional Voucher Section -->
            <div>
                <h2 class="text-base font-bold text-slate-900 dark:text-white">3. Promotional Voucher (Optional)</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Enter an institutional campaign promo code to apply a discount</p>
            </div>

            <div class="space-y-3">
                <div class="flex items-center gap-2">
                    <div class="relative flex-1">
                        <input type="text" id="voucher_code" name="voucher_code" x-model="voucherCode" placeholder="e.g. TOEIC-K7M4PX or PROMOTOEIC-UAT"
                               @keydown.enter.prevent="applyVoucher()"
                               class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-white text-xs font-mono uppercase focus:outline-hidden focus:ring-2 focus:ring-indigo-500">
                    </div>

                    <button type="button" @click="applyVoucher()" :disabled="voucherLoading || !voucherCode"
                            class="px-5 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 dark:bg-indigo-600 dark:hover:bg-indigo-500 disabled:opacity-50 text-white font-bold text-xs transition cursor-pointer flex items-center gap-1.5">
                        <span x-show="!voucherLoading">Apply</span>
                        <span x-show="voucherLoading">Checking...</span>
                    </button>
                </div>

                <!-- Success State Banner -->
                <div x-show="appliedVoucher && appliedVoucher.applied" x-transition class="p-3.5 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-700 dark:text-emerald-300 text-xs flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <span>✓</span>
                        <span>
                            Voucher <strong class="font-mono" x-text="appliedVoucher ? appliedVoucher.code : ''"></strong> applied:
                            <span class="font-bold" x-text="appliedVoucher && appliedVoucher.type === 'percentage' ? appliedVoucher.value + '% OFF' : 'IDR ' + new Intl.NumberFormat('id-ID').format(appliedVoucher ? appliedVoucher.value : 0) + ' OFF'"></span>
                        </span>
                    </div>
                    <button type="button" @click="removeVoucher()" class="text-[11px] font-bold text-emerald-600 dark:text-emerald-400 hover:underline cursor-pointer">
                        Remove
                    </button>
                </div>

                <!-- Error State Banner -->
                <div x-show="voucherError" x-transition class="p-3.5 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-700 dark:text-rose-300 text-xs flex items-center gap-2">
                    <span>⚠️</span>
                    <span x-text="voucherError"></span>
                </div>
            </div>

            <div>
                <label for="notes" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                    Order Remarks / Department Reference (Optional)
                </label>
                <textarea id="notes" name="notes" rows="2" placeholder="e.g. For Grade 12 Semester Assessment 2026/2027"
                          class="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-white text-xs focus:outline-hidden focus:ring-2 focus:ring-indigo-500">{{ old('notes') }}</textarea>
            </div>
        </div>

        <!-- Order Summary & Confirmation Box (with Full VAT Parity) -->
        <div class="bg-indigo-900/10 dark:bg-indigo-950/40 rounded-2xl border border-indigo-200 dark:border-indigo-800/80 p-6 space-y-4">
            <h3 class="font-bold text-sm text-indigo-950 dark:text-indigo-200">Order Summary</h3>

            <div class="space-y-2.5 text-xs sm:text-sm">
                <div class="flex items-center justify-between text-slate-600 dark:text-slate-400">
                    <span>Selected Package:</span>
                    <span class="font-semibold text-slate-900 dark:text-white" x-text="selectedProduct ? selectedProduct.title : '-'"></span>
                </div>
                <div class="flex items-center justify-between text-slate-600 dark:text-slate-400">
                    <span>Seat Quantity:</span>
                    <span class="font-semibold text-slate-900 dark:text-white"><span x-text="quantity"></span> seats</span>
                </div>
                <div class="flex items-center justify-between text-slate-600 dark:text-slate-400">
                    <span>Unit Price:</span>
                    <span class="font-semibold text-slate-900 dark:text-white" x-text="formatRupiah(unitPrice)"></span>
                </div>
                <div class="flex items-center justify-between text-slate-600 dark:text-slate-400">
                    <span>Base Subtotal:</span>
                    <span class="font-semibold text-slate-900 dark:text-white" x-text="formatRupiah(baseSubtotal)"></span>
                </div>

                <!-- Voucher Discount Line -->
                <template x-if="discountAmount > 0">
                    <div class="flex items-center justify-between text-emerald-600 dark:text-emerald-400 font-semibold">
                        <span>Voucher Discount (<span x-text="appliedVoucher ? appliedVoucher.code : ''"></span>):</span>
                        <span x-text="'- ' + formatRupiah(discountAmount)"></span>
                    </div>
                </template>

                <div class="flex items-center justify-between text-slate-600 dark:text-slate-400">
                    <span>Taxable Subtotal:</span>
                    <span class="font-semibold text-slate-900 dark:text-white" x-text="formatRupiah(taxableSubtotal)"></span>
                </div>

                <div class="flex items-center justify-between text-slate-600 dark:text-slate-400">
                    <span>Value Added Tax (11% VAT):</span>
                    <span class="font-semibold text-slate-900 dark:text-white" x-text="formatRupiah(taxAmount)"></span>
                </div>

                <hr class="border-indigo-200/60 dark:border-indigo-800/60 my-2">

                <div class="flex items-center justify-between text-base font-bold text-indigo-950 dark:text-indigo-100">
                    <span>Total Order Amount:</span>
                    <span class="text-xl text-indigo-600 dark:text-indigo-400" x-text="formatRupiah(grandTotal)"></span>
                </div>
            </div>

            <div class="pt-2 flex flex-col sm:flex-row items-center justify-between gap-3">
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    🔒 Payment-First: Seats will be provisioned immediately upon Finance confirmation of payment.
                </p>

                <button type="submit" :disabled="!selectedProduct || quantity < 1"
                        class="w-full sm:w-auto px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white font-bold text-sm shadow-sm transition flex items-center justify-center gap-2 cursor-pointer">
                    <span>Generate Institutional Invoice</span>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                </button>
            </div>
        </div>
    </form>
</div>
@endsection
