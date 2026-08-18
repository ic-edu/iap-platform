@extends('layouts.admin')

@section('title', 'Review Action Completed — Repository Governance')

@push('styles')
<style>
.rc-container { display:flex; flex-direction:column; gap:1.75rem; width:100%; max-width:850px; margin:0 auto; }
.rc-card { background:#0f172a; border:1px solid #1e293b; border-radius:1.25rem; padding:2.25rem; text-align:center; }
.rc-badge { padding:.35rem 1rem; border-radius:99px; font-size:.8rem; font-weight:800; text-transform:uppercase; letter-spacing:.05em; display:inline-block; margin-top:.5rem; }
.rc-badge--published { background:rgba(52,211,153,.15); color:#34d399; border:1px solid rgba(52,211,153,.4); }
.rc-badge--needs_revision { background:rgba(251,191,36,.15); color:#fbbf24; border:1px solid rgba(251,191,36,.4); }
.rc-badge--rejected { background:rgba(244,63,94,.15); color:#f43f5e; border:1px solid rgba(244,63,94,.4); }
.rc-badge--archived { background:rgba(148,163,184,.15); color:#94a3b8; border:1px solid rgba(148,163,184,.4); }
</style>
@endpush

@section('content')
@php
    $from = request('from');
    $filter = request('filter');

    if ($from === 'explorer' || $from === 'academic_explorer') {
        $rcPrimaryUrl = route('admin.academic-library.explorer', array_filter(['filter' => $filter]));
    } elseif ($from === 'reviewed_issues') {
        $rcPrimaryUrl = route('admin.academic-library.explorer', ['filter' => 'reviewed_issues']);
    } elseif ($from === 'all_repositories') {
        $rcPrimaryUrl = route('admin.academic-library.explorer', ['filter' => 'all_repositories']);
    } elseif ($from === 'assessments_approval') {
        $rcPrimaryUrl = route('admin.repository-manager.assessments-approval');
    } elseif (request('from_url') && str_starts_with(request('from_url'), '/') && !str_starts_with(request('from_url'), '//') && !str_contains(request('from_url'), '://')) {
        $rcPrimaryUrl = request('from_url');
    } else {
        $rcPrimaryUrl = route('admin.repository-manager.questions-approval');
    }
@endphp

<div class="rc-container">

    {{-- Explicit Back Button --}}
    <div>
        <a href="{{ $rcPrimaryUrl }}" style="color:#818cf8;font-size:.84rem;font-weight:700;text-decoration:none;display:inline-flex;align-items:center;gap:.3rem;">
            ← Back to Governance Queue
        </a>
    </div>

    {{-- Main Completion Card --}}
    <div class="rc-card">
        <div style="font-size:3.5rem;margin-bottom:1rem;color:#34d399;">✓</div>
        
        <h1 style="font-size:1.75rem;font-weight:900;color:#fff;margin:0 0 .5rem;">
            Done — Governance Review Completed
        </h1>
        
        <p style="font-size:.92rem;color:#94a3b8;margin:0 0 1.5rem;max-width:550px;margin-left:auto;margin-right:auto;">
            The governance action for this repository has been completed successfully.
        </p>

        @php
            $statusVal = is_object($questionBank->status) ? $questionBank->status->value : (string)($questionBank->status ?? 'published');
            $badgeClass = match($statusVal) {
                'published', 'approved' => 'rc-badge--published',
                'needs_revision'        => 'rc-badge--needs_revision',
                'rejected'              => 'rc-badge--rejected',
                'archived'              => 'rc-badge--archived',
                default                 => 'rc-badge--published',
            };
        @endphp

        {{-- Repository Summary Sub-card --}}
        <div style="background:#080f1d;border:1px solid #1e293b;border-radius:1rem;padding:1.5rem;margin-bottom:2rem;text-align:left;">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap;">
                <div>
                    <div style="font-size:1.15rem;font-weight:800;color:#f8fafc;">{{ $questionBank->title }}</div>
                    <div style="font-size:.78rem;color:#64748b;margin-top:.25rem;">
                        Version v{{ $questionBank->current_version ?? '1.0' }} • Author: {{ $questionBank->creator?->name ?? 'Teacher Author' }}
                    </div>
                </div>
                <div>
                    <span class="rc-badge {{ $badgeClass }}">
                        Status: {{ strtoupper(str_replace('_', ' ', $statusVal)) }}
                    </span>
                </div>
            </div>

            @if($latestLog)
            <div style="border-top:1px solid #1e293b;margin-top:1rem;padding-top:1rem;font-size:.82rem;color:#cbd5e1;">
                <div style="font-weight:700;color:#94a3b8;margin-bottom:.3rem;">Governance Decision Note:</div>
                <div style="background:#0f172a;padding:.75rem 1rem;border-radius:.5rem;border:1px solid #334155;color:#e2e8f0;font-style:italic;">
                    "{{ $latestLog->approval_note ?? 'Decision recorded by Repository Manager.' }}"
                </div>
                <div style="font-size:.72rem;color:#64748b;margin-top:.5rem;">
                    Reviewed by <strong>{{ $latestLog->reviewer?->name ?? 'Repository Manager' }}</strong> on {{ $latestLog->created_at?->format('F d, Y \a\t H:i') }}
                </div>
            </div>
            @endif
        </div>

        {{-- CTAs --}}
        <div style="display:flex;gap:1rem;justify-content:center;flex-wrap:wrap;">
            <a href="{{ $rcPrimaryUrl }}" style="padding:.75rem 1.75rem;background:#6366f1;color:#fff;border-radius:.65rem;font-size:.88rem;font-weight:800;text-decoration:none;box-shadow:0 4px 14px rgba(99,102,241,0.4);display:inline-flex;align-items:center;gap:.4rem;">
                ← Back to Governance Queue
            </a>
        </div>

    </div>

</div>
@endsection
