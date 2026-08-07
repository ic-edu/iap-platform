@extends('layouts.admin')

@section('title', 'Repository Explorer — IRQA Quality Audit')

@push('styles')
<style>
.exp-workspace { display: flex; flex-direction: column; gap: 1.75rem; }

.exp-hero {
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
.exp-hero__title { font-size: 1.5rem; font-weight: 800; color: #fff; margin: 0 0 .3rem; }
.exp-hero__sub   { font-size: .85rem; color: #94a3b8; margin: 0; }

.exp-controls {
    background: #0f172a;
    border: 1px solid #1e293b;
    border-radius: 1rem;
    padding: 1.25rem;
    display: flex;
    flex-direction: column;
    gap: 1rem;
}
.exp-filters { display: flex; gap: .5rem; flex-wrap: wrap; }
.exp-filter-btn {
    padding: .5rem 1rem;
    border-radius: 99px;
    font-size: .78rem;
    font-weight: 700;
    text-decoration: none;
    background: #1e293b;
    color: #94a3b8;
    border: 1px solid #334155;
    transition: all .15s;
}
.exp-filter-btn--active { background: #6366f1; color: #fff; border-color: #6366f1; }

.exp-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1.25rem;
}
@media (max-width: 900px) { .exp-grid { grid-template-columns: 1fr; } }

.exp-card {
    background: #0f172a;
    border: 1px solid #1e293b;
    border-radius: 1.1rem;
    padding: 1.35rem;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    gap: 1.1rem;
    transition: border-color .2s, transform .15s;
}
.exp-card:hover { border-color: #6366f1; transform: translateY(-2px); }
.exp-card__head { display: flex; justify-content: space-between; align-items: flex-start; gap: .75rem; }
.exp-card__title { font-size: 1.05rem; font-weight: 800; color: #f1f5f9; text-decoration: none; }
.exp-card__sub { font-size: .75rem; color: #64748b; margin-top: .2rem; }

.exp-badge { padding: .25rem .7rem; border-radius: 99px; font-size: .68rem; font-weight: 800; text-transform: uppercase; letter-spacing: .05em; display: inline-block; white-space: nowrap; }
.exp-badge--excellent { background: rgba(52,211,153,.15); color: #34d399; border: 1px solid rgba(52,211,153,.3); }
.exp-badge--good      { background: rgba(129,140,248,.15); color: #818cf8; border: 1px solid rgba(129,140,248,.3); }
.exp-badge--warning   { background: rgba(251,113,133,.15); color: #fb7185; border: 1px solid rgba(251,113,133,.3); }

.exp-problems { display: flex; flex-direction: column; gap: .35rem; margin-top: .5rem; }
.exp-problem-tag { font-size: .72rem; color: #fb7185; background: rgba(251,113,133,.1); border: 1px solid rgba(251,113,133,.2); padding: .25rem .6rem; border-radius: .4rem; width: fit-content; }
</style>
@endpush

@section('content')
<div class="exp-workspace">

    {{-- Navigation Breadcrumb (Part 14: Explicit route destination) --}}
    <div style="display:flex;justify-content:space-between;align-items:center;">
        <a href="{{ route('admin.repository-manager.dashboard') }}" style="color:#818cf8;font-size:.82rem;font-weight:700;text-decoration:none;">
            ← Back
        </a>
    </div>

    {{-- Hero Header --}}
    <div class="exp-hero">
        <div>
            <h1 class="exp-hero__title">🔍 IRQA Repository Explorer</h1>
            <p class="exp-hero__sub">Browse, search, and audit institutional repositories with quality problem priority highlighting.</p>
        </div>
        <div>
            <a href="{{ route('admin.academic-library.quality') }}" style="padding:.6rem 1.2rem;background:#1e293b;border:1px solid #334155;border-radius:.6rem;color:#e2e8f0;font-size:.85rem;font-weight:700;text-decoration:none;">
                🛡 IRQA Overview
            </a>
        </div>
    </div>

    {{-- Search & Filter Controls (TASK 1.1 - 1.4 & PART 6/7) --}}
    <div class="exp-controls">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;">
            <div class="exp-filters">
                <a href="{{ route('admin.academic-library.explorer', ['filter' => 'all', 'search' => request('search')]) }}" class="exp-filter-btn {{ $explorerData['filter'] === 'all' ? 'exp-filter-btn--active' : '' }}">
                    All Repositories
                </a>
                <a href="{{ route('admin.academic-library.explorer', ['filter' => 'healthy', 'search' => request('search')]) }}" class="exp-filter-btn {{ $explorerData['filter'] === 'healthy' ? 'exp-filter-btn--active' : '' }}">
                    🟢 Healthy
                </a>
                <a href="{{ route('admin.academic-library.explorer', ['filter' => 'needs_improvement', 'search' => request('search'), 'sort' => 'health_asc']) }}" class="exp-filter-btn {{ $explorerData['filter'] === 'needs_improvement' ? 'exp-filter-btn--active' : '' }}">
                    ⚠️ Needs Improvement
                </a>
                <a href="{{ route('admin.academic-library.explorer', ['filter' => 'awaiting_approval', 'search' => request('search')]) }}" class="exp-filter-btn {{ $explorerData['filter'] === 'awaiting_approval' ? 'exp-filter-btn--active' : '' }}">
                    ⏳ Awaiting Approval
                </a>
                <a href="{{ route('admin.academic-library.explorer', ['filter' => 'reviewed_issues', 'search' => request('search')]) }}" class="exp-filter-btn {{ $explorerData['filter'] === 'reviewed_issues' ? 'exp-filter-btn--active' : '' }}">
                    📋 Reviewed Issues
                </a>
                <a href="{{ route('admin.academic-library.explorer', ['filter' => 'archived', 'search' => request('search')]) }}" class="exp-filter-btn {{ $explorerData['filter'] === 'archived' ? 'exp-filter-btn--active' : '' }}">
                    📁 Archived
                </a>
            </div>

            <form method="GET" action="{{ route('admin.academic-library.explorer') }}" style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;">
                <input type="hidden" name="filter" value="{{ $explorerData['filter'] }}">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search title or program..." style="background:#1e293b;border:1px solid #334155;color:#fff;padding:.45rem .85rem;border-radius:.5rem;font-size:.8rem;">
                
                @if($explorerData['filter'] === 'reviewed_issues')
                <select name="decision" onchange="this.form.submit()" style="background:#1e293b;border:1px solid #334155;color:#fff;padding:.45rem .85rem;border-radius:.5rem;font-size:.8rem;">
                    <option value="all" {{ ($explorerData['decision'] ?? 'all') === 'all' ? 'selected' : '' }}>All Decisions</option>
                    <option value="published" {{ ($explorerData['decision'] ?? '') === 'published' ? 'selected' : '' }}>Published</option>
                    <option value="needs_revision" {{ ($explorerData['decision'] ?? '') === 'needs_revision' ? 'selected' : '' }}>Needs Revision</option>
                    <option value="rejected" {{ ($explorerData['decision'] ?? '') === 'rejected' ? 'selected' : '' }}>Rejected / Archived</option>
                </select>
                @endif

                <select name="sort" onchange="this.form.submit()" style="background:#1e293b;border:1px solid #334155;color:#fff;padding:.45rem .85rem;border-radius:.5rem;font-size:.8rem;">
                    <option value="health_asc" {{ $explorerData['sort'] === 'health_asc' ? 'selected' : '' }}>Lowest Health Score (Priority)</option>
                    <option value="health_desc" {{ $explorerData['sort'] === 'health_desc' ? 'selected' : '' }}>Highest Health Score</option>
                    <option value="title_asc" {{ $explorerData['sort'] === 'title_asc' ? 'selected' : '' }}>Alphabetical A-Z</option>
                    <option value="questions_desc" {{ $explorerData['sort'] === 'questions_desc' ? 'selected' : '' }}>Most Questions</option>
                </select>
                <button type="submit" style="background:#6366f1;color:#fff;border:none;padding:.45rem .9rem;border-radius:.5rem;font-size:.8rem;font-weight:700;cursor:pointer;">Search</button>
            </form>
        </div>
    </div>

    {{-- Repositories List Grid (TASK 1.3 & PART 4/7) --}}
    @if(count($explorerData['audits']) === 0)
    <div style="background:#0f172a;border:1px solid #1e293b;border-radius:1rem;padding:3rem;text-align:center;">
        <div style="font-size:2.5rem;margin-bottom:.5rem;">🔍</div>
        <div style="font-size:1.1rem;font-weight:800;color:#f1f5f9;">No matching repositories found.</div>
        <div style="font-size:.82rem;color:#94a3b8;margin-top:.25rem;">Try resetting your search query or filter selection.</div>
    </div>
    @else
    <div class="exp-grid">
        @foreach($explorerData['audits'] as $audit)
        <div class="exp-card">
            <div>
                <div class="exp-card__head">
                    <div>
                        <a href="{{ route('admin.repository-manager.question-bank-validate', $audit['bank_id']) }}" class="exp-card__title">
                            {{ $audit['title'] }}
                        </a>
                        <div class="exp-card__sub">
                            {{ $audit['governance']['current_version'] }} • {{ is_object($audit['test_type']) ? $audit['test_type']->label() : strtoupper((string) ($audit['test_type_label'] ?? $audit['test_type'] ?? 'GENERAL')) }}
                        </div>
                    </div>
                    <div>
                        @if($audit['is_reviewed'] || $explorerData['filter'] === 'reviewed_issues')
                            @php
                                $d = $audit['review_details']['decision'] ?? 'PUBLISHED';
                                $bStyle = match($d) {
                                    'PUBLISHED', 'APPROVED' => 'background:rgba(52,211,153,.15);color:#34d399;border:1px solid rgba(52,211,153,.3);',
                                    'NEEDS REVISION' => 'background:rgba(251,191,36,.15);color:#fbbf24;border:1px solid rgba(251,191,36,.3);',
                                    default => 'background:rgba(244,63,94,.15);color:#f43f5e;border:1px solid rgba(244,63,94,.3);',
                                };
                            @endphp
                            <span class="exp-badge" style="{{ $bStyle }}">Decided: {{ $d }}</span>
                        @elseif($audit['needs_improvement'])
                        <span class="exp-badge exp-badge--warning">Score: {{ $audit['health_score'] }}</span>
                        @elseif($audit['health_score'] >= 90)
                        <span class="exp-badge exp-badge--excellent">Score: {{ $audit['health_score'] }}</span>
                        @else
                        <span class="exp-badge exp-badge--good">Score: {{ $audit['health_score'] }}</span>
                        @endif
                    </div>
                </div>

                <div style="font-size:.78rem;color:#cbd5e1;margin-top:.75rem;display:flex;gap:1.25rem;">
                    <span>📝 <strong>{{ $audit['total_questions'] }}</strong> questions</span>
                    <span>💡 <strong>{{ $audit['explanation_pct'] }}%</strong> explanations</span>
                </div>

                @if($audit['is_reviewed'] || $explorerData['filter'] === 'reviewed_issues')
                <div style="font-size:.72rem;color:#94a3b8;margin-top:.5rem;background:#080f1d;padding:.4rem .6rem;border-radius:.4rem;border:1px solid #1e293b;">
                    Reviewed by <strong>{{ $audit['review_details']['reviewer_name'] }}</strong> on {{ $audit['review_details']['reviewed_at'] }}
                </div>
                @endif

                {{-- Detected Quality Problems List (TASK 1.3 & PART 5: Preserved Issue History) --}}
                @if(count($audit['warnings']) > 0)
                <div class="exp-problems">
                    <div style="font-size:.68rem;font-weight:800;text-transform:uppercase;color:#fb7185;margin-top:.4rem;">Detected Issues (Preserved History):</div>
                    @foreach($audit['warnings'] as $w)
                    <span class="exp-problem-tag">⚠️ {{ $w }}</span>
                    @endforeach
                </div>
                @else
                <div style="font-size:.72rem;color:#34d399;margin-top:.6rem;font-weight:700;">✓ Pass All Quality Audits</div>
                @endif
            </div>

            <div style="display:flex;justify-content:space-between;align-items:center;border-top:1px solid #1e293b;padding-top:.85rem;margin-top:.5rem;">
                <div style="font-size:.7rem;color:#64748b;">
                    Author: <strong>{{ $audit['governance']['contributor'] }}</strong>
                </div>
                <a href="{{ route('admin.repository-manager.question-bank-validate', $audit['bank_id']) }}" style="padding:.45rem .95rem;background:#6366f1;color:#fff;border-radius:.5rem;font-size:.78rem;font-weight:700;text-decoration:none;">
                    Open Repository →
                </a>
            </div>
        </div>
        @endforeach
    </div>
    @endif

</div>
@endsection
