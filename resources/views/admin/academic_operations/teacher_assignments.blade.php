@extends('layouts.admin')

@section('title', 'Teacher Assignments — Academic Operations')

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
            <h1 class="text-2xl font-bold text-white">👩‍🏫 Teacher Assignments</h1>
            <p class="text-xs text-slate-400">Assign teachers into approved master courses. Assigned teachers automatically receive a clickable notification.</p>
        </div>
        <button type="button" onclick="document.getElementById('assign-teacher-modal').classList.remove('hidden'); document.getElementById('assign-teacher-modal').classList.add('flex');" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-xl shadow transition-colors">
            ＋ Assign Teacher to Course
        </button>
    </div>

    {{-- Assignments Table --}}
    <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead class="bg-slate-950 border-b border-slate-800 text-[10px] font-bold uppercase tracking-wider text-slate-400">
                    <tr>
                        <th class="px-4 py-3">Teacher Name</th>
                        <th class="px-4 py-3">Email</th>
                        <th class="px-4 py-3">Assigned Master Course</th>
                        <th class="px-4 py-3">Role</th>
                        <th class="px-4 py-3">Assignment Status</th>
                        <th class="px-4 py-3">Assigned Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/80">
                    @forelse($assignments as $assign)
                    <tr class="hover:bg-slate-800/30 transition-colors">
                        <td class="px-4 py-3.5 font-bold text-white">{{ $assign->teacher?->name ?? 'Unknown Teacher' }}</td>
                        <td class="px-4 py-3.5 text-slate-400">{{ $assign->teacher?->email ?? '-' }}</td>
                        <td class="px-4 py-3.5">
                            <div class="font-bold text-indigo-400">{{ $assign->course?->title ?? 'Deleted Course' }}</div>
                            <div class="text-[11px] text-slate-400 font-mono">{{ $assign->course?->code }}</div>
                        </td>
                        <td class="px-4 py-3.5">
                            <span class="px-2.5 py-1 rounded-md bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 text-[11px] font-bold">
                                {{ str_replace('_', ' ', strtoupper($assign->role)) }}
                            </span>
                        </td>
                        <td class="px-4 py-3.5">
                            <x-status-badge :status="$assign->status" />
                        </td>
                        <td class="px-4 py-3.5 text-slate-400">{{ $assign->created_at->format('M d, Y') }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="p-10 text-center text-slate-400">No teacher assignments recorded yet.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($assignments->hasPages())
        <div class="p-4 border-t border-slate-800">
            {{ $assignments->links() }}
        </div>
        @endif
    </div>

</div>

{{-- Assign Modal --}}
<div id="assign-teacher-modal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-lg w-full p-6 shadow-2xl space-y-4">
        <div class="flex justify-between items-center">
            <h3 class="text-base font-bold text-white">👩‍🏫 Assign Teacher into Master Course</h3>
            <button type="button" onclick="document.getElementById('assign-teacher-modal').classList.add('hidden'); document.getElementById('assign-teacher-modal').classList.remove('flex');" class="text-slate-400 hover:text-white text-lg">✕</button>
        </div>
        <form action="{{ route('admin.academic-operations.teacher-assignments.store') }}" method="POST" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5">Select Teacher *</label>
                <select name="teacher_id" required class="w-full bg-slate-950 border border-slate-800 text-white text-xs rounded-xl px-3.5 py-2.5 focus:outline-none focus:border-indigo-500 transition-colors">
                    <option value="">— Select Teacher —</option>
                    @foreach($teachers as $t)
                    <option value="{{ $t->id }}">{{ $t->name }} ({{ $t->email }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5">Select Approved Master Course *</label>
                <select name="course_id" required class="w-full bg-slate-950 border border-slate-800 text-white text-xs rounded-xl px-3.5 py-2.5 focus:outline-none focus:border-indigo-500 transition-colors">
                    <option value="">— Select Master Course —</option>
                    @foreach($activeCourses as $c)
                    <option value="{{ $c->id }}">{{ $c->code }} — {{ $c->title }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5">Assignment Role *</label>
                <select name="role" required class="w-full bg-slate-950 border border-slate-800 text-white text-xs rounded-xl px-3.5 py-2.5 focus:outline-none focus:border-indigo-500 transition-colors">
                    <option value="lead_teacher">Lead Teacher</option>
                    <option value="assistant_teacher">Assistant Teacher</option>
                    <option value="mentor">Academic Mentor</option>
                </select>
            </div>
            <button type="submit" class="w-full py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-xl shadow transition-colors">Assign Teacher &amp; Send Clickable Notification</button>
        </form>
    </div>
</div>
@endsection
