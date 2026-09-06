@extends('organization::layouts.organization', [
    'title' => 'Purchase Assessment Seats',
    'heading' => 'Order Assessment Seats',
    'subheading' => 'Select an assessment package and specify number of seats for your organization'
])

@section('content')
<div class="max-w-4xl space-y-6" x-data="{
    selectedProductId: '{{ old('product_id', $products->first()?->id ?? '') }}',
    products: {{ json_encode($products->map(fn($p) => ['id' => $p->id, 'title' => $p->title, 'price' => (float)$p->price, 'type' => $p->product_type, 'description' => $p->description])) }},
    quantity: {{ old('quantity', 10) }},
    get selectedProduct() {
        return this.products.find(p => p.id === this.selectedProductId) || this.products[0] || null;
    },
    get unitPrice() {
        return this.selectedProduct ? this.selectedProduct.price : 0;
    },
    get totalPrice() {
        return this.unitPrice * (this.quantity > 0 ? this.quantity : 0);
    },
    formatRupiah(number) {
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(number);
    }
}">
    <!-- Back button -->
    <div>
        <a href="{{ route('organization.purchases', $organization->slug) }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-slate-900 dark:hover:text-white transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Back to Orders
        </a>
    </div>

    <form method="POST" action="{{ route('organization.purchases.store', $organization->slug) }}" class="space-y-6">
        @csrf

        <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 p-6 shadow-xs space-y-6">
            <div>
                <h2 class="text-base font-bold text-slate-900 dark:text-white">1. Select Assessment Product</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Choose the assessment or test package you want to purchase seats for</p>
            </div>

            @if($products->isEmpty())
                <div class="p-4 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 text-amber-800 dark:text-amber-200 text-sm">
                    No active assessment products found in the catalog. Please contact the platform administrator.
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

            <div>
                <label for="notes" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                    Order Remarks / Department Reference (Optional)
                </label>
                <textarea id="notes" name="notes" rows="2" placeholder="e.g. For Grade 12 Semester Assessment 2026/2027"
                          class="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-white text-xs focus:outline-hidden focus:ring-2 focus:ring-indigo-500">{{ old('notes') }}</textarea>
            </div>
        </div>

        <!-- Order Summary & Confirmation Box -->
        <div class="bg-indigo-900/10 dark:bg-indigo-950/40 rounded-2xl border border-indigo-200 dark:border-indigo-800/80 p-6 space-y-4">
            <h3 class="font-bold text-sm text-indigo-950 dark:text-indigo-200">Order Summary</h3>

            <div class="space-y-2 text-sm">
                <div class="flex items-center justify-between text-slate-600 dark:text-slate-400">
                    <span>Selected Assessment:</span>
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
                <hr class="border-indigo-200/60 dark:border-indigo-800/60">
                <div class="flex items-center justify-between text-base font-bold text-indigo-950 dark:text-indigo-100">
                    <span>Total Order Amount:</span>
                    <span class="text-lg text-indigo-600 dark:text-indigo-400" x-text="formatRupiah(totalPrice)"></span>
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
