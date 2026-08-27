<x-candidate-layout>
    <div class="space-y-8">
        {{-- Header --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-slate-900 dark:text-white">Assessment & Learning Store</h1>
                <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-400 mt-1">Browse institutional mock tests, assessment packages, and preparatory courses.</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('candidate.orders.index') }}" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-semibold transition-colors">
                    <svg class="w-3.5 h-3.5 text-slate-500 dark:text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                    </svg>
                    <span>My Orders</span>
                </a>
                <a href="{{ route('candidate.invoices.index') }}" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-950/50 dark:hover:bg-indigo-900/50 text-indigo-700 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-800/50 text-xs font-semibold transition-colors">
                    <svg class="w-3.5 h-3.5 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                    </svg>
                    <span>Billing &amp; Invoices</span>
                </a>
            </div>
        </div>

        {{-- Flash message --}}
        @if(session('status'))
        <div class="p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-700 dark:text-emerald-400 text-xs font-medium flex items-center gap-2">
            <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            <span>{{ session('status') }}</span>
        </div>
        @endif

        {{-- Product Catalog Grid --}}
        @if($products->isEmpty())
        <div class="p-12 text-center rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-3">
            <div class="w-12 h-12 rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-400 mx-auto flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                </svg>
            </div>
            <h2 class="text-base font-bold text-slate-900 dark:text-white">No assessment packages currently available</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 max-w-md mx-auto">Assessment packages offered by iC.edu will appear here when available.</p>
        </div>
        @else
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($products as $product)
            <div class="flex flex-col bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 hover:border-indigo-400 dark:hover:border-indigo-500/50 rounded-2xl p-5 shadow-sm transition-all group">
                {{-- Product Type Badge & Test Mode --}}
                <div class="flex items-center justify-between gap-2 mb-3">
                    <span class="px-2.5 py-1 rounded-lg text-[10px] font-extrabold uppercase tracking-wider bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-500/20">
                        {{ strtoupper($product->product_type) }}
                    </span>
                    @if($product->test)
                    <span class="text-[11px] font-medium text-emerald-600 dark:text-emerald-400 flex items-center gap-1">
                        <svg class="w-3 h-3 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span>Mock Test Ready</span>
                    </span>
                    @endif
                </div>

                {{-- Product Title & Description --}}
                <div class="flex-1">
                    <h2 class="text-base font-bold text-slate-900 dark:text-white group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                        <a href="{{ route('candidate.store.show', $product->id) }}">{{ $product->title }}</a>
                    </h2>
                    <p class="text-xs text-slate-600 dark:text-slate-400 mt-2 line-clamp-3 leading-relaxed">
                        {{ $product->description ?? 'Institutional assessment package with verified scoring and certificate eligibility upon completion.' }}
                    </p>

                    @if($product->test)
                    <div class="mt-3.5 p-3 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-100 dark:border-slate-800/80 space-y-1">
                        <div class="flex items-center justify-between text-[11px] text-slate-500 dark:text-slate-400">
                            <span>Assessment Type:</span>
                            <span class="font-semibold text-slate-800 dark:text-slate-200 uppercase">{{ strtoupper(is_object($product->test->test_type) ? $product->test->test_type->value : $product->test->test_type) }} ({{ ucfirst(str_replace('_', ' ', is_object($product->test->assessment_mode) ? $product->test->assessment_mode->value : $product->test->assessment_mode)) }})</span>
                        </div>
                        <div class="flex items-center justify-between text-[11px] text-slate-500 dark:text-slate-400">
                            <span>Duration:</span>
                            <span class="font-semibold text-slate-800 dark:text-slate-200">{{ $product->test->duration_minutes }} Mins</span>
                        </div>
                    </div>
                    @elseif($product->getEffectiveFamily())
                    <div class="mt-3.5 p-3 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-100 dark:border-slate-800/80 space-y-1">
                        <div class="flex items-center justify-between text-[11px] text-slate-500 dark:text-slate-400">
                            <span>Assessment Family:</span>
                            <span class="font-semibold text-slate-800 dark:text-slate-200 uppercase">{{ strtoupper($product->getEffectiveFamily()) }}</span>
                        </div>
                        <div class="flex items-center justify-between text-[11px] text-slate-500 dark:text-slate-400">
                            <span>Delivery Mode:</span>
                            <span class="font-semibold text-slate-800 dark:text-slate-200">Real Test Mock Session</span>
                        </div>
                    </div>
                    @endif
                </div>

                {{-- Price & CTA --}}
                <div class="pt-4 mt-4 border-t border-slate-100 dark:border-slate-800/80 flex items-center justify-between gap-3">
                    <div>
                        <span class="text-[10px] uppercase font-bold text-slate-400 block">Price</span>
                        <span class="text-lg font-extrabold text-slate-900 dark:text-white">IDR {{ number_format($product->price) }}</span>
                    </div>
                    <a href="{{ route('candidate.checkout.show', $product->id) }}" class="px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold transition-all shadow-md shadow-indigo-600/20 flex items-center gap-1.5 cursor-pointer">
                        <span>Buy Now</span>
                        <span class="ml-0.5">&rarr;</span>
                    </a>
                </div>
            </div>
            @endforeach
        </div>

        {{-- Pagination --}}
        @if($products->hasPages())
        <div class="pt-4">
            {{ $products->links() }}
        </div>
        @endif
        @endif
    </div>
</x-candidate-layout>
