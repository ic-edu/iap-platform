@extends('layouts.admin')

@section('title', 'IRQA Quality Analytics — iC.edu Platform')

@push('styles')
<style>
.an-workspace { display: flex; flex-direction: column; gap: 1.75rem; }

.an-hero {
    background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #0f172a 100%);
    border: 1px solid #1e293b;
    border-radius: 1.25rem;
    padding: 1.75rem 2rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1.5rem;
    flex-wrap: wrap;
}
.an-hero__title { font-size: 1.5rem; font-weight: 800; color: #fff; margin: 0 0 .3rem; }
.an-hero__sub   { font-size: .85rem; color: #94a3b8; margin: 0; }

.an-kpi-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 1rem;
}
@media (max-width: 1200px) { .an-kpi-grid { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 768px)  { .an-kpi-grid { grid-template-columns: repeat(2, 1fr); } }

.an-kpi {
    background: #0f172a;
    border: 1px solid #1e293b;
    border-radius: 1rem;
    padding: 1.1rem 1.25rem;
    display: flex;
    flex-direction: column;
    gap: .25rem;
}
.an-kpi__count { font-size: 1.85rem; font-weight: 900; line-height: 1; }
.an-kpi__label { font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .07em; color: #64748b; }

.an-panel {
    background: #0f172a;
    border: 1px solid #1e293b;
    border-radius: 1rem;
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 1rem;
}
.an-panel__title { font-size: 1rem; font-weight: 800; color: #f1f5f9; }

.an-spot-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1.25rem;
}
@media (max-width: 900px) { .an-spot-grid { grid-template-columns: 1fr; } }
</style>
@endpush

@section('content')
<div class="an-workspace">

    {{-- Breadcrumb Navigation --}}
    <div style="display:flex;justify-content:space-between;align-items:center;">
        <a href="{{ route('admin.academic-library.quality') }}" style="color:#818cf8;font-size:.82rem;font-weight:700;text-decoration:none;">
            ← Back to Quality Dashboard
        </a>
        <span style="padding:.25rem .75rem;background:#1e293b;color:#94a3b8;border-radius:99px;font-size:.7rem;font-weight:700;">READ ONLY ANALYTICS</span>
    </div>

    {{-- Hero Header --}}
    <div class="an-hero">
        <div>
            <h1 class="an-hero__title">📊 IRQA Quality Analytics</h1>
            <p class="an-hero__sub">Comprehensive health distribution, completion percentages, coverage ratios, and quality trends.</p>
        </div>
        <div>
            <a href="{{ route('admin.academic-library.explorer') }}" style="padding:.6rem 1.2rem;background:#6366f1;color:#fff;border-radius:.6rem;font-size:.85rem;font-weight:700;text-decoration:none;">
                🔍 Open Explorer
            </a>
        </div>
    </div>

    {{-- KPI Gauges Grid (TASK 1.5) --}}
    <div class="an-kpi-grid">
        <div class="an-kpi">
            <div class="an-kpi__count" style="color:#38bdf8;">{{ $analyticsData['avg_health_score'] }}%</div>
            <div class="an-kpi__label">Average Health Score</div>
        </div>
        <div class="an-kpi">
            <div class="an-kpi__count" style="color:#34d399;">{{ $analyticsData['metadata_completion'] }}%</div>
            <div class="an-kpi__label">Metadata Completion</div>
        </div>
        <div class="an-kpi">
            <div class="an-kpi__count" style="color:#818cf8;">{{ $analyticsData['question_completeness'] }}%</div>
            <div class="an-kpi__label">Question Completeness</div>
        </div>
        <div class="an-kpi">
            <div class="an-kpi__count" style="color:#fbbf24;">{{ $analyticsData['explanation_coverage'] }}%</div>
            <div class="an-kpi__label">Explanation Coverage</div>
        </div>
        <div class="an-kpi">
            <div class="an-kpi__count" style="color:#a78bfa;">{{ $analyticsData['difficulty_balance'] }}%</div>
            <div class="an-kpi__label">Difficulty Balance</div>
        </div>
        <div class="an-kpi">
            <div class="an-kpi__count" style="color:#f43f5e;">{{ $analyticsData['overall_coverage'] }}%</div>
            <div class="an-kpi__label">Global Category Coverage</div>
        </div>
    </div>

    {{-- Health Distribution Breakdown (TASK 1.5) --}}
    <div class="an-panel">
        <div class="an-panel__title">📈 Repository Health Distribution</div>
        <div style="display:flex;gap:1.5rem;flex-wrap:wrap;">
            <div style="flex:1;background:#080f1d;border:1px solid #1e293b;border-radius:.75rem;padding:1.2rem;text-align:center;">
                <div style="font-size:2rem;font-weight:900;color:#34d399;">{{ $analyticsData['distribution']['excellent'] }}</div>
                <div style="font-size:.75rem;color:#94a3b8;margin-top:.2rem;font-weight:700;">EXCELLENT (90-100)</div>
            </div>
            <div style="flex:1;background:#080f1d;border:1px solid #1e293b;border-radius:.75rem;padding:1.2rem;text-align:center;">
                <div style="font-size:2rem;font-weight:900;color:#818cf8;">{{ $analyticsData['distribution']['good'] }}</div>
                <div style="font-size:.75rem;color:#94a3b8;margin-top:.2rem;font-weight:700;">GOOD (75-89)</div>
            </div>
            <div style="flex:1;background:#080f1d;border:1px solid #1e293b;border-radius:.75rem;padding:1.2rem;text-align:center;">
                <div style="font-size:2rem;font-weight:900;color:#fb7185;">{{ $analyticsData['distribution']['needs_improvement'] }}</div>
                <div style="font-size:.75rem;color:#94a3b8;margin-top:.2rem;font-weight:700;">NEEDS IMPROVEMENT (<75)</div>
            </div>
        </div>
    </div>

    {{-- Top & Lowest Repositories Spotlight (TASK 1.5) --}}
    <div class="an-spot-grid">
        {{-- Top Healthy Repo --}}
        @if($analyticsData['top_healthy_repo'])
        <div style="background:#0f172a;border:1px solid rgba(52,211,153,.3);border-radius:1rem;padding:1.5rem;">
            <div style="font-size:.7rem;font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:#34d399;">🏆 Top Healthy Repository</div>
            <h3 style="font-size:1.2rem;font-weight:800;color:#f1f5f9;margin:.35rem 0 .5rem;">{{ $analyticsData['top_healthy_repo']['title'] }}</h3>
            <div style="font-size:.8rem;color:#94a3b8;">
                Health Score: <strong style="color:#34d399;">{{ $analyticsData['top_healthy_repo']['health_score'] }} / 100</strong> • Questions: {{ $analyticsData['top_healthy_repo']['total_questions'] }}
            </div>
        </div>
        @endif

        {{-- Lowest Repo --}}
        @if($analyticsData['lowest_repo'])
        <div style="background:#0f172a;border:1px solid rgba(251,113,133,.3);border-radius:1rem;padding:1.5rem;">
            <div style="font-size:.7rem;font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:#fb7185;">⚠️ Highest Priority for Improvement</div>
            <h3 style="font-size:1.2rem;font-weight:800;color:#f1f5f9;margin:.35rem 0 .5rem;">{{ $analyticsData['lowest_repo']['title'] }}</h3>
            <div style="font-size:.8rem;color:#94a3b8;">
                Health Score: <strong style="color:#fb7185;">{{ $analyticsData['lowest_repo']['health_score'] }} / 100</strong> • Questions: {{ $analyticsData['lowest_repo']['total_questions'] }}
            </div>
            <div style="margin-top:1rem;">
                <a href="{{ route('admin.academic-library.explorer', ['filter' => 'needs_improvement', 'sort' => 'health_asc']) }}" style="padding:.4rem .85rem;background:#fb7185;color:#fff;border-radius:.4rem;font-size:.75rem;font-weight:700;text-decoration:none;">
                    Fix Lowest Repositories →
                </a>
            </div>
        </div>
        @endif
    </div>

</div>
@endsection
