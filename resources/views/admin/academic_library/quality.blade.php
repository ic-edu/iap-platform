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
    text-decoration: none;
    transition: border-color .2s, transform .15s, box-shadow .2s;
    cursor: pointer;
}
.irqa-kpi:hover {
    border-color: #6366f1;
    transform: translateY(-2px);
    box-shadow: 0 12px 32px rgba(99,102,241,.15);
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

.irqa-count--indigo  { color: #818cf8; }
.irqa-count--emerald { color: #34d399; }
.irqa-count--rose    { color: #fb7185; }
.irqa-count--amber   { color: #fbbf24; }
.irqa-count--sky     { color: #38bdf8; }

.irqa-dup-panel { background: #1e1022; border: 1px solid rgba(244,63,94,.25); border-radius: 1rem; padding: 1.25rem 1.5rem; }
.irqa-dup-title { font-size: .9rem; font-weight: 800; color: #fb7185; margin-bottom: .5rem; }
.irqa-dup-list  { font-size: .8rem; color: #cbd5e1; display: flex; flex-direction: column; gap: .35rem; }
</style>
@endpush

@section('content')
<div class="irqa-workspace">

    {{-- Breadcrumb & Navigation --}}
    <div style="display:flex;justify-content:space-between;align-items:center;">
        <a href="{{ route('admin.repository-manager.dashboard') }}" style="color:#818cf8;font-size:.82rem;font-weight:700;text-decoration:none;">
            ← Back
        </a>
    </div>

    {{-- Hero Header --}}
    <div class="irqa-hero">
        <div>
            <h1 class="irqa-hero__title">🛡 Institutional Repository Quality Assurance (IRQA)</h1>
            <p class="irqa-hero__sub">Comprehensive metadata completeness, difficulty balance, explanation coverage, and governance audit dashboard.</p>
        </div>
        <div>
            <a href="{{ route('admin.academic-library.explorer') }}" class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs rounded-xl shadow-lg transition-colors inline-flex items-center gap-2" style="text-decoration:none;">
                🔍 Open Repository Explorer
            </a>
        </div>
    </div>

    {{-- Global Quality Metrics Grid (PART 11 & TASK 1) --}}
    <div class="irqa-kpi-grid">
        {{-- Card 1: Total Repositories --}}
        <a href="{{ route('admin.academic-library.explorer', ['filter' => 'all']) }}" class="irqa-kpi">
            <div class="irqa-kpi__count irqa-count--indigo">{{ $summary['total_repositories'] }}</div>
            <div class="irqa-kpi__label">Total Repositories →</div>
        </a>

        {{-- Card 2: Healthy Repositories --}}
        <a href="{{ route('admin.academic-library.explorer', ['filter' => 'healthy']) }}" class="irqa-kpi">
            <div class="irqa-kpi__count irqa-count--emerald">{{ $summary['healthy_count'] }}</div>
            <div class="irqa-kpi__label">Healthy Repositories →</div>
        </a>

        {{-- Card 3: Needs Improvement (TASK 1.3 & TASK 3: Lowest Health Score First) --}}
        <a href="{{ route('admin.academic-library.explorer', ['filter' => 'needs_improvement', 'sort' => 'health_asc']) }}" class="irqa-kpi">
            <div class="irqa-kpi__count irqa-count--rose">{{ $summary['needs_improvement_count'] }}</div>
            <div class="irqa-kpi__label">Needing Improvement →</div>
        </a>

        {{-- Card 4: Awaiting Approval (TASK 1.4: Floating Dialog if count == 0) --}}
        @if($summary['pending_approval_count'] > 0)
        <a href="{{ route('admin.academic-library.explorer', ['filter' => 'awaiting_approval']) }}" class="irqa-kpi">
            <div class="irqa-kpi__count irqa-count--amber">{{ $summary['pending_approval_count'] }}</div>
            <div class="irqa-kpi__label">Awaiting Approval →</div>
        </a>
        @else
        <button type="button" onclick="openNoApprovalModal()" class="irqa-kpi" style="text-align:left;background:#0f172a;border:1px solid #1e293b;">
            <div class="irqa-kpi__count irqa-count--amber">{{ $summary['pending_approval_count'] }}</div>
            <div class="irqa-kpi__label">Awaiting Approval ⓘ</div>
        </button>
        @endif

        {{-- Card 5: Average Health Score (TASK 1.5: Opens IRQA Analytics) --}}
        <a href="{{ route('admin.academic-library.analytics') }}" class="irqa-kpi">
            <div class="irqa-kpi__count irqa-count--sky">{{ $summary['avg_health_score'] }} <span style="font-size:.85rem;color:#64748b;">/ 100</span></div>
            <div class="irqa-kpi__label">Average Health Score 📊</div>
        </a>
    </div>

    {{-- Duplicate Detection Alerts Panel (PART 6) --}}
    @if(count($summary['duplicates']['duplicate_titles']) > 0 || count($summary['duplicates']['duplicate_prompts']) > 0)
    <div class="irqa-dup-panel">
        <div class="irqa-dup-title">⚠️ Duplicate Detection Warnings (PART 6)</div>
        <div class="irqa-dup-list">
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
                            <a href="{{ route('admin.repository-manager.question-bank-validate', $audit['bank_id']) }}" style="font-weight:800;color:#818cf8;text-decoration:none;">
                                {{ $audit['title'] }}
                            </a>
                            <div style="font-size:.7rem;color:#64748b;margin-top:2px;">{{ $audit['governance']['current_version'] }} • {{ is_object($audit['test_type']) ? $audit['test_type']->label() : strtoupper((string) ($audit['test_type_label'] ?? $audit['test_type'] ?? 'GENERAL')) }}</div>
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

    {{-- Floating Dialog Modal for 0 Awaiting Approval (TASK 1.4) --}}
    <div id="noApprovalModal" style="display:none;position:fixed;inset:0;background:rgba(2,6,23,.75);backdrop-filter:blur(6px);z-index:999;align-items:center;justify-content:center;padding:1rem;">
        <div style="background:#0f172a;border:1px solid #1e293b;border-radius:1.25rem;max-width:440px;width:100%;padding:2rem;text-align:center;box-shadow:0 24px 64px rgba(0,0,0,.6);">
            <div style="font-size:2.5rem;margin-bottom:.5rem;">🎉</div>
            <h3 style="font-size:1.15rem;font-weight:800;color:#f1f5f9;margin-bottom:.5rem;">No Repository Awaiting Approval</h3>
            <p style="font-size:.85rem;color:#94a3b8;line-height:1.45;margin-bottom:1.5rem;">Everything has already been reviewed.</p>
            <button type="button" onclick="closeNoApprovalModal()" style="padding:.6rem 1.5rem;background:#6366f1;color:#fff;border:none;border-radius:.6rem;font-weight:700;cursor:pointer;font-size:.85rem;">
                OK, Understood
            </button>
        </div>
    </div>

    <script>
    function openNoApprovalModal() {
        document.getElementById('noApprovalModal').style.display = 'flex';
    }
    function closeNoApprovalModal() {
        document.getElementById('noApprovalModal').style.display = 'none';
    }
    </script>

</div>
@endsection
