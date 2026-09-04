<x-candidate-layout>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">My Test Attempt History</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">View your past CBT examination results, Pass/Fail status, and digital certificates.</p>
    </div>

    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl overflow-hidden shadow-sm">
        <table class="w-full text-left text-sm text-slate-700 dark:text-slate-300">
            <thead class="bg-slate-50 dark:bg-slate-950 text-xs uppercase text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
                <tr>
                    <th class="p-4">Test Title</th>
                    <th class="p-4">Date</th>
                    <th class="p-4">Score</th>
                    <th class="p-4">Pass / Fail</th>
                    <th class="p-4 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 dark:divide-slate-800/60">
                @forelse ($attempts as $att)
                    @php
                        $res = $att->result_summary;
                        $isPassed = $res['is_passed'] ?? false;
                        $finalScore = $res['final_score'] ?? 0;
                        $passScore = $res['pass_score'] ?? 0;
                    @endphp
                    <tr>
                        <td class="p-4 font-semibold text-slate-900 dark:text-white">{{ $att->test?->title ?? 'Test Session' }}</td>
                        <td class="p-4 text-slate-500 dark:text-slate-400 text-xs">{{ $att->started_at?->format('d M Y, H:i') }}</td>
                        <td class="p-4 font-bold text-indigo-600 dark:text-indigo-400">
                            {{ $finalScore }} <span class="text-xs font-normal text-slate-500 dark:text-slate-400">/ {{ $passScore }}</span>
                        </td>
                        <td class="p-4">
                            @if ($att->status->value === 'in_progress')
                                <span class="px-2.5 py-0.5 text-xs font-semibold rounded bg-amber-500/15 text-amber-700 dark:text-amber-400 border border-amber-500/30">
                                    IN PROGRESS
                                </span>
                            @elseif ($isPassed)
                                <span class="px-2.5 py-0.5 text-xs font-semibold rounded bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 border border-emerald-500/30">
                                    PASSED
                                </span>
                            @else
                                <span class="px-2.5 py-0.5 text-xs font-semibold rounded bg-rose-500/15 text-rose-700 dark:text-rose-400 border border-rose-500/30">
                                    FAILED
                                </span>
                            @endif
                        </td>
                        <td class="p-4 text-right space-x-2">
                            @if ($att->status->value === 'in_progress')
                                <a href="{{ route('candidate.exam', $att) }}" class="text-xs text-indigo-600 dark:text-indigo-400 font-semibold hover:underline">Resume Exam &rarr;</a>
                            @else
                                <a href="{{ route('candidate.review', $att) }}" class="text-xs text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white font-semibold hover:underline">View Review</a>
                                @if ($isPassed && $att->certificate)
                                    <a href="{{ route('candidate.certificates.download', $att->certificate->id) }}" target="_blank" class="inline-flex items-center gap-1 text-xs text-indigo-600 dark:text-indigo-400 font-semibold hover:underline ml-2">
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
                        <td colspan="5" class="p-6 text-center text-slate-500 dark:text-slate-400">No test attempts recorded yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $attempts->links() }}
    </div>
</x-candidate-layout>
