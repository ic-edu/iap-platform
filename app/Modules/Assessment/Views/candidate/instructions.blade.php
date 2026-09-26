<x-candidate-layout>
    <div class="max-w-4xl mx-auto py-6">
        <!-- Back navigation link -->
        <div class="mb-6">
            <a href="{{ route('candidate.available-tests') }}" class="inline-flex items-center gap-2 text-xs font-semibold text-slate-400 hover:text-slate-200 transition-colors">
                &larr; Back to Available Tests
            </a>
        </div>

        <!-- Main Instructions Container -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 sm:p-8 shadow-xl space-y-6">
            <!-- Header with Exam Type & Title -->
            <div class="border-b border-slate-800/80 pb-5">
                <div class="flex items-center gap-3 mb-3">
                    <span class="px-2.5 py-1 text-xs font-bold rounded-md bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 uppercase tracking-wider">
                        {{ is_object($test->test_type) ? $test->test_type->label() : strtoupper($test->test_type) }}
                    </span>
                    <span class="text-xs text-slate-400 font-medium">
                        Pre-Assessment Briefing &amp; Instructions
                    </span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight leading-snug">
                    {{ $test->title }}
                </h1>
            </div>

            <!-- Metadata Metrics Strip -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 p-4 rounded-xl bg-slate-950/60 border border-slate-800">
                <div>
                    <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider block">Duration</span>
                    <span class="text-base font-bold text-white mt-0.5 block">⏱ {{ $test->duration_minutes }} Minutes</span>
                </div>
                <div>
                    <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider block">Pass Threshold</span>
                    <span class="text-base font-bold text-emerald-400 mt-0.5 block">🎯 {{ $test->getPassingThresholdDisplay() }}</span>
                </div>
                <div>
                    <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider block">Sections</span>
                    <span class="text-base font-bold text-indigo-400 mt-0.5 block">📑 {{ $test->sections->count() }} Section(s)</span>
                </div>
                <div>
                    <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider block">Total Questions</span>
                    <span class="text-base font-bold text-slate-200 mt-0.5 block">📝 {{ $totalQuestions }} Questions</span>
                </div>
            </div>

            <!-- General Candidate Instructions Body -->
            <div class="space-y-4">
                <h2 class="text-base font-bold text-white uppercase tracking-wide flex items-center gap-2">
                    <span>📋</span> Examination Guidelines &amp; Directions
                </h2>

                @if($test->isRealTest())
                <div class="p-4 rounded-xl bg-indigo-50 dark:bg-indigo-950/40 border border-indigo-200 dark:border-indigo-500/30 text-indigo-900 dark:text-indigo-200 text-xs sm:text-sm flex items-center gap-3">
                    <span class="text-lg flex-shrink-0">🛡️</span>
                    <p class="leading-relaxed">
                        <strong class="font-bold text-indigo-950 dark:text-indigo-100">Mock Test Notice:</strong> This Mock Test is independently prepared for assessment purposes and is not affiliated with, endorsed by, or produced by any third-party test provider.
                    </p>
                </div>
                @endif

                <div class="p-5 rounded-xl bg-slate-950 border border-slate-800 text-slate-300 text-sm sm:text-base leading-relaxed whitespace-pre-line">
                    {{ $test->instructions }}
                </div>
            </div>

            <!-- Section Overview if available -->
            @if($test->sections->isNotEmpty())
            <div class="space-y-3 pt-2">
                <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">
                    Assessment Section Breakdown
                </h3>
                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach($test->sections as $sec)
                    <div class="p-3.5 rounded-lg bg-slate-950/40 border border-slate-800/80 flex items-center justify-between">
                        <div>
                            <div class="font-bold text-sm text-slate-200">{{ $sec->title }}</div>
                            <div class="text-xs text-indigo-400 font-medium capitalize">
                                {{ is_object($sec->section_type) ? $sec->section_type->label() : $sec->section_type }}
                            </div>
                        </div>
                        <span class="text-xs text-slate-400 font-semibold bg-slate-900 px-2.5 py-1 rounded border border-slate-800">
                            {{ $sec->testQuestions->count() }} Qs
                        </span>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            <!-- Confirmation & Start Action Bar -->
            <div class="pt-6 border-t border-slate-800 flex flex-col sm:flex-row items-center justify-between gap-4">
                <p class="text-xs text-slate-400 text-center sm:text-left">
                    💡 Clicking <strong class="text-slate-200">Begin Assessment</strong> will start your timed CBT session.
                </p>

                <form method="POST" action="{{ route('candidate.tests.start', $test) }}" class="w-full sm:w-auto">
                    @csrf
                    <button type="submit" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-8 py-3.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-extrabold text-sm transition-all shadow-lg shadow-indigo-600/30 hover:scale-[1.02] active:scale-[0.98]">
                        <span>🚀 I Understand &amp; Begin Assessment</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</x-candidate-layout>
