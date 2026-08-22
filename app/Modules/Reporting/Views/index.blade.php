@extends('layouts.admin')

@section('title', 'Operational Reports & Analytics — Assessment & Candidate Statistics')

@section('content')
<div class="space-y-6">

    {{-- Page Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-800">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="px-2.5 py-0.5 rounded text-[11px] font-bold tracking-wider uppercase bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                    OPERATIONAL ANALYTICS
                </span>
            </div>
            <h1 class="text-2xl font-black text-white tracking-tight">Assessment Reports &amp; Analytics</h1>
            <p class="text-sm text-slate-400">Live candidate completions, pass/fail performance, test modes, and institutional analytics.</p>
        </div>
        <a href="{{ route('admin.reporting.export-csv') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold rounded-lg shadow-md shadow-emerald-600/20 transition-all">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            Export CSV Audit Report
        </a>
    </div>

    {{-- KPI Section 1: Candidate Results & Performance --}}
    <section>
        <h2 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3 flex items-center gap-2">
            <span>Assessment Results &amp; Candidate Activity</span>
        </h2>
        <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-3">
            
            <div class="p-3.5 bg-slate-900/90 border border-slate-800 rounded-xl text-center">
                <span class="text-[10px] font-bold uppercase text-slate-400 block truncate">Total Submissions</span>
                <span class="text-2xl font-black text-white mt-1 block">{{ number_format($totalAttempts) }}</span>
                <span class="text-[10px] text-slate-500 mt-0.5 block">Finished tests</span>
            </div>

            <div class="p-3.5 bg-slate-900/90 border border-slate-800 rounded-xl text-center">
                <span class="text-[10px] font-bold uppercase text-slate-400 block truncate">Pass Rate</span>
                <span class="text-2xl font-black text-emerald-400 mt-1 block">{{ $passRate }}%</span>
                <span class="text-[10px] text-emerald-500/80 mt-0.5 block">{{ number_format($totalPassed) }} passed</span>
            </div>

            <div class="p-3.5 bg-slate-900/90 border border-slate-800 rounded-xl text-center">
                <span class="text-[10px] font-bold uppercase text-slate-400 block truncate">Failed Tests</span>
                <span class="text-2xl font-black text-rose-400 mt-1 block">{{ number_format($totalFailed) }}</span>
                <span class="text-[10px] text-rose-500/80 mt-0.5 block">Below passing score</span>
            </div>

            <div class="p-3.5 bg-slate-900/90 border border-slate-800 rounded-xl text-center">
                <span class="text-[10px] font-bold uppercase text-slate-400 block truncate">Certificates</span>
                <span class="text-2xl font-black text-indigo-400 mt-1 block">{{ number_format($totalCertificates) }}</span>
                <span class="text-[10px] text-indigo-500/80 mt-0.5 block">Issued &amp; verified</span>
            </div>

            <div class="p-3.5 bg-slate-900/90 border border-slate-800 rounded-xl text-center">
                <span class="text-[10px] font-bold uppercase text-slate-400 block truncate">Active Assignments</span>
                <span class="text-2xl font-black text-amber-400 mt-1 block">{{ number_format($activeAssignments) }}</span>
                <span class="text-[10px] text-amber-500/80 mt-0.5 block">Allocated seats</span>
            </div>

            <div class="p-3.5 bg-slate-900/90 border border-slate-800 rounded-xl text-center">
                <span class="text-[10px] font-bold uppercase text-slate-400 block truncate">In Progress</span>
                <span class="text-2xl font-black text-sky-400 mt-1 block">{{ number_format($inProgressAttempts) }}</span>
                <span class="text-[10px] text-sky-500/80 mt-0.5 block">Active exam sessions</span>
            </div>

            <div class="p-3.5 bg-slate-900/90 border border-slate-800 rounded-xl text-center">
                <span class="text-[10px] font-bold uppercase text-slate-400 block truncate">Paid / Eligible</span>
                <span class="text-2xl font-black text-emerald-400 mt-1 block">{{ number_format($paidEligibleCandidates) }}</span>
                <span class="text-[10px] text-slate-500 mt-0.5 block">Mock Test verified</span>
            </div>

            <div class="p-3.5 bg-slate-900/90 border border-slate-800 rounded-xl text-center">
                <span class="text-[10px] font-bold uppercase text-slate-400 block truncate">Registered Students</span>
                <span class="text-2xl font-black text-white mt-1 block">{{ number_format($totalStudents) }}</span>
                <span class="text-[10px] text-slate-500 mt-0.5 block">Total candidates</span>
            </div>

        </div>
    </section>

    {{-- KPI Section 2: Institutional Inventory & Staff Distribution --}}
    <section>
        <h2 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3 flex items-center gap-2">
            <span>Assessment Inventory &amp; Institutional Staff</span>
        </h2>
        <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-3">
            <div class="p-3 bg-slate-950/70 border border-slate-800 rounded-lg">
                <span class="text-[10px] font-semibold text-slate-400 block">Total Assessments</span>
                <span class="text-lg font-bold text-white mt-0.5 block">{{ number_format($totalTests) }}</span>
            </div>
            <div class="p-3 bg-slate-950/70 border border-slate-800 rounded-lg">
                <span class="text-[10px] font-semibold text-slate-400 block">Published / Live</span>
                <span class="text-lg font-bold text-emerald-400 mt-0.5 block">{{ number_format($publishedTests) }}</span>
            </div>
            <div class="p-3 bg-slate-950/70 border border-slate-800 rounded-lg">
                <span class="text-[10px] font-semibold text-slate-400 block">Test Simulators</span>
                <span class="text-lg font-bold text-amber-400 mt-0.5 block">{{ number_format($simulatorTests) }}</span>
            </div>
            <div class="p-3 bg-slate-950/70 border border-slate-800 rounded-lg">
                <span class="text-[10px] font-semibold text-slate-400 block">Mock Tests</span>
                <span class="text-lg font-bold text-rose-400 mt-0.5 block">{{ number_format($realTests) }}</span>
            </div>
            <div class="p-3 bg-slate-950/70 border border-slate-800 rounded-lg">
                <span class="text-[10px] font-semibold text-slate-400 block">Teachers</span>
                <span class="text-lg font-bold text-indigo-400 mt-0.5 block">{{ number_format($totalTeachers) }}</span>
            </div>
            <div class="p-3 bg-slate-950/70 border border-slate-800 rounded-lg">
                <span class="text-[10px] font-semibold text-slate-400 block">Repository Managers</span>
                <span class="text-lg font-bold text-purple-400 mt-0.5 block">{{ number_format($totalRMs) }}</span>
            </div>
            <div class="p-3 bg-slate-950/70 border border-slate-800 rounded-lg">
                <span class="text-[10px] font-semibold text-slate-400 block">Admins &amp; Staff</span>
                <span class="text-lg font-bold text-slate-200 mt-0.5 block">{{ number_format($totalAdmins + $totalSuperAdmins) }}</span>
            </div>
        </div>
    </section>

    {{-- Visual Analytics & Charts Hub --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Chart 1: Assessment Activity Trend (7 Days) --}}
        <div class="p-5 bg-slate-900 border border-slate-800 rounded-xl shadow-sm">
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-300 mb-1 flex items-center justify-between">
                <span>📈 Attempt Activity (Last 7 Days)</span>
            </h3>
            <p class="text-[11px] text-slate-500 mb-4">Daily volume of candidate assessment attempts</p>
            <div class="relative h-48 flex items-center justify-center">
                <canvas id="chartActivityTrend"></canvas>
            </div>
        </div>

        {{-- Chart 2: Pass vs Fail Distribution --}}
        <div class="p-5 bg-slate-900 border border-slate-800 rounded-xl shadow-sm">
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-300 mb-1 flex items-center justify-between">
                <span>🎯 Pass vs Fail Performance</span>
            </h3>
            <p class="text-[11px] text-slate-500 mb-4">Final candidate score outcomes</p>
            <div class="relative h-48 flex items-center justify-center">
                <canvas id="chartPassFail"></canvas>
            </div>
        </div>

        {{-- Chart 3: Assessment Mode Breakdown --}}
        <div class="p-5 bg-slate-900 border border-slate-800 rounded-xl shadow-sm">
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-300 mb-1 flex items-center justify-between">
                <span>🛡️ Mode: Test Simulator vs Mock Test</span>
            </h3>
            <p class="text-[11px] text-slate-500 mb-4">Attempts distributed by assessment mode</p>
            <div class="relative h-48 flex items-center justify-center">
                <canvas id="chartModeBreakdown"></canvas>
            </div>
        </div>

    </div>

    {{-- Popular Assessments & Status Breakdown Section --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {{-- Popular Assessments by Attempts --}}
        <div class="p-5 bg-slate-900 border border-slate-800 rounded-xl shadow-sm">
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-300 mb-3">
                🔥 Top Assessments by Attempt Volume
            </h3>
            <div class="divide-y divide-slate-800/80">
                @forelse($popularAssessments as $t)
                <div class="py-2.5 flex items-center justify-between gap-3 text-xs">
                    <div class="min-w-0">
                        <span class="font-bold text-white block truncate">{{ $t->title }}</span>
                        <span class="text-[10px] text-slate-400 uppercase tracking-wider">{{ is_object($t->test_type) ? $t->test_type->label() : $t->test_type }}</span>
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0">
                        <span class="px-1.5 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider {{ $t->isRealTest() ? 'bg-rose-500/20 text-rose-300 border border-rose-500/30' : 'bg-amber-500/20 text-amber-300 border border-amber-500/30' }}">
                            {{ $t->assessment_mode?->label() ?? 'Assessment' }}
                        </span>
                        <span class="font-black text-indigo-400">{{ number_format($t->attempts_count) }} attempts</span>
                    </div>
                </div>
                @empty
                <p class="text-xs text-slate-500 py-4 text-center">No assessment attempt data yet.</p>
                @endforelse
            </div>
        </div>

        {{-- Candidate Lifecycle Status --}}
        <div class="p-5 bg-slate-900 border border-slate-800 rounded-xl shadow-sm">
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-300 mb-3">
                📊 Candidate Assessment Status
            </h3>
            <div class="space-y-2.5">
                <div class="p-3 bg-slate-950/60 border border-slate-800/80 rounded-lg flex items-center justify-between">
                    <span class="text-xs font-semibold text-amber-300 flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-amber-400"></span> Active Assignments
                    </span>
                    <span class="text-sm font-bold text-white font-mono">{{ number_format($activeAssignments) }}</span>
                </div>
                <div class="p-3 bg-slate-950/60 border border-slate-800/80 rounded-lg flex items-center justify-between">
                    <span class="text-xs font-semibold text-sky-300 flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-sky-400"></span> In Progress (Live Exams)
                    </span>
                    <span class="text-sm font-bold text-white font-mono">{{ number_format($inProgressAttempts) }}</span>
                </div>
                <div class="p-3 bg-slate-950/60 border border-slate-800/80 rounded-lg flex items-center justify-between">
                    <span class="text-xs font-semibold text-emerald-300 flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-emerald-400"></span> Completed &amp; Passed
                    </span>
                    <span class="text-sm font-bold text-white font-mono">{{ number_format($totalPassed) }}</span>
                </div>
                <div class="p-3 bg-slate-950/60 border border-slate-800/80 rounded-lg flex items-center justify-between">
                    <span class="text-xs font-semibold text-rose-300 flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-rose-400"></span> Completed &amp; Failed
                    </span>
                    <span class="text-sm font-bold text-white font-mono">{{ number_format($totalFailed) }}</span>
                </div>
            </div>
        </div>

    </div>
</div>

{{-- Chart.js Scripts --}}
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof Chart === 'undefined') return;

    const isDark = document.documentElement.classList.contains('dark');
    const textColor = isDark ? '#94a3b8' : '#475569';
    const gridColor = isDark ? 'rgba(51, 65, 85, 0.4)' : 'rgba(226, 232, 240, 0.8)';
    const passColor = '#10b981';
    const failColor = '#f43f5e';
    const simColor = '#f59e0b';
    const emptyColor = isDark ? '#334155' : '#cbd5e1';
    const doughnutBorder = isDark ? '#0f172a' : '#ffffff';
    const doughnutBorderWidth = 3;
    const legendColor = isDark ? '#cbd5e1' : '#1e293b';

    // 1. Activity Trend (Line Chart)
    const ctxTrend = document.getElementById('chartActivityTrend');
    if (ctxTrend) {
        new Chart(ctxTrend, {
            type: 'line',
            data: {
                labels: @json($activityTrend['labels']),
                datasets: [{
                    label: 'Attempts',
                    data: @json($activityTrend['data']),
                    borderColor: '#6366f1',
                    backgroundColor: 'rgba(99, 102, 241, 0.1)',
                    borderWidth: 2.5,
                    fill: true,
                    tension: 0.35,
                    pointBackgroundColor: '#6366f1',
                    pointRadius: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { color: gridColor }, ticks: { color: textColor, font: { size: 10 } } },
                    y: { beginAtZero: true, grid: { color: gridColor }, ticks: { color: textColor, font: { size: 10 }, stepSize: 1 } }
                }
            }
        });
    }

    // 2. Pass vs Fail (Doughnut Chart)
    const ctxPassFail = document.getElementById('chartPassFail');
    if (ctxPassFail) {
        const passedCount = {{ $totalPassed }};
        const failedCount = {{ $totalFailed }};
        const hasData = (passedCount + failedCount) > 0;

        new Chart(ctxPassFail, {
            type: 'doughnut',
            data: {
                labels: hasData ? ['Passed', 'Failed'] : ['No Data'],
                datasets: [{
                    data: hasData ? [passedCount, failedCount] : [1],
                    backgroundColor: hasData ? [passColor, failColor] : [emptyColor],
                    borderColor: doughnutBorder,
                    borderWidth: doughnutBorderWidth
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { color: legendColor, font: { size: 11 } } }
                },
                cutout: '70%'
            }
        });
    }

    // 3. Test Simulator vs Mock Test (Pie/Doughnut Chart)
    const ctxMode = document.getElementById('chartModeBreakdown');
    if (ctxMode) {
        const simCount = {{ $simulatorAttemptsCount }};
        const realCount = {{ $realTestAttemptsCount }};
        const hasModeData = (simCount + realCount) > 0;

        new Chart(ctxMode, {
            type: 'doughnut',
            data: {
                labels: hasModeData ? ['Test Simulator', 'Mock Test'] : ['No Data'],
                datasets: [{
                    data: hasModeData ? [simCount, realCount] : [1],
                    backgroundColor: hasModeData ? [simColor, failColor] : [emptyColor],
                    borderColor: doughnutBorder,
                    borderWidth: doughnutBorderWidth
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { color: legendColor, font: { size: 11 } } }
                },
                cutout: '70%'
            }
        });
    }
});
</script>
@endsection
