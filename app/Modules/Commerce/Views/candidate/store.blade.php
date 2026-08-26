<x-candidate-layout>
    <div class="space-y-8">
        {{-- Header --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-slate-900 dark:text-white">🛍️ Assessment & Learning Store</h1>
                <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-400 mt-1">Browse official certification mock tests, vocational packages, and preparatory courses.</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('candidate.orders.index') }}" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-semibold transition-colors">
                    📦 My Orders
                </a>
                <a href="{{ route('candidate.invoices.index') }}" class="px-4 py-2 rounded-xl bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-950/50 dark:hover:bg-indigo-900/50 text-indigo-700 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-800/50 text-xs font-semibold transition-colors">
                    💳 Billing & Invoices
                </a>
            </div>
        </div>

        {{-- Flash message --}}
        @if(session('status'))
        <div class="p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-700 dark:text-emerald-400 text-xs font-medium flex items-center gap-2">
            <span>✅</span> {{ session('status') }}
        </div>
        @endif

        {{-- Product Catalog Grid --}}
        @if($products->isEmpty())
        <div class="p-12 text-center rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-3">
            <span class="text-4xl block">📦</span>
            <h2 class="text-base font-bold text-slate-900 dark:text-white">No products currently available</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 max-w-md mx-auto">New certification packages and mock tests will appear here once published by the academic repository.</p>
        </div>
        @else
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($products as $product)
            <div class="flex flex-col bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 hover:border-indigo-400 dark:hover:border-indigo-500/50 rounded-2xl p-5 shadow-sm transition-all group">
                {{-- Product Type Badge --}}
                <div class="flex items-center justify-between gap-2 mb-3">
                    <span class="px-2.5 py-1 rounded-lg text-[10px] font-extrabold uppercase tracking-wider bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-500/20">
                        {{ strtoupper($product->product_type) }}
                    </span>
                    @if($product->test)
                    <span class="text-[11px] font-medium text-emerald-600 dark:text-emerald-400 flex items-center gap-1">
                        <span>✓</span> Mock Test Ready
                    </span>
                    @endif
                </div>

                {{-- Product Title & Description --}}
                <div class="flex-1">
                    <h2 class="text-base font-bold text-slate-900 dark:text-white group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                        <a href="{{ route('candidate.store.show', $product->id) }}">{{ $product->title }}</a>
                    </h2>
                    <p class="text-xs text-slate-600 dark:text-slate-400 mt-2 line-clamp-3 leading-relaxed">
                        {{ $product->description ?? 'Official institutional assessment package with verified scoring and instant certificate eligibility upon completion.' }}
                    </p>

                    @if($product->test)
                    <div class="mt-3.5 p-3 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-100 dark:border-slate-800/80 space-y-1">
                        <div class="flex items-center justify-between text-[11px] text-slate-500 dark:text-slate-400">
                            <span>Assessment:</span>
                            <span class="font-semibold text-slate-800 dark:text-slate-200">{{ strtoupper(is_object($product->test->test_type) ? $product->test->test_type->value : $product->test->test_type) }} ({{ ucfirst(str_replace('_', ' ', is_object($product->test->assessment_mode) ? $product->test->assessment_mode->value : $product->test->assessment_mode)) }})</span>
                        </div>
                        <div class="flex items-center justify-between text-[11px] text-slate-500 dark:text-slate-400">
                            <span>Duration:</span>
                            <span class="font-semibold text-slate-800 dark:text-slate-200">{{ $product->test->duration_minutes }} Mins</span>
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
                    <a href="{{ route('candidate.checkout.show', $product->id) }}" class="px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold transition-all shadow-md shadow-indigo-600/20 flex items-center gap-1.5">
                        <span>Buy Now</span>
                        <span>&rarr;</span>
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
