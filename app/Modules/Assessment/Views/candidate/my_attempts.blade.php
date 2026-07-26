<x-candidate-layout>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-white">My Test Attempt History</h1>
        <p class="text-sm text-slate-400">View your past CBT examination results and evaluations.</p>
    </div>

    <div class="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden shadow-sm">
        <table class="w-full text-left text-sm text-slate-300">
            <thead class="bg-slate-950 text-xs uppercase text-slate-400 border-b border-slate-800">
                <tr>
                    <th class="p-4">Test Title</th>
                    <th class="p-4">Started At</th>
                    <th class="p-4">Status</th>
                    <th class="p-4">Score</th>
                    <th class="p-4 text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60">
                @forelse ($attempts as $att)
                    <tr>
                        <td class="p-4 font-semibold text-white">{{ $att->test?->title ?? 'Test Session' }}</td>
                        <td class="p-4 text-slate-400 text-xs">{{ $att->started_at?->format('d M Y, H:i') }}</td>
                        <td class="p-4">
                            <span class="px-2.5 py-0.5 text-xs font-semibold rounded bg-slate-800 text-slate-300 border border-slate-700">
                                {{ $att->status->label() }}
                            </span>
                        </td>
                        <td class="p-4 font-bold text-indigo-400">{{ $att->total_score ?? 'N/A' }}</td>
                        <td class="p-4 text-right">
                            @if ($att->status->value === 'in_progress')
                                <a href="{{ route('candidate.exam', $att) }}" class="text-xs text-indigo-400 font-semibold hover:underline">Resume Exam &rarr;</a>
                            @else
                                <a href="{{ route('candidate.review', $att) }}" class="text-xs text-slate-400 font-semibold hover:underline">View Review</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="p-6 text-center text-slate-500">No test attempts recorded yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $attempts->links() }}
    </div>
</x-candidate-layout>
