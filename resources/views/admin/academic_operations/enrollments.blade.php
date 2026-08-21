@extends('layouts.admin')

@section('title', 'Student Enrolments — Academic Operations')

@section('content')
<div class="space-y-6">

    @if(session('status'))
    <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-medium flex items-center gap-2">
        <span>✅</span> {{ session('status') }}
    </div>
    @endif

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-white">👥 Student Enrolments</h1>
            <p class="text-xs text-slate-400">Operational student assignment, transfer, and class enrolment management.</p>
        </div>
        <button type="button" onclick="document.getElementById('enroll-student-modal').classList.remove('hidden'); document.getElementById('enroll-student-modal').classList.add('flex');" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-xl shadow transition-colors">
            ＋ Enroll Student into Course
        </button>
    </div>

    {{-- Enrolments Table --}}
    <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead class="bg-slate-950 border-b border-slate-800 text-[10px] font-bold uppercase tracking-wider text-slate-400">
                    <tr>
                        <th class="px-4 py-3">Student Name</th>
                        <th class="px-4 py-3">Email</th>
                        <th class="px-4 py-3">Enrolled Master Course</th>
                        <th class="px-4 py-3">Teacher</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Enrollment Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/80">
                    @forelse($enrollments as $enr)
                    <tr class="hover:bg-slate-800/30 transition-colors">
                        <td class="px-4 py-3.5 font-bold text-white">{{ $enr->student?->name ?? 'Student' }}</td>
                        <td class="px-4 py-3.5 text-slate-400">{{ $enr->student?->email ?? '-' }}</td>
                        <td class="px-4 py-3.5">
                            <div class="font-bold text-indigo-400">{{ $enr->course?->title ?? 'Course' }}</div>
                            <div class="text-[11px] text-slate-400 font-mono">{{ $enr->course?->code }}</div>
                        </td>
                        <td class="px-4 py-3.5 text-slate-300">{{ $enr->course?->lead_teacher_name ?? 'Unassigned' }}</td>
                        <td class="px-4 py-3.5">
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                {{ strtoupper($enr->status ?? 'ACTIVE') }}
                            </span>
                        </td>
                        <td class="px-4 py-3.5 text-slate-400">{{ optional($enr->created_at)->format('M d, Y') ?? 'Recently' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="p-10 text-center text-slate-400">No student enrolments registered yet.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($enrollments->hasPages())
        <div class="p-4 border-t border-slate-800">
            {{ $enrollments->links() }}
        </div>
        @endif
    </div>

</div>

{{-- Enroll Modal --}}
<div id="enroll-student-modal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-lg w-full p-6 shadow-2xl space-y-4">
        <div class="flex justify-between items-center">
            <h3 class="text-base font-bold text-white">👥 Enroll Student into Master Course</h3>
            <button type="button" onclick="document.getElementById('enroll-student-modal').classList.add('hidden'); document.getElementById('enroll-student-modal').classList.remove('flex');" class="text-slate-400 hover:text-white text-lg">✕</button>
        </div>
        <form action="{{ route('admin.academic-operations.enrollments.store') }}" method="POST" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5">Select Student *</label>
                <select name="student_id" required class="w-full bg-slate-950 border border-slate-800 text-white text-xs rounded-xl px-3.5 py-2.5 focus:outline-none focus:border-indigo-500 transition-colors">
                    <option value="">— Select Student —</option>
                    @foreach($students as $st)
                    <option value="{{ $st->id }}">{{ $st->name }} ({{ $st->email }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5">Select Master Course *</label>
                <select name="course_id" required class="w-full bg-slate-950 border border-slate-800 text-white text-xs rounded-xl px-3.5 py-2.5 focus:outline-none focus:border-indigo-500 transition-colors">
                    <option value="">— Select Master Course —</option>
                    @foreach($activeCourses as $ac)
                    <option value="{{ $ac->id }}">{{ $ac->code }} — {{ $ac->title }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="w-full py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-xl shadow transition-colors">Complete Student Enrolment &amp; Activate Dashboard</button>
        </form>
    </div>
</div>
@endsection
