@extends('layouts.admin')

@section('title', $isRm ? 'Assessment Request Intake Queue — Repository Governance' : 'Assessment Requests — Operational Planning')

@section('content')
<div style="max-width:1400px;margin:0 auto;padding:1.5rem 0 3rem;">

    {{-- Header --}}
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;margin-bottom:2rem;">
        <div>
            <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.35rem;">
                <span style="font-size:1.5rem;">📋</span>
                <h1 style="font-size:1.5rem;font-weight:800;color:#fff;margin:0;">
                    {{ $isRm ? 'Assessment Request Intake Queue' : 'Assessment Operational Requests' }}
                </h1>
            </div>
            <p style="font-size:.85rem;color:#94a3b8;margin:0;">
                {{ $isRm ? 'Review operational requests from Regular Admins, generate Assessment Drafts, and assign them to Teachers for authoring.' : 'Submit operational assessment needs and program briefs to the Repository Manager for draft creation and teacher assignment.' }}
            </p>
        </div>

        <div>
            @if(!$isRm && Auth::user()?->hasRole('admin'))
            <button type="button" onclick="openCreateRequestModal()" style="padding:.65rem 1.25rem;background:#6366f1;color:#fff;font-weight:800;font-size:.82rem;border:none;border-radius:.65rem;cursor:pointer;display:inline-flex;align-items:center;gap:.4rem;box-shadow:0 4px 12px rgba(99,102,241,.3);">
                + Request Assessment
            </button>
            @endif
        </div>
    </div>

    {{-- Status Flash --}}
    @if(session('status'))
    <div style="padding:.85rem 1.25rem;background:rgba(52,211,153,.1);border:1px solid rgba(52,211,153,.3);border-radius:.75rem;color:#34d399;font-size:.82rem;font-weight:700;margin-bottom:1.5rem;display:flex;align-items:center;gap:.5rem;">
        <span>✅</span> {{ session('status') }}
    </div>
    @endif

    {{-- Requests Table --}}
    <div style="background:#0f172a;border:1px solid #1e293b;border-radius:1rem;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.25);">
        @if($requests->isEmpty())
        <div style="padding:4rem 2rem;text-align:center;">
            <div style="font-size:3rem;margin-bottom:.75rem;">📭</div>
            <div style="font-size:1.15rem;font-weight:800;color:#f1f5f9;margin-bottom:.35rem;">No Assessment Requests</div>
            <p style="font-size:.85rem;color:#64748b;max-width:480px;margin:0 auto;">
                {{ $isRm ? 'There are currently no operational assessment requests in the intake queue.' : 'No assessment requests submitted yet. Click "+ Request Assessment" to submit a program brief to Repository Managers.' }}
            </p>
        </div>
        @else
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;text-align:left;font-size:.82rem;">
                <thead>
                    <tr style="background:#1e293b;border-bottom:1px solid #334155;color:#94a3b8;font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;">
                        <th style="padding:.9rem 1.25rem;">Title &amp; Context</th>
                        <th style="padding:.9rem 1rem;">Type</th>
                        <th style="padding:.9rem 1rem;">Requested By</th>
                        <th style="padding:.9rem 1rem;">Deadline</th>
                        <th style="padding:.9rem 1rem;">Status</th>
                        <th style="padding:.9rem 1.25rem;text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody style="divide-y:1px solid #1e293b;">
                    @foreach($requests as $req)
                    @php
                        $statusBadge = match($req->status) {
                            'draft_created' => 'background:rgba(52,211,153,.12);border:1px solid rgba(52,211,153,.3);color:#34d399;',
                            'pending'       => 'background:rgba(245,158,11,.12);border:1px solid rgba(245,158,11,.3);color:#fbbf24;',
                            'archived'      => 'background:rgba(100,116,139,.12);border:1px solid rgba(100,116,139,.3);color:#94a3b8;',
                            default         => 'background:rgba(148,163,184,.1);border:1px solid #334155;color:#cbd5e1;',
                        };
                    @endphp
                    <tr style="border-bottom:1px solid #1e293b;">
                        <td style="padding:1rem 1.25rem;">
                            <div style="font-weight:800;color:#f8fafc;font-size:.9rem;margin-bottom:.2rem;">
                                {{ $req->title }}
                            </div>
                            @if($req->program_context)
                            <div style="font-size:.75rem;color:#818cf8;margin-bottom:.2rem;">
                                🎯 Context: {{ $req->program_context }}
                            </div>
                            @endif
                            @if($req->notes)
                            <div style="font-size:.75rem;color:#94a3b8;max-width:450px;">
                                {{ \Illuminate\Support\Str::limit($req->notes, 80) }}
                            </div>
                            @endif
                        </td>
                        <td style="padding:1rem;">
                            <span style="font-size:.72rem;font-weight:800;padding:.2rem .5rem;border-radius:.35rem;background:#1e293b;border:1px solid #334155;color:#e2e8f0;text-transform:uppercase;">
                                {{ $req->test_type }}
                            </span>
                        </td>
                        <td style="padding:1rem;color:#cbd5e1;">
                            <div style="font-weight:700;">{{ $req->requester?->name ?? 'Admin' }}</div>
                            <div style="font-size:.7rem;color:#64748b;">{{ $req->created_at?->diffForHumans() }}</div>
                        </td>
                        <td style="padding:1rem;color:#94a3b8;font-size:.78rem;">
                            {{ $req->requested_deadline ? $req->requested_deadline->format('M d, Y') : 'No deadline' }}
                        </td>
                        <td style="padding:1rem;">
                            <span style="display:inline-block;padding:.2rem .6rem;border-radius:.4rem;font-size:.72rem;font-weight:800;text-transform:uppercase;{{ $statusBadge }}">
                                {{ str_replace('_', ' ', $req->status) }}
                            </span>
                            @if($req->test && $req->test->assignedTeacher)
                            <div style="font-size:.7rem;color:#a5b4fc;margin-top:.25rem;">
                                👤 Assigned: {{ $req->test->assignedTeacher->name }}
                            </div>
                            @endif
                        </td>
                        <td style="padding:1rem 1.25rem;text-align:right;">
                            @if($isRm && $req->status === 'pending')
                            <button type="button" onclick='openAssignDraftModal({{ json_encode($req) }})' style="padding:.45rem .85rem;background:#10b981;color:#fff;border:none;border-radius:.45rem;font-size:.75rem;font-weight:800;cursor:pointer;display:inline-flex;align-items:center;gap:.3rem;box-shadow:0 2px 8px rgba(16,185,129,.3);">
                                📝 Create Draft &amp; Assign
                            </button>
                            @elseif($req->test)
                            <a href="{{ route('admin.tests.show', $req->test->id) }}" style="padding:.45rem .85rem;background:#334155;color:#e2e8f0;border-radius:.45rem;font-size:.75rem;font-weight:700;text-decoration:none;display:inline-flex;align-items:center;gap:.3rem;">
                                👁 View Assessment
                            </a>
                            @else
                            <span style="color:#64748b;font-size:.75rem;">Awaiting RM</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($requests->hasPages())
        <div style="padding:1rem 1.25rem;border-top:1px solid #1e293b;">
            {{ $requests->links() }}
        </div>
        @endif
        @endif
    </div>
</div>

{{-- Admin: Create Assessment Request Modal --}}
<div id="create-request-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:9999;align-items:center;justify-content:center;padding:1rem;" onclick="closeCreateRequestModal(event)">
    <div style="background:#0f172a;border:1px solid #334155;border-radius:1rem;max-width:550px;width:100%;padding:1.75rem;box-shadow:0 20px 50px rgba(0,0,0,.5);" onclick="event.stopPropagation()">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;">
            <div style="font-size:1.15rem;font-weight:800;color:#fff;display:flex;align-items:center;gap:.5rem;">
                <span>📋</span> Request New Assessment
            </div>
            <button type="button" onclick="closeCreateRequestModal()" style="background:none;border:none;color:#94a3b8;font-size:1.25rem;cursor:pointer;">×</button>
        </div>

        <form method="POST" action="{{ route('admin.assessment-requests.store') }}">
            @csrf
            <div style="margin-bottom:1rem;">
                <label style="display:block;font-size:.75rem;font-weight:700;color:#cbd5e1;margin-bottom:.3rem;">Assessment Title / Need <span style="color:#f43f5e;">*</span></label>
                <input type="text" name="title" required placeholder="e.g. TOEIC Listening &amp; Reading for SMK Perhotelan" style="width:100%;padding:.6rem;background:#1e293b;border:1px solid #334155;border-radius:.5rem;color:#fff;font-size:.82rem;">
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem;">
                <div>
                    <label style="display:block;font-size:.75rem;font-weight:700;color:#cbd5e1;margin-bottom:.3rem;">Assessment Type <span style="color:#f43f5e;">*</span></label>
                    <select name="test_type" required style="width:100%;padding:.6rem;background:#1e293b;border:1px solid #334155;border-radius:.5rem;color:#fff;font-size:.82rem;">
                        <option value="toeic">TOEIC</option>
                        <option value="toefl">TOEFL</option>
                        <option value="ielts">IELTS</option>
                        <option value="general">General</option>
                    </select>
                </div>
                <div>
                    <label style="display:block;font-size:.75rem;font-weight:700;color:#cbd5e1;margin-bottom:.3rem;">Target Deadline (Optional)</label>
                    <input type="date" name="requested_deadline" style="width:100%;padding:.6rem;background:#1e293b;border:1px solid #334155;border-radius:.5rem;color:#fff;font-size:.82rem;">
                </div>
            </div>

            <div style="margin-bottom:1rem;">
                <label style="display:block;font-size:.75rem;font-weight:700;color:#cbd5e1;margin-bottom:.3rem;">Program / Institutional Context</label>
                <input type="text" name="program_context" placeholder="e.g. SMK Pariwisata &amp; Perhotelan Semester 1 Placement" style="width:100%;padding:.6rem;background:#1e293b;border:1px solid #334155;border-radius:.5rem;color:#fff;font-size:.82rem;">
            </div>

            <div style="margin-bottom:1.5rem;">
                <label style="display:block;font-size:.75rem;font-weight:700;color:#cbd5e1;margin-bottom:.3rem;">Operational Notes &amp; Skill Requirements</label>
                <textarea name="notes" rows="3" placeholder="Describe skill emphasis, sections, target student level..." style="width:100%;padding:.6rem;background:#1e293b;border:1px solid #334155;border-radius:.5rem;color:#fff;font-size:.82rem;"></textarea>
            </div>

            <div style="display:flex;justify-content:flex-end;gap:.75rem;">
                <button type="button" onclick="closeCreateRequestModal()" style="padding:.6rem 1.1rem;background:#334155;color:#fff;border:none;border-radius:.5rem;font-size:.82rem;font-weight:700;cursor:pointer;">Cancel</button>
                <button type="submit" style="padding:.6rem 1.25rem;background:#6366f1;color:#fff;border:none;border-radius:.5rem;font-size:.82rem;font-weight:800;cursor:pointer;box-shadow:0 4px 12px rgba(99,102,241,.3);">Submit Request</button>
            </div>
        </form>
    </div>
</div>

{{-- RM: Create Draft & Assign Teacher Modal --}}
<div id="assign-draft-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:9999;align-items:center;justify-content:center;padding:1rem;" onclick="closeAssignDraftModal(event)">
    <div style="background:#0f172a;border:1px solid #334155;border-radius:1rem;max-width:550px;width:100%;padding:1.75rem;box-shadow:0 20px 50px rgba(0,0,0,.5);" onclick="event.stopPropagation()">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;">
            <div style="font-size:1.15rem;font-weight:800;color:#fff;display:flex;align-items:center;gap:.5rem;">
                <span>📝</span> Create Draft &amp; Assign to Teacher
            </div>
            <button type="button" onclick="closeAssignDraftModal()" style="background:none;border:none;color:#94a3b8;font-size:1.25rem;cursor:pointer;">×</button>
        </div>

        <form id="assign-draft-form" method="POST" action="">
            @csrf
            <div style="margin-bottom:1rem;">
                <label style="display:block;font-size:.75rem;font-weight:700;color:#cbd5e1;margin-bottom:.3rem;">Assessment Title <span style="color:#f43f5e;">*</span></label>
                <input type="text" id="modal-assign-title" name="title" required style="width:100%;padding:.6rem;background:#1e293b;border:1px solid #334155;border-radius:.5rem;color:#fff;font-size:.82rem;">
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem;">
                <div>
                    <label style="display:block;font-size:.75rem;font-weight:700;color:#cbd5e1;margin-bottom:.3rem;">Test Type <span style="color:#f43f5e;">*</span></label>
                    <select id="modal-assign-type" name="test_type" required style="width:100%;padding:.6rem;background:#1e293b;border:1px solid #334155;border-radius:.5rem;color:#fff;font-size:.82rem;">
                        <option value="toeic">TOEIC</option>
                        <option value="toefl">TOEFL</option>
                        <option value="ielts">IELTS</option>
                        <option value="general">General</option>
                    </select>
                </div>
                <div>
                    <label style="display:block;font-size:.75rem;font-weight:700;color:#cbd5e1;margin-bottom:.3rem;">Assign to Teacher <span style="color:#f43f5e;">*</span></label>
                    <select name="teacher_id" required style="width:100%;padding:.6rem;background:#1e293b;border:1px solid #334155;border-radius:.5rem;color:#fff;font-size:.82rem;">
                        <option value="">-- Select Teacher --</option>
                        @foreach($teachers as $t)
                        <option value="{{ $t->id }}">{{ $t->name }} ({{ $t->email }})</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1.5rem;">
                <div>
                    <label style="display:block;font-size:.75rem;font-weight:700;color:#cbd5e1;margin-bottom:.3rem;">Duration (Minutes)</label>
                    <input type="number" name="duration_minutes" value="120" min="1" required style="width:100%;padding:.6rem;background:#1e293b;border:1px solid #334155;border-radius:.5rem;color:#fff;font-size:.82rem;">
                </div>
                <div>
                    <label style="display:block;font-size:.75rem;font-weight:700;color:#cbd5e1;margin-bottom:.3rem;">Pass Threshold (Score)</label>
                    <input type="number" name="pass_score" value="700" min="0" required style="width:100%;padding:.6rem;background:#1e293b;border:1px solid #334155;border-radius:.5rem;color:#fff;font-size:.82rem;">
                </div>
            </div>

            <div style="display:flex;justify-content:flex-end;gap:.75rem;">
                <button type="button" onclick="closeAssignDraftModal()" style="padding:.6rem 1.1rem;background:#334155;color:#fff;border:none;border-radius:.5rem;font-size:.82rem;font-weight:700;cursor:pointer;">Cancel</button>
                <button type="submit" style="padding:.6rem 1.25rem;background:#10b981;color:#fff;border:none;border-radius:.5rem;font-size:.82rem;font-weight:800;cursor:pointer;box-shadow:0 4px 12px rgba(16,185,129,.3);">Create Draft &amp; Assign</button>
            </div>
        </form>
    </div>
</div>

<script>
function openCreateRequestModal() {
    const modal = document.getElementById('create-request-modal');
    if (modal) modal.style.display = 'flex';
}
function closeCreateRequestModal(e) {
    if (!e || e.target === document.getElementById('create-request-modal')) {
        const modal = document.getElementById('create-request-modal');
        if (modal) modal.style.display = 'none';
    }
}
function openAssignDraftModal(req) {
    const modal = document.getElementById('assign-draft-modal');
    const form = document.getElementById('assign-draft-form');
    if (modal && form) {
        form.action = `/admin/repository-manager/assessment-requests/${req.id}/create-draft`;
        document.getElementById('modal-assign-title').value = req.title || '';
        document.getElementById('modal-assign-type').value = req.test_type || 'toeic';
        modal.style.display = 'flex';
    }
}
function closeAssignDraftModal(e) {
    if (!e || e.target === document.getElementById('assign-draft-modal')) {
        const modal = document.getElementById('assign-draft-modal');
        if (modal) modal.style.display = 'none';
    }
}
</script>
@endsection
