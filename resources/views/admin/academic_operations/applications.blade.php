@extends('layouts.admin')

@section('title', 'Student Applications — Academic Operations')

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
            <h1 class="text-2xl font-bold text-white">📋 Student Applications</h1>
            <p class="text-xs text-slate-400">Manage student registrations, placement test verification, and program assignments.</p>
        </div>
    </div>

    {{-- Search & Filters --}}
    <div class="p-4 bg-slate-900 border border-slate-800 rounded-2xl">
        <form action="{{ route('admin.academic-operations.applications') }}" method="GET" class="flex flex-wrap items-center gap-3">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by Registration ID or Name…" class="bg-slate-950 border border-slate-800 text-white text-xs rounded-xl px-3.5 py-2 min-w-[260px] focus:outline-none focus:border-indigo-500 transition-colors">

            <select name="program" class="bg-slate-950 border border-slate-800 text-white text-xs rounded-xl px-3.5 py-2 focus:outline-none focus:border-indigo-500 transition-colors">
                <option value="">— All Programs —</option>
                <option value="TOEFL" {{ request('program') === 'TOEFL' ? 'selected' : '' }}>TOEFL</option>
                <option value="TOEIC" {{ request('program') === 'TOEIC' ? 'selected' : '' }}>TOEIC</option>
                <option value="IELTS" {{ request('program') === 'IELTS' ? 'selected' : '' }}>IELTS</option>
                <option value="General" {{ request('program') === 'General' ? 'selected' : '' }}>General</option>
            </select>

            <select name="status" class="bg-slate-950 border border-slate-800 text-white text-xs rounded-xl px-3.5 py-2 focus:outline-none focus:border-indigo-500 transition-colors">
                <option value="">— All Statuses —</option>
                <option value="waiting_review" {{ request('status') === 'waiting_review' ? 'selected' : '' }}>Waiting Review</option>
                <option value="placement_required" {{ request('status') === 'placement_required' ? 'selected' : '' }}>Placement Required</option>
                <option value="ready_for_assignment" {{ request('status') === 'ready_for_assignment' ? 'selected' : '' }}>Ready For Assignment</option>
                <option value="assigned" {{ request('status') === 'assigned' ? 'selected' : '' }}>Assigned</option>
                <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Completed</option>
                <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>Rejected</option>
            </select>

            <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-xl shadow transition-colors">Filter</button>
        </form>
    </div>

    {{-- Applications Table --}}
    <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead class="bg-slate-950 border-b border-slate-800 text-[10px] font-bold uppercase tracking-wider text-slate-400">
                    <tr>
                        <th class="px-4 py-3">Reg ID</th>
                        <th class="px-4 py-3">Student Name</th>
                        <th class="px-4 py-3">Selected Program</th>
                        <th class="px-4 py-3">English Level</th>
                        <th class="px-4 py-3">Placement Status</th>
                        <th class="px-4 py-3">Target Score</th>
                        <th class="px-4 py-3">Application Status</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/80">
                    @forelse($applications as $app)
                    <tr class="hover:bg-slate-800/30 transition-colors">
                        <td class="px-4 py-3.5 font-mono font-bold text-indigo-400">{{ $app->registration_id }}</td>
                        <td class="px-4 py-3.5 font-bold text-white">{{ $app->student_name }}</td>
                        <td class="px-4 py-3.5">
                            <span class="px-2.5 py-1 rounded-md bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 text-[11px] font-bold">{{ $app->selected_program }}</span>
                        </td>
                        <td class="px-4 py-3.5 text-slate-300">{{ $app->english_level }}</td>
                        <td class="px-4 py-3.5 text-slate-400">{{ str_replace('_', ' ', ucfirst($app->placement_test_status)) }}</td>
                        <td class="px-4 py-3.5 font-bold text-emerald-400">{{ $app->target_score }}</td>
                        <td class="px-4 py-3.5">
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider border
                                @if($app->application_status === 'assigned') bg-emerald-500/10 text-emerald-400 border-emerald-500/20
                                @elseif($app->application_status === 'ready_for_assignment') bg-indigo-500/10 text-indigo-400 border-indigo-500/20
                                @elseif($app->application_status === 'waiting_review') bg-amber-500/10 text-amber-400 border-amber-500/20
                                @else bg-slate-800 text-slate-400 border-slate-700 @endif">
                                {{ str_replace('_', ' ', $app->application_status) }}
                            </span>
                        </td>
                        <td class="px-4 py-3.5 text-right">
                            <form action="{{ route('admin.academic-operations.applications.status', $app->id) }}" method="POST" class="inline-flex gap-1.5">
                                @csrf
                                @method('PATCH')
                                <select name="application_status" onchange="this.form.submit()" class="bg-slate-950 border border-slate-800 text-white text-[11px] px-2.5 py-1 rounded-lg focus:outline-none focus:border-indigo-500 transition-colors cursor-pointer">
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
                        <td colspan="8" class="p-10 text-center text-slate-400">No student applications found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($applications->hasPages())
        <div class="p-4 border-t border-slate-800">
            {{ $applications->links() }}
        </div>
        @endif
    </div>

</div>
@endsection
