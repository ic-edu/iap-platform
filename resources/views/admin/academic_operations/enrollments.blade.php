@extends('layouts.admin')

@section('title', 'Student Enrolments — Academic Operations')

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
            <h1 style="font-size:1.5rem;font-weight:800;color:#fff;margin:0 0 .25rem;">👥 Student Enrolments</h1>
            <p style="font-size:.85rem;color:#94a3b8;margin:0;">Operational student assignment, transfer, and class enrolment management.</p>
        </div>
        <button type="button" onclick="document.getElementById('enroll-student-modal').style.display='flex'" style="padding:.6rem 1.25rem;background:#6366f1;color:#fff;border:none;border-radius:.65rem;font-size:.85rem;font-weight:700;cursor:pointer;">
            ＋ Enroll Student into Course
        </button>
    </div>

    {{-- Enrolments Table --}}
    <div style="background:#0f172a;border:1px solid #1e293b;border-radius:1rem;overflow:hidden;">
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:.82rem;">
                <thead>
                    <tr style="background:#080f1d;color:#475569;text-transform:uppercase;font-size:.67rem;font-weight:800;letter-spacing:.06em;text-align:left;">
                        <th style="padding:.75rem 1rem;">Student Name</th>
                        <th style="padding:.75rem 1rem;">Email</th>
                        <th style="padding:.75rem 1rem;">Enrolled Master Course</th>
                        <th style="padding:.75rem 1rem;">Teacher</th>
                        <th style="padding:.75rem 1rem;">Status</th>
                        <th style="padding:.75rem 1rem;">Enrollment Date</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($enrollments as $enr)
                    <tr style="border-bottom:1px solid #1e293b;">
                        <td style="padding:.85rem 1rem;font-weight:700;color:#f1f5f9;">{{ $enr->student?->name ?? 'Student' }}</td>
                        <td style="padding:.85rem 1rem;color:#94a3b8;">{{ $enr->student?->email ?? '-' }}</td>
                        <td style="padding:.85rem 1rem;">
                            <div style="font-weight:700;color:#818cf8;">{{ $enr->course?->title ?? 'Course' }}</div>
                            <div style="font-size:.7rem;color:#64748b;font-family:monospace;">{{ $enr->course?->code }}</div>
                        </td>
                        <td style="padding:.85rem 1rem;color:#cbd5e1;">{{ $enr->course?->lead_teacher_name ?? 'Unassigned' }}</td>
                        <td style="padding:.85rem 1rem;">
                            <span style="padding:.25rem .65rem;border-radius:99px;font-size:.65rem;font-weight:800;text-transform:uppercase;background:rgba(52,211,153,.12);color:#34d399;border:1px solid rgba(52,211,153,.3);">
                                {{ strtoupper($enr->status ?? 'ACTIVE') }}
                            </span>
                        </td>
                        <td style="padding:.85rem 1rem;color:#64748b;">{{ optional($enr->created_at)->format('M d, Y') ?? 'Recently' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" style="padding:2.5rem;text-align:center;color:#64748b;">No student enrolments registered yet.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($enrollments->hasPages())
        <div style="padding:1rem;border-top:1px solid #1e293b;">
            {{ $enrollments->links() }}
        </div>
        @endif
    </div>

</div>

{{-- Enroll Modal --}}
<div id="enroll-student-modal" style="position:fixed;inset:0;background:rgba(2,6,23,.75);backdrop-filter:blur(6px);z-index:900;display:none;align-items:center;justify-content:center;padding:1rem;">
    <div style="background:#0f172a;border:1px solid #1e293b;border-radius:1.25rem;max-width:500px;width:100%;padding:2rem;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;">
            <div style="font-size:1.05rem;font-weight:800;color:#f1f5f9;">👥 Enroll Student into Master Course</div>
            <button type="button" onclick="document.getElementById('enroll-student-modal').style.display='none'" style="color:#475569;font-size:1.35rem;cursor:pointer;background:none;border:none;">×</button>
        </div>
        <form action="{{ route('admin.academic-operations.enrollments.store') }}" method="POST">
            @csrf
            <div style="margin-bottom:1rem;">
                <label style="display:block;font-size:.78rem;color:#94a3b8;margin-bottom:.3rem;">Select Student *</label>
                <select name="student_id" required style="width:100%;background:#1e293b;border:1px solid #334155;color:#fff;padding:.6rem .85rem;border-radius:.6rem;box-sizing:border-box;">
                    <option value="">— Select Student —</option>
                    @foreach($students as $st)
                    <option value="{{ $st->id }}">{{ $st->name }} ({{ $st->email }})</option>
                    @endforeach
                </select>
            </div>
            <div style="margin-bottom:1.25rem;">
                <label style="display:block;font-size:.78rem;color:#94a3b8;margin-bottom:.3rem;">Select Master Course *</label>
                <select name="course_id" required style="width:100%;background:#1e293b;border:1px solid #334155;color:#fff;padding:.6rem .85rem;border-radius:.6rem;box-sizing:border-box;">
                    <option value="">— Select Master Course —</option>
                    @foreach($activeCourses as $ac)
                    <option value="{{ $ac->id }}">{{ $ac->code }} — {{ $ac->title }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" style="width:100%;padding:.75rem;background:#6366f1;color:#fff;border:none;border-radius:.65rem;font-weight:700;cursor:pointer;">Complete Student Enrolment &amp; Activate Dashboard</button>
        </form>
    </div>
</div>
@endsection
