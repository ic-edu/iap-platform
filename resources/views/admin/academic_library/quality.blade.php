@extends('layouts.admin')

@section('title', 'IRQA Quality Dashboard — iC.edu Platform')

@push('styles')
<style>
.irqa-workspace { display: flex; flex-direction: column; gap: 1.75rem; }

.irqa-hero {
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
.irqa-hero__title { font-size: 1.5rem; font-weight: 800; color: #fff; margin: 0 0 .3rem; }
.irqa-hero__sub   { font-size: .85rem; color: #94a3b8; margin: 0; }

.irqa-kpi-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 1rem;
}
@media (max-width: 1100px) { .irqa-kpi-grid { grid-template-columns: repeat(2, 1fr); } }

.irqa-kpi {
    background: #0f172a;
    border: 1px solid #1e293b;
    border-radius: 1rem;
    padding: 1.1rem 1.25rem;
    display: flex;
    flex-direction: column;
    gap: .25rem;
}
.irqa-kpi__count { font-size: 1.85rem; font-weight: 900; line-height: 1; }
.irqa-kpi__label { font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .07em; color: #64748b; }

.irqa-panel {
    background: #0f172a;
    border: 1px solid #1e293b;
    border-radius: 1rem;
    overflow: hidden;
}
.irqa-panel__head {
    padding: 1.1rem 1.35rem;
    border-bottom: 1px solid #1e293b;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}
.irqa-panel__title { font-size: 1rem; font-weight: 800; color: #f1f5f9; }

.irqa-table { width: 100%; border-collapse: collapse; font-size: .82rem; }
.irqa-table th { padding: .75rem 1rem; background: #080f1d; font-size: .67rem; font-weight: 700; text-transform: uppercase; letter-spacing: .07em; color: #64748b; text-align: left; border-bottom: 1px solid #1e293b; }
.irqa-table td { padding: .9rem 1rem; border-bottom: 1px solid #1e293b; vertical-align: middle; }

.irqa-badge { padding: .2rem .65rem; border-radius: 99px; font-size: .65rem; font-weight: 800; text-transform: uppercase; letter-spacing: .05em; display: inline-block; }
.irqa-badge--excellent { background: rgba(52,211,153,.15); color: #34d399; border: 1px solid rgba(52,211,153,.3); }
.irqa-badge--good      { background: rgba(129,140,248,.15); color: #818cf8; border: 1px solid rgba(129,140,248,.3); }
.irqa-badge--warning   { background: rgba(251,113,133,.15); color: #fb7185; border: 1px solid rgba(251,113,133,.3); }
</style>
@endpush

@section('content')
<div class="irqa-workspace">

    {{-- Breadcrumb & Navigation --}}
    <div style="display:flex;justify-content:space-between;align-items:center;">
        <a href="{{ route('admin.academic-library.index') }}" style="color:#818cf8;font-size:.82rem;font-weight:700;text-decoration:none;">
            ← Back to Academic Library
        </a>
    </div>

    {{-- Hero Header --}}
    <div class="irqa-hero">
        <div>
            <h1 class="irqa-hero__title">🛡 Institutional Repository Quality Assurance (IRQA)</h1>
            <p class="irqa-hero__sub">Comprehensive metadata completeness, difficulty balance, explanation coverage, and governance audit dashboard.</p>
        </div>
        <div>
            <a href="{{ route('admin.academic-library.index') }}" style="padding:.6rem 1.2rem;background:#1e293b;border:1px solid #334155;border-radius:.6rem;color:#e2e8f0;font-size:.85rem;font-weight:700;text-decoration:none;">
                📚 Library Overview
            </a>
        </div>
    </div>

    {{-- Global Quality Metrics Grid (PART 11) --}}
    <div class="irqa-kpi-grid">
        <div class="irqa-kpi">
            <div class="irqa-kpi__count" style="color:#818cf8;">{{ $summary['total_repositories'] }}</div>
            <div class="irqa-kpi__label">Total Repositories</div>
        </div>
        <div class="irqa-kpi">
            <div class="irqa-kpi__count" style="color:#34d399;">{{ $summary['healthy_count'] }}</div>
            <div class="irqa-kpi__label">Healthy Repositories</div>
        </div>
        <div class="irqa-kpi">
            <div class="irqa-kpi__count" style="color:#fb7185;">{{ $summary['needs_improvement_count'] }}</div>
            <div class="irqa-kpi__label">Needing Improvement</div>
        </div>
        <div class="irqa-kpi">
            <div class="irqa-kpi__count" style="color:#fbbf24;">{{ $summary['pending_approval_count'] }}</div>
            <div class="irqa-kpi__label">Awaiting Approval</div>
        </div>
        <div class="irqa-kpi">
            <div class="irqa-kpi__count" style="color:#38bdf8;">{{ $summary['avg_health_score'] }} <span style="font-size:.85rem;color:#64748b;">/ 100</span></div>
            <div class="irqa-kpi__label">Average Health Score</div>
        </div>
    </div>

    {{-- Duplicate Detection Alerts Panel (PART 6) --}}
    @if(count($summary['duplicates']['duplicate_titles']) > 0 || count($summary['duplicates']['duplicate_prompts']) > 0)
    <div style="background:#1e1022;border:1px solid #f43f5e40;border-radius:1rem;padding:1.25rem 1.5rem;">
        <div style="font-size:.9rem;font-weight:800;color:#fb7185;margin-bottom:.5rem;">⚠️ Duplicate Detection Warnings (PART 6)</div>
        <div style="font-size:.8rem;color:#cbd5e1;display:flex;flex-direction:column;gap:.35rem;">
            @foreach($summary['duplicates']['duplicate_titles'] as $dupTitle)
            <div>• {{ $dupTitle }}</div>
            @endforeach
            @foreach($summary['duplicates']['duplicate_prompts'] as $dupPrompt)
            <div>• {{ $dupPrompt }}</div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Repository Quality Audit Table (PART 1 to 10) --}}
    <div class="irqa-panel">
        <div class="irqa-panel__head">
            <span class="irqa-panel__title">📋 Repository Health & Governance Audit</span>
        </div>
        <div style="overflow-x:auto;">
            <table class="irqa-table">
                <thead>
                    <tr>
                        <th>Repository Title</th>
                        <th>Health Score</th>
                        <th>Questions</th>
                        <th>Explanation %</th>
                        <th>Difficulty Distribution</th>
                        <th>Governance Owner</th>
                        <th>Quality Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($summary['audits'] as $audit)
                    <tr>
                        <td>
                            <a href="{{ route('admin.question-banks.show', $audit['bank_id']) }}" style="font-weight:800;color:#818cf8;text-decoration:none;">
                                {{ $audit['title'] }}
                            </a>
                            <div style="font-size:.7rem;color:#64748b;margin-top:2px;">{{ $audit['governance']['current_version'] }} • {{ strtoupper($audit['test_type']) }}</div>
                        </td>
                        <td>
                            <div style="font-size:1.15rem;font-weight:900;color:#f1f5f9;">
                                {{ $audit['health_score'] }} <span style="font-size:.7rem;color:#64748b;">/ 100</span>
                            </div>
                        </td>
                        <td style="font-weight:700;color:#cbd5e1;">{{ $audit['total_questions'] }} items</td>
                        <td>
                            <span style="font-weight:700;color:{{ $audit['explanation_pct'] >= 80 ? '#34d399' : '#fb7185' }};">
                                {{ $audit['explanation_pct'] }}%
                            </span>
                        </td>
                        <td>
                            <div style="display:flex;gap:.4rem;font-size:.72rem;">
                                <span style="color:#34d399;font-weight:700;">Easy: {{ $audit['difficulty_counts']['easy'] }}</span> • 
                                <span style="color:#fbbf24;font-weight:700;">Med: {{ $audit['difficulty_counts']['medium'] }}</span> • 
                                <span style="color:#fb7185;font-weight:700;">Hard: {{ $audit['difficulty_counts']['hard'] }}</span>
                            </div>
                        </td>
                        <td>
                            <div style="font-size:.75rem;color:#e2e8f0;font-weight:600;">{{ $audit['governance']['institution_owner'] }}</div>
                            <div style="font-size:.68rem;color:#64748b;">Author: {{ $audit['governance']['contributor'] }}</div>
                        </td>
                        <td>
                            @if($audit['needs_improvement'])
                            <span class="irqa-badge irqa-badge--warning">Needs Improvement</span>
                            <div style="font-size:.65rem;color:#fb7185;margin-top:3px;max-width:220px;line-height:1.2;">
                                {{ $audit['warnings'][0] ?? 'Low health rating' }}
                            </div>
                            @else
                            <span class="irqa-badge irqa-badge--excellent">Healthy ({{ $audit['health_grade'] }})</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
