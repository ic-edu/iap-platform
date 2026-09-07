@extends('layouts.admin')

@section('content')
<div class="max-w-4xl mx-auto space-y-6" x-data="{
    name: '{{ old('name', '') }}',
    family: '{{ old('assessment_family', 'toeic') }}',
    scopeMode: '{{ old('scope_mode', 'all_products_in_family') }}',
    discountType: '{{ old('discount_type', 'percentage') }}',
    discountValue: {{ old('discount_value', 10) }},
    generationMode: '{{ old('generation_mode', 'shared') }}',
    totalCodes: {{ old('total_codes', 1) }},
    usesPerCode: {{ old('uses_per_code', 10) }},
    customPrefix: '{{ old('code_prefix', '') }}',
    suffixLength: {{ old('code_length', 6) }},
    products: {{ json_encode($products->map(fn($p) => ['id' => $p->id, 'title' => $p->title, 'price' => (float)$p->price, 'family' => strtolower($p->assessment_family ?? '')])) }},
    selectedProductIds: {{ json_encode(old('products', [])) }},
    
    get defaultPrefix() {
        const map = { 'toeic': 'TOEIC', 'toefl': 'TOEFL', 'ielts': 'IELTS', 'general': 'GE', 'vocational': 'VOC' };
        return map[this.family] || (this.family ? this.family.toUpperCase().substring(0, 5) : 'PROMO');
    },
    get effectivePrefix() {
        return (this.customPrefix && this.customPrefix.trim().length > 0) ? this.customPrefix.trim().toUpperCase() : this.defaultPrefix;
    },
    get previewCode() {
        return this.effectivePrefix + '-' + 'X'.repeat(this.suffixLength);
    },
    get filteredProducts() {
        return this.products.filter(p => p.family === this.family);
    }
}">
    <!-- Breadcrumbs -->
    <div class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
        <a href="{{ route('admin.commerce.index') }}" class="hover:text-indigo-600 dark:hover:text-indigo-400 transition">&larr; Commercial Catalog</a>
        <span>/</span>
        <span class="text-slate-900 dark:text-white font-medium">Create Voucher Campaign</span>
    </div>

    <!-- Header -->
    <div class="flex items-start justify-between gap-4 pb-4 border-b border-slate-200 dark:border-slate-800">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Generate Voucher Campaign</h1>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Configure an assessment-family governed promotional campaign and generate unique secure voucher codes.</p>
        </div>
    </div>

    @if ($errors->any())
        <div class="p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-600 dark:text-rose-400 text-xs font-medium space-y-1">
            <span class="font-bold block">Please resolve the following errors:</span>
            <ul class="list-disc pl-5 space-y-0.5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.commerce.campaigns.store') }}" class="space-y-6">
        @csrf

        <!-- 1. Campaign Identity & Assessment Family -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 space-y-5 shadow-sm">
            <h2 class="text-sm font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">1. Campaign Identity &amp; Assessment Family</h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="name" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                        Campaign Name <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" id="name" name="name" x-model="name" required placeholder="e.g. TOEIC Institutional UAT Promotion"
                           class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white text-xs font-medium focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                </div>

                <div>
                    <label for="assessment_family" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                        Target Assessment Family <span class="text-rose-500">*</span>
                    </label>
                    <select id="assessment_family" name="assessment_family" x-model="family" required
                            class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white text-xs font-semibold focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        @foreach($assessmentFamilies as $af)
                            <option value="{{ $af->value }}">{{ $af->label() }} ({{ strtoupper($af->value) }})</option>
                        @endforeach
                    </select>
                    <p class="text-[11px] text-slate-500 mt-1">Campaign will only apply to commercial products matching this assessment family.</p>
                </div>
            </div>

            <!-- Product Scope Mode -->
            <div class="pt-2 space-y-3">
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300">Product Applicability Scope <span class="text-rose-500">*</span></label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <label class="flex items-start gap-3 p-3.5 rounded-xl border cursor-pointer transition"
                           :class="scopeMode === 'all_products_in_family' ? 'border-indigo-600 dark:border-indigo-500 bg-indigo-50/70 dark:bg-indigo-950/50 text-indigo-950 dark:text-white ring-1 ring-indigo-600 dark:ring-indigo-500' : 'border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 text-slate-700 dark:text-slate-400 hover:border-slate-300 dark:hover:border-slate-700'">
                        <input type="radio" name="scope_mode" value="all_products_in_family" x-model="scopeMode" class="mt-0.5 text-indigo-600 focus:ring-indigo-500">
                        <div>
                            <span class="text-xs font-bold block">All Products in Family</span>
                            <span class="text-[11px] text-slate-500 dark:text-slate-400 block mt-0.5">Applies to all active packages belonging to the selected family.</span>
                        </div>
                    </label>

                    <label class="flex items-start gap-3 p-3.5 rounded-xl border cursor-pointer transition"
                           :class="scopeMode === 'selected_products' ? 'border-indigo-600 dark:border-indigo-500 bg-indigo-50/70 dark:bg-indigo-950/50 text-indigo-950 dark:text-white ring-1 ring-indigo-600 dark:ring-indigo-500' : 'border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 text-slate-700 dark:text-slate-400 hover:border-slate-300 dark:hover:border-slate-700'">
                        <input type="radio" name="scope_mode" value="selected_products" x-model="scopeMode" class="mt-0.5 text-indigo-600 focus:ring-indigo-500">
                        <div>
                            <span class="text-xs font-bold block">Selected Products Only</span>
                            <span class="text-[11px] text-slate-500 dark:text-slate-400 block mt-0.5">Explicitly choose which packages are eligible for this campaign.</span>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Product Picker when Selected Products Mode -->
            <div x-show="scopeMode === 'selected_products'" x-transition class="pt-2 space-y-2 border-t border-slate-200 dark:border-slate-800/80">
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300">Choose Eligible Packages (Filtered by Family):</label>
                <div class="space-y-2 max-h-48 overflow-y-auto p-3 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800">
                    <template x-if="filteredProducts.length === 0">
                        <p class="text-xs text-amber-600 dark:text-amber-400 p-2">No active commercial products found for <span x-text="family.toUpperCase()"></span> family.</p>
                    </template>
                    <template x-for="prod in filteredProducts" :key="prod.id">
                        <label class="flex items-center justify-between p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-900 cursor-pointer text-xs">
                            <div class="flex items-center gap-2">
                                <input type="checkbox" name="products[]" :value="prod.id" x-model="selectedProductIds" class="rounded border-slate-300 dark:border-slate-700 text-indigo-600 focus:ring-indigo-500">
                                <span class="font-medium text-slate-800 dark:text-slate-200" x-text="prod.title"></span>
                            </div>
                            <span class="text-slate-500 dark:text-slate-400 font-mono" x-text="'IDR ' + new Intl.NumberFormat('id-ID').format(prod.price)"></span>
                        </label>
                    </template>
                </div>
            </div>
        </div>

        <!-- 2. Discount Policy & Validity Window -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 space-y-5 shadow-sm">
            <h2 class="text-sm font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">2. Discount Policy &amp; Validity Window</h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="discount_type" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Discount Type</label>
                    <select id="discount_type" name="discount_type" x-model="discountType"
                            class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white text-xs font-semibold focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        <option value="percentage">Percentage (% OFF)</option>
                        <option value="fixed">Fixed Amount (IDR Deducted)</option>
                    </select>
                </div>

                <div>
                    <label for="discount_value" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                        Discount Value <span class="text-rose-500">*</span>
                    </label>
                    <div class="relative">
                        <input type="number" id="discount_value" name="discount_value" x-model.number="discountValue" min="1" :max="discountType === 'percentage' ? 100 : 100000000" required
                               class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white text-xs font-bold focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        <span class="absolute right-3.5 top-2.5 text-xs text-slate-500 dark:text-slate-400 font-bold" x-text="discountType === 'percentage' ? '%' : 'IDR'"></span>
                    </div>
                </div>

                <div>
                    <label for="valid_from" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Valid From (Optional)</label>
                    <input type="datetime-local" id="valid_from" name="valid_from" value="{{ old('valid_from', now()->format('Y-m-d\TH:i')) }}"
                           class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white text-xs font-medium focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                </div>

                <div>
                    <label for="valid_until" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                        Valid Until (Expiration) <span class="text-rose-500">*</span>
                    </label>
                    <input type="datetime-local" id="valid_until" name="valid_until" value="{{ old('valid_until', now()->addDays(30)->format('Y-m-d\TH:i')) }}" required
                           class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white text-xs font-medium focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                </div>
            </div>
        </div>

        <!-- 3. Code Generator & Security Options -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 space-y-5 shadow-sm">
            <h2 class="text-sm font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">3. Secure Code Generation &amp; Capacity</h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Generation Mode</label>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="flex items-center justify-center p-2.5 rounded-xl border cursor-pointer text-xs font-bold transition"
                               :class="generationMode === 'shared' ? 'border-indigo-600 dark:border-indigo-500 bg-indigo-50/70 dark:bg-indigo-950/50 text-indigo-950 dark:text-white ring-1 ring-indigo-600 dark:ring-indigo-500' : 'border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 text-slate-700 dark:text-slate-400 hover:border-slate-300 dark:hover:border-slate-700'">
                            <input type="radio" name="generation_mode" value="shared" x-model="generationMode" class="sr-only">
                            <span>Shared Single Code</span>
                        </label>
                        <label class="flex items-center justify-center p-2.5 rounded-xl border cursor-pointer text-xs font-bold transition"
                               :class="generationMode === 'batch' ? 'border-indigo-600 dark:border-indigo-500 bg-indigo-50/70 dark:bg-indigo-950/50 text-indigo-950 dark:text-white ring-1 ring-indigo-600 dark:ring-indigo-500' : 'border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 text-slate-700 dark:text-slate-400 hover:border-slate-300 dark:hover:border-slate-700'">
                            <input type="radio" name="generation_mode" value="batch" x-model="generationMode" class="sr-only">
                            <span>Unique Code Batch</span>
                        </label>
                    </div>
                </div>

                <div x-show="generationMode === 'batch'">
                    <label for="total_codes" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Number of Unique Codes (1–500)</label>
                    <input type="number" id="total_codes" name="total_codes" x-model.number="totalCodes" min="1" max="500"
                           class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white text-xs font-semibold focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                </div>

                <div>
                    <label for="uses_per_code" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                        Usage Limit per Code <span class="text-rose-500">*</span>
                    </label>
                    <input type="number" id="uses_per_code" name="uses_per_code" x-model.number="usesPerCode" min="1" required
                           class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white text-xs font-semibold focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    <p class="text-[11px] text-slate-500 mt-1" x-text="generationMode === 'shared' ? 'Total times this single promo code can be redeemed.' : 'Maximum redemptions allowed for each individual code.'"></p>
                </div>

                <div>
                    <label for="code_prefix" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Code Prefix</label>
                    <input type="text" id="code_prefix" name="code_prefix" x-model="customPrefix" :placeholder="defaultPrefix" maxlength="20"
                           class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white text-xs font-mono uppercase focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                </div>

                <div>
                    <label for="code_length" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Random Suffix Length (4–12)</label>
                    <input type="number" id="code_length" name="code_length" x-model.number="suffixLength" min="4" max="12"
                           class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white text-xs font-semibold focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                </div>
            </div>

            <!-- Live Sample Preview Box -->
            <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800/80 flex items-center justify-between">
                <div>
                    <span class="text-[10px] uppercase tracking-wider font-bold text-slate-500 dark:text-slate-400 block">Generated Code Format Sample</span>
                    <span class="font-mono text-base font-bold text-emerald-600 dark:text-emerald-400 block mt-0.5" x-text="previewCode"></span>
                    <span class="text-[11px] text-slate-500">Omits ambiguous characters (0, O, 1, I, L) for maximum clarity.</span>
                </div>
                <div class="text-right">
                    <span class="text-xs font-bold text-slate-700 dark:text-slate-300 block" x-text="generationMode === 'shared' ? '1 Code (' + usesPerCode + ' uses)' : totalCodes + ' Codes (' + usesPerCode + ' use/each)'"></span>
                </div>
            </div>
        </div>

        <!-- Submit & Actions -->
        <div class="flex items-center justify-between pt-4 border-t border-slate-200 dark:border-slate-800">
            <a href="{{ route('admin.commerce.index') }}" class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs font-semibold transition">
                Cancel
            </a>

            <button type="submit" class="px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs shadow-lg shadow-indigo-600/30 transition flex items-center gap-2 cursor-pointer">
                <span>Generate Campaign &amp; Codes</span>
                <span>&rarr;</span>
            </button>
        </div>
    </form>
</div>
@endsection
