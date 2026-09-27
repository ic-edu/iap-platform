<x-candidate-layout>
    <div id="candidate-result-processing-top" class="max-w-4xl mx-auto">
        <!-- Top Navigation Header -->
        <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <a href="{{ route('candidate.my-attempts') }}" class="inline-flex items-center text-xs text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white transition-colors mb-2">
                    &larr; Back to Attempt History
                </a>
                <h1 class="text-3xl font-bold tracking-tight text-slate-900 dark:text-white flex items-center gap-3">
                    {{ $attempt->test?->title ?? 'Assessment Review' }}
                </h1>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ $attempt->getFormattedCompletionDisplay() }}</p>
            </div>

            <!-- Processing Status Badge -->
            <div>
                <span class="inline-flex items-center gap-1.5 px-4 py-2 rounded-full text-sm font-bold bg-amber-500/15 text-amber-700 dark:text-amber-400 border border-amber-500/30 shadow-sm">
                    <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                    RESULT PROCESSING
                </span>
            </div>
        </div>

        <!-- Flash Alerts -->
        @if(session('status'))
            <div class="mb-6 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-700 dark:text-emerald-400 text-xs font-semibold flex items-center gap-2">
                <span>✅</span> {{ session('status') }}
            </div>
        @endif
        @if(session('error'))
            <div class="mb-6 p-4 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-700 dark:text-rose-400 text-xs font-semibold flex items-center gap-2">
                <span>⚠️</span> {{ session('error') }}
            </div>
        @endif

        <!-- Processing Information Hero Card -->
        <div class="p-8 sm:p-10 rounded-2xl bg-gradient-to-br from-amber-50/80 via-white to-slate-50 dark:from-slate-900 dark:via-amber-950/20 dark:to-slate-900 border border-amber-200/80 dark:border-amber-500/30 shadow-md mb-8">
            <div class="flex flex-col sm:flex-row items-start sm:items-center gap-5 mb-6">
                <div class="w-14 h-14 rounded-2xl bg-amber-500/15 border border-amber-500/30 flex items-center justify-center text-amber-600 dark:text-amber-400 shrink-0 text-2xl shadow-inner">
                    ⏳
                </div>
                <div>
                    <h2 class="text-xl font-bold text-slate-900 dark:text-white">Assessment Result Under Processing</h2>
                    <p class="text-xs text-slate-600 dark:text-slate-400 mt-1">
                        Your assessment submission has been securely recorded and is currently going through the result release validation workflow.
                    </p>
                </div>
            </div>

            <div class="grid sm:grid-cols-2 gap-4 mt-6 pt-6 border-t border-amber-200/60 dark:border-amber-500/20">
                <div class="p-4 rounded-xl bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 block">Assessment Title</span>
                    <span class="text-sm font-bold text-slate-800 dark:text-slate-200 mt-0.5 block">{{ $attempt->test?->title ?? 'Mock Test' }}</span>
                </div>

                <div class="p-4 rounded-xl bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 block">Completion Time</span>
                    <span class="text-sm font-bold text-slate-800 dark:text-slate-200 mt-0.5 block">{{ $attempt->getFormattedCompletionDisplay() }}</span>
                </div>

                <div class="p-4 rounded-xl bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 block">Estimated Result Release</span>
                    <span class="text-sm font-bold text-amber-700 dark:text-amber-400 mt-0.5 block">
                        {{ $attempt->result_release_at ? $attempt->result_release_at->format('d M Y, H:i') : 'Within 24 hours' }}
                    </span>
                </div>

                <div class="p-4 rounded-xl bg-white dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 block">Attempt Sequence</span>
                    <span class="text-sm font-bold text-slate-800 dark:text-slate-200 mt-0.5 block">Attempt #{{ $attempt->attempt_number }}</span>
                </div>
            </div>

            <div class="mt-6 p-4 rounded-xl bg-amber-500/10 border border-amber-500/20 text-xs text-amber-800 dark:text-amber-300 leading-relaxed flex items-start gap-2.5">
                <span class="text-base shrink-0">ℹ️</span>
                <span>
                    In accordance with assessment governance policies, results for Mock Tests are released after the scheduled review period. Your official score report, section performance breakdown, retry/finalization options, and certificate issuance (if eligible) will become accessible here once release is complete.
                </span>
            </div>

            <div class="mt-8 flex flex-col sm:flex-row items-center justify-between gap-4 pt-6 border-t border-slate-200 dark:border-slate-800">
                <a href="{{ route('candidate.my-attempts') }}" class="w-full sm:w-auto px-5 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-white font-bold text-xs transition-colors text-center">
                    &larr; Return to My Attempts
                </a>
                <a href="{{ route('candidate.portal') }}" class="w-full sm:w-auto px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs transition-all shadow-md shadow-indigo-600/30 text-center">
                    Go to Dashboard &rarr;
                </a>
            </div>
        </div>
    </div>
</x-candidate-layout>
