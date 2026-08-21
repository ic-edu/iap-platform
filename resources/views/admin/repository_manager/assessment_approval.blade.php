@extends('layouts.admin')

@section('title', 'Assessment Approval Queue — Repository Manager')

@section('content')
<div class="space-y-6">
    {{-- Header --}}
    <div class="flex justify-between items-center flex-wrap gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white mb-1">📋 Assessment Approval Queue</h1>
            <p class="text-xs text-slate-500 dark:text-slate-400">Review, validate, and approve submitted Assessment Tests from Teachers for institutional deployment.</p>
        </div>
        <div>
            <a href="{{ route('admin.repository-manager.dashboard') }}" class="gov-btn-secondary px-3.5 py-2 text-xs font-bold inline-flex items-center gap-1.5">
                ← Back
            </a>
        </div>
    </div>

    {{-- Filter & Counter Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <a href="{{ route('admin.repository-manager.assessment-approval', ['status' => 'pending']) }}" class="gov-card p-5 block {{ $status === 'pending' ? 'ring-2 ring-indigo-500' : '' }}">
            <div class="text-[11px] font-bold text-amber-600 dark:text-amber-400 uppercase tracking-wider">Pending Approval</div>
            <div class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white mt-1">{{ $pendingCount }}</div>
            <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">Awaiting Repository Review</div>
        </a>

        <a href="{{ route('admin.repository-manager.assessment-approval', ['status' => 'approved']) }}" class="gov-card p-5 block {{ $status === 'approved' ? 'ring-2 ring-emerald-500' : '' }}">
            <div class="text-[11px] font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider">Approved Tests</div>
            <div class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white mt-1">{{ $approvedCount }}</div>
            <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">Live in Academic Repository</div>
        </a>

        <a href="{{ route('admin.repository-manager.assessment-approval', ['status' => 'needs_revision']) }}" class="gov-card p-5 block {{ $status === 'needs_revision' ? 'ring-2 ring-rose-500' : '' }}">
            <div class="text-[11px] font-bold text-rose-600 dark:text-rose-400 uppercase tracking-wider">Needs Revision</div>
            <div class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white mt-1">{{ $needsRevisionCount }}</div>
            <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">Returned to Author</div>
        </a>
    </div>

    {{-- Assessments Queue Table --}}
    <div class="gov-card p-0 overflow-hidden shadow-sm">
        <div class="p-4 border-b border-slate-200 dark:border-slate-800 flex justify-between items-center">
            <h3 class="text-sm font-bold text-slate-900 dark:text-white">Assessment Submissions</h3>
            <span class="text-xs text-slate-500 dark:text-slate-400">Showing {{ $assessments->count() }} of {{ $assessments->total() }} items</span>
        </div>

        @if($assessments->isEmpty())
        <div class="p-12 text-center text-slate-400">
            <div class="text-4xl mb-2">🎉</div>
            <div class="text-sm font-bold text-slate-800 dark:text-slate-200">Queue Empty</div>
            <div class="text-xs text-slate-500 mt-1">There are no assessment test submissions matching your current filter.</div>
        </div>
        @else
        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-left text-xs">
                <thead class="bg-slate-50 dark:bg-slate-950 text-[10px] text-slate-500 dark:text-slate-400 uppercase tracking-wider border-b border-slate-200 dark:border-slate-800">
                    <tr>
                        <th class="px-4 py-3">Title &amp; Type</th>
                        <th class="px-4 py-3">Teacher Author</th>
                        <th class="px-4 py-3">Sections &amp; Duration</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Submitted At</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
                    @foreach($assessments as $item)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/30 transition-colors">
                        <td class="px-4 py-3.5">
                            <div class="font-bold text-slate-900 dark:text-white text-xs">{{ $item->title }}</div>
                            <div class="text-[10px] text-indigo-600 dark:text-indigo-400 mt-0.5 uppercase font-bold">
                                {{ is_object($item->test_type) ? $item->test_type->value : $item->test_type }}
                            </div>
                        </td>
                        <td class="px-4 py-3.5">
                            <div class="font-semibold text-slate-800 dark:text-slate-200 text-xs">{{ $item->creator?->name ?? 'Institutional System' }}</div>
                            <div class="text-[11px] text-slate-500 dark:text-slate-400">{{ $item->creator?->email }}</div>
                        </td>
                        <td class="px-4 py-3.5">
                            <div class="font-bold text-slate-800 dark:text-slate-200 text-xs">{{ $item->sections->count() }} Sections</div>
                            <div class="text-[11px] text-slate-500 dark:text-slate-400">⏱ {{ $item->duration_minutes }} Mins</div>
                        </td>
                        <td class="px-4 py-3.5">
                            @if(in_array($item->status, ['pending', 'pending_approval']))
                                <span class="px-2.5 py-1 rounded-md text-[10px] font-bold uppercase bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">
                                    ⏳ Pending Approval
                                </span>
                            @elseif($item->status === 'approved')
                                <span class="px-2.5 py-1 rounded-md text-[10px] font-bold uppercase bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                                    ✓ Approved
                                </span>
                            @else
                                <span class="px-2.5 py-1 rounded-md text-[10px] font-bold uppercase bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20">
                                    ⚠️ Needs Revision
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-3.5 text-xs text-slate-500 dark:text-slate-400">
                            {{ $item->updated_at?->diffForHumans() ?? 'Recently' }}
                        </td>
                        <td class="px-4 py-3.5 text-right">
                            @if(in_array($item->status, ['pending', 'pending_approval']))
                                <a href="{{ route('admin.repository-manager.assessment-review', $item->id) }}" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-xs font-bold transition-colors inline-flex items-center gap-1">
                                    🔍 Review &amp; Governance
                                </a>
                            @elseif(in_array($item->status, ['needs_revision', 'revision_requested']))
                                <span class="px-3 py-1.5 bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20 rounded-lg text-xs font-bold inline-flex items-center gap-1">
                                    📝 Awaiting Teacher Resubmission
                                </span>
                            @elseif($item->status === 'approved')
                                <span class="px-3 py-1.5 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 rounded-lg text-xs font-bold inline-flex items-center gap-1">
                                    ✓ Governance Complete
                                </span>
                            @else
                                <span class="px-3 py-1.5 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-bold">
                                    {{ ucfirst($item->status) }}
                                </span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-slate-200 dark:border-slate-800">
            {{ $assessments->links() }}
        </div>
        @endif
    </div>
</div>
<script>
window.addEventListener('pageshow', function (event) {
    if (event.persisted) {
        window.location.reload();
    }
});
</script>
@endsection

