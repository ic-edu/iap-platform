<x-candidate-layout>
    <div class="max-w-4xl mx-auto">
        <!-- Top Navigation Header -->
        <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <a href="{{ route('candidate.my-attempts') }}" class="inline-flex items-center text-xs text-slate-400 hover:text-white transition-colors mb-2">
                    &larr; Back to Attempt History
                </a>
                <h1 class="text-3xl font-bold tracking-tight text-white flex items-center gap-3">
                    {{ $summary['test_title'] ?? 'Assessment Review' }}
                </h1>
                <p class="text-xs text-slate-400 mt-1">Submitted at {{ $summary['submitted_at'] ?? now()->toIso8601String() }}</p>
            </div>

            <!-- Pass / Fail Badge -->
            <div>
                @if($summary['is_passed'] ?? false)
                    <span class="inline-flex items-center gap-1.5 px-4 py-2 rounded-full text-sm font-bold bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 shadow-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        RESULT: PASSED
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 px-4 py-2 rounded-full text-sm font-bold bg-rose-500/20 text-rose-400 border border-rose-500/30 shadow-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        RESULT: FAILED
                    </span>
                @endif
            </div>
        </div>

        <!-- Metrics & Performance Card Grid -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-8">
            <div class="p-5 bg-slate-900 border border-slate-800 rounded-xl text-center shadow-sm">
                <span class="block text-xs font-medium text-slate-400 uppercase tracking-wider">Final Test Score</span>
                <span class="text-3xl font-extrabold {{ ($summary['is_passed'] ?? false) ? 'text-emerald-400' : 'text-rose-400' }} mt-1 block">
                    {{ $summary['total_score'] ?? 0 }}
                </span>
                <span class="text-xs text-slate-500 mt-1 block">Pass Threshold: {{ $summary['pass_score'] ?? 0 }}</span>
            </div>

            <div class="p-5 bg-slate-900 border border-slate-800 rounded-xl text-center shadow-sm">
                <span class="block text-xs font-medium text-slate-400 uppercase tracking-wider">Accuracy &amp; Grade</span>
                <span class="text-3xl font-extrabold text-indigo-400 mt-1 block">
                    {{ $summary['percentage'] ?? 0 }}%
                </span>
                <span class="text-xs font-semibold text-amber-400 mt-1 block">Grade {{ $summary['grade'] ?? 'D' }}</span>
            </div>

            <div class="p-5 bg-slate-900 border border-slate-800 rounded-xl text-center shadow-sm">
                <span class="block text-xs font-medium text-slate-400 uppercase tracking-wider">Correct Answers</span>
                <span class="text-3xl font-extrabold text-emerald-400 mt-1 block">
                    {{ $summary['correct_answers'] ?? 0 }}
                </span>
                <span class="text-xs text-slate-500 mt-1 block">out of {{ $summary['total_questions'] ?? 0 }} questions</span>
            </div>

            <div class="p-5 bg-slate-900 border border-slate-800 rounded-xl text-center shadow-sm">
                <span class="block text-xs font-medium text-slate-400 uppercase tracking-wider">Incorrect Answers</span>
                <span class="text-3xl font-extrabold text-rose-400 mt-1 block">
                    {{ $summary['incorrect_answers'] ?? 0 }}
                </span>
                <span class="text-xs text-slate-500 mt-1 block">Review items below</span>
            </div>
        </div>

        <!-- Certificate Issued Card (If Passed) -->
        @if(($summary['is_passed'] ?? false) && !empty($summary['certificate_id']))
            <div class="mb-8 p-6 rounded-xl bg-indigo-950/40 border border-indigo-500/30 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h3 class="text-base font-bold text-indigo-300 flex items-center gap-2">
                        <span>🎓</span> Official Digital Certificate Issued!
                    </h3>
                    <p class="text-xs text-slate-400 mt-1">Certificate #{{ $summary['certificate_number'] ?? '' }}</p>
                </div>
                <a href="{{ route('candidate.certificates.download', $summary['certificate_id']) }}" target="_blank" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs transition-colors shadow-md shadow-indigo-600/30 shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                    Download Digital Certificate
                </a>
            </div>
        @endif

        <!-- Skill & Section Breakdown + Learning Recommendations -->
        @if(!empty($summary['section_stats']) || !empty($summary['suggested_learning_areas']))
            <div class="grid sm:grid-cols-2 gap-5 mb-8">
                <!-- Section Breakdown -->
                <div class="bg-slate-900 border border-slate-800 rounded-xl p-5 shadow-sm">
                    <h3 class="text-sm font-bold text-white mb-3 flex items-center gap-2">
                        📊 Section &amp; Skill Breakdown
                    </h3>
                    <div class="space-y-3 text-xs">
                        @foreach($summary['section_stats'] as $secName => $stats)
                            @php
                                $acc = $stats['total'] > 0 ? round(($stats['correct'] / $stats['total']) * 100) : 0;
                            @endphp
                            <div>
                                <div class="flex justify-between text-slate-300 mb-1">
                                    <span>{{ $secName }}</span>
                                    <span class="font-bold text-indigo-400">{{ $stats['correct'] }}/{{ $stats['total'] }} ({{ $acc }}%)</span>
                                </div>
                                <div class="w-full h-2 bg-slate-950 rounded-full overflow-hidden border border-slate-800">
                                    <div class="h-full bg-indigo-500 rounded-full" style="width: {{ $acc }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Suggested Learning Areas -->
                <div class="bg-slate-900 border border-slate-800 rounded-xl p-5 shadow-sm">
                    <h3 class="text-sm font-bold text-white mb-3 flex items-center gap-2">
                        💡 Suggested Learning Areas
                    </h3>
                    <ul class="space-y-2 text-xs text-slate-300">
                        @forelse($summary['suggested_learning_areas'] as $area)
                            <li class="flex items-start gap-2 bg-slate-950 p-2.5 rounded-lg border border-slate-800">
                                <span class="text-amber-400 shrink-0">🎯</span>
                                <span>{{ $area }}</span>
                            </li>
                        @empty
                            <li class="text-slate-500">All section targets achieved with high accuracy!</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        @endif

        <!-- Detailed Question Review Header -->
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-xl font-bold text-white flex items-center gap-2">
                📝 Detailed Question Review
            </h2>
            <span class="text-xs text-slate-400">Read-Only Mode</span>
        </div>

        <!-- Detailed Question Cards List -->
        <div class="space-y-6 mb-8">
            @forelse($summary['detailed_questions'] ?? [] as $q)
                <div class="bg-slate-900 border {{ $q['is_correct'] ? 'border-emerald-500/30' : 'border-rose-500/30' }} rounded-xl p-6 shadow-sm transition-all">
                    <!-- Question Header & Badges -->
                    <div class="flex items-center justify-between gap-4 mb-4 pb-3 border-b border-slate-800 flex-wrap">
                        <div class="flex items-center gap-3">
                            <span class="w-8 h-8 rounded-lg bg-slate-800 border border-slate-700 flex items-center justify-center font-bold text-xs text-white">
                                Q{{ $q['index'] }}
                            </span>

                            @if($q['is_correct'])
                                <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-bold bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                    CORRECT (+{{ $q['score_earned'] }} pts)
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-bold bg-rose-500/20 text-rose-400 border border-rose-500/30">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                    INCORRECT (0 / {{ $q['points_possible'] }} pts)
                                </span>
                            @endif
                        </div>

                        <div class="flex items-center gap-2 text-xs">
                            <span class="px-2.5 py-0.5 rounded bg-slate-950 text-slate-400 border border-slate-800 font-medium">
                                {{ $q['question_type'] }}
                            </span>
                            <span class="px-2.5 py-0.5 rounded bg-slate-950 text-slate-400 border border-slate-800 font-medium">
                                {{ $q['difficulty'] }}
                            </span>
                        </div>
                    </div>

                    <!-- Reading Passage Block (if available) -->
                    @if(!empty($q['passage_text']))
                        <div class="mb-4 p-4 rounded-lg bg-slate-950 border border-slate-800 text-xs text-slate-300 space-y-1">
                            <span class="font-bold text-indigo-400 block mb-1">📖 Reading Passage</span>
                            <p class="leading-relaxed">{!! nl2br(e($q['passage_text'])) !!}</p>
                        </div>
                    @endif

                    <!-- Audio Player Block (if available) -->
                    @if(!empty($q['audio_url']))
                        <div class="mb-4 p-3 rounded-lg bg-slate-950 border border-slate-800">
                            <span class="font-bold text-xs text-indigo-400 block mb-1.5">🎧 Audio Prompt</span>
                            <audio controls class="w-full h-8">
                                <source src="{{ $q['audio_url'] }}" type="audio/mpeg">
                                Your browser does not support audio playback.
                            </audio>
                        </div>
                    @endif

                    <!-- Image Block (if available) -->
                    @if(!empty($q['image_url']))
                        <div class="mb-4 text-center">
                            <img src="{{ $q['image_url'] }}" alt="Question Attachment" class="max-h-64 max-w-full rounded-lg mx-auto border border-slate-800">
                        </div>
                    @endif

                    <!-- Question Prompt Text -->
                    <h3 class="text-base font-semibold text-white mb-4 leading-snug">
                        {{ $q['prompt'] }}
                    </h3>

                    <!-- Choices List -->
                    @if(!empty($q['choices']))
                        <div class="space-y-2 mb-4">
                            @foreach($q['choices'] as $choice)
                                @php
                                    $isSelected = $choice['is_selected'];
                                    $isCorrectChoice = $choice['is_correct'];
                                    
                                    $choiceStyle = 'bg-slate-950 border-slate-800 text-slate-300';
                                    if ($isSelected && $isCorrectChoice) {
                                        $choiceStyle = 'bg-emerald-500/15 border-emerald-500/40 text-emerald-300 font-semibold';
                                    } elseif ($isSelected && !$isCorrectChoice) {
                                        $choiceStyle = 'bg-rose-500/15 border-rose-500/40 text-rose-300 font-semibold';
                                    } elseif (!$isSelected && $isCorrectChoice) {
                                        $choiceStyle = 'bg-emerald-500/10 border-emerald-500/30 text-emerald-400 font-semibold';
                                    }
                                @endphp

                                <div class="p-3 rounded-lg border text-xs flex items-center justify-between {{ $choiceStyle }}">
                                    <div class="flex items-center gap-2.5">
                                        <span class="w-6 h-6 rounded bg-slate-900 border border-slate-700 flex items-center justify-center font-bold text-white shrink-0">
                                            {{ $choice['label'] }}
                                        </span>
                                        <span>{{ $choice['content'] }}</span>
                                    </div>

                                    <div class="text-xs shrink-0">
                                        @if($isSelected && $isCorrectChoice)
                                            <span class="text-emerald-400 font-bold">✓ Your Answer (Correct)</span>
                                        @elseif($isSelected && !$isCorrectChoice)
                                            <span class="text-rose-400 font-bold">✗ Your Answer (Incorrect)</span>
                                        @elseif(!$isSelected && $isCorrectChoice)
                                            <span class="text-emerald-400 font-bold">✓ Correct Answer</span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <!-- Text / Short Answer / Essay / Audio Answer Summary -->
                        <div class="grid sm:grid-cols-2 gap-3 mb-4 text-xs">
                            <div class="p-3 rounded-lg bg-slate-950 border border-slate-800">
                                <span class="block text-slate-400 text-xxs uppercase mb-0.5">Candidate Answer</span>
                                <span class="font-semibold text-white">{{ $q['candidate_answer'] }}</span>
                            </div>
                            <div class="p-3 rounded-lg bg-slate-950 border border-slate-800">
                                <span class="block text-slate-400 text-xxs uppercase mb-0.5">Expected Answer</span>
                                <span class="font-semibold text-emerald-400">{{ $q['correct_answer'] }}</span>
                            </div>
                        </div>
                    @endif

                    <!-- Rationale & Explanation Box -->
                    <div class="mt-4 p-4 rounded-lg bg-indigo-950/20 border border-indigo-500/20 text-xs">
                        <span class="font-bold text-indigo-300 block mb-1 flex items-center gap-1.5">
                            💡 Explanation &amp; Rationale
                        </span>
                        <p class="text-slate-300 leading-relaxed">{{ $q['explanation'] }}</p>

                        @if(!empty($q['feedback']))
                            <div class="mt-3 pt-3 border-t border-indigo-500/20 text-slate-300">
                                <span class="font-bold text-amber-400 block mb-0.5">💬 Instructor Feedback</span>
                                <p>{{ $q['feedback'] }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            @empty
                <div class="p-8 bg-slate-900 border border-slate-800 rounded-xl text-center text-slate-500">
                    No detailed question breakdown available for this attempt.
                </div>
            @endforelse
        </div>

        <!-- Action Footer -->
        <div class="flex items-center justify-between pb-8 border-t border-slate-800 pt-6">
            <a href="{{ route('candidate.my-attempts') }}" class="px-5 py-2.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 font-semibold text-sm transition-colors border border-slate-700">
                &larr; Back to Attempts List
            </a>
            <a href="{{ route('candidate.portal') }}" class="px-5 py-2.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-sm transition-colors shadow-md">
                Return to Dashboard
            </a>
        </div>
    </div>
</x-candidate-layout>
