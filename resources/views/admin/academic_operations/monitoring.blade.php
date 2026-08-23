@extends('layouts.admin')

@section('title', 'Course Monitoring Dashboard — Academic Operations')

@section('content')
<div class="space-y-6">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-white">📊 Course Monitoring Dashboard</h1>
            <p class="text-xs text-slate-400">Academic Operations Center overview for active courses, student assignments, teacher assignments, and approval queues.</p>
        </div>
    </div>

    {{-- KPI Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-sm">
            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Active Courses</div>
            <div class="text-3xl font-black text-emerald-600 dark:text-emerald-400 mt-1">{{ $activeCoursesCount }}</div>
            <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">Approved institutional courses</div>
        </div>

        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-sm">
            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Students Assigned</div>
            <div class="text-3xl font-black text-indigo-600 dark:text-indigo-400 mt-1">{{ $studentsAssignedCount }}</div>
            <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">Active student enrolments</div>
        </div>

        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-sm">
            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Teachers Assigned</div>
            <div class="text-3xl font-black text-sky-600 dark:text-sky-400 mt-1">{{ $teachersAssignedCount }}</div>
            <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">Active teacher roles</div>
        </div>

        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-sm">
            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Courses Awaiting Approval</div>
            <div class="text-3xl font-black text-amber-600 dark:text-amber-400 mt-1">{{ $waitingApprovalCount }}</div>
            <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">Pending Super Admin review</div>
        </div>
    </div>

    {{-- Two Column Layout --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

        {{-- Recently Created Courses --}}
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-sm">
            <div class="text-sm font-bold text-slate-900 dark:text-white mb-4">🎓 Recently Created Master Courses</div>
            <div class="space-y-3">
                @forelse($recentlyCreatedCourses as $rc)
                <div class="flex justify-between items-center pb-3 border-b border-slate-800 last:border-b-0 last:pb-0">
                    <div>
                        <div class="text-xs font-bold text-slate-900 dark:text-white">{{ $rc->title }}</div>
                        <div class="text-[11px] text-slate-500 dark:text-slate-400 font-mono">{{ $rc->code }} · {{ $rc->program }}</div>
                    </div>
                    <x-status-badge :status="$rc->course_status ?? 'DRAFT'" />
                </div>
                @empty
                <div class="text-xs text-slate-400 py-4 text-center">No recent courses.</div>
                @endforelse
            </div>
        </div>

        {{-- Recent Operational Notifications --}}
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-sm">
            <div class="text-sm font-bold text-white mb-4">🔔 Recent System Notifications</div>
            <div class="space-y-3">
                @forelse($recentNotifications as $notif)
                <div class="flex flex-col gap-0.5 pb-3 border-b border-slate-800 last:border-b-0 last:pb-0">
                    <div class="text-xs font-bold text-white">{{ $notif->title }}</div>
                    <div class="text-[11px] text-slate-400">{{ Str::limit($notif->message, 80) }}</div>
                    <div class="text-[10px] text-slate-500">{{ optional($notif->created_at)->diffForHumans() }}</div>
                </div>
                @empty
                <div class="text-xs text-slate-400 py-4 text-center">No recent notifications.</div>
                @endforelse
            </div>
        </div>

    </div>

</div>
@endsection
