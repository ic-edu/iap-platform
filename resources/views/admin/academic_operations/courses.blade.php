@extends('layouts.admin')

@section('title', 'Course Management & Approval — Academic Operations')

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
            <h1 class="text-2xl font-bold text-white">🎓 Master Course Management</h1>
            <p class="text-xs text-slate-400">Operational Admin creates Master Courses. Super Admin approval is required for activation.</p>
        </div>
        @if(!Auth::user()?->hasRole('teacher'))
        <button type="button" onclick="document.getElementById('create-course-modal').classList.remove('hidden'); document.getElementById('create-course-modal').classList.add('flex');" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-xl shadow transition-colors">
            ＋ Create Master Course
        </button>
        @endif
    </div>

    {{-- Courses Table --}}
    <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead class="bg-slate-950 border-b border-slate-800 text-[10px] font-bold uppercase tracking-wider text-slate-400">
                    <tr>
                        <th class="px-4 py-3">Code</th>
                        <th class="px-4 py-3">Course Title</th>
                        <th class="px-4 py-3">Program</th>
                        <th class="px-4 py-3">Capacity</th>
                        <th class="px-4 py-3">Assigned Teacher</th>
                        <th class="px-4 py-3">Approval Status</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/80">
                    @forelse($courses as $course)
                    @php
                        $st = $course->course_status ?? 'active';
                    @endphp
                    <tr class="hover:bg-slate-800/30 transition-colors">
                        <td class="px-4 py-3.5 font-mono font-bold text-indigo-400">{{ $course->code }}</td>
                        <td class="px-4 py-3.5">
                            <div class="font-bold text-white">{{ $course->title }}</div>
                            <div class="text-[11px] text-slate-400">{{ $course->schedule }}</div>
                        </td>
                        <td class="px-4 py-3.5">
                            <span class="px-2.5 py-1 rounded-md bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 text-[11px] font-bold">{{ $course->program ?? 'TOEFL' }}</span>
                        </td>
                        <td class="px-4 py-3.5 font-bold text-slate-300">{{ $course->capacity ?? 30 }} seats</td>
                        <td class="px-4 py-3.5 text-slate-400">
                            @php
                                $leadTeacher = $course->leadTeacher();
                            @endphp
                            @if($leadTeacher)
                                <span class="font-semibold text-white">{{ $leadTeacher->name }}</span>
                                <span class="text-[10px] text-emerald-400 font-bold block">Lead Teacher</span>
                            @elseif($course->assignedTeachers->isNotEmpty())
                                <span class="font-semibold text-slate-300">{{ $course->assignedTeachers->first()->name }}</span>
                            @elseif(($course->course_status ?? '') === 'waiting_approval')
                                <span class="italic text-amber-400">Assignment Pending</span>
                            @else
                                <span class="italic text-slate-500">No teacher assigned</span>
                            @endif
                        </td>
                        <td class="px-4 py-3.5">
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider border
                                @if($st === 'active' || $st === 'approved') bg-emerald-500/10 text-emerald-400 border-emerald-500/20
                                @elseif($st === 'waiting_approval') bg-amber-500/10 text-amber-400 border-amber-500/20
                                @else bg-slate-800 text-slate-400 border-slate-700 @endif">
                                {{ str_replace('_', ' ', $st) }}
                            </span>
                        </td>
                        <td class="px-4 py-3.5 text-right">
                            @if(Auth::user()?->hasRole('super-admin') && $st === 'waiting_approval')
                            <div class="flex gap-1.5 justify-end">
                                <form action="{{ route('admin.academic-operations.courses.approve', $course->id) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="bg-emerald-600 hover:bg-emerald-500 text-white px-2.5 py-1 rounded-lg text-[11px] font-bold transition-colors">
                                        ✓ Approve
                                    </button>
                                </form>
                                <form action="{{ route('admin.academic-operations.courses.reject', $course->id) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="bg-rose-600 hover:bg-rose-500 text-white px-2.5 py-1 rounded-lg text-[11px] font-bold transition-colors">
                                        ✕ Reject
                                    </button>
                                </form>
                            </div>
                            @else
                            <span class="text-xs font-semibold text-slate-400">
                                Master Course Active
                            </span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="p-10 text-center text-slate-400">No master courses found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($courses->hasPages())
        <div class="p-4 border-t border-slate-800">
            {{ $courses->links() }}
        </div>
        @endif
    </div>

</div>

{{-- Create Modal --}}
@if(!Auth::user()?->hasRole('teacher'))
<div id="create-course-modal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-lg w-full p-6 shadow-2xl space-y-4">
        <div class="flex justify-between items-center">
            <h3 class="text-base font-bold text-white">🎓 Create Master Course</h3>
            <button type="button" onclick="document.getElementById('create-course-modal').classList.add('hidden'); document.getElementById('create-course-modal').classList.remove('flex');" class="text-slate-400 hover:text-white text-lg">✕</button>
        </div>
        <form action="{{ route('admin.academic-operations.courses.store') }}" method="POST" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5">Course Code *</label>
                <input type="text" name="code" required class="w-full bg-slate-950 border border-slate-800 text-white text-xs rounded-xl px-3.5 py-2.5 focus:outline-none focus:border-indigo-500 transition-colors" placeholder="e.g. TOEFL-MASTER-101">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5">Course Title *</label>
                <input type="text" name="title" required class="w-full bg-slate-950 border border-slate-800 text-white text-xs rounded-xl px-3.5 py-2.5 focus:outline-none focus:border-indigo-500 transition-colors" placeholder="e.g. TOEFL iBT Intensive Master Program">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5">Program *</label>
                <select name="program" class="w-full bg-slate-950 border border-slate-800 text-white text-xs rounded-xl px-3.5 py-2.5 focus:outline-none focus:border-indigo-500 transition-colors">
                    <option value="TOEFL">TOEFL</option>
                    <option value="TOEIC">TOEIC</option>
                    <option value="IELTS">IELTS</option>
                    <option value="General">General</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5">Capacity *</label>
                <input type="number" name="capacity" value="30" required class="w-full bg-slate-950 border border-slate-800 text-white text-xs rounded-xl px-3.5 py-2.5 focus:outline-none focus:border-indigo-500 transition-colors">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5">Schedule</label>
                <input type="text" name="schedule" value="Mon, Wed 09:00 - 11:00 AM" class="w-full bg-slate-950 border border-slate-800 text-white text-xs rounded-xl px-3.5 py-2.5 focus:outline-none focus:border-indigo-500 transition-colors">
            </div>
            <button type="submit" class="w-full py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-xl shadow transition-colors">Submit for Super Admin Approval</button>
        </form>
    </div>
</div>
@endif
@endsection
