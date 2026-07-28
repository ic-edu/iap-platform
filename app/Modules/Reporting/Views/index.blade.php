<x-admin-layout>
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-white">Assessment Reports &amp; Analytics</h1>
            <p class="text-xs text-slate-400">View real-time candidate completion statistics, pass rate metrics, and export audit reports.</p>
        </div>
        <a href="{{ route('admin.reporting.export-csv') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-semibold rounded-lg shadow transition-colors">
            📊 Export CSV Report
        </a>
    </div>

    <!-- Analytics Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-8">
        <div class="p-5 bg-slate-900 border border-slate-800 rounded-xl text-center">
            <span class="text-xs font-medium text-slate-400 uppercase">Total Submissions</span>
            <span class="text-3xl font-extrabold text-white mt-1 block">{{ $totalAttempts }}</span>
        </div>
        <div class="p-5 bg-slate-900 border border-slate-800 rounded-xl text-center">
            <span class="text-xs font-medium text-slate-400 uppercase">Pass Rate Percentage</span>
            <span class="text-3xl font-extrabold text-emerald-400 mt-1 block">{{ $passRate }}%</span>
            <span class="text-xs text-slate-500 mt-0.5 block">{{ $totalPassed }} candidates passed</span>
        </div>
        <div class="p-5 bg-slate-900 border border-slate-800 rounded-xl text-center">
            <span class="text-xs font-medium text-slate-400 uppercase">Certificates Issued</span>
            <span class="text-3xl font-extrabold text-indigo-400 mt-1 block">{{ $totalCertificates }}</span>
        </div>
        <div class="p-5 bg-slate-900 border border-slate-800 rounded-xl text-center">
            <span class="text-xs font-medium text-slate-400 uppercase">Active Test Packages</span>
            <span class="text-3xl font-extrabold text-amber-400 mt-1 block">{{ $totalTests }}</span>
        </div>
    </div>

    <!-- Recent Submissions Table -->
    <div class="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden shadow-sm">
        <div class="p-4 border-b border-slate-800">
            <h2 class="text-sm font-bold text-white">Recent Assessment Submissions</h2>
        </div>
        <table class="w-full text-left text-sm text-slate-300">
            <thead class="bg-slate-950 text-xs uppercase text-slate-400 border-b border-slate-800">
                <tr>
                    <th class="p-4">Candidate</th>
                    <th class="p-4">Assessment Title</th>
                    <th class="p-4">Final Score</th>
                    <th class="p-4">Result</th>
                    <th class="p-4 text-right">Date</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60">
                @forelse ($recentAttempts as $att)
                    @php
                        $res = $att->result_summary;
                        $passed = $res['is_passed'] ?? false;
                    @endphp
                    <tr>
                        <td class="p-4 font-semibold text-white">
                            {{ $att->user?->name ?? 'Candidate' }}
                            <div class="text-xs font-normal text-slate-400 font-mono">{{ $att->user?->email }}</div>
                        </td>
                        <td class="p-4 text-xs font-medium text-indigo-400">{{ $att->test?->title ?? 'Test Session' }}</td>
                        <td class="p-4 font-bold text-white">{{ $res['final_score'] ?? 0 }} <span class="text-xs font-normal text-slate-500">/ {{ $res['pass_score'] ?? 0 }}</span></td>
                        <td class="p-4">
                            @if ($passed)
                                <span class="px-2.5 py-0.5 text-xs font-bold rounded bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">PASSED</span>
                            @else
                                <span class="px-2.5 py-0.5 text-xs font-bold rounded bg-rose-500/10 text-rose-400 border border-rose-500/20">FAILED</span>
                            @endif
                        </td>
                        <td class="p-4 text-right text-xs text-slate-400">{{ $att->submitted_at?->format('d M Y, H:i') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="p-8 text-center text-slate-500">No submission records logged yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-admin-layout>
