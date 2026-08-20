@extends('layouts.admin')

@section('title', 'Repository Manager Command Center — Mission Control Governance')

@push('styles')
<style>
/* Full Width Command Center Styling (Executive Enterprise Design) */
.rm-command-center {
    width: 100%;
    max-width: 100%;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 2rem;
}

.rm-hero {
    background: linear-gradient(135deg, #1e1b4b 0%, #0f172a 60%, #020617 100%);
    border: 1px solid #3730a3;
    border-radius: 1.25rem;
    padding: 2.25rem 2.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1.5rem;
    box-shadow: 0 20px 40px -15px rgba(67,56,202,0.25);
}

.rm-hero__title {
    font-size: 2rem;
    font-weight: 900;
    color: #ffffff;
    margin: 0 0 0.5rem 0;
    letter-spacing: -0.025em;
}
.rm-hero__sub {
    font-size: 0.92rem;
    color: #94a3b8;
    margin: 0;
    line-height: 1.5;
}

/* Actionable KPI Cards Grid (Priority 1) */
.rm-kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
    gap: 1.25rem;
    width: 100%;
}

.rm-kpi-card {
    background: #0f172a;
    border: 1px solid #1e293b;
    border-radius: 1.25rem;
    padding: 1.5rem;
    text-decoration: none;
    color: inherit;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    min-height: 135px;
}
.rm-kpi-card:hover {
    border-color: #6366f1;
    transform: translateY(-3px);
    box-shadow: 0 12px 30px -8px rgba(99,102,241,0.25);
}

.rm-kpi-lbl {
    font-size: 0.76rem;
    font-weight: 800;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
.rm-kpi-val {
    font-size: 2.15rem;
    font-weight: 900;
    line-height: 1;
    margin: 0.5rem 0 0.25rem 0;
}
.rm-kpi-sub {
    font-size: 0.75rem;
    color: #64748b;
    font-weight: 700;
}

/* Restructured Dashboard Sections (Balanced 2-Column Grids) */
.rm-section-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.75rem;
    width: 100%;
}
@media (max-width: 1200px) {
    .rm-section-grid { grid-template-columns: 1fr; }
}

.rm-panel {
    background: #0f172a;
    border: 1px solid #1e293b;
    border-radius: 1.25rem;
    padding: 1.75rem;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    box-shadow: 0 10px 25px -5px rgba(0,0,0,0.3);
}

.rm-panel__header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.25rem;
}
.rm-panel__title {
    font-size: 1.05rem;
    font-weight: 800;
    color: #ffffff;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.6rem;
}

.rm-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; text-align: left; }
.rm-table th { padding: 0.8rem 1rem; background: #1e293b; color: #94a3b8; font-weight: 700; border-bottom: 1px solid #334155; }
.rm-table td { padding: 0.9rem 1rem; border-bottom: 1px solid #1e293b; color: #e2e8f0; }
.rm-table tr:hover td { background: rgba(30,41,59,0.5); }
</style>
@endpush

@section('content')
<div class="rm-command-center">

    {{-- Executive Mission Control Hero (PART 1) --}}
    <div class="rm-hero">
        <div>
            <div style="display:flex;align-items:center;gap:.65rem;margin-bottom:.5rem;">
                <span style="background:#4338ca;color:#e0e7ff;font-size:.72rem;font-weight:900;padding:.25rem .75rem;border-radius:99px;text-transform:uppercase;letter-spacing:.05em;">
                    Enterprise Governance
                </span>
                <span style="color:#818cf8;font-size:.82rem;font-weight:700;">Mission Control Workspace</span>
            </div>
            <h1 class="rm-hero__title">Repository Manager Command Center</h1>
            <p class="rm-hero__sub">
                Actionable decision engine for quality assurance, assessment approval, passage verification, and duplicate prevention.
            </p>
        </div>

        {{-- PART 1: MISSION CONTROL PRIMARY HERO ACTION --}}
        <div style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:center;">
            {{-- IRQA Repository Explorer --}}
            <a href="{{ route('admin.academic-library.explorer', ['from' => 'dashboard']) }}" style="padding:.7rem 1.2rem;background:#1e293b;border:1px solid #334155;color:#e0e7ff;border-radius:.65rem;font-size:.85rem;font-weight:800;text-decoration:none;display:inline-flex;align-items:center;gap:.4rem;">
                🔍 IRQA Explorer
            </a>
        </div>
    </div>

    {{-- PRIORITY 1: ACTIONABLE MISSION CONTROL KPI CARDS (PART 2 & PART 5) --}}
    <div class="rm-kpi-grid">
        
        {{-- Card 1: Question Bank Governance --}}
        <a href="{{ route('admin.repository-manager.questions-approval') }}" class="rm-kpi-card" style="border-color:#6366f1;">
            <div style="display:flex;justify-content:space-between;align-items:center;">
                <span class="rm-kpi-lbl">Question Bank Governance</span>
                <span style="font-size:1.5rem;">📚</span>
            </div>
            <div class="rm-kpi-val" style="color:#6366f1;">{{ $pendingQuestionsCount }}</div>
            <div class="rm-kpi-sub">Governance Queue →</div>
        </a>

        {{-- Card 2: Assessment Approval --}}
        <a href="{{ route('admin.repository-manager.assessment-approval') }}" class="rm-kpi-card" style="border-color:#38bdf8;">
            <div style="display:flex;justify-content:space-between;align-items:center;">
                <span class="rm-kpi-lbl">Assessment Approval</span>
                <span style="font-size:1.5rem;">📋</span>
            </div>
            <div class="rm-kpi-val" style="color:#38bdf8;">{{ $pendingAssessmentsCount }}</div>
            <div class="rm-kpi-sub">Assessment Queue →</div>
        </a>

        {{-- Card 2: Pending Media Reviews --}}
        <a href="{{ route('admin.repository-manager.media-approval') }}" class="rm-kpi-card">
            <div style="display:flex;justify-content:space-between;align-items:center;">
                <span class="rm-kpi-lbl">Pending Media</span>
                <span style="font-size:1.5rem;">🖼</span>
            </div>
            <div class="rm-kpi-val" style="color:#a78bfa;">{{ $pendingMediaCount }}</div>
            <div class="rm-kpi-sub">Media Queue →</div>
        </a>

        {{-- Card 3: Duplicate Detection --}}
        <a href="{{ route('admin.repository-manager.duplicates') }}" class="rm-kpi-card">
            <div style="display:flex;justify-content:space-between;align-items:center;">
                <span class="rm-kpi-lbl">Duplicate Detection</span>
                <span style="font-size:1.5rem;">🔍</span>
            </div>
            <div class="rm-kpi-val" style="color:#f87171;">{{ $duplicatesCount }}</div>
            <div class="rm-kpi-sub">Duplicate Center →</div>
        </a>

        {{-- Card 4: Repository Explorer --}}
        <a href="{{ route('admin.academic-library.explorer', ['from' => 'dashboard']) }}" class="rm-kpi-card">
            <div style="display:flex;justify-content:space-between;align-items:center;">
                <span class="rm-kpi-lbl">Repository Explorer</span>
                <span style="font-size:1.5rem;">🏛</span>
            </div>
            <div class="rm-kpi-val" style="color:#38bdf8;">{{ $totalRepositoriesCount }}</div>
            <div class="rm-kpi-sub">Total Repositories →</div>
        </a>

        {{-- Card 5: Metadata Compliance --}}
        <a href="{{ route('admin.academic-library.quality', ['from' => 'dashboard']) }}" class="rm-kpi-card">
            <div style="display:flex;justify-content:space-between;align-items:center;">
                <span class="rm-kpi-lbl">Metadata Compliance</span>
                <span style="font-size:1.5rem;">✅</span>
            </div>
            <div class="rm-kpi-val" style="color:#818cf8;">{{ $metadataCompleteness }}%</div>
            <div class="rm-kpi-sub">Metadata Validator →</div>
        </a>

        {{-- Card 6: Health Score --}}
        <a href="{{ route('admin.academic-library.analytics') }}" class="rm-kpi-card">
            <div style="display:flex;justify-content:space-between;align-items:center;">
                <span class="rm-kpi-lbl">Health Score</span>
                <span style="font-size:1.5rem;">💚</span>
            </div>
            <div class="rm-kpi-val" style="color:#34d399;">{{ $repositoryHealthScore }}/100</div>
            <div class="rm-kpi-sub">Full Analytics →</div>
        </a>

    </div>

    {{-- PRIORITY 1: URGENT ALERTS & TEACHER REVISIONS (PART 4 & PART 9) --}}
    <div class="rm-section-grid">

        {{-- Panel 1: Urgent Academic Alerts --}}
        <div class="rm-panel">
            <div>
                <div class="rm-panel__header">
                    <h3 class="rm-panel__title">🚨 Urgent Academic Alerts</h3>
                    <a href="{{ route('admin.academic-library.quality') }}" style="font-size:.78rem;color:#f87171;font-weight:700;text-decoration:none;">Alert Center →</a>
                </div>

                @php
                    $activeAlerts = array_filter($urgentAlerts, fn($alert) => $alert['count'] > 0);
                @endphp

                @if(count($activeAlerts) > 0)
                    <div style="display:flex;flex-direction:column;gap:.85rem;">
                        @foreach($activeAlerts as $alert)
                        <a href="{{ $alert['link'] }}" style="display:flex;justify-content:space-between;align-items:center;padding:1rem 1.25rem;background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);border-radius:.85rem;color:#f87171;text-decoration:none;transition:transform .15s ease;">
                            <div>
                                <div style="font-size:.88rem;font-weight:800;">{{ $alert['title'] }}</div>
                                <div style="font-size:.75rem;color:#fca5a5;margin-top:2px;">Immediate reviewer action required</div>
                            </div>
                            <span style="font-size:1.15rem;font-weight:900;background:#ef4444;color:#fff;padding:.25rem .75rem;border-radius:99px;">
                                {{ $alert['count'] }}
                            </span>
                        </a>
                        @endforeach
                    </div>
                @else
                    {{-- PART 4: COMPACT OPTIMIZED EMPTY STATE --}}
                    <div style="text-align:center;padding:1.5rem;color:#34d399;font-size:.82rem;background:rgba(52,211,153,.08);border-radius:.75rem;border:1px solid rgba(52,211,153,.2);">
                        ✓ No urgent academic alerts.
                    </div>
                @endif
            </div>
        </div>

        {{-- Panel 2: Teacher Revision Queue --}}
        <div class="rm-panel">
            <div>
                <div class="rm-panel__header">
                    <h3 class="rm-panel__title">📥 Teacher Revision Queue</h3>
                    <a href="{{ route('admin.repository-manager.questions-approval') }}" style="font-size:.78rem;color:#818cf8;font-weight:700;text-decoration:none;">View All Queue →</a>
                </div>

                @if($teacherSubmissionsQueue->count() > 0)
                    <table class="rm-table">
                        <thead>
                            <tr>
                                <th>Teacher</th>
                                <th>Question Bank</th>
                                <th>Submitted At</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($teacherSubmissionsQueue as $item)
                            <tr>
                                <td>
                                    <div style="font-weight:700;color:#f1f5f9;">{{ $item->teacher?->name ?? 'Teacher' }}</div>
                                    <div style="font-size:.7rem;color:#64748b;">{{ $item->teacher?->email }}</div>
                                </td>
                                <td>
                                    <div style="font-weight:700;color:#818cf8;">{{ $item->questionBank?->title ?? 'Question Bank' }}</div>
                                    @if(!empty($item->notes))
                                    <div style="font-size:.72rem;color:#cbd5e1;max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="{{ $item->notes }}">
                                        "{{ $item->notes }}"
                                    </div>
                                    @endif
                                </td>
                                <td style="font-size:.75rem;color:#94a3b8;">{{ ($item->updated_at ?? $item->created_at)?->diffForHumans() }}</td>
                                <td>
                                    @if($item->status === 'RESUBMITTED')
                                    <span style="padding:.2rem .5rem;background:rgba(52,211,153,.15);border:1px solid rgba(52,211,153,.4);color:#34d399;border-radius:.4rem;font-size:.7rem;font-weight:700;">
                                        Resubmitted — Awaiting RM Review
                                    </span>
                                    @else
                                    <span style="padding:.2rem .5rem;background:rgba(251,191,36,.15);border:1px solid rgba(251,191,36,.4);color:#fbbf24;border-radius:.4rem;font-size:.7rem;font-weight:700;">
                                        New Revision Request
                                    </span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('admin.repository-manager.question-bank-validate', $item->question_bank_id) }}" style="padding:.35rem .75rem;background:#6366f1;color:#fff;border-radius:.45rem;font-size:.72rem;font-weight:800;text-decoration:none;display:inline-block;">
                                        Review Workspace →
                                    </a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    {{-- PART 4: COMPACT OPTIMIZED EMPTY STATE --}}
                    <div style="text-align:center;padding:1.5rem;color:#64748b;font-size:.82rem;background:#080f1d;border-radius:.75rem;border:1px solid #1e293b;">
                        ✓ No pending teacher revisions.
                    </div>
                @endif
            </div>
        </div>

    </div>

    {{-- PRIORITY 2: RECENTLY UPDATED REPOSITORIES & AUDIT LOG (PART 3 & PART 9) --}}
    <div class="rm-section-grid">

        {{-- Panel 3: Open Repository Governance Queue --}}
        <div class="rm-panel">
            <div>
                <div class="rm-panel__header">
                    <h3 class="rm-panel__title">⚡ Open Repository Governance Queue</h3>
                    <span style="font-size:.7rem;color:#64748b;font-weight:600;">(Recently Updated Repositories)</span>
                    <a href="{{ route('admin.repository-manager.questions-approval') }}" style="font-size:.78rem;color:#818cf8;font-weight:700;text-decoration:none;">Open Queue →</a>
                </div>

                @if(isset($openApprovalTasks) && $openApprovalTasks->count() > 0)
                    <table class="rm-table">
                        <thead>
                            <tr>
                                <th>Repository Title</th>
                                <th>Teacher Author</th>
                                <th>Submitted At</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($openApprovalTasks as $task)
                            <tr>
                                <td>
                                    <div style="font-weight:700;color:#fff;">{{ $task->questionBank?->title ?? 'Repository Asset' }}</div>
                                    <div style="font-size:.7rem;color:#64748b;">Task ID: {{ substr($task->id, 0, 13) }}</div>
                                </td>
                                <td>
                                    <div style="font-weight:700;color:#e2e8f0;">{{ $task->teacher?->name ?? 'Teacher' }}</div>
                                </td>
                                <td style="font-size:.75rem;color:#94a3b8;">{{ $task->submitted_at ? \Carbon\Carbon::parse($task->submitted_at)->diffForHumans() : $task->created_at?->diffForHumans() }}</td>
                                <td>
                                    <span style="padding:.2rem .55rem;background:rgba(251,191,36,.15);border:1px solid rgba(251,191,36,.4);color:#fbbf24;border-radius:.4rem;font-size:.7rem;font-weight:800;text-transform:uppercase;">
                                        {{ $task->status }}
                                    </span>
                                </td>
                                <td>
                                    <a href="{{ route('admin.repository-manager.question-bank-validate', $task->question_bank_id) }}" style="padding:.35rem .75rem;background:#6366f1;color:#fff;border-radius:.45rem;font-size:.72rem;font-weight:800;text-decoration:none;">
                                        Validation Workspace →
                                    </a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                @elseif($recentlyUpdatedRepositories->count() > 0)
                    <table class="rm-table">
                        <thead>
                            <tr>
                                <th>Question Bank Repository</th>
                                <th>Exam Type</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recentlyUpdatedRepositories as $qb)
                            <tr>
                                <td>
                                    <div style="font-weight:700;color:#fff;">{{ $qb->title }}</div>
                                    <div style="font-size:.7rem;color:#64748b;">ID: {{ $qb->id }}</div>
                                </td>
                                <td>
                                    <span style="padding:.2rem .55rem;background:#1e293b;border:1px solid #334155;color:#34d399;border-radius:.4rem;font-size:.7rem;font-weight:700;text-transform:uppercase;">
                                        {{ is_object($qb->test_type) ? $qb->test_type->value : $qb->test_type }}
                                    </span>
                                </td>
                                <td>
                                    <span style="padding:.2rem .55rem;background:rgba(56,189,248,.1);border:1px solid rgba(56,189,248,.3);color:#38bdf8;border-radius:.4rem;font-size:.7rem;font-weight:700;">
                                        {{ $qb->status ?? 'Active' }}
                                    </span>
                                </td>
                                <td>
                                    <a href="{{ route('admin.repository-manager.question-bank-validate', $qb->id) }}" style="padding:.35rem .75rem;background:#1e293b;border:1px solid #334155;color:#e2e8f0;border-radius:.45rem;font-size:.72rem;font-weight:700;text-decoration:none;">
                                        Validation Workspace →
                                    </a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <div style="text-align:center;padding:1.5rem;color:#64748b;font-size:.82rem;background:#080f1d;border-radius:.75rem;border:1px solid #1e293b;">
                        ✨ Governance queue is empty. No pending approval tasks.
                    </div>
                @endif
            </div>
        </div>

        {{-- Panel 4: Repository Activity Audit Log --}}
        <div class="rm-panel">
            <div>
                <div class="rm-panel__header">
                    <h3 class="rm-panel__title">📜 Repository Activity Audit Log</h3>
                    <span style="font-size:.75rem;color:#64748b;font-weight:700;">Real-time Audit Trail</span>
                </div>

                @if($recentActivityLogs->count() > 0)
                    <div style="display:flex;flex-direction:column;gap:.85rem;">
                        @foreach($recentActivityLogs as $log)
                        <div style="display:flex;gap:1rem;align-items:flex-start;padding:.85rem;background:#1e293b;border-radius:.75rem;border-left:4px solid #6366f1;">
                            <div style="font-size:1.25rem;">📝</div>
                            <div style="flex:1;">
                                <div style="font-size:.84rem;font-weight:700;color:#e2e8f0;">
                                    {{ $log->actor?->name ?? 'User' }} <span style="font-weight:400;color:#94a3b8;">executed action</span> <code style="color:#a78bfa;">{{ $log->action }}</code>
                                </div>
                                @if($log->approval_note)
                                <div style="font-size:.78rem;color:#cbd5e1;margin-top:.25rem;font-style:italic;">
                                    "{{ $log->approval_note }}"
                                </div>
                                @endif
                                <div style="font-size:.72rem;color:#64748b;margin-top:.35rem;">
                                    {{ $log->created_at?->format('d M Y, H:i:s') }}
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                @else
                    <div style="text-align:center;padding:1.5rem;color:#64748b;font-size:.82rem;background:#080f1d;border-radius:.75rem;border:1px solid #1e293b;">
                        No activity audit logs recorded yet.
                    </div>
                @endif
            </div>
        </div>

    </div>

    {{-- PRIORITY 3: COMPACT INSTITUTIONAL ANALYTICS CTA BANNER (PART 2 & PART 9) --}}
    <div style="background:linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%);border:1px solid #3730a3;border-radius:1.25rem;padding:1.5rem 2rem;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;">
        <div>
            <div style="font-size:.75rem;font-weight:800;color:#818cf8;text-transform:uppercase;letter-spacing:.05em;">Priority 3 • Institutional Quality Engine</div>
            <h3 style="font-size:1.2rem;font-weight:800;color:#fff;margin:.2rem 0 0;">Macro IRQA Analytics & Teacher Submission Performance</h3>
            <p style="font-size:.82rem;color:#94a3b8;margin:0;">Deep-dive into category coverage, difficulty balance distribution, and author performance analytics on the dedicated analytics page.</p>
        </div>
        <a href="{{ route('admin.academic-library.analytics') }}" style="padding:.7rem 1.35rem;background:#6366f1;color:#fff;border-radius:.65rem;font-size:.85rem;font-weight:800;text-decoration:none;box-shadow:0 4px 14px rgba(99,102,241,0.35);">
            Open Full Analytics →
        </a>
    </div>

</div>
@endsection
