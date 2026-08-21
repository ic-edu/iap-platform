@extends('layouts.admin')

@section('title', 'Media Approval Center — Enterprise Repository Management')

@section('content')
<div class="space-y-6">

    {{-- Top Header --}}
    <div class="flex justify-between items-center flex-wrap gap-4">
        <div>
            <a href="{{ route('admin.repository-manager.dashboard') }}" class="text-indigo-600 dark:text-indigo-400 text-xs font-bold hover:underline inline-flex items-center gap-1">
                ← Back
            </a>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white mt-1">
                Media Approval Center (QA Queue)
            </h1>
        </div>
    </div>

    {{-- Status & Type Filters (PART F) --}}
    <div class="gov-card space-y-4">
        <div>
            <div class="text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2">Filter by Submission Status</div>
            <div class="flex gap-2 flex-wrap">
                <a href="{{ route('admin.repository-manager.media-approval', ['status' => 'pending_review', 'type' => $type]) }}" class="px-3.5 py-1.5 rounded-full text-xs font-bold transition-all {{ $status === 'pending_review' ? 'bg-indigo-600 text-white shadow-sm' : 'gov-btn-secondary' }}">
                    ⏳ Pending Review Queue
                </a>
                <a href="{{ route('admin.repository-manager.media-approval', ['status' => 'approved', 'type' => $type]) }}" class="px-3.5 py-1.5 rounded-full text-xs font-bold transition-all {{ $status === 'approved' ? 'bg-indigo-600 text-white shadow-sm' : 'gov-btn-secondary' }}">
                    ✅ Approved Revisions
                </a>
                <a href="{{ route('admin.repository-manager.media-approval', ['status' => 'revision_requested', 'type' => $type]) }}" class="px-3.5 py-1.5 rounded-full text-xs font-bold transition-all {{ $status === 'revision_requested' ? 'bg-indigo-600 text-white shadow-sm' : 'gov-btn-secondary' }}">
                    ⚠️ Revision Requested
                </a>
                <a href="{{ route('admin.repository-manager.media-approval', ['status' => 'rejected', 'type' => $type]) }}" class="px-3.5 py-1.5 rounded-full text-xs font-bold transition-all {{ $status === 'rejected' ? 'bg-indigo-600 text-white shadow-sm' : 'gov-btn-secondary' }}">
                    ❌ Rejected
                </a>
                <a href="{{ route('admin.repository-manager.media-approval', ['status' => 'all', 'type' => $type]) }}" class="px-3.5 py-1.5 rounded-full text-xs font-bold transition-all {{ $status === 'all' ? 'bg-indigo-600 text-white shadow-sm' : 'gov-btn-secondary' }}">
                    🌐 All Submissions
                </a>
            </div>
        </div>

        <div>
            <div class="text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2">Filter by Asset Type</div>
            <div class="flex gap-2 flex-wrap">
                <a href="{{ route('admin.repository-manager.media-approval', ['status' => $status]) }}" class="px-3.5 py-1.5 rounded-full text-xs font-bold transition-all {{ empty($type) ? 'bg-indigo-600 text-white shadow-sm' : 'gov-btn-secondary' }}">
                    All Asset Types
                </a>
                <a href="{{ route('admin.repository-manager.media-approval', ['status' => $status, 'type' => 'audio']) }}" class="px-3.5 py-1.5 rounded-full text-xs font-bold transition-all {{ $type === 'audio' ? 'bg-indigo-600 text-white shadow-sm' : 'gov-btn-secondary' }}">
                    🎵 Audio Clips
                </a>
                <a href="{{ route('admin.repository-manager.media-approval', ['status' => $status, 'type' => 'image']) }}" class="px-3.5 py-1.5 rounded-full text-xs font-bold transition-all {{ $type === 'image' ? 'bg-indigo-600 text-white shadow-sm' : 'gov-btn-secondary' }}">
                    🖼 Images
                </a>
                <a href="{{ route('admin.repository-manager.media-approval', ['status' => $status, 'type' => 'passage']) }}" class="px-3.5 py-1.5 rounded-full text-xs font-bold transition-all {{ $type === 'passage' ? 'bg-indigo-600 text-white shadow-sm' : 'gov-btn-secondary' }}">
                    📖 Passages
                </a>
                <a href="{{ route('admin.repository-manager.media-approval', ['status' => $status, 'type' => 'pdf']) }}" class="px-3.5 py-1.5 rounded-full text-xs font-bold transition-all {{ $type === 'pdf' ? 'bg-indigo-600 text-white shadow-sm' : 'gov-btn-secondary' }}">
                    📄 PDF Documents
                </a>
            </div>
        </div>
    </div>

    {{-- Review Requests Table --}}
    <div class="gov-card p-0 overflow-hidden shadow-sm">
        @if($reviewRequests->count() > 0)
        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-left text-xs">
                <thead class="bg-slate-50 dark:bg-slate-950 text-[10px] text-slate-500 dark:text-slate-400 uppercase tracking-wider border-b border-slate-200 dark:border-slate-800">
                    <tr>
                        <th class="px-4 py-3">Asset Title / Request ID</th>
                        <th class="px-4 py-3">Type</th>
                        <th class="px-4 py-3">Submitter Teacher</th>
                        <th class="px-4 py-3">Submitted At</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Reviewer Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
                    @foreach($reviewRequests as $req)
                    @php
                        $mediaType = $req->changes_data['media_type'] ?? 'Asset';
                    @endphp
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/30 transition-colors">
                        <td class="px-4 py-3.5">
                            <div class="font-bold text-slate-900 dark:text-white text-xs">
                                {{ $req->changes_data['new_title'] ?? 'Media Revision Request' }}
                            </div>
                            <div class="text-[10px] text-slate-400 mt-0.5 font-mono">
                                Request ID: <span class="text-indigo-600 dark:text-indigo-400 font-bold">{{ $req->id }}</span> • Resource ID: {{ $req->resource_id }}
                            </div>
                        </td>
                        <td class="px-4 py-3.5">
                            <span class="px-2 py-0.5 rounded-md text-[10px] font-bold uppercase bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border border-indigo-500/20">
                                {{ $mediaType }}
                            </span>
                        </td>
                        <td class="px-4 py-3.5">
                            <div class="font-semibold text-slate-800 dark:text-slate-200 text-xs">{{ $req->submitter?->name ?? 'Teacher' }}</div>
                            <div class="text-[10px] text-slate-400">{{ $req->submitter?->email }}</div>
                        </td>
                        <td class="px-4 py-3.5 text-xs text-slate-500 dark:text-slate-400">
                            {{ $req->created_at?->format('d M Y, H:i') }}
                        </td>
                        <td class="px-4 py-3.5">
                            @if($req->status === 'pending_review')
                                <span class="px-2.5 py-1 rounded-md text-[10px] font-bold uppercase bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">
                                    ⏳ Pending QA Review
                                </span>
                            @elseif($req->status === 'approved')
                                <span class="px-2.5 py-1 rounded-md text-[10px] font-bold uppercase bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                                    ✅ Approved &amp; Published
                                </span>
                            @elseif($req->status === 'revision_requested')
                                <span class="px-2.5 py-1 rounded-md text-[10px] font-bold uppercase bg-orange-500/10 text-orange-600 dark:text-orange-400 border border-orange-500/20">
                                    ⚠️ Revision Requested
                                </span>
                            @else
                                <span class="px-2.5 py-1 rounded-md text-[10px] font-bold uppercase bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20">
                                    ❌ Rejected
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-3.5 text-right">
                            <a href="{{ route('admin.repository-manager.media-review', $req->id) }}" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-xs font-bold shadow transition-colors inline-block">
                                Review Diff →
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="p-4 border-t border-slate-200 dark:border-slate-800">
            {{ $reviewRequests->links() }}
        </div>
        @else
        <div class="p-12 text-center text-slate-400">
            <div class="text-4xl mb-2">✨</div>
            <div class="text-sm font-bold text-slate-800 dark:text-slate-200 mb-1">No Submissions Matching Selected Filter</div>
            <div class="text-xs text-slate-500">All teacher media revision requests in this queue have been processed.</div>
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

