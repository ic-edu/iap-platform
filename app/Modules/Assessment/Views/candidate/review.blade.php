<x-candidate-layout>
    <div class="max-w-2xl mx-auto bg-slate-900 border border-slate-800 rounded-xl p-8 shadow-sm text-center">
        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold {{ ($summary['is_passed'] ?? false) ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-rose-500/10 text-rose-400 border border-rose-500/20' }}">
            {{ ($summary['is_passed'] ?? false) ? 'PASSED & EVALUATED' : 'TEST SUBMITTED' }}
        </span>

        <h1 class="text-2xl font-bold text-white mt-4">{{ $summary['test_title'] ?? 'Assessment Test' }}</h1>
        <p class="text-xs text-slate-400 mt-1">Submitted at {{ $summary['submitted_at'] ?? now()->toIso8601String() }}</p>

        <!-- Score Display -->
        <div class="my-8 p-6 rounded-xl bg-slate-950 border border-slate-800/80">
            <p class="text-xs font-medium text-slate-400 uppercase tracking-wider">Final Test Score</p>
            <p class="text-4xl font-extrabold text-indigo-400 mt-2">{{ $summary['total_score'] ?? 0 }}</p>
            <p class="text-xs text-slate-500 mt-1">Passing threshold: {{ $summary['pass_score'] ?? 0 }} points</p>
        </div>

        <div class="grid grid-cols-2 gap-4 text-sm mb-8 text-slate-300">
            <div class="p-3 bg-slate-950 rounded-lg border border-slate-800">
                <span class="block text-xs text-slate-400">Total Questions</span>
                <span class="font-bold text-white text-base">{{ $summary['total_questions'] ?? 0 }}</span>
            </div>
            <div class="p-3 bg-slate-950 rounded-lg border border-slate-800">
                <span class="block text-xs text-slate-400">Correct Answers</span>
                <span class="font-bold text-emerald-400 text-base">{{ $summary['correct_answers'] ?? 0 }}</span>
            </div>
        </div>

        <a href="{{ route('candidate.portal') }}" class="inline-flex items-center justify-center px-6 py-2.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-sm transition-colors shadow-md">
            Back to Student Portal
        </a>
    </div>
</x-candidate-layout>
