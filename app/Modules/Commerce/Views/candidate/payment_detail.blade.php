<x-candidate-layout>
    <div class="max-w-4xl mx-auto space-y-6">
        {{-- Breadcrumb --}}
        <div class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
            <a href="{{ route('candidate.payments.index') }}" class="hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">&larr; Back to Payments</a>
            <span>/</span>
            <span class="text-slate-700 dark:text-slate-200 font-mono font-medium truncate">{{ $payment->reference_number }}</span>
        </div>

        {{-- Flash message --}}
        @if(session('status'))
        <div class="p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-700 dark:text-emerald-400 text-xs font-medium flex items-center gap-2">
            <span>✅</span> {{ session('status') }}
        </div>
        @endif

        {{-- Validation Errors --}}
        @if($errors->any())
        <div class="p-4 rounded-2xl bg-rose-500/10 border border-rose-500/20 text-rose-700 dark:text-rose-400 text-xs space-y-1">
            @foreach($errors->all() as $error)
                <p>⚠️ {{ $error }}</p>
            @endforeach
        </div>
        @endif

        {{-- Payment Box --}}
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 sm:p-10 shadow-sm space-y-8">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-slate-100 dark:border-slate-800">
                <div class="space-y-1">
                    <span class="text-[10px] uppercase font-bold text-slate-400 block tracking-wider">Payment Transaction</span>
                    <h1 class="text-2xl font-black font-mono text-slate-900 dark:text-white">{{ $payment->reference_number }}</h1>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Gateway: {{ strtoupper(str_replace('_', ' ', $payment->payment_gateway)) }}</p>
                </div>
                <div class="flex items-center gap-3">
                    <x-status-badge :status="$payment->status" />
                </div>
            </div>

            {{-- Summary Grid --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 text-xs p-5 rounded-2xl bg-slate-50 dark:bg-slate-950/60 border border-slate-100 dark:border-slate-800">
                <div>
                    <span class="text-slate-400 uppercase font-bold text-[10px] block tracking-wider">Amount Due</span>
                    <span class="font-extrabold text-indigo-600 dark:text-indigo-400 text-lg block mt-1">IDR {{ number_format($payment->amount) }}</span>
                </div>
                <div>
                    <span class="text-slate-400 uppercase font-bold text-[10px] block tracking-wider">Invoice Reference</span>
                    @if($payment->invoice)
                    <a href="{{ route('candidate.invoices.show', $payment->invoice->id) }}" class="font-bold font-mono text-slate-900 dark:text-white text-sm block mt-1 hover:underline">
                        {{ $payment->invoice->invoice_number }}
                    </a>
                    <span class="text-slate-500 dark:text-slate-400">Status: {{ strtoupper(is_object($payment->invoice->status) ? $payment->invoice->status->value : $payment->invoice->status) }}</span>
                    @endif
                </div>
                <div>
                    <span class="text-slate-400 uppercase font-bold text-[10px] block tracking-wider">Verification Date</span>
                    <span class="font-bold text-slate-900 dark:text-white text-sm block mt-1">{{ $payment->confirmed_at?->format('d M Y, H:i') ?? 'Awaiting Verification' }}</span>
                    @if($payment->transaction_id)
                    <span class="text-slate-500 dark:text-slate-400 font-mono text-[11px] truncate block">TXN: {{ $payment->transaction_id }}</span>
                    @endif
                </div>
            </div>

            {{-- Bank Transfer Instructions --}}
            <div class="p-6 rounded-3xl bg-indigo-50/50 dark:bg-indigo-950/20 border border-indigo-100 dark:border-indigo-900/30 space-y-3">
                <h2 class="text-xs font-bold uppercase tracking-wider text-indigo-900 dark:text-indigo-300">Bank Destination Account</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                    <div>
                        <span class="text-slate-500 dark:text-slate-400 block">Bank Name:</span>
                        <strong class="text-slate-900 dark:text-white text-sm">Bank Central Asia (BCA)</strong>
                    </div>
                    <div>
                        <span class="text-slate-500 dark:text-slate-400 block">Account Number:</span>
                        <strong class="font-mono text-indigo-600 dark:text-indigo-400 text-sm font-bold">8830-1928-3001</strong>
                    </div>
                </div>
            </div>

            {{-- Evidence Upload / View Section --}}
            <div class="space-y-4 pt-2">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider">Proof of Transfer / Receipt Evidence</h2>

                @if($payment->proof_path)
                <div class="p-5 rounded-2xl bg-emerald-50/50 dark:bg-emerald-950/20 border border-emerald-200 dark:border-emerald-900/40 space-y-2 text-xs">
                    <div class="flex items-center gap-2 text-emerald-800 dark:text-emerald-300 font-bold">
                        <span>✓</span>
                        <span>Evidence File Uploaded</span>
                    </div>
                    <p class="text-slate-600 dark:text-slate-300 font-mono text-[11px]">Filename: {{ $payment->proof_original_name ?? 'transfer_proof.jpg' }}</p>
                    <p class="text-slate-500 dark:text-slate-400 text-[11px]">Uploaded at: {{ $payment->proof_uploaded_at?->format('d M Y, H:i') }}</p>
                    @if($payment->proof_notes)
                    <p class="text-slate-700 dark:text-slate-300 mt-2 p-3 bg-white dark:bg-slate-900 rounded-xl border border-emerald-200/50 dark:border-emerald-900/30">Notes: {{ $payment->proof_notes }}</p>
                    @endif
                </div>
                @endif

                @if((is_object($payment->status) ? $payment->status->value === 'pending' : $payment->status === 'pending'))
                <form action="{{ route('candidate.payments.proof', $payment->id) }}" method="POST" enctype="multipart/form-data" class="p-6 rounded-3xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800 space-y-4">
                    @csrf

                    <div>
                        <label for="proof" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">
                            {{ $payment->proof_path ? 'Re-upload / Update Evidence File' : 'Upload Bank Transfer Slip / Payment Evidence' }}
                        </label>
                        <input type="file" name="proof" id="proof" required accept=".jpeg,.png,.jpg,.pdf" class="block w-full text-xs text-slate-500 dark:text-slate-400 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-indigo-600 file:text-white hover:file:bg-indigo-500 file:cursor-pointer">
                        <span class="text-[11px] text-slate-400 dark:text-slate-500 block mt-1">Accepted formats: JPG, PNG, PDF (Max 5MB)</span>
                    </div>

                    <div>
                        <label for="notes" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">Sender Notes (Optional)</label>
                        <textarea name="notes" id="notes" rows="2" placeholder="e.g. Transfer from BCA account a/n Budi Santoso..." class="w-full text-xs bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-3 text-slate-900 dark:text-white placeholder:text-slate-400 focus:outline-none focus:border-indigo-500">{{ old('notes', $payment->proof_notes) }}</textarea>
                    </div>

                    <button type="submit" class="px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold transition-all shadow-md shadow-indigo-600/20 cursor-pointer">
                        Submit Payment Evidence &rarr;
                    </button>
                </form>
                @endif
            </div>
        </div>
    </div>
</x-candidate-layout>
