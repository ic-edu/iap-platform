@extends('layouts.admin')

@section('title', 'Course Monitoring Dashboard — Academic Operations')

@section('content')
<div style="display:flex;flex-direction:column;gap:1.5rem;">

    {{-- Header --}}
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;">
        <div>
            <h1 style="font-size:1.5rem;font-weight:800;color:#fff;margin:0 0 .25rem;">📊 Course Monitoring Dashboard</h1>
            <p style="font-size:.85rem;color:#94a3b8;margin:0;">Academic Operations Center overview for active courses, student assignments, teacher assignments, and approval queues.</p>
        </div>
    </div>

    {{-- KPI Cards --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(220px, 1fr));gap:1rem;">
        <div style="background:#0f172a;border:1px solid #1e293b;border-radius:1rem;padding:1.15rem 1.25rem;">
            <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#64748b;">Active Courses</div>
            <div style="font-size:1.85rem;font-weight:900;color:#34d399;margin-top:.25rem;">{{ $activeCoursesCount }}</div>
            <div style="font-size:.7rem;color:#475569;margin-top:.25rem;">Approved institutional courses</div>
        </div>

        <div style="background:#0f172a;border:1px solid #1e293b;border-radius:1rem;padding:1.15rem 1.25rem;">
            <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#64748b;">Students Assigned</div>
            <div style="font-size:1.85rem;font-weight:900;color:#818cf8;margin-top:.25rem;">{{ $studentsAssignedCount }}</div>
            <div style="font-size:.7rem;color:#475569;margin-top:.25rem;">Active student enrolments</div>
        </div>

        <div style="background:#0f172a;border:1px solid #1e293b;border-radius:1rem;padding:1.15rem 1.25rem;">
            <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#64748b;">Teachers Assigned</div>
            <div style="font-size:1.85rem;font-weight:900;color:#38bdf8;margin-top:.25rem;">{{ $teachersAssignedCount }}</div>
            <div style="font-size:.7rem;color:#475569;margin-top:.25rem;">Active teacher roles</div>
        </div>

        <div style="background:#0f172a;border:1px solid #1e293b;border-radius:1rem;padding:1.15rem 1.25rem;">
            <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#64748b;">Courses Awaiting Approval</div>
            <div style="font-size:1.85rem;font-weight:900;color:#fbbf24;margin-top:.25rem;">{{ $waitingApprovalCount }}</div>
            <div style="font-size:.7rem;color:#475569;margin-top:.25rem;">Pending Super Admin review</div>
        </div>
    </div>

    {{-- Two Column Layout --}}
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;">

        {{-- Recently Created Courses --}}
        <div style="background:#0f172a;border:1px solid #1e293b;border-radius:1.25rem;padding:1.35rem 1.5rem;">
            <div style="font-size:.9rem;font-weight:800;color:#f1f5f9;margin-bottom:1rem;">🎓 Recently Created Master Courses</div>
            <div style="display:flex;flex-direction:column;gap:.75rem;">
                @forelse($recentlyCreatedCourses as $rc)
                <div style="display:flex;justify-content:space-between;align-items:center;padding-bottom:.65rem;border-bottom:1px solid #1e293b;">
                    <div>
                        <div style="font-size:.82rem;font-weight:700;color:#f1f5f9;">{{ $rc->title }}</div>
                        <div style="font-size:.7rem;color:#64748b;font-family:monospace;">{{ $rc->code }} · {{ $rc->program }}</div>
                    </div>
                    <span style="font-size:.65rem;font-weight:800;padding:.2rem .55rem;border-radius:99px;background:rgba(99,102,241,.12);color:#818cf8;border:1px solid rgba(99,102,241,.25);">
                        {{ strtoupper($rc->course_status ?? 'DRAFT') }}
                    </span>
                </div>
                @empty
                <div style="font-size:.78rem;color:#64748b;padding:1rem 0;text-align:center;">No recent courses.</div>
                @endforelse
            </div>
        </div>

        {{-- Recent Operational Notifications --}}
        <div style="background:#0f172a;border:1px solid #1e293b;border-radius:1.25rem;padding:1.35rem 1.5rem;">
            <div style="font-size:.9rem;font-weight:800;color:#f1f5f9;margin-bottom:1rem;">🔔 Recent System Notifications</div>
            <div style="display:flex;flex-direction:column;gap:.75rem;">
                @forelse($recentNotifications as $notif)
                <div style="display:flex;flex-direction:column;gap:2px;padding-bottom:.65rem;border-bottom:1px solid #1e293b;">
                    <div style="font-size:.8rem;font-weight:700;color:#e2e8f0;">{{ $notif->title }}</div>
                    <div style="font-size:.72rem;color:#94a3b8;">{{ Str::limit($notif->message, 80) }}</div>
                    <div style="font-size:.67rem;color:#64748b;">{{ optional($notif->created_at)->diffForHumans() }}</div>
                </div>
                @empty
                <div style="font-size:.78rem;color:#64748b;padding:1rem 0;text-align:center;">No recent notifications.</div>
                @endforelse
            </div>
        </div>

    </div>

</div>
@endsection
