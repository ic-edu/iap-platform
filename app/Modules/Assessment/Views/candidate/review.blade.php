<x-candidate-layout>
    <div class="max-w-2xl mx-auto bg-slate-900 border border-slate-800 rounded-xl p-8 shadow-sm text-center">
        <!-- Pass / Fail Status Badge -->
        <div class="mb-4">
            @if($summary['is_passed'] ?? false)
                <span class="inline-flex items-center gap-1.5 px-4 py-1.5 rounded-full text-sm font-bold bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    RESULT: PASSED
                </span>
            @else
                <span class="inline-flex items-center gap-1.5 px-4 py-1.5 rounded-full text-sm font-bold bg-rose-500/20 text-rose-400 border border-rose-500/30 shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    RESULT: FAILED
                </span>
            @endif
        </div>

        <h1 class="text-2xl font-bold text-white mt-2">{{ $summary['test_title'] ?? 'Assessment Test' }}</h1>
        <p class="text-xs text-slate-400 mt-1">Submitted at {{ $summary['submitted_at'] ?? now()->toIso8601String() }}</p>

        <!-- Score Display Card -->
        <div class="my-6 p-6 rounded-xl bg-slate-950 border border-slate-800/80">
            <p class="text-xs font-medium text-slate-400 uppercase tracking-wider">Final Test Score</p>
            <p class="text-5xl font-extrabold {{ ($summary['is_passed'] ?? false) ? 'text-emerald-400' : 'text-rose-400' }} mt-2">
                {{ $summary['total_score'] ?? 0 }}
            </p>
            <p class="text-xs text-slate-400 mt-2">
                Passing Threshold: <span class="font-semibold text-slate-200">{{ $summary['pass_score'] ?? 0 }} points</span>
            </p>
        </div>

        <!-- Breakdown Grid -->
        <div class="grid grid-cols-4 gap-3 text-sm mb-6 text-slate-300">
            <div class="p-3 bg-slate-950 rounded-lg border border-slate-800">
                <span class="block text-xs text-slate-400">Questions</span>
                <span class="font-bold text-white text-base">{{ $summary['total_questions'] ?? 0 }}</span>
            </div>
            <div class="p-3 bg-slate-950 rounded-lg border border-slate-800">
                <span class="block text-xs text-slate-400">Correct</span>
                <span class="font-bold text-emerald-400 text-base">{{ $summary['correct_answers'] ?? 0 }}</span>
            </div>
            <div class="p-3 bg-slate-950 rounded-lg border border-slate-800">
                <span class="block text-xs text-slate-400">Accuracy</span>
                <span class="font-bold text-indigo-400 text-base">{{ $summary['percentage'] ?? 0 }}%</span>
            </div>
            <div class="p-3 bg-slate-950 rounded-lg border border-slate-800">
                <span class="block text-xs text-slate-400">Grade</span>
                <span class="font-bold text-amber-400 text-base">{{ $summary['grade'] ?? 'D' }}</span>
            </div>
        </div>

        <!-- Certificate Download Section (If Passed) -->
        @if(($summary['is_passed'] ?? false) && !empty($summary['certificate_id']))
            <div class="mb-6 p-5 rounded-xl bg-indigo-950/40 border border-indigo-500/30 text-left">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-bold text-indigo-300">🎓 Digital Certificate Issued!</p>
                        <p class="text-xs text-slate-400 mt-0.5">Certificate #{{ $summary['certificate_number'] ?? '' }}</p>
                    </div>
                    <a href="{{ route('candidate.certificates.download', $summary['certificate_id']) }}" target="_blank" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs transition-colors shadow-md">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                        Download Certificate
                    </a>
                </div>
            </div>
        @endif

        <a href="{{ route('candidate.portal') }}" class="inline-flex items-center justify-center px-6 py-2.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 font-semibold text-sm transition-colors border border-slate-700">
            Back to Student Portal
        </a>
    </div>
</x-candidate-layout>
