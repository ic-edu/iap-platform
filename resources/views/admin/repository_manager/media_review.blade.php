@extends('layouts.admin')

@section('title', 'Review Media Revision Request — Repository Governance')

@push('styles')
<style>
.mr-container { display:flex; flex-direction:column; gap:1.75rem; }
.mr-card { background:#0f172a; border:1px solid #1e293b; border-radius:1.25rem; padding:1.75rem; }

.diff-grid { display:grid; grid-template-columns:1fr 1fr; gap:1.25rem; margin-top:1rem; }
@media (max-width: 900px) { .diff-grid { grid-template-columns:1fr; } }

.diff-box { background:#080f1d; border:1px solid #1e293b; border-radius:.85rem; padding:1.25rem; }
.diff-box.before { border-left:4px solid #ef4444; }
.diff-box.after { border-left:4px solid #10b981; }

.diff-title { font-size:.78rem; font-weight:800; text-transform:uppercase; letter-spacing:.05em; margin-bottom:1rem; }
</style>
@endpush

@section('content')
<div class="mr-container">

    {{-- Header --}}
    <div>
        <a href="{{ route('admin.repository-manager.media-approval') }}" style="color:#818cf8;font-size:.8rem;font-weight:700;text-decoration:none;">
            ← Back to Media Approval Center
        </a>
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;margin-top:.5rem;">
            <div>
                <h1 style="font-size:1.5rem;font-weight:800;color:#fff;margin:0;">
                    Review Revision Request #{{ substr($reviewRequest->id, 0, 12) }}
                </h1>
                <p style="font-size:.82rem;color:#64748b;margin:.2rem 0 0;">
                    Submitted by Teacher: <strong style="color:#e2e8f0;">{{ $reviewRequest->submitter?->name ?? 'Teacher' }}</strong> ({{ $reviewRequest->submitter?->email }}) • {{ $reviewRequest->created_at?->format('d M Y, H:i') }}
                </p>
            </div>

            <div style="display:flex;align-items:center;gap:.5rem;">
                <span style="font-size:.78rem;font-weight:700;color:#94a3b8;">Status:</span>
                <span style="padding:.25rem .7rem;background:rgba(251,191,36,.1);border:1px solid rgba(251,191,36,.3);color:#fbbf24;border-radius:99px;font-size:.75rem;font-weight:800;text-transform:uppercase;">
                    {{ $reviewRequest->status }}
                </span>
            </div>
        </div>
    </div>

    {{-- PART F: BEFORE vs AFTER DIFF COMPARISON --}}
    @php
        $changes = $reviewRequest->changes_data ?? [];
        $old = $changes['old_data'] ?? [];
    @endphp

    <div class="mr-card">
        <h3 style="font-size:1.05rem;font-weight:800;color:#fff;margin:0 0 .5rem;">🔍 BEFORE vs AFTER Revision Comparison</h3>
        <p style="font-size:.82rem;color:#94a3b8;margin:0;">
            Compare the current published metadata with the proposed teacher revision before approving.
        </p>

        <div class="diff-grid">
            
            {{-- BEFORE BOX --}}
            <div class="diff-box before">
                <div class="diff-title" style="color:#f87171;">🔴 CURRENT PUBLISHED ASSET (BEFORE)</div>

                <div style="display:flex;flex-direction:column;gap:.85rem;font-size:.85rem;">
                    <div>
                        <div style="font-size:.72rem;color:#64748b;font-weight:700;">Asset Title:</div>
                        <div style="color:#e2e8f0;font-weight:700;">{{ $old['title'] ?? ($media?->title ?? 'Untitled Asset') }}</div>
                    </div>
                    <div>
                        <div style="font-size:.72rem;color:#64748b;font-weight:700;">Exam Repository:</div>
                        <div style="color:#e2e8f0;text-transform:uppercase;">{{ $old['exam_type'] ?? ($media?->exam_type ?? 'GENERAL') }}</div>
                    </div>
                    <div>
                        <div style="font-size:.72rem;color:#64748b;font-weight:700;">Category:</div>
                        <div style="color:#e2e8f0;">{{ $old['category'] ?? ($media?->category ?? 'General') }}</div>
                    </div>
                    <div>
                        <div style="font-size:.72rem;color:#64748b;font-weight:700;">Description:</div>
                        <div style="color:#cbd5e1;line-height:1.5;">{{ $old['description'] ?? ($media?->description ?? 'No description.') }}</div>
                    </div>
                    @if(!empty($old['content_text']) || !empty($media?->content_text))
                    <div>
                        <div style="font-size:.72rem;color:#64748b;font-weight:700;">Transcript / Content Text:</div>
                        <div style="color:#cbd5e1;line-height:1.5;white-space:pre-wrap;background:#0f172a;padding:.75rem;border-radius:.5rem;margin-top:.25rem;">{{ $old['content_text'] ?? $media?->content_text }}</div>
                    </div>
                    @endif
                </div>
            </div>

            {{-- AFTER BOX --}}
            <div class="diff-box after">
                <div class="diff-title" style="color:#34d399;">🟢 PROPOSED TEACHER REVISION (AFTER)</div>

                <div style="display:flex;flex-direction:column;gap:.85rem;font-size:.85rem;">
                    <div>
                        <div style="font-size:.72rem;color:#64748b;font-weight:700;">Proposed Title:</div>
                        <div style="color:#34d399;font-weight:800;">{{ $changes['new_title'] ?? 'No change' }}</div>
                    </div>
                    <div>
                        <div style="font-size:.72rem;color:#64748b;font-weight:700;">Proposed Exam Repository:</div>
                        <div style="color:#34d399;font-weight:800;text-transform:uppercase;">{{ $changes['new_exam_type'] ?? 'No change' }}</div>
                    </div>
                    <div>
                        <div style="font-size:.72rem;color:#64748b;font-weight:700;">Proposed Category:</div>
                        <div style="color:#34d399;font-weight:800;">{{ $changes['new_category'] ?? 'No change' }}</div>
                    </div>
                    <div>
                        <div style="font-size:.72rem;color:#64748b;font-weight:700;">Proposed Description:</div>
                        <div style="color:#34d399;line-height:1.5;">{{ $changes['new_description'] ?? 'No change' }}</div>
                    </div>
                    @if(!empty($changes['new_content_text']))
                    <div>
                        <div style="font-size:.72rem;color:#64748b;font-weight:700;">Proposed Transcript / Content Text:</div>
                        <div style="color:#34d399;line-height:1.5;white-space:pre-wrap;background:#0f172a;padding:.75rem;border-radius:.5rem;margin-top:.25rem;">{{ $changes['new_content_text'] }}</div>
                    </div>
                    @endif
                </div>
            </div>

        </div>
    </div>

    {{-- PART F: REPOSITORY MANAGER APPROVAL ACTIONS --}}
    <div class="mr-card">
        <h3 style="font-size:1.05rem;font-weight:800;color:#fff;margin:0 0 1rem;">⚖️ Repository Manager Governance Action</h3>

        <form action="{{ route('admin.repository-manager.media-approve', $reviewRequest->id) }}" method="POST" style="display:flex;flex-direction:column;gap:1.25rem;">
            @csrf
            <div>
                <label style="font-size:.8rem;font-weight:700;color:#cbd5e1;display:block;margin-bottom:.4rem;">
                    Quality Assurance & Governance Notes (Sent to Teacher & Recorded in Audit Log):
                </label>
                <textarea name="notes" rows="3" style="width:100%;background:#1e293b;border:1px solid #334155;border-radius:.6rem;padding:.75rem;color:#fff;font-size:.85rem;" placeholder="e.g. Approved. Metadata verified against institutional TOEFL evaluation guidelines."></textarea>
            </div>

            <div style="display:flex;gap:.75rem;flex-wrap:wrap;">
                {{-- Approve Action --}}
                <button type="submit" style="padding:.75rem 1.5rem;background:#10b981;color:#fff;border:none;border-radius:.6rem;font-size:.85rem;font-weight:800;cursor:pointer;">
                    ✅ Approve Revision (Bump Version & Publish)
                </button>

                {{-- Request Revision Action --}}
                <button type="submit" formaction="{{ route('admin.repository-manager.media-revision', $reviewRequest->id) }}" style="padding:.75rem 1.5rem;background:#f59e0b;color:#fff;border:none;border-radius:.6rem;font-size:.85rem;font-weight:800;cursor:pointer;">
                    ⚠️ Request Revision from Author
                </button>

                {{-- Reject Action --}}
                <button type="submit" formaction="{{ route('admin.repository-manager.media-reject', $reviewRequest->id) }}" onclick="return confirm('Are you sure you want to reject this teacher revision request?')" style="padding:.75rem 1.5rem;background:#ef4444;color:#fff;border:none;border-radius:.6rem;font-size:.85rem;font-weight:800;cursor:pointer;">
                    ❌ Reject Revision
                </button>
            </div>
        </form>
    </div>

</div>
@endsection
