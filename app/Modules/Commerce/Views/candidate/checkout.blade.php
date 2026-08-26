<x-candidate-layout>
    <div class="max-w-3xl mx-auto space-y-6">
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

        {{-- Checkout Form Box --}}
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 sm:p-8 shadow-sm space-y-6">
            <div>
                <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white">Order Checkout &amp; Confirmation</h1>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Review your order summary and confirm purchase to generate your payment invoice.</p>
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

            {{-- Financial Summary Breakdown --}}
            <div class="p-5 rounded-2xl bg-slate-50 dark:bg-slate-950/60 border border-slate-100 dark:border-slate-800 space-y-2 text-xs">
                <div class="flex justify-between text-slate-600 dark:text-slate-400">
                    <span>Base Price (Subtotal)</span>
                    <span class="font-semibold text-slate-900 dark:text-white">IDR {{ number_format($pricing['base_price']) }}</span>
                </div>
                <div class="flex justify-between text-slate-600 dark:text-slate-400">
                    <span>Value Added Tax (11%)</span>
                    <span class="font-semibold text-slate-900 dark:text-white">IDR {{ number_format($pricing['tax']) }}</span>
                </div>
                <div class="flex justify-between pt-2 border-t border-slate-200 dark:border-slate-800 text-base font-black text-slate-900 dark:text-white">
                    <span>Grand Total Due</span>
                    <span class="text-indigo-600 dark:text-indigo-400">IDR {{ number_format($pricing['grand_total']) }}</span>
                </div>
            </div>

            {{-- Confirmation Form --}}
            <form action="{{ route('candidate.checkout.process', $product->id) }}" method="POST" class="space-y-4 pt-2">
                @csrf

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
