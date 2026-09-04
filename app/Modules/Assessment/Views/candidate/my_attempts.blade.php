<x-candidate-layout>
    <div class="mb-6">
        <a href="{{ route('candidate.portal') }}" class="inline-flex items-center text-xs text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white transition-colors mb-2">
            &larr; Back to Dashboard
        </a>
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">My Test Attempt History</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">Track all your assessment sessions, active exam progress, and session history.</p>
    </div>

    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl overflow-hidden shadow-sm">
        <table class="w-full text-left text-sm text-slate-700 dark:text-slate-300">
            <thead class="bg-slate-50 dark:bg-slate-950 text-xs uppercase text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
                <tr>
                    <th class="p-4">Assessment</th>
                    <th class="p-4">Attempt Status</th>
                    <th class="p-4">Started At</th>
                    <th class="p-4">Last Activity</th>
                    <th class="p-4">Progress / Score</th>
                    <th class="p-4 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 dark:divide-slate-800/60">
                @forelse ($attempts as $att)
                    @php
                        $res = $att->result_summary;
                        $isPassed = $res['is_passed'] ?? false;
                        $isSim = $att->test?->isSimulator() ?? false;
                        $isInProgress = $att->status->value === 'in_progress';
                        $correctCount = $res['correct_count'] ?? ($att->correct_answers_count ?? 0);
                        $totalQ = $res['total_questions'] ?? ($att->total_questions_count ?? 0);
                        $finalScore = $res['final_score'] ?? ($att->total_score ?? 0);
                        $passScore = $res['pass_score'] ?? ($att->test?->pass_score ?? 0);
                    @endphp
                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors">
                        <td class="p-4 font-semibold text-slate-900 dark:text-white">
                            {{ $att->test?->title ?? 'Test Session' }}
                        </td>
                        <td class="p-4">
                            @if ($isInProgress)
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 text-xs font-semibold rounded bg-amber-500/15 text-amber-700 dark:text-amber-400 border border-amber-500/30">
                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                    IN PROGRESS
                                </span>
                            @elseif ($att->status->value === 'submitted')
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 text-xs font-semibold rounded bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 border border-emerald-500/30">
                                    SUBMITTED
                                </span>
                            @elseif ($att->status->value === 'expired')
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 text-xs font-semibold rounded bg-slate-500/15 text-slate-700 dark:text-slate-400 border border-slate-500/30">
                                    TIME EXPIRED
                                </span>
                            @elseif ($att->status->value === 'cancelled')
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 text-xs font-semibold rounded bg-rose-500/15 text-rose-700 dark:text-rose-400 border border-rose-500/30">
                                    CANCELLED
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 text-xs font-semibold rounded bg-slate-500/15 text-slate-700 dark:text-slate-400 border border-slate-500/30">
                                    {{ strtoupper($att->status->value) }}
                                </span>
                            @endif
                        </td>
                        <td class="p-4 text-slate-500 dark:text-slate-400 text-xs">
                            {{ $att->started_at?->format('d M Y, H:i') ?? 'N/A' }}
                        </td>
                        <td class="p-4 text-slate-500 dark:text-slate-400 text-xs">
                            {{ ($att->submitted_at ?? $att->updated_at)?->format('d M Y, H:i') ?? 'N/A' }}
                        </td>
                        <td class="p-4">
                            @if ($isInProgress)
                                <span class="text-xs text-amber-600 dark:text-amber-400 font-medium">Active Session</span>
                            @elseif ($isSim)
                                <span class="text-xs font-bold text-slate-900 dark:text-white">{{ $correctCount }} / {{ $totalQ }}</span>
                            @else
                                <span class="text-xs font-bold text-slate-900 dark:text-white">{{ $finalScore }} <span class="font-normal text-slate-500 dark:text-slate-400">/ {{ $passScore }}</span></span>
                            @endif
                        </td>
                        <td class="p-4 text-right space-x-2">
                            @if ($isInProgress)
                                <a href="{{ route('candidate.exam', $att) }}" class="inline-flex items-center gap-1 text-xs text-indigo-600 dark:text-indigo-400 font-bold hover:underline focus:outline-none focus:ring-1 focus:ring-indigo-500 rounded px-1.5 py-0.5">
                                    <span>Resume Exam</span>
                                    <span>&rarr;</span>
                                </a>
                            @else
                                <a href="{{ route('candidate.review', $att) }}" class="inline-flex items-center gap-1 text-xs text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white font-semibold hover:underline focus:outline-none focus:ring-1 focus:ring-indigo-500 rounded px-1.5 py-0.5">
                                    <span>View Result</span>
                                    <span>&rarr;</span>
                                </a>
                                @if ($isPassed && $att->certificate && !$isSim)
                                    <a href="{{ route('candidate.certificates.download', $att->certificate->id) }}" target="_blank" class="inline-flex items-center gap-1 text-xs text-amber-600 dark:text-amber-400 font-semibold hover:underline ml-2">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
                                        </svg>
                                        <span>Certificate</span>
                                    </a>
                                @endif
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="p-12 text-center">
                            <div class="max-w-sm mx-auto space-y-3">
                                <div class="w-12 h-12 mx-auto rounded-2xl bg-indigo-50 dark:bg-indigo-950/50 border border-indigo-200 dark:border-indigo-800/60 flex items-center justify-center text-indigo-600 dark:text-indigo-400">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <h3 class="text-sm font-bold text-slate-900 dark:text-white">No test attempts yet.</h3>
                                <p class="text-xs text-slate-500 dark:text-slate-400">Use Available Tests to start an assessment session.</p>
                                <div class="pt-2">
                                    <a href="{{ route('candidate.available-tests') }}" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs transition-colors shadow-sm">
                                        <span>Browse Available Tests</span>
                                        <span>&rarr;</span>
                                    </a>
                                </div>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $attempts->links() }}
    </div>
</x-candidate-layout>
