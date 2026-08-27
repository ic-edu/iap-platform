<x-candidate-layout>
    <div class="max-w-4xl mx-auto space-y-6">
        {{-- Breadcrumb --}}
        <div class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
            <a href="{{ route('candidate.store') }}" class="hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">&larr; Back to Store</a>
            <span>/</span>
            <span class="text-slate-700 dark:text-slate-200 font-medium truncate">{{ $product->title }}</span>
        </div>

        {{-- Product Box --}}
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 sm:p-8 shadow-sm space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4 pb-6 border-b border-slate-100 dark:border-slate-800">
                <div class="space-y-2">
                    <span class="px-2.5 py-1 rounded-lg text-[10px] font-extrabold uppercase tracking-wider bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-500/20">
                        {{ strtoupper($product->product_type) }}
                    </span>
                    <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-white">{{ $product->title }}</h1>
                    <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400">SKU: {{ $product->slug }}</p>
                </div>
                <div class="sm:text-right bg-slate-50 dark:bg-slate-950/60 p-4 rounded-2xl border border-slate-100 dark:border-slate-800">
                    <span class="text-[10px] uppercase font-bold text-slate-400 block">Total Price</span>
                    <span class="text-2xl font-black text-indigo-600 dark:text-indigo-400">IDR {{ number_format($pricing['grand_total']) }}</span>
                    <span class="text-[10px] text-slate-400 block mt-0.5">(incl. {{ number_format($pricing['tax']) }} Tax)</span>
                </div>
            </div>

            {{-- Description --}}
            <div class="space-y-3">
                <h2 class="text-sm font-bold uppercase tracking-wider text-slate-900 dark:text-white">Package Description</h2>
                <div class="text-sm text-slate-600 dark:text-slate-300 leading-relaxed space-y-2">
                    <p>{{ $product->description ?? 'Institutional assessment package with verified scoring and certificate eligibility upon completion.' }}</p>
                </div>
            </div>

            {{-- Assessment Details if linked --}}
            @if($product->test)
            <div class="p-5 rounded-2xl bg-indigo-50/50 dark:bg-indigo-950/20 border border-indigo-100 dark:border-indigo-900/40 space-y-3">
                <h3 class="text-xs font-bold text-indigo-900 dark:text-indigo-300 uppercase tracking-wider flex items-center gap-2">
                    <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                    </svg>
                    <span>Included Assessment Structure</span>
                </h3>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 text-xs">
                    <div>
                        <span class="text-slate-500 dark:text-slate-400 block">Test Type:</span>
                        <span class="font-bold text-slate-900 dark:text-white uppercase">{{ strtoupper(is_object($product->test->test_type) ? $product->test->test_type->value : $product->test->test_type) }}</span>
                    </div>
                    <div>
                        <span class="text-slate-500 dark:text-slate-400 block">Mode:</span>
                        <span class="font-bold text-slate-900 dark:text-white capitalize">{{ str_replace('_', ' ', is_object($product->test->assessment_mode) ? $product->test->assessment_mode->value : $product->test->assessment_mode) }}</span>
                    </div>
                    <div>
                        <span class="text-slate-500 dark:text-slate-400 block">Duration:</span>
                        <span class="font-bold text-slate-900 dark:text-white">{{ $product->test->duration_minutes }} Minutes</span>
                    </div>
                </div>
            </div>
            @elseif($product->getEffectiveFamily())
            <div class="p-5 rounded-2xl bg-indigo-50/50 dark:bg-indigo-950/20 border border-indigo-100 dark:border-indigo-900/40 space-y-3">
                <h3 class="text-xs font-bold text-indigo-900 dark:text-indigo-300 uppercase tracking-wider flex items-center gap-2">
                    <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                    </svg>
                    <span>Assessment Package Details</span>
                </h3>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 text-xs">
                    <div>
                        <span class="text-slate-500 dark:text-slate-400 block">Assessment Family:</span>
                        <span class="font-bold text-slate-900 dark:text-white uppercase">{{ strtoupper($product->getEffectiveFamily()) }}</span>
                    </div>
                    <div>
                        <span class="text-slate-500 dark:text-slate-400 block">Delivery:</span>
                        <span class="font-bold text-slate-900 dark:text-white">Institutional Mock Assessment</span>
                    </div>
                    <div>
                        <span class="text-slate-500 dark:text-slate-400 block">Attempts:</span>
                        <span class="font-bold text-slate-900 dark:text-white">2 Exam Attempts</span>
                    </div>
                </div>
            </div>
            @endif

            {{-- Price Breakdown --}}
            <div class="p-5 rounded-2xl bg-slate-50 dark:bg-slate-950/60 border border-slate-100 dark:border-slate-800 space-y-2 text-xs">
                <div class="flex justify-between text-slate-600 dark:text-slate-400">
                    <span>Base Price</span>
                    <span class="font-semibold text-slate-900 dark:text-white">IDR {{ number_format($pricing['base_price']) }}</span>
                </div>
                <div class="flex justify-between text-slate-600 dark:text-slate-400">
                    <span>VAT / Tax (11%)</span>
                    <span class="font-semibold text-slate-900 dark:text-white">IDR {{ number_format($pricing['tax']) }}</span>
                </div>
                <div class="flex justify-between pt-2 border-t border-slate-200 dark:border-slate-800 text-sm font-bold text-slate-900 dark:text-white">
                    <span>Grand Total</span>
                    <span class="text-indigo-600 dark:text-indigo-400">IDR {{ number_format($pricing['grand_total']) }}</span>
                </div>
            </div>

            {{-- Actions --}}
            <div class="pt-4 flex flex-col sm:flex-row items-center justify-between gap-4">
                <a href="{{ route('candidate.store') }}" class="text-xs font-semibold text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200">
                    &larr; Choose a different package
                </a>
                <a href="{{ route('candidate.checkout.show', $product->id) }}" class="w-full sm:w-auto px-8 py-3.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold transition-all shadow-lg shadow-indigo-600/30 text-center">
                    Proceed to Checkout &rarr;
                </a>
            </div>
        </div>
    </div>
</x-candidate-layout>
