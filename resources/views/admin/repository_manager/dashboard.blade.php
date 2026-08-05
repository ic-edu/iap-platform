@extends('layouts.admin')

@section('title', 'Repository Manager Workspace — Academic Quality & Governance')

@push('styles')
<style>
.rm-container { display:flex; flex-direction:column; gap:1.75rem; }

.rm-hero {
    background: linear-gradient(135deg, #1e1b4b 0%, #0f172a 100%);
    border: 1px solid #3730a3;
    border-radius: 1.25rem;
    padding: 1.75rem 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}

.rm-kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 1.25rem;
}

.rm-card {
    background: #0f172a;
    border: 1px solid #1e293b;
    border-radius: 1.25rem;
    padding: 1.5rem;
    text-decoration: none;
    color: inherit;
    transition: all .2s ease;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}
.rm-card:hover {
    border-color: #6366f1;
    transform: translateY(-2px);
    box-shadow: 0 10px 25px -5px rgba(99,102,241,.2);
}

.rm-kpi-val { font-size: 2rem; font-weight: 800; color: #fff; line-height: 1; margin: .5rem 0 .25rem; }
.rm-kpi-lbl { font-size: .8rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: .04em; }

.rm-main-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 1.5rem;
}
@media (max-width: 1024px) { .rm-main-grid { grid-template-columns: 1fr; } }

.rm-table { width: 100%; border-collapse: collapse; font-size: .84rem; text-align: left; }
.rm-table th { padding: .75rem 1rem; background: #1e293b; color: #94a3b8; font-weight: 700; border-bottom: 1px solid #334155; }
.rm-table td { padding: .85rem 1rem; border-bottom: 1px solid #1e293b; color: #e2e8f0; }
.rm-table tr:hover td { background: rgba(30,41,59,.5); }
</style>
@endpush

@section('content')
<div class="rm-container">

    {{-- Hero Section --}}
    <div class="rm-hero">
        <div>
            <div style="display:flex;align-items:center;gap:.6rem;margin-bottom:.4rem;">
                <span style="background:#4338ca;color:#e0e7ff;font-size:.7rem;font-weight:800;padding:.2rem .6rem;border-radius:99px;text-transform:uppercase;">
                    Academic Leadership Role
                </span>
                <span style="color:#818cf8;font-size:.78rem;font-weight:700;">Enterprise Repository Governance</span>
            </div>
            <h1 style="font-size:1.6rem;font-weight:800;color:#fff;margin:0 0 .25rem;">
                Repository Manager Command Center
            </h1>
            <p style="font-size:.84rem;color:#94a3b8;margin:0;">
                Quality assurance, metadata compliance, passage verification, and teacher contribution oversight.
            </p>
        </div>

        <div style="display:flex;gap:.75rem;">
            <a href="{{ route('admin.repository-manager.media-approval') }}" style="padding:.65rem 1.25rem;background:#6366f1;color:#fff;border-radius:.65rem;font-size:.82rem;font-weight:800;text-decoration:none;">
                Review Pending Media ({{ $pendingMediaCount }})
            </a>
            <a href="{{ route('admin.repository-manager.questions-approval') }}" style="padding:.65rem 1.25rem;background:#1e293b;border:1px solid #3730a3;color:#e0e7ff;border-radius:.65rem;font-size:.82rem;font-weight:800;text-decoration:none;">
                Review Question Banks
            </a>
        </div>
    </div>

    {{-- PART C: CLICKABLE SUMMARY CARDS --}}
    <div class="rm-kpi-grid">
        
        {{-- Card 1: Pending Question Reviews --}}
        <a href="{{ route('admin.repository-manager.questions-approval') }}" class="rm-card">
            <div style="display:flex;justify-content:space-between;align-items:center;">
                <span class="rm-kpi-lbl">Pending Question Reviews</span>
                <span style="font-size:1.4rem;">❓</span>
            </div>
            <div class="rm-kpi-val" style="color:#fbbf24;">{{ $pendingQuestionsCount }}</div>
            <div style="font-size:.72rem;color:#64748b;">Requires Academic Approval →</div>
        </a>

        {{-- Card 2: Pending Media Reviews --}}
        <a href="{{ route('admin.repository-manager.media-approval') }}" class="rm-card">
            <div style="display:flex;justify-content:space-between;align-items:center;">
                <span class="rm-kpi-lbl">Pending Media Reviews</span>
                <span style="font-size:1.4rem;">🖼</span>
            </div>
            <div class="rm-kpi-val" style="color:#a78bfa;">{{ $pendingMediaCount }}</div>
            <div style="font-size:.72rem;color:#64748b;">Open Approval Center →</div>
        </a>

        {{-- Card 3: Pending Repository Reviews --}}
        <a href="{{ route('admin.academic-library.index') }}?filter=awaiting_approval" class="rm-card">
            <div style="display:flex;justify-content:space-between;align-items:center;">
                <span class="rm-kpi-lbl">Pending Repositories</span>
                <span style="font-size:1.4rem;">🏛</span>
            </div>
            <div class="rm-kpi-val" style="color:#38bdf8;">{{ $pendingRepositoriesCount }}</div>
            <div style="font-size:.72rem;color:#64748b;">Explorer Queue →</div>
        </a>

        {{-- Card 4: Duplicate Detection --}}
        <a href="{{ route('admin.repository-manager.duplicates') }}" class="rm-card">
            <div style="display:flex;justify-content:space-between;align-items:center;">
                <span class="rm-kpi-lbl">Duplicate Detection</span>
                <span style="font-size:1.4rem;">🔍</span>
            </div>
            <div class="rm-kpi-val" style="color:#f87171;">{{ $duplicatesCount }}</div>
            <div style="font-size:.72rem;color:#64748b;">Open Duplicate Center →</div>
        </a>

        {{-- Card 5: Repository Coverage --}}
        <a href="{{ route('admin.academic-library.index') }}" class="rm-card">
            <div style="display:flex;justify-content:space-between;align-items:center;">
                <span class="rm-kpi-lbl">Repository Coverage</span>
                <span style="font-size:1.4rem;">📊</span>
            </div>
            <div class="rm-kpi-val" style="color:#34d399;">100%</div>
            <div style="font-size:.72rem;color:#64748b;">Academic Coverage →</div>
        </a>

        {{-- Card 6: Metadata Completeness --}}
        <a href="{{ route('admin.academic-library.quality') }}" class="rm-card">
            <div style="display:flex;justify-content:space-between;align-items:center;">
                <span class="rm-kpi-lbl">Metadata Completeness</span>
                <span style="font-size:1.4rem;">✅</span>
            </div>
            <div class="rm-kpi-val" style="color:#818cf8;">{{ $metadataCompleteness }}%</div>
            <div style="font-size:.72rem;color:#64748b;">Metadata Validator →</div>
        </a>

        {{-- Card 7: Health Score --}}
        <a href="{{ route('admin.academic-library.analytics') }}" class="rm-card">
            <div style="display:flex;justify-content:space-between;align-items:center;">
                <span class="rm-kpi-lbl">Health Score</span>
                <span style="font-size:1.4rem;">💚</span>
            </div>
            <div class="rm-kpi-val" style="color:#34d399;">{{ $repositoryHealthScore }}/100</div>
            <div style="font-size:.72rem;color:#64748b;">IRQA Analytics →</div>
        </a>

    </div>

    {{-- Main Content Grid --}}
    <div class="rm-main-grid">

        {{-- Left: Submissions Queue & Activity --}}
        <div style="display:flex;flex-direction:column;gap:1.5rem;">

            {{-- Teacher Submissions Queue --}}
            <div class="rm-card" style="padding:1.5rem;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
                    <h3 style="font-size:1rem;font-weight:800;color:#fff;margin:0;">📥 Teacher Revision Submissions Queue</h3>
                    <a href="{{ route('admin.repository-manager.media-approval') }}" style="font-size:.78rem;color:#818cf8;font-weight:700;text-decoration:none;">View All →</a>
                </div>

                @if($teacherSubmissionsQueue->count() > 0)
                    <table class="rm-table">
                        <thead>
                            <tr>
                                <th>Submitter Teacher</th>
                                <th>Resource Type</th>
                                <th>Submitted At</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($teacherSubmissionsQueue as $item)
                            <tr>
                                <td>
                                    <div style="font-weight:700;color:#f1f5f9;">{{ $item->submitter?->name ?? 'Teacher' }}</div>
                                    <div style="font-size:.7rem;color:#64748b;">{{ $item->submitter?->email }}</div>
                                </td>
                                <td><span style="text-transform:uppercase;font-weight:700;color:#818cf8;">{{ $item->resource_type }}</span></td>
                                <td style="font-size:.75rem;color:#94a3b8;">{{ $item->created_at?->diffForHumans() }}</td>
                                <td>
                                    <span style="padding:.2rem .5rem;background:rgba(251,191,36,.1);border:1px solid rgba(251,191,36,.3);color:#fbbf24;border-radius:.4rem;font-size:.7rem;font-weight:700;">
                                        Pending Review
                                    </span>
                                </td>
                                <td>
                                    <a href="{{ route('admin.repository-manager.media-review', $item->id) }}" style="padding:.3rem .7rem;background:#6366f1;color:#fff;border-radius:.4rem;font-size:.72rem;font-weight:700;text-decoration:none;">
                                        Review Diff
                                    </a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <div style="text-align:center;padding:2rem;color:#64748b;font-size:.85rem;">
                        ✨ All teacher submissions have been reviewed. Queue is clear!
                    </div>
                @endif
            </div>

            {{-- Repository Activity Timeline (PART I) --}}
            <div class="rm-card" style="padding:1.5rem;">
                <h3 style="font-size:1rem;font-weight:800;color:#fff;margin:0 0 1rem;">📜 Repository Activity Audit Log</h3>
                
                @if($recentActivityLogs->count() > 0)
                    <div style="display:flex;flex-direction:column;gap:.85rem;">
                        @foreach($recentActivityLogs as $log)
                        <div style="display:flex;gap:1rem;align-items:flex-start;padding:.75rem;background:#1e293b;border-radius:.75rem;border-left:3px solid #6366f1;">
                            <div style="font-size:1.2rem;">📝</div>
                            <div style="flex:1;">
                                <div style="font-size:.82rem;font-weight:700;color:#e2e8f0;">
                                    {{ $log->actor?->name ?? 'User' }} <span style="font-weight:400;color:#94a3b8;">executed action</span> <code style="color:#a78bfa;">{{ $log->action }}</code>
                                </div>
                                @if($log->approval_note)
                                <div style="font-size:.75rem;color:#cbd5e1;margin-top:.2rem;font-style:italic;">
                                    "{{ $log->approval_note }}"
                                </div>
                                @endif
                                <div style="font-size:.7rem;color:#64748b;margin-top:.3rem;">
                                    {{ $log->created_at?->format('d M Y, H:i:s') }}
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                @else
                    <div style="text-align:center;padding:1.5rem;color:#64748b;font-size:.82rem;">
                        No audit log entries recorded yet.
                    </div>
                @endif
            </div>

        </div>

        {{-- Right: Urgent Alerts & Performance --}}
        <div style="display:flex;flex-direction:column;gap:1.5rem;">

            {{-- Urgent Alerts Widget --}}
            <div class="rm-card">
                <h3 style="font-size:1rem;font-weight:800;color:#fff;margin:0 0 1rem;">🚨 Urgent Academic Alerts</h3>
                <div style="display:flex;flex-direction:column;gap:.75rem;">
                    @foreach($urgentAlerts as $alert)
                    <a href="{{ $alert['link'] }}" style="display:flex;justify-content:space-between;align-items:center;padding:.85rem 1rem;background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);border-radius:.75rem;color:#f87171;text-decoration:none;">
                        <div>
                            <div style="font-size:.82rem;font-weight:800;">{{ $alert['title'] }}</div>
                            <div style="font-size:.72rem;color:#fca5a5;">Requires immediate review</div>
                        </div>
                        <span style="font-size:1.1rem;font-weight:800;background:#ef4444;color:#fff;padding:.2rem .6rem;border-radius:99px;">
                            {{ $alert['count'] }}
                        </span>
                    </a>
                    @endforeach
                </div>
            </div>

            {{-- Teacher Performance Summary Widget --}}
            <div class="rm-card">
                <h3 style="font-size:1rem;font-weight:800;color:#fff;margin:0 0 1rem;">👨‍🏫 Teacher Submission Summary</h3>
                <div style="display:flex;flex-direction:column;gap:.75rem;">
                    @foreach($teacherPerformanceSummary as $tp)
                    <div style="padding:.75rem;background:#1e293b;border-radius:.65rem;">
                        <div style="font-size:.82rem;font-weight:700;color:#f1f5f9;margin-bottom:.25rem;">{{ $tp['name'] }}</div>
                        <div style="display:flex;justify-content:space-between;font-size:.72rem;color:#94a3b8;">
                            <span>Submissions: <strong style="color:#fff;">{{ $tp['submitted'] }}</strong></span>
                            <span>Approved: <strong style="color:#34d399;">{{ $tp['approved'] }}</strong></span>
                            <span>Revisions: <strong style="color:#fbbf24;">{{ $tp['revisions'] }}</strong></span>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

        </div>

    </div>

</div>
@endsection
