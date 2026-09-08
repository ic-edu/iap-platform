@extends('layouts.admin')

@section('title', 'Assessment Governance — Repository Manager')

@section('content')
<div class="space-y-6 max-w-7xl mx-auto">

    {{-- Breadcrumb & Header --}}
    <div class="flex justify-between items-start flex-wrap gap-4">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <a href="{{ \App\Services\NavigationService::getDashboardRouteForUser() }}" class="breadcrumb-link" style="font-size: 0.75rem; font-weight: 600; text-decoration: none;">
                    ← Dashboard
                </a>
                <span class="text-xs text-slate-400">/</span>
                <span class="text-xs font-bold text-slate-800 dark:text-slate-200">Assessment Governance</span>
            </div>
            <h1 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight">Assessment Governance Workspace</h1>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 max-w-3xl">
                Manage assessment requests, repository review, publication readiness, and published assessment governance.
            </p>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ \App\Services\NavigationService::getDashboardRouteForUser() }}" class="gov-btn-secondary px-3.5 py-2 text-xs font-bold inline-flex items-center gap-1.5 shadow-sm">
                ← Dashboard
            </a>
        </div>
    </div>

    {{-- Informational Lifecycle KPI Strip (Section 8 & 29) --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {{-- KPI 1: Request Intake --}}
        <a href="{{ route('admin.repository-manager.assessment-governance', ['tab' => 'request-intake']) }}" 
           class="gov-card p-4 block transition-all no-underline {{ $activeTab === 'request-intake' ? 'ring-2 ring-indigo-500 bg-indigo-500/5' : 'hover:border-indigo-500/60' }}">
            <div class="flex justify-between items-center mb-1">
                <span class="text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Request Intake</span>
                <span class="text-base">📥</span>
            </div>
            <div class="text-2xl font-black text-slate-900 dark:text-white">{{ $requestIntakeCount }}</div>
            <div class="text-[10px] text-slate-500 dark:text-slate-400 mt-1">Operational Briefs &amp; Drafts</div>
        </a>

        {{-- KPI 2: Pending Review --}}
        <a href="{{ route('admin.repository-manager.assessment-governance', ['tab' => 'pending-review']) }}" 
           class="gov-card p-4 block transition-all no-underline {{ $activeTab === 'pending-review' ? 'ring-2 ring-amber-500 bg-amber-500/5' : 'hover:border-amber-500/60' }}">
            <div class="flex justify-between items-center mb-1">
                <span class="text-[11px] font-bold text-amber-600 dark:text-amber-400 uppercase tracking-wider">Pending Review</span>
                <span class="text-base">⏳</span>
            </div>
            <div class="text-2xl font-black text-slate-900 dark:text-white">{{ $pendingReviewCount }}</div>
            <div class="text-[10px] text-slate-500 dark:text-slate-400 mt-1">Awaiting Smart Review</div>
        </a>

        {{-- KPI 3: Ready to Publish --}}
        <a href="{{ route('admin.repository-manager.assessment-governance', ['tab' => 'ready-to-publish']) }}" 
           class="gov-card p-4 block transition-all no-underline {{ $activeTab === 'ready-to-publish' ? 'ring-2 ring-emerald-500 bg-emerald-500/5' : 'hover:border-emerald-500/60' }}">
            <div class="flex justify-between items-center mb-1">
                <span class="text-[11px] font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider">Ready to Publish</span>
                <span class="text-base">🚀</span>
            </div>
            <div class="text-2xl font-black text-slate-900 dark:text-white">{{ $readyToPublishCount }}</div>
            <div class="text-[10px] text-slate-500 dark:text-slate-400 mt-1">Approved &bull; Unpublished</div>
        </a>

        {{-- KPI 4: Published Registry --}}
        <a href="{{ route('admin.repository-manager.assessment-governance', ['tab' => 'published']) }}" 
           class="gov-card p-4 block transition-all no-underline {{ $activeTab === 'published' ? 'ring-2 ring-sky-500 bg-sky-500/5' : 'hover:border-sky-500/60' }}">
            <div class="flex justify-between items-center mb-1">
                <span class="text-[11px] font-bold text-sky-600 dark:text-sky-400 uppercase tracking-wider">Published</span>
                <span class="text-base">🟢</span>
            </div>
            <div class="text-2xl font-black text-slate-900 dark:text-white">{{ $publishedCount }}</div>
            <div class="text-[10px] text-slate-500 dark:text-slate-400 mt-1">Live Assessments Registry</div>
        </a>
    </div>

    {{-- Lifecycle Navigation Tabs (Section 8) --}}
    <div class="border-b border-slate-200 dark:border-slate-800 flex items-center gap-2 overflow-x-auto pb-px">
        <a href="{{ route('admin.repository-manager.assessment-governance', ['tab' => 'request-intake']) }}" 
           class="px-4 py-2.5 text-xs font-bold rounded-t-xl transition-all inline-flex items-center gap-2 whitespace-nowrap no-underline {{ $activeTab === 'request-intake' ? 'border-b-2 border-indigo-600 text-indigo-600 dark:text-indigo-400 bg-indigo-50/50 dark:bg-indigo-950/20' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' }}">
            <span>📥 Request Intake</span>
            <span class="px-2 py-0.5 rounded-full text-[10px] font-black {{ $activeTab === 'request-intake' ? 'bg-indigo-600 text-white' : 'bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300' }}">
                {{ $requestIntakeCount }}
            </span>
        </a>

        <a href="{{ route('admin.repository-manager.assessment-governance', ['tab' => 'pending-review']) }}" 
           class="px-4 py-2.5 text-xs font-bold rounded-t-xl transition-all inline-flex items-center gap-2 whitespace-nowrap no-underline {{ $activeTab === 'pending-review' ? 'border-b-2 border-amber-600 text-amber-600 dark:text-amber-400 bg-amber-50/50 dark:bg-amber-950/20' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' }}">
            <span>⏳ Pending Review</span>
            <span class="px-2 py-0.5 rounded-full text-[10px] font-black {{ $activeTab === 'pending-review' ? 'bg-amber-600 text-white' : 'bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300' }}">
                {{ $pendingReviewCount }}
            </span>
        </a>

        <a href="{{ route('admin.repository-manager.assessment-governance', ['tab' => 'ready-to-publish']) }}" 
           class="px-4 py-2.5 text-xs font-bold rounded-t-xl transition-all inline-flex items-center gap-2 whitespace-nowrap no-underline {{ $activeTab === 'ready-to-publish' ? 'border-b-2 border-emerald-600 text-emerald-600 dark:text-emerald-400 bg-emerald-50/50 dark:bg-emerald-950/20' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' }}">
            <span>🚀 Ready to Publish</span>
            <span class="px-2 py-0.5 rounded-full text-[10px] font-black {{ $activeTab === 'ready-to-publish' ? 'bg-emerald-600 text-white' : 'bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300' }}">
                {{ $readyToPublishCount }}
            </span>
        </a>

        <a href="{{ route('admin.repository-manager.assessment-governance', ['tab' => 'published']) }}" 
           class="px-4 py-2.5 text-xs font-bold rounded-t-xl transition-all inline-flex items-center gap-2 whitespace-nowrap no-underline {{ $activeTab === 'published' ? 'border-b-2 border-sky-600 text-sky-600 dark:text-sky-400 bg-sky-50/50 dark:bg-sky-950/20' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' }}">
            <span>🟢 Published</span>
            <span class="px-2 py-0.5 rounded-full text-[10px] font-black {{ $activeTab === 'published' ? 'bg-sky-600 text-white' : 'bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300' }}">
                {{ $publishedCount }}
            </span>
        </a>
    </div>

    {{-- TAB 1: REQUEST INTAKE (Section 9) --}}
    @if($activeTab === 'request-intake')
    <div class="space-y-4">
        {{-- Filter Bar --}}
        <form method="GET" action="{{ route('admin.repository-manager.assessment-governance') }}" class="flex gap-2 items-center flex-wrap">
            <input type="hidden" name="tab" value="request-intake">
            <input type="text" name="search" value="{{ $search }}" placeholder="Search intake requests or context..." 
                   class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-slate-900 dark:text-white text-xs rounded-xl px-3.5 py-2 w-full sm:w-80 focus:outline-none focus:border-indigo-500 transition-colors">
            <button type="submit" class="gov-btn-primary px-3.5 py-2 text-xs font-bold">Search</button>
            @if($search)
            <a href="{{ route('admin.repository-manager.assessment-governance', ['tab' => 'request-intake']) }}" class="text-xs text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 font-semibold">Clear</a>
            @endif
        </form>

        <div class="gov-card p-0 overflow-hidden shadow-sm">
            <div class="p-4 border-b border-slate-200 dark:border-slate-800 flex justify-between items-center">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Assessment Request Intake Queue</h3>
                <span class="text-xs text-slate-500 dark:text-slate-400">Showing {{ $assessmentRequests->count() }} of {{ $assessmentRequests->total() }} requests</span>
            </div>

            @if($assessmentRequests->isEmpty())
            <div class="p-12 text-center text-slate-400">
                <div class="text-4xl mb-2">📭</div>
                <div class="text-sm font-bold text-slate-800 dark:text-slate-200">No Assessment Requests</div>
                <div class="text-xs text-slate-500 mt-1">There are currently no operational assessment requests in the intake queue.</div>
            </div>
            @else
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-left text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-950 text-[10px] text-slate-500 dark:text-slate-400 uppercase tracking-wider border-b border-slate-200 dark:border-slate-800">
                        <tr>
                            <th class="px-4 py-3">Title &amp; Context</th>
                            <th class="px-4 py-3">Type</th>
                            <th class="px-4 py-3">Requested By</th>
                            <th class="px-4 py-3">Deadline</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
                        @foreach($assessmentRequests as $req)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/30 transition-colors">
                            <td class="px-4 py-3.5">
                                <div class="font-bold text-slate-900 dark:text-white text-xs mb-0.5">
                                    {{ $req->title }}
                                </div>
                                @if($req->candidate)
                                <div class="text-[11px] text-emerald-600 dark:text-emerald-400 mb-0.5 font-semibold flex items-center gap-1">
                                    <span>👤 Target Candidate:</span> <span class="font-bold">{{ $req->candidate->name }}</span>
                                </div>
                                @endif
                                @if($req->program_context)
                                <div class="text-[11px] text-indigo-600 dark:text-indigo-400 mb-0.5 font-semibold">
                                    🎯 Context: {{ $req->program_context }}
                                </div>
                                @endif
                                @if($req->notes)
                                <div class="text-[11px] text-slate-500 dark:text-slate-400 max-w-md">
                                    {{ \Illuminate\Support\Str::limit($req->notes, 80) }}
                                </div>
                                @endif
                            </td>
                            <td class="px-4 py-3.5">
                                <span class="px-2 py-0.5 rounded-md text-[10px] font-bold uppercase bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                                    {{ $req->test_type }}
                                </span>
                            </td>
                            <td class="px-4 py-3.5 text-slate-600 dark:text-slate-400">
                                <div class="font-bold text-slate-800 dark:text-slate-200">{{ $req->requester?->name ?? 'Admin' }}</div>
                                <div class="text-[10px] text-slate-400">{{ $req->created_at?->diffForHumans() }}</div>
                            </td>
                            <td class="px-4 py-3.5 text-xs text-slate-500 dark:text-slate-400">
                                {{ $req->requested_deadline ? $req->requested_deadline->format('M d, Y') : 'No deadline' }}
                            </td>
                            <td class="px-4 py-3.5">
                                @if($req->status === 'completed')
                                    <span class="px-2.5 py-1 rounded-md text-[10px] font-bold uppercase bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border border-indigo-500/20">
                                        COMPLETED
                                    </span>
                                @elseif($req->status === 'draft_created')
                                    <span class="px-2.5 py-1 rounded-md text-[10px] font-bold uppercase bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                                        DRAFT CREATED
                                    </span>
                                @elseif($req->status === 'pending')
                                    <span class="px-2.5 py-1 rounded-md text-[10px] font-bold uppercase bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">
                                        PENDING
                                    </span>
                                @elseif($req->status === 'archived')
                                    <span class="px-2.5 py-1 rounded-md text-[10px] font-bold uppercase bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-700">
                                        ARCHIVED
                                    </span>
                                @else
                                    <span class="px-2.5 py-1 rounded-md text-[10px] font-bold uppercase bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-700">
                                        {{ str_replace('_', ' ', strtoupper($req->status)) }}
                                    </span>
                                @endif

                                @if($req->test && $req->test->assignedTeacher)
                                <div class="text-[10px] text-indigo-600 dark:text-indigo-400 mt-1 font-semibold">
                                    👤 Assigned: {{ $req->test->assignedTeacher->name }}
                                </div>
                                @endif
                            </td>
                            <td class="px-4 py-3.5 text-right space-x-2 whitespace-nowrap">
                                @if($req->status === 'pending')
                                <button type="button" onclick='openAssignDraftModal({{ json_encode($req) }})' class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-xs font-bold shadow transition-colors inline-flex items-center gap-1">
                                    📝 Create Draft &amp; Assign
                                </button>
                                @elseif($req->test)
                                <a href="{{ route('admin.repository-manager.assessment-requests.assessment-show', $req->id) }}" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-lg text-xs font-bold transition-colors inline-flex items-center gap-1 no-underline">
                                    👁 View Assessment
                                </a>
                                @else
                                <span class="text-slate-400 text-xs">—</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($assessmentRequests->hasPages())
            <div class="p-4 border-t border-slate-200 dark:border-slate-800">
                {{ $assessmentRequests->appends(['tab' => 'request-intake'])->links() }}
            </div>
            @endif
            @endif
        </div>
    </div>
    @endif

    {{-- TAB 2: PENDING REVIEW (Section 10 & 11) --}}
    @if($activeTab === 'pending-review')
    <div class="space-y-6">
        {{-- Filter Bar --}}
        <form method="GET" action="{{ route('admin.repository-manager.assessment-governance') }}" class="flex gap-2 items-center flex-wrap">
            <input type="hidden" name="tab" value="pending-review">
            <input type="text" name="search" value="{{ $search }}" placeholder="Search pending assessments..." 
                   class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-slate-900 dark:text-white text-xs rounded-xl px-3.5 py-2 w-full sm:w-80 focus:outline-none focus:border-indigo-500 transition-colors">
            <button type="submit" class="gov-btn-primary px-3.5 py-2 text-xs font-bold">Search</button>
            @if($search)
            <a href="{{ route('admin.repository-manager.assessment-governance', ['tab' => 'pending-review']) }}" class="text-xs text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 font-semibold">Clear</a>
            @endif
        </form>

        {{-- Active Pending Review Queue Table --}}
        <div class="gov-card p-0 overflow-hidden shadow-sm">
            <div class="p-4 border-b border-slate-200 dark:border-slate-800 flex justify-between items-center">
                <div>
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                        Active Review Queue (Submitted by Teachers)
                    </h3>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">Only assessments actively awaiting Repository Manager Smart Review.</p>
                </div>
                <span class="text-xs text-slate-500 dark:text-slate-400">{{ $pendingReviewAssessments->total() }} items</span>
            </div>

            @if($pendingReviewAssessments->isEmpty())
            <div class="p-12 text-center text-slate-400">
                <div class="text-4xl mb-2">🎉</div>
                <div class="text-sm font-bold text-slate-800 dark:text-slate-200">Review Queue Clear</div>
                <div class="text-xs text-slate-500 mt-1">There are no assessment tests currently awaiting Repository Manager review.</div>
            </div>
            @else
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-left text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-950 text-[10px] text-slate-500 dark:text-slate-400 uppercase tracking-wider border-b border-slate-200 dark:border-slate-800">
                        <tr>
                            <th class="px-4 py-3">Title &amp; Type</th>
                            <th class="px-4 py-3">Teacher Author</th>
                            <th class="px-4 py-3">Sections &amp; Questions</th>
                            <th class="px-4 py-3">Duration</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Submitted At</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
                        @foreach($pendingReviewAssessments as $item)
                        @php
                            $sectionCount = $item->sections->count();
                            $questionCount = $item->sections->sum(fn($s) => $s->testQuestions->count());
                        @endphp
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
                                <div class="font-bold text-slate-800 dark:text-slate-200">{{ $sectionCount }} Sections</div>
                                <div class="text-[11px] text-slate-500 dark:text-slate-400">{{ $questionCount }} Questions</div>
                            </td>
                            <td class="px-4 py-3.5 text-slate-600 dark:text-slate-400">
                                ⏱ {{ $item->duration_minutes ?? 0 }} mins
                            </td>
                            <td class="px-4 py-3.5">
                                <span class="px-2.5 py-1 rounded-md text-[10px] font-bold uppercase bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">
                                    ⏳ Pending Review
                                </span>
                            </td>
                            <td class="px-4 py-3.5 text-xs text-slate-500 dark:text-slate-400">
                                {{ $item->updated_at?->diffForHumans() ?? 'Recently' }}
                            </td>
                            <td class="px-4 py-3.5 text-right whitespace-nowrap">
                                <a href="{{ route('admin.repository-manager.assessment-review', $item->id) }}" 
                                   class="px-3.5 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-xs font-bold transition-colors inline-flex items-center gap-1 shadow-sm no-underline">
                                    🔍 Smart Review
                                </a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($pendingReviewAssessments->hasPages())
            <div class="p-4 border-t border-slate-200 dark:border-slate-800">
                {{ $pendingReviewAssessments->appends(['tab' => 'pending-review'])->links() }}
            </div>
            @endif
            @endif
        </div>

        {{-- Section 11: Needs Revision Oversight (Read-Only / Non-Actionable until resubmitted) --}}
        @if($needsRevisionAssessments->isNotEmpty())
        <div class="gov-card p-0 overflow-hidden border-rose-500/30 dark:border-rose-900/40">
            <div class="p-4 bg-rose-50/50 dark:bg-rose-950/20 border-b border-rose-200 dark:border-rose-900/40 flex justify-between items-center">
                <div>
                    <h3 class="text-xs font-bold text-rose-700 dark:text-rose-400 flex items-center gap-1.5 uppercase tracking-wider">
                        <span>⚠️</span> Needs Revision (Returned to Teacher)
                    </h3>
                    <p class="text-[11px] text-rose-600/80 dark:text-rose-400/80 mt-0.5">
                        These assessments are currently under teacher authoring revision. No active review action until resubmitted.
                    </p>
                </div>
                <span class="text-xs font-bold text-rose-600 dark:text-rose-400">{{ $needsRevisionAssessments->count() }} items</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-left text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-950 text-[10px] text-slate-500 dark:text-slate-400 uppercase tracking-wider border-b border-slate-200 dark:border-slate-800">
                        <tr>
                            <th class="px-4 py-2.5">Title</th>
                            <th class="px-4 py-2.5">Author</th>
                            <th class="px-4 py-2.5">Sections</th>
                            <th class="px-4 py-2.5">Status</th>
                            <th class="px-4 py-2.5 text-right">Lifecycle State</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
                        @foreach($needsRevisionAssessments as $revItem)
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/20">
                            <td class="px-4 py-3 font-semibold text-slate-900 dark:text-white">{{ $revItem->title }}</td>
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-400">{{ $revItem->creator?->name ?? 'Teacher' }}</td>
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-400">{{ $revItem->sections->count() }} Sections</td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20">
                                    ⚠️ Needs Revision
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <span class="text-[11px] text-slate-400 italic">📝 Awaiting Teacher Resubmission</span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif
    </div>
    @endif

    {{-- TAB 3: READY TO PUBLISH (Section 12 & 13 — Canonical Publication Queue) --}}
    @if($activeTab === 'ready-to-publish')
    <div class="space-y-4">
        {{-- Filter Bar --}}
        <form method="GET" action="{{ route('admin.repository-manager.assessment-governance') }}" class="flex gap-2 items-center flex-wrap">
            <input type="hidden" name="tab" value="ready-to-publish">
            <input type="text" name="search" value="{{ $search }}" placeholder="Search approved assessments..." 
                   class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-slate-900 dark:text-white text-xs rounded-xl px-3.5 py-2 w-full sm:w-80 focus:outline-none focus:border-indigo-500 transition-colors">
            <button type="submit" class="gov-btn-primary px-3.5 py-2 text-xs font-bold">Search</button>
            @if($search)
            <a href="{{ route('admin.repository-manager.assessment-governance', ['tab' => 'ready-to-publish']) }}" class="text-xs text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 font-semibold">Clear</a>
            @endif
        </form>

        <div class="gov-card p-0 overflow-hidden shadow-sm">
            <div class="p-4 border-b border-slate-200 dark:border-slate-800 flex justify-between items-center">
                <div>
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                        Publication Queue (Approved Assessments Ready for Publication)
                    </h3>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">Approved assessments ready for live candidate deployment. Single canonical publication surface.</p>
                </div>
                <span class="text-xs text-slate-500 dark:text-slate-400">{{ $readyToPublishAssessments->total() }} items</span>
            </div>

            @if($readyToPublishAssessments->isEmpty())
            <div class="p-12 text-center text-slate-400">
                <div class="text-4xl mb-2">📋</div>
                <div class="text-sm font-bold text-slate-800 dark:text-slate-200">No Approved Assessments Pending Publication</div>
                <div class="text-xs text-slate-500 mt-1">Approved assessments will appear here when they are ready for publication.</div>
            </div>
            @else
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-left text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-950 text-[10px] text-slate-500 dark:text-slate-400 uppercase tracking-wider border-b border-slate-200 dark:border-slate-800">
                        <tr>
                            <th class="px-4 py-3">Assessment Name</th>
                            <th class="px-4 py-3">Author</th>
                            <th class="px-4 py-3 text-center">Sections</th>
                            <th class="px-4 py-3 text-center">Questions</th>
                            <th class="px-4 py-3">Duration</th>
                            <th class="px-4 py-3">Approval Status</th>
                            <th class="px-4 py-3">Publication Status</th>
                            <th class="px-4 py-3">Approved / Created</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
                        @foreach($readyToPublishAssessments as $test)
                        @php
                            $sectionCount = $test->sections->count();
                            $questionCount = $test->sections->sum(fn($s) => $s->testQuestions->count());
                        @endphp
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/30 transition-colors {{ request('highlight') == $test->id ? 'bg-emerald-50/40 dark:bg-emerald-950/20' : '' }}"
                            id="assessment-{{ $test->id }}">
                            <td class="px-4 py-3.5">
                                <div class="font-bold text-slate-900 dark:text-white text-xs">{{ $test->title }}</div>
                                <div class="text-[10px] text-indigo-600 dark:text-indigo-400 mt-0.5 uppercase font-bold">
                                    {{ is_object($test->test_type) ? $test->test_type->value : $test->test_type }}
                                </div>
                            </td>
                            <td class="px-4 py-3.5 text-slate-700 dark:text-slate-300">
                                {{ $test->creator?->name ?? 'Institutional System' }}
                            </td>
                            <td class="px-4 py-3.5 text-center font-bold text-slate-800 dark:text-slate-200">
                                {{ $sectionCount }}
                            </td>
                            <td class="px-4 py-3.5 text-center font-bold text-slate-800 dark:text-slate-200">
                                {{ $questionCount }}
                            </td>
                            <td class="px-4 py-3.5 text-slate-600 dark:text-slate-400">
                                ⏱ {{ $test->duration_minutes ?? 0 }} mins
                            </td>
                            <td class="px-4 py-3.5">
                                <span class="px-2.5 py-1 rounded-md text-[10px] font-bold uppercase bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                                    ✓ Approved — Ready to Publish
                                </span>
                            </td>
                            <td class="px-4 py-3.5">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-700">
                                    Unpublished
                                </span>
                            </td>
                            <td class="px-4 py-3.5 text-xs text-slate-500 dark:text-slate-400">
                                {{ $test->updated_at?->diffForHumans() ?? $test->created_at?->diffForHumans() }}
                            </td>
                            <td class="px-4 py-3.5 text-right whitespace-nowrap">
                                <form action="{{ route('admin.publications.assessments.publish', $test->id) }}" method="POST" class="inline"
                                      onsubmit="event.preventDefault(); iapConfirm({ title: 'Publish Assessment Live?', message: 'Publish \'{{ addslashes($test->title) }}\' live? Candidates will immediately be able to access this assessment.', confirmText: 'Publish Live', variant: 'success', form: this });">
                                    @csrf
                                    <button type="submit" class="px-3.5 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-xs font-bold transition-colors inline-flex items-center gap-1 shadow-sm">
                                        🚀 Publish
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($readyToPublishAssessments->hasPages())
            <div class="p-4 border-t border-slate-200 dark:border-slate-800">
                {{ $readyToPublishAssessments->appends(['tab' => 'ready-to-publish'])->links() }}
            </div>
            @endif
            @endif
        </div>
    </div>
    @endif

    {{-- TAB 4: PUBLISHED REGISTRY (Section 14) --}}
    @if($activeTab === 'published')
    <div class="space-y-4">
        {{-- Filter Bar --}}
        <form method="GET" action="{{ route('admin.repository-manager.assessment-governance') }}" class="flex gap-2 items-center flex-wrap">
            <input type="hidden" name="tab" value="published">
            <input type="text" name="search" value="{{ $search }}" placeholder="Search published assessments..." 
                   class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-slate-900 dark:text-white text-xs rounded-xl px-3.5 py-2 w-full sm:w-80 focus:outline-none focus:border-indigo-500 transition-colors">
            <button type="submit" class="gov-btn-primary px-3.5 py-2 text-xs font-bold">Search</button>
            @if($search)
            <a href="{{ route('admin.repository-manager.assessment-governance', ['tab' => 'published']) }}" class="text-xs text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 font-semibold">Clear</a>
            @endif
        </form>

        <div class="gov-card p-0 overflow-hidden shadow-sm">
            <div class="p-4 border-b border-slate-200 dark:border-slate-800 flex justify-between items-center">
                <div>
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-sky-500"></span>
                        Published Assessments Registry
                    </h3>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">Active live assessments available for candidate testing.</p>
                </div>
                <span class="text-xs text-slate-500 dark:text-slate-400">{{ $publishedAssessments->total() }} items</span>
            </div>

            @if($publishedAssessments->isEmpty())
            <div class="p-12 text-center text-slate-400">
                <div class="text-4xl mb-2">🏛</div>
                <div class="text-sm font-bold text-slate-800 dark:text-slate-200">No Published Assessments</div>
                <div class="text-xs text-slate-500 mt-1">Assessments that have been published live will appear here.</div>
            </div>
            @else
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-left text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-950 text-[10px] text-slate-500 dark:text-slate-400 uppercase tracking-wider border-b border-slate-200 dark:border-slate-800">
                        <tr>
                            <th class="px-4 py-3">Assessment Name</th>
                            <th class="px-4 py-3">Author</th>
                            <th class="px-4 py-3 text-center">Sections</th>
                            <th class="px-4 py-3 text-center">Questions</th>
                            <th class="px-4 py-3">Duration</th>
                            <th class="px-4 py-3">Publication Status</th>
                            <th class="px-4 py-3">Published Timestamp</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
                        @foreach($publishedAssessments as $test)
                        @php
                            $sectionCount = $test->sections->count();
                            $questionCount = $test->sections->sum(fn($s) => $s->testQuestions->count());
                        @endphp
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/30 transition-colors">
                            <td class="px-4 py-3.5">
                                <div class="font-bold text-slate-900 dark:text-white text-xs">{{ $test->title }}</div>
                                <div class="text-[10px] text-indigo-600 dark:text-indigo-400 mt-0.5 uppercase font-bold">
                                    {{ is_object($test->test_type) ? $test->test_type->value : $test->test_type }}
                                </div>
                            </td>
                            <td class="px-4 py-3.5 text-slate-700 dark:text-slate-300">
                                {{ $test->creator?->name ?? 'Institutional System' }}
                            </td>
                            <td class="px-4 py-3.5 text-center font-bold text-slate-800 dark:text-slate-200">
                                {{ $sectionCount }}
                            </td>
                            <td class="px-4 py-3.5 text-center font-bold text-slate-800 dark:text-slate-200">
                                {{ $questionCount }}
                            </td>
                            <td class="px-4 py-3.5 text-slate-600 dark:text-slate-400">
                                ⏱ {{ $test->duration_minutes ?? 0 }} mins
                            </td>
                            <td class="px-4 py-3.5">
                                <span class="px-2.5 py-1 rounded-md text-[10px] font-bold uppercase bg-sky-500/10 text-sky-600 dark:text-sky-400 border border-sky-500/20">
                                    🟢 Published
                                </span>
                            </td>
                            <td class="px-4 py-3.5 text-xs text-slate-500 dark:text-slate-400">
                                {{ $test->updated_at?->diffForHumans() ?? $test->created_at?->diffForHumans() }}
                            </td>
                            <td class="px-4 py-3.5 text-right whitespace-nowrap">
                                <form action="{{ route('admin.publications.assessments.unpublish', $test->id) }}" method="POST" class="inline"
                                      onsubmit="event.preventDefault(); iapConfirm({ title: 'Unpublish Assessment?', message: 'Unpublish \'{{ addslashes($test->title) }}\'? Candidates will lose immediate access.', confirmText: 'Unpublish Assessment', variant: 'warning', form: this });">
                                    @csrf
                                    <button type="submit" class="px-3 py-1.5 bg-amber-600 hover:bg-amber-500 text-white rounded-lg text-xs font-bold transition-colors inline-flex items-center gap-1 shadow-sm">
                                        ⏸ Unpublish
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($publishedAssessments->hasPages())
            <div class="p-4 border-t border-slate-200 dark:border-slate-800">
                {{ $publishedAssessments->appends(['tab' => 'published'])->links() }}
            </div>
            @endif
            @endif
        </div>
    </div>
    @endif

</div>

{{-- RM Create Governed Draft Modal (Reused from Intake) --}}
<div id="assign-draft-modal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden items-center justify-center p-4" onclick="closeAssignDraftModal(event)">
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl max-w-lg w-full p-6 shadow-2xl space-y-4 text-left" onclick="event.stopPropagation()">
        <div class="flex justify-between items-center">
            <h3 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <span>📝</span> Create Governed Draft &amp; Assign Teacher
            </h3>
            <button type="button" onclick="closeAssignDraftModal()" class="text-slate-400 hover:text-slate-600 dark:hover:text-white text-lg">×</button>
        </div>

        <form id="assign-draft-form" method="POST" action="" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-1">Request Title</label>
                <div id="modal-req-title" class="font-bold text-slate-900 dark:text-white text-xs bg-slate-50 dark:bg-slate-950 p-2.5 rounded-xl border border-slate-200 dark:border-slate-800"></div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-1.5">Assign Authoring Teacher <span class="text-rose-500">*</span></label>
                <select name="assigned_teacher_id" required class="w-full bg-white dark:bg-slate-950 border border-slate-300 dark:border-slate-800 text-slate-900 dark:text-white text-xs rounded-xl px-3.5 py-2.5 focus:outline-none focus:border-indigo-500 transition-colors">
                    <option value="">Select an active teacher...</option>
                    @foreach($teachers as $teacher)
                    <option value="{{ $teacher->id }}">{{ $teacher->name }} ({{ $teacher->email }})</option>
                    @endforeach
                </select>
            </div>

            <div class="flex justify-end gap-2 pt-2 border-t border-slate-200 dark:border-slate-800">
                <button type="button" onclick="closeAssignDraftModal()" class="gov-btn-secondary px-4 py-2 text-xs font-bold">Cancel</button>
                <button type="submit" class="gov-btn-primary px-4 py-2 text-xs font-bold shadow-sm">Generate Draft &amp; Assign</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAssignDraftModal(req) {
    const modal = document.getElementById('assign-draft-modal');
    const form = document.getElementById('assign-draft-form');
    const titleEl = document.getElementById('modal-req-title');

    titleEl.textContent = req.title + ' (' + req.test_type.toUpperCase() + ')';
    form.action = `/admin/repository-manager/assessment-requests/${req.id}/create-draft`;

    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeAssignDraftModal(e) {
    const modal = document.getElementById('assign-draft-modal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

window.addEventListener('pageshow', function(event) {
    if (event.persisted) {
        window.location.reload();
    }
});
</script>
@endsection
