@extends('layouts.admin')

@section('title', 'Course Management & Approval — Academic Operations')

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
            <h1 style="font-size:1.5rem;font-weight:800;color:#fff;margin:0 0 .25rem;">🎓 Master Course Management</h1>
            <p style="font-size:.85rem;color:#94a3b8;margin:0;">Operational Admin creates Master Courses. Super Admin approval is required for activation.</p>
        </div>
        @if(!Auth::user()?->hasRole('teacher'))
        <button type="button" onclick="document.getElementById('create-course-modal').style.display='flex'" style="padding:.6rem 1.25rem;background:#6366f1;color:#fff;border:none;border-radius:.65rem;font-size:.85rem;font-weight:700;cursor:pointer;">
            ＋ Create Master Course
        </button>
        @endif
    </div>

    {{-- Courses Table --}}
    <div style="background:#0f172a;border:1px solid #1e293b;border-radius:1rem;overflow:hidden;">
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:.82rem;">
                <thead>
                    <tr style="background:#080f1d;color:#475569;text-transform:uppercase;font-size:.67rem;font-weight:800;letter-spacing:.06em;text-align:left;">
                        <th style="padding:.75rem 1rem;">Code</th>
                        <th style="padding:.75rem 1rem;">Course Title</th>
                        <th style="padding:.75rem 1rem;">Program</th>
                        <th style="padding:.75rem 1rem;">Capacity</th>
                        <th style="padding:.75rem 1rem;">Assigned Teacher</th>
                        <th style="padding:.75rem 1rem;">Approval Status</th>
                        <th style="padding:.75rem 1rem;text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($courses as $course)
                    @php
                        $st = $course->course_status ?? 'active';
                    @endphp
                    <tr style="border-bottom:1px solid #1e293b;">
                        <td style="padding:.85rem 1rem;font-family:monospace;font-weight:800;color:#818cf8;">{{ $course->code }}</td>
                        <td style="padding:.85rem 1rem;">
                            <div style="font-weight:700;color:#f1f5f9;">{{ $course->title }}</div>
                            <div style="font-size:.7rem;color:#64748b;">{{ $course->schedule }}</div>
                        </td>
                        <td style="padding:.85rem 1rem;"><span style="padding:.2rem .6rem;border-radius:.4rem;background:rgba(99,102,241,.12);color:#818cf8;font-size:.7rem;font-weight:700;">{{ $course->program ?? 'TOEFL' }}</span></td>
                        <td style="padding:.85rem 1rem;font-weight:700;color:#cbd5e1;">{{ $course->capacity ?? 30 }} seats</td>
                        <td style="padding:.85rem 1rem;color:#94a3b8;">
                            @php
                                $leadTeacher = $course->leadTeacher();
                            @endphp
                            @if($leadTeacher)
                                <span style="font-weight:600;color:#f1f5f9;">{{ $leadTeacher->name }}</span>
                                <span style="font-size:.65rem;color:#34d399;font-weight:700;display:block;">Lead Teacher</span>
                            @elseif($course->assignedTeachers->isNotEmpty())
                                <span style="font-weight:600;color:#cbd5e1;">{{ $course->assignedTeachers->first()->name }}</span>
                            @elseif(($course->course_status ?? '') === 'waiting_approval')
                                <span style="font-style:italic;color:#fbbf24;">Assignment Pending</span>
                            @else
                                <span style="font-style:italic;color:#64748b;">No teacher assigned</span>
                            @endif
                        </td>
                        <td style="padding:.85rem 1rem;">
                            <span style="padding:.25rem .65rem;border-radius:99px;font-size:.65rem;font-weight:800;text-transform:uppercase;border:1px solid;
                                @if($st === 'active' || $st === 'approved') background:rgba(52,211,153,.12);color:#34d399;border-color:rgba(52,211,153,.3);
                                @elseif($st === 'waiting_approval') background:rgba(251,191,36,.12);color:#fbbf24;border-color:rgba(251,191,36,.3);
                                @else background:rgba(100,116,139,.12);color:#94a3b8;border-color:rgba(100,116,139,.3); @endif">
                                {{ str_replace('_', ' ', $st) }}
                            </span>
                        </td>
                        <td style="padding:.85rem 1rem;text-align:right;">
                            @if(Auth::user()?->hasRole('super-admin') && $st === 'waiting_approval')
                            <div style="display:flex;gap:.35rem;justify-content:flex-end;">
                                <form action="{{ route('admin.academic-operations.courses.approve', $course->id) }}" method="POST">
                                    @csrf
                                    <button type="submit" style="background:#34d399;color:#0f172a;border:none;padding:.25rem .6rem;border-radius:.35rem;font-size:.7rem;font-weight:800;cursor:pointer;">
                                        ✓ Approve
                                    </button>
                                </form>
                                <form action="{{ route('admin.academic-operations.courses.reject', $course->id) }}" method="POST">
                                    @csrf
                                    <button type="submit" style="background:#fb7185;color:#fff;border:none;padding:.25rem .6rem;border-radius:.35rem;font-size:.7rem;font-weight:800;cursor:pointer;">
                                        ✕ Reject
                                    </button>
                                </form>
                            </div>
                            @else
                            <span style="font-size:.75rem;font-weight:600;color:#64748b;">
                                Master Course Active
                            </span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" style="padding:2.5rem;text-align:center;color:#64748b;">No master courses found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($courses->hasPages())
        <div style="padding:1rem;border-top:1px solid #1e293b;">
            {{ $courses->links() }}
        </div>
        @endif
    </div>

</div>

{{-- Create Modal --}}
@if(!Auth::user()?->hasRole('teacher'))
<div id="create-course-modal" style="position:fixed;inset:0;background:rgba(2,6,23,.75);backdrop-filter:blur(6px);z-index:900;display:none;align-items:center;justify-content:center;padding:1rem;">
    <div style="background:#0f172a;border:1px solid #1e293b;border-radius:1.25rem;max-width:500px;width:100%;padding:2rem;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;">
            <div style="font-size:1.05rem;font-weight:800;color:#f1f5f9;">🎓 Create Master Course</div>
            <button type="button" onclick="document.getElementById('create-course-modal').style.display='none'" style="color:#475569;font-size:1.35rem;cursor:pointer;background:none;border:none;">×</button>
        </div>
        <form action="{{ route('admin.academic-operations.courses.store') }}" method="POST">
            @csrf
            <div style="margin-bottom:1rem;">
                <label style="display:block;font-size:.78rem;color:#94a3b8;margin-bottom:.3rem;">Course Code *</label>
                <input type="text" name="code" required style="width:100%;background:#1e293b;border:1px solid #334155;color:#fff;padding:.6rem .85rem;border-radius:.6rem;box-sizing:border-box;" placeholder="e.g. TOEFL-MASTER-101">
            </div>
            <div style="margin-bottom:1rem;">
                <label style="display:block;font-size:.78rem;color:#94a3b8;margin-bottom:.3rem;">Course Title *</label>
                <input type="text" name="title" required style="width:100%;background:#1e293b;border:1px solid #334155;color:#fff;padding:.6rem .85rem;border-radius:.6rem;box-sizing:border-box;" placeholder="e.g. TOEFL iBT Intensive Master Program">
            </div>
            <div style="margin-bottom:1rem;">
                <label style="display:block;font-size:.78rem;color:#94a3b8;margin-bottom:.3rem;">Program *</label>
                <select name="program" style="width:100%;background:#1e293b;border:1px solid #334155;color:#fff;padding:.6rem .85rem;border-radius:.6rem;box-sizing:border-box;">
                    <option value="TOEFL">TOEFL</option>
                    <option value="TOEIC">TOEIC</option>
                    <option value="IELTS">IELTS</option>
                    <option value="General">General</option>
                </select>
            </div>
            <div style="margin-bottom:1rem;">
                <label style="display:block;font-size:.78rem;color:#94a3b8;margin-bottom:.3rem;">Capacity *</label>
                <input type="number" name="capacity" value="30" required style="width:100%;background:#1e293b;border:1px solid #334155;color:#fff;padding:.6rem .85rem;border-radius:.6rem;box-sizing:border-box;">
            </div>
            <div style="margin-bottom:1.25rem;">
                <label style="display:block;font-size:.78rem;color:#94a3b8;margin-bottom:.3rem;">Schedule</label>
                <input type="text" name="schedule" value="Mon, Wed 09:00 - 11:00 AM" style="width:100%;background:#1e293b;border:1px solid #334155;color:#fff;padding:.6rem .85rem;border-radius:.6rem;box-sizing:border-box;">
            </div>
            <button type="submit" style="width:100%;padding:.75rem;background:#6366f1;color:#fff;border:none;border-radius:.65rem;font-weight:700;cursor:pointer;">Submit for Super Admin Approval</button>
        </form>
    </div>
</div>
@endif
@endsection
