@extends('layouts.admin')

@section('title', 'Teacher Assignments — Academic Operations')

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
            <h1 style="font-size:1.5rem;font-weight:800;color:#fff;margin:0 0 .25rem;">👩‍🏫 Teacher Assignments</h1>
            <p style="font-size:.85rem;color:#94a3b8;margin:0;">Assign teachers into approved master courses. Assigned teachers automatically receive a clickable notification.</p>
        </div>
        <button type="button" onclick="document.getElementById('assign-teacher-modal').style.display='flex'" style="padding:.6rem 1.25rem;background:#6366f1;color:#fff;border:none;border-radius:.65rem;font-size:.85rem;font-weight:700;cursor:pointer;">
            ＋ Assign Teacher to Course
        </button>
    </div>

    {{-- Assignments Table --}}
    <div style="background:#0f172a;border:1px solid #1e293b;border-radius:1rem;overflow:hidden;">
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:.82rem;">
                <thead>
                    <tr style="background:#080f1d;color:#475569;text-transform:uppercase;font-size:.67rem;font-weight:800;letter-spacing:.06em;text-align:left;">
                        <th style="padding:.75rem 1rem;">Teacher Name</th>
                        <th style="padding:.75rem 1rem;">Email</th>
                        <th style="padding:.75rem 1rem;">Assigned Master Course</th>
                        <th style="padding:.75rem 1rem;">Role</th>
                        <th style="padding:.75rem 1rem;">Assignment Status</th>
                        <th style="padding:.75rem 1rem;">Assigned Date</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($assignments as $assign)
                    <tr style="border-bottom:1px solid #1e293b;">
                        <td style="padding:.85rem 1rem;font-weight:700;color:#f1f5f9;">{{ $assign->teacher?->name ?? 'Unknown Teacher' }}</td>
                        <td style="padding:.85rem 1rem;color:#94a3b8;">{{ $assign->teacher?->email ?? '-' }}</td>
                        <td style="padding:.85rem 1rem;">
                            <div style="font-weight:700;color:#818cf8;">{{ $assign->course?->title ?? 'Deleted Course' }}</div>
                            <div style="font-size:.7rem;color:#64748b;font-family:monospace;">{{ $assign->course?->code }}</div>
                        </td>
                        <td style="padding:.85rem 1rem;">
                            <span style="padding:.2rem .6rem;border-radius:.4rem;background:rgba(99,102,241,.12);color:#818cf8;font-size:.7rem;font-weight:700;">
                                {{ str_replace('_', ' ', strtoupper($assign->role)) }}
                            </span>
                        </td>
                        <td style="padding:.85rem 1rem;">
                            <span style="padding:.25rem .65rem;border-radius:99px;font-size:.65rem;font-weight:800;text-transform:uppercase;background:rgba(52,211,153,.12);color:#34d399;border:1px solid rgba(52,211,153,.3);">
                                {{ strtoupper($assign->status) }}
                            </span>
                        </td>
                        <td style="padding:.85rem 1rem;color:#64748b;">{{ $assign->created_at->format('M d, Y') }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" style="padding:2.5rem;text-align:center;color:#64748b;">No teacher assignments recorded yet.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($assignments->hasPages())
        <div style="padding:1rem;border-top:1px solid #1e293b;">
            {{ $assignments->links() }}
        </div>
        @endif
    </div>

</div>

{{-- Assign Modal --}}
<div id="assign-teacher-modal" style="position:fixed;inset:0;background:rgba(2,6,23,.75);backdrop-filter:blur(6px);z-index:900;display:none;align-items:center;justify-content:center;padding:1rem;">
    <div style="background:#0f172a;border:1px solid #1e293b;border-radius:1.25rem;max-width:500px;width:100%;padding:2rem;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;">
            <div style="font-size:1.05rem;font-weight:800;color:#f1f5f9;">👩‍🏫 Assign Teacher into Master Course</div>
            <button type="button" onclick="document.getElementById('assign-teacher-modal').style.display='none'" style="color:#475569;font-size:1.35rem;cursor:pointer;background:none;border:none;">×</button>
        </div>
        <form action="{{ route('admin.academic-operations.teacher-assignments.store') }}" method="POST">
            @csrf
            <div style="margin-bottom:1rem;">
                <label style="display:block;font-size:.78rem;color:#94a3b8;margin-bottom:.3rem;">Select Teacher *</label>
                <select name="teacher_id" required style="width:100%;background:#1e293b;border:1px solid #334155;color:#fff;padding:.6rem .85rem;border-radius:.6rem;box-sizing:border-box;">
                    <option value="">— Select Teacher —</option>
                    @foreach($teachers as $t)
                    <option value="{{ $t->id }}">{{ $t->name }} ({{ $t->email }})</option>
                    @endforeach
                </select>
            </div>
            <div style="margin-bottom:1rem;">
                <label style="display:block;font-size:.78rem;color:#94a3b8;margin-bottom:.3rem;">Select Approved Master Course *</label>
                <select name="course_id" required style="width:100%;background:#1e293b;border:1px solid #334155;color:#fff;padding:.6rem .85rem;border-radius:.6rem;box-sizing:border-box;">
                    <option value="">— Select Master Course —</option>
                    @foreach($activeCourses as $c)
                    <option value="{{ $c->id }}">{{ $c->code }} — {{ $c->title }}</option>
                    @endforeach
                </select>
            </div>
            <div style="margin-bottom:1.25rem;">
                <label style="display:block;font-size:.78rem;color:#94a3b8;margin-bottom:.3rem;">Assignment Role *</label>
                <select name="role" required style="width:100%;background:#1e293b;border:1px solid #334155;color:#fff;padding:.6rem .85rem;border-radius:.6rem;box-sizing:border-box;">
                    <option value="lead_teacher">Lead Teacher</option>
                    <option value="assistant_teacher">Assistant Teacher</option>
                    <option value="mentor">Academic Mentor</option>
                </select>
            </div>
            <button type="submit" style="width:100%;padding:.75rem;background:#6366f1;color:#fff;border:none;border-radius:.65rem;font-weight:700;cursor:pointer;">Assign Teacher &amp; Send Clickable Notification</button>
        </form>
    </div>
</div>
@endsection
