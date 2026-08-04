@extends('layouts.admin')

@section('title', 'Student Applications — Academic Operations')

@section('content')
<div style="display:flex;flex-direction:column;gap:1.5rem;">

    @if(session('status'))
    <div style="padding:.85rem 1.1rem;border-radius:.75rem;background:rgba(52,211,153,.08);border:1px solid rgba(52,211,153,.2);color:#34d399;font-size:.82rem;font-weight:600;">
        ✅ {{ session('status') }}
    </div>
    @endif

    {{-- Header --}}
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;">
        <div>
            <h1 style="font-size:1.5rem;font-weight:800;color:#fff;margin:0 0 .25rem;">📋 Student Applications</h1>
            <p style="font-size:.85rem;color:#94a3b8;margin:0;">Manage student registrations, placement test verification, and program assignments.</p>
        </div>
    </div>

    {{-- Search & Filters --}}
    <div style="background:#0f172a;border:1px solid #1e293b;border-radius:1rem;padding:1rem 1.25rem;">
        <form action="{{ route('admin.academic-operations.applications') }}" method="GET" style="display:flex;gap:.75rem;flex-wrap:wrap;">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by Registration ID or Name…" style="background:#1e293b;border:1px solid #334155;color:#e2e8f0;font-size:.82rem;padding:.5rem .85rem;border-radius:.5rem;min-width:260px;outline:none;">

            <select name="program" style="background:#1e293b;border:1px solid #334155;color:#e2e8f0;font-size:.82rem;padding:.5rem .85rem;border-radius:.5rem;outline:none;">
                <option value="">— All Programs —</option>
                <option value="TOEFL" {{ request('program') === 'TOEFL' ? 'selected' : '' }}>TOEFL</option>
                <option value="TOEIC" {{ request('program') === 'TOEIC' ? 'selected' : '' }}>TOEIC</option>
                <option value="IELTS" {{ request('program') === 'IELTS' ? 'selected' : '' }}>IELTS</option>
                <option value="General" {{ request('program') === 'General' ? 'selected' : '' }}>General</option>
            </select>

            <select name="status" style="background:#1e293b;border:1px solid #334155;color:#e2e8f0;font-size:.82rem;padding:.5rem .85rem;border-radius:.5rem;outline:none;">
                <option value="">— All Statuses —</option>
                <option value="waiting_review" {{ request('status') === 'waiting_review' ? 'selected' : '' }}>Waiting Review</option>
                <option value="placement_required" {{ request('status') === 'placement_required' ? 'selected' : '' }}>Placement Required</option>
                <option value="ready_for_assignment" {{ request('status') === 'ready_for_assignment' ? 'selected' : '' }}>Ready For Assignment</option>
                <option value="assigned" {{ request('status') === 'assigned' ? 'selected' : '' }}>Assigned</option>
                <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Completed</option>
                <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>Rejected</option>
            </select>

            <button type="submit" style="background:#6366f1;color:#fff;border:none;padding:.5rem 1.1rem;border-radius:.5rem;font-size:.82rem;font-weight:700;cursor:pointer;">Filter</button>
        </form>
    </div>

    {{-- Applications Table --}}
    <div style="background:#0f172a;border:1px solid #1e293b;border-radius:1rem;overflow:hidden;">
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:.82rem;">
                <thead>
                    <tr style="background:#080f1d;color:#475569;text-transform:uppercase;font-size:.67rem;font-weight:800;letter-spacing:.06em;text-align:left;">
                        <th style="padding:.75rem 1rem;">Reg ID</th>
                        <th style="padding:.75rem 1rem;">Student Name</th>
                        <th style="padding:.75rem 1rem;">Selected Program</th>
                        <th style="padding:.75rem 1rem;">English Level</th>
                        <th style="padding:.75rem 1rem;">Placement Status</th>
                        <th style="padding:.75rem 1rem;">Target Score</th>
                        <th style="padding:.75rem 1rem;">Application Status</th>
                        <th style="padding:.75rem 1rem;text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($applications as $app)
                    <tr style="border-bottom:1px solid #1e293b;">
                        <td style="padding:.85rem 1rem;font-family:monospace;font-weight:700;color:#818cf8;">{{ $app->registration_id }}</td>
                        <td style="padding:.85rem 1rem;font-weight:700;color:#f1f5f9;">{{ $app->student_name }}</td>
                        <td style="padding:.85rem 1rem;"><span style="padding:.2rem .6rem;border-radius:.4rem;background:rgba(99,102,241,.12);color:#818cf8;font-size:.7rem;font-weight:700;">{{ $app->selected_program }}</span></td>
                        <td style="padding:.85rem 1rem;color:#cbd5e1;">{{ $app->english_level }}</td>
                        <td style="padding:.85rem 1rem;color:#94a3b8;">{{ str_replace('_', ' ', ucfirst($app->placement_test_status)) }}</td>
                        <td style="padding:.85rem 1rem;font-weight:800;color:#34d399;">{{ $app->target_score }}</td>
                        <td style="padding:.85rem 1rem;">
                            <span style="padding:.25rem .65rem;border-radius:99px;font-size:.65rem;font-weight:800;text-transform:uppercase;border:1px solid;
                                @if($app->application_status === 'assigned') background:rgba(52,211,153,.12);color:#34d399;border-color:rgba(52,211,153,.3);
                                @elseif($app->application_status === 'ready_for_assignment') background:rgba(99,102,241,.12);color:#818cf8;border-color:rgba(99,102,241,.3);
                                @elseif($app->application_status === 'waiting_review') background:rgba(251,191,36,.12);color:#fbbf24;border-color:rgba(251,191,36,.3);
                                @else background:rgba(100,116,139,.12);color:#94a3b8;border-color:rgba(100,116,139,.3); @endif">
                                {{ str_replace('_', ' ', $app->application_status) }}
                            </span>
                        </td>
                        <td style="padding:.85rem 1rem;text-align:right;">
                            <form action="{{ route('admin.academic-operations.applications.status', $app->id) }}" method="POST" style="display:inline-flex;gap:.35rem;">
                                @csrf
                                @method('PATCH')
                                <select name="application_status" onchange="this.form.submit()" style="background:#1e293b;border:1px solid #334155;color:#e2e8f0;font-size:.7rem;padding:.25rem .5rem;border-radius:.4rem;outline:none;cursor:pointer;">
                                    <option value="waiting_review" {{ $app->application_status === 'waiting_review' ? 'selected' : '' }}>Waiting Review</option>
                                    <option value="placement_required" {{ $app->application_status === 'placement_required' ? 'selected' : '' }}>Placement Required</option>
                                    <option value="ready_for_assignment" {{ $app->application_status === 'ready_for_assignment' ? 'selected' : '' }}>Ready For Assignment</option>
                                    <option value="assigned" {{ $app->application_status === 'assigned' ? 'selected' : '' }}>Assigned</option>
                                    <option value="completed" {{ $app->application_status === 'completed' ? 'selected' : '' }}>Completed</option>
                                    <option value="rejected" {{ $app->application_status === 'rejected' ? 'selected' : '' }}>Rejected</option>
                                </select>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" style="padding:2.5rem;text-align:center;color:#64748b;">No student applications found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($applications->hasPages())
        <div style="padding:1rem;border-top:1px solid #1e293b;">
            {{ $applications->links() }}
        </div>
        @endif
    </div>

</div>
@endsection
