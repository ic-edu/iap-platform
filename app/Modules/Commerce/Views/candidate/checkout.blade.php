<x-candidate-layout>
    <div class="max-w-3xl mx-auto space-y-6" x-data="{
        voucherCode: '{{ old('voucher_code', '') }}',
        appliedVoucher: null,
        voucherError: null,
        voucherLoading: false,
        basePrice: {{ (float) $pricing['base_price'] }},
        discountAmount: {{ (float) $pricing['discount'] }},
        taxableAmount: {{ (float) ($pricing['base_price'] - $pricing['discount']) }},
        taxAmount: {{ (float) $pricing['tax'] }},
        grandTotal: {{ (float) $pricing['grand_total'] }},

        formatRupiah(number) {
            return 'IDR ' + new Intl.NumberFormat('id-ID').format(number);
        },
        async fetchQuote() {
            this.voucherLoading = true;
            this.voucherError = null;

            try {
                const response = await fetch('{{ route('candidate.checkout.quote', $product->id) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        voucher_code: this.voucherCode ? this.voucherCode.trim() : null
                    })
                });

                const data = await response.json();
                if (response.ok) {
                    this.basePrice = data.base_price;
                    this.discountAmount = data.discount;
                    this.taxableAmount = data.taxable_amount;
                    this.taxAmount = data.tax;
                    this.grandTotal = data.grand_total;

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
                    this.voucherError = data.error || 'Failed to validate voucher.';
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
        }
    }">
        {{-- Breadcrumb --}}
        <div class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
            <a href="{{ route('candidate.store') }}" class="hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">Store</a>
            <span>/</span>
            <a href="{{ route('candidate.store.show', $product->id) }}" class="hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors truncate">{{ $product->title }}</a>
            <span>/</span>
            <span class="text-slate-700 dark:text-slate-200 font-medium">Checkout</span>
        </div>

        {{-- Active Pending Order Notice if already exists --}}
        @if(isset($existingOrder) && $existingOrder)
        <div class="p-4 rounded-2xl bg-amber-500/10 border border-amber-500/20 text-amber-800 dark:text-amber-300 text-xs flex items-center justify-between gap-4">
            <div class="flex items-center gap-2">
                <span class="text-base">⚠️</span>
                <span>You already have an active pending order (<strong>{{ $existingOrder->order_number }}</strong>) for this package.</span>
            </div>
            @if($existingOrder->invoice)
            <a href="{{ route('candidate.invoices.show', $existingOrder->invoice->id) }}" class="px-3 py-1.5 rounded-lg bg-amber-600 text-white font-bold text-[11px] hover:bg-amber-500 transition-colors flex-shrink-0">
                View Existing Invoice &rarr;
            </a>
            @endif
        </div>
        @endif

        @if ($errors->any())
            <div class="p-4 rounded-2xl bg-rose-500/10 border border-rose-500/20 text-rose-700 dark:text-rose-400 text-xs font-medium space-y-1">
                <span class="font-bold block">Please resolve the following errors:</span>
                <ul class="list-disc pl-5 space-y-0.5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Checkout Form Box --}}
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 sm:p-8 shadow-sm space-y-6">
            <div>
                <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white">Order Checkout &amp; Confirmation</h1>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Review your order summary, apply promotional vouchers, and confirm purchase.</p>
            </div>

            {{-- Candidate Details --}}
            <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-950/60 border border-slate-100 dark:border-slate-800 space-y-2 text-xs">
                <span class="text-[10px] uppercase font-bold text-slate-400 block tracking-wider">Candidate / Billed To</span>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <div>
                        <span class="text-slate-500 dark:text-slate-400">Full Name:</span>
                        <span class="font-bold text-slate-900 dark:text-white block">{{ Auth::user()->name }}</span>
                    </div>
                    <div>
                        <span class="text-slate-500 dark:text-slate-400">Email:</span>
                        <span class="font-bold text-slate-900 dark:text-white font-mono block">{{ Auth::user()->email }}</span>
                    </div>
                </div>
            </div>

            {{-- Order Item Summary --}}
            <div class="space-y-3">
                <span class="text-[10px] uppercase font-bold text-slate-400 block tracking-wider">Item Details</span>
                <div class="p-4 rounded-2xl border border-slate-200 dark:border-slate-800 flex items-center justify-between gap-4">
                    <div class="space-y-1">
                        <span class="text-sm font-bold text-slate-900 dark:text-white">{{ $product->title }}</span>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Type: {{ strtoupper($product->product_type) }} &bull; Quantity: 1</p>
                    </div>
                    <span class="text-base font-bold text-slate-900 dark:text-white flex-shrink-0">IDR {{ number_format($product->price) }}</span>
                </div>
            </div>

            {{-- Promotional Voucher Component --}}
            <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-950/60 border border-slate-100 dark:border-slate-800 space-y-3">
                <span class="text-[10px] uppercase font-bold text-slate-400 block tracking-wider">Promotional Voucher</span>
                <div class="flex items-center gap-2">
                    <div class="relative flex-1">
                        <input type="text" x-model="voucherCode" placeholder="Enter voucher code (e.g. PROMOTOEIC-UAT)"
                               @keydown.enter.prevent="applyVoucher()"
                               class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-xs font-mono uppercase focus:outline-hidden focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <button type="button" @click="applyVoucher()" :disabled="voucherLoading || !voucherCode"
                            class="px-5 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 dark:bg-indigo-600 dark:hover:bg-indigo-500 disabled:opacity-50 text-white font-bold text-xs transition cursor-pointer">
                        <span x-show="!voucherLoading">Apply</span>
                        <span x-show="voucherLoading">Checking...</span>
                    </button>
                </div>

                <!-- Success Banner -->
                <div x-show="appliedVoucher && appliedVoucher.applied" x-transition class="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-700 dark:text-emerald-300 text-xs flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <span>✓</span>
                        <span>Voucher <strong class="font-mono" x-text="appliedVoucher ? appliedVoucher.code : ''"></strong> applied:
                            <span class="font-bold" x-text="appliedVoucher && appliedVoucher.type === 'percentage' ? appliedVoucher.value + '% OFF' : 'IDR ' + new Intl.NumberFormat('id-ID').format(appliedVoucher ? appliedVoucher.value : 0) + ' OFF'"></span>
                        </span>
                    </div>
                    <button type="button" @click="removeVoucher()" class="text-[11px] font-bold text-emerald-600 dark:text-emerald-400 hover:underline cursor-pointer">
                        Remove
                    </button>
                </div>

                <!-- Error Banner -->
                <div x-show="voucherError" x-transition class="p-3 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-700 dark:text-rose-300 text-xs flex items-center gap-2">
                    <span>⚠️</span>
                    <span x-text="voucherError"></span>
                </div>
            </div>

            {{-- Financial Summary Breakdown with Full VAT Parity --}}
            <div class="p-5 rounded-2xl bg-slate-50 dark:bg-slate-950/60 border border-slate-100 dark:border-slate-800 space-y-2 text-xs">
                <div class="flex justify-between text-slate-600 dark:text-slate-400">
                    <span>Base Price (Subtotal)</span>
                    <span class="font-semibold text-slate-900 dark:text-white" x-text="formatRupiah(basePrice)"></span>
                </div>

                <template x-if="discountAmount > 0">
                    <div class="flex justify-between text-emerald-600 dark:text-emerald-400 font-semibold">
                        <span>Voucher Discount (<span x-text="appliedVoucher ? appliedVoucher.code : ''"></span>)</span>
                        <span x-text="'- ' + formatRupiah(discountAmount)"></span>
                    </div>
                </template>

                <div class="flex justify-between text-slate-600 dark:text-slate-400">
                    <span>Taxable Subtotal</span>
                    <span class="font-semibold text-slate-900 dark:text-white" x-text="formatRupiah(taxableAmount)"></span>
                </div>

                <div class="flex justify-between text-slate-600 dark:text-slate-400">
                    <span>Value Added Tax (11% VAT)</span>
                    <span class="font-semibold text-slate-900 dark:text-white" x-text="formatRupiah(taxAmount)"></span>
                </div>

                <div class="flex justify-between pt-2 border-t border-slate-200 dark:border-slate-800 text-base font-black text-slate-900 dark:text-white">
                    <span>Grand Total Due</span>
                    <span class="text-indigo-600 dark:text-indigo-400" x-text="formatRupiah(grandTotal)"></span>
                </div>
            </div>

            {{-- Confirmation Form --}}
            <form action="{{ route('candidate.checkout.process', $product->id) }}" method="POST" class="space-y-4 pt-2">
                @csrf
                <input type="hidden" name="voucher_code" :value="voucherCode">

                <div class="p-4 rounded-2xl bg-indigo-50/50 dark:bg-indigo-950/20 border border-indigo-100 dark:border-indigo-900/30 text-xs text-slate-600 dark:text-slate-300 leading-relaxed flex items-start gap-2.5">
                    <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <p><strong>Note on Assessment Delivery:</strong> After order confirmation, an invoice will be generated. Once your payment is verified by the Finance Officer, you will become eligible for Mock Test assignment by the Operational Admin.</p>
                </div>

                <div class="flex items-center justify-between pt-4 border-t border-slate-100 dark:border-slate-800">
                    <a href="{{ route('candidate.store.show', $product->id) }}" class="text-xs font-semibold text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200">
                        &larr; Cancel
                    </a>

                    <button type="submit" class="px-8 py-3.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold transition-all shadow-lg shadow-indigo-600/30 flex items-center gap-2 cursor-pointer">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                        <span>Confirm &amp; Place Order</span>
                        <span>&rarr;</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-candidate-layout>
