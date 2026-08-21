@extends('layouts.admin')

@section('title', $isRm ? 'Assessment Request Intake Queue — Repository Governance' : 'Assessment Requests — Operational Planning')

@section('content')
<div class="space-y-6 max-w-7xl mx-auto">

    {{-- Header --}}
    <div class="flex justify-between items-center flex-wrap gap-4">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="text-2xl">📋</span>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
                    {{ $isRm ? 'Assessment Request Intake Queue' : 'Assessment Operational Requests' }}
                </h1>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400 max-w-3xl">
                {{ $isRm ? 'Review operational requests from Regular Admins, generate Assessment Drafts, and assign them to Teachers for authoring.' : 'Submit operational assessment needs and program briefs to the Repository Manager for draft creation and teacher assignment.' }}
            </p>
        </div>

        <div>
            @if(!$isRm && Auth::user()?->hasRole('admin'))
            <button type="button" onclick="openCreateRequestModal()" class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs rounded-xl shadow transition-colors inline-flex items-center gap-1.5">
                + Request Assessment
            </button>
            @endif
        </div>
    </div>

    {{-- Status Flash --}}
    @if(session('status'))
    <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-400 text-xs font-bold flex items-center gap-2">
        <span>✅</span> {{ session('status') }}
    </div>
    @endif

    {{-- Requests Table --}}
    <div class="gov-card p-0 overflow-hidden shadow-sm">
        @if($requests->isEmpty())
        <div class="p-12 text-center text-slate-400">
            <div class="text-4xl mb-3">📭</div>
            <div class="text-base font-bold text-slate-800 dark:text-slate-200 mb-1">No Assessment Requests</div>
            <p class="text-xs text-slate-500 max-w-md mx-auto">
                {{ $isRm ? 'There are currently no operational assessment requests in the intake queue.' : 'No assessment requests submitted yet. Click "+ Request Assessment" to submit a program brief to Repository Managers.' }}
            </p>
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
                    @foreach($requests as $req)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/30 transition-colors">
                        <td class="px-4 py-3.5">
                            <div class="font-bold text-slate-900 dark:text-white text-xs mb-0.5">
                                {{ $req->title }}
                            </div>
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
                            @if($req->status === 'draft_created')
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
                        <td class="px-4 py-3.5 text-right">
                            @if($isRm && $req->status === 'pending')
                            <button type="button" onclick='openAssignDraftModal({{ json_encode($req) }})' class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-xs font-bold shadow transition-colors inline-flex items-center gap-1">
                                📝 Create Draft &amp; Assign
                            </button>
                            @elseif($req->test)
                            <a href="{{ route('admin.tests.show', $req->test->id) }}" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-lg text-xs font-bold transition-colors inline-flex items-center gap-1">
                                👁 View Assessment
                            </a>
                            @else
                            <span class="text-slate-400 text-xs">Awaiting RM</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($requests->hasPages())
        <div class="p-4 border-t border-slate-200 dark:border-slate-800">
            {{ $requests->links() }}
        </div>
        @endif
        @endif
    </div>
</div>

{{-- Admin: Create Assessment Request Modal --}}
<div id="create-request-modal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden items-center justify-center p-4" onclick="closeCreateRequestModal(event)">
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl max-w-lg w-full p-6 shadow-2xl space-y-4" onclick="event.stopPropagation()">
        <div class="flex justify-between items-center">
            <h3 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <span>📋</span> Request New Assessment
            </h3>
            <button type="button" onclick="closeCreateRequestModal()" class="text-slate-400 hover:text-slate-600 dark:hover:text-white text-lg">×</button>
        </div>

        <form method="POST" action="{{ route('admin.assessment-requests.store') }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-1.5">Assessment Title / Need <span class="text-rose-500">*</span></label>
                <input type="text" name="title" required placeholder="e.g. TOEIC Listening &amp; Reading for SMK Perhotelan" class="w-full bg-white dark:bg-slate-950 border border-slate-300 dark:border-slate-800 text-slate-900 dark:text-white text-xs rounded-xl px-3.5 py-2.5 focus:outline-none focus:border-indigo-500 transition-colors">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-1.5">Assessment Type <span class="text-rose-500">*</span></label>
                    <select name="test_type" required class="w-full bg-white dark:bg-slate-950 border border-slate-300 dark:border-slate-800 text-slate-900 dark:text-white text-xs rounded-xl px-3.5 py-2.5 focus:outline-none focus:border-indigo-500 transition-colors">
                        <option value="toeic">TOEIC</option>
                        <option value="toefl">TOEFL</option>
                        <option value="ielts">IELTS</option>
                        <option value="general">General</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-1.5">Target Deadline (Optional)</label>
                    <input type="date" name="requested_deadline" class="w-full bg-white dark:bg-slate-950 border border-slate-300 dark:border-slate-800 text-slate-900 dark:text-white text-xs rounded-xl px-3.5 py-2.5 focus:outline-none focus:border-indigo-500 transition-colors">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-1.5">Program / Institutional Context</label>
                <input type="text" name="program_context" placeholder="e.g. SMK Pariwisata &amp; Perhotelan Semester 1 Placement" class="w-full bg-white dark:bg-slate-950 border border-slate-300 dark:border-slate-800 text-slate-900 dark:text-white text-xs rounded-xl px-3.5 py-2.5 focus:outline-none focus:border-indigo-500 transition-colors">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-1.5">Operational Notes &amp; Skill Requirements</label>
                <textarea name="notes" rows="3" placeholder="Describe skill emphasis, sections, target student level..." class="w-full bg-white dark:bg-slate-950 border border-slate-300 dark:border-slate-800 text-slate-900 dark:text-white text-xs rounded-xl px-3.5 py-2.5 focus:outline-none focus:border-indigo-500 transition-colors"></textarea>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="closeCreateRequestModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-xl text-xs font-bold transition-colors">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-bold shadow transition-colors">Submit Request</button>
            </div>
        </form>
    </div>
</div>

{{-- RM: Create Draft & Assign Teacher Modal --}}
<div id="assign-draft-modal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden items-center justify-center p-4" onclick="closeAssignDraftModal(event)">
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl max-w-lg w-full p-6 shadow-2xl space-y-4" onclick="event.stopPropagation()">
        <div class="flex justify-between items-center">
            <h3 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <span>📝</span> Create Draft &amp; Assign to Teacher
            </h3>
            <button type="button" onclick="closeAssignDraftModal()" class="text-slate-400 hover:text-slate-600 dark:hover:text-white text-lg">×</button>
        </div>

        <form id="assign-draft-form" method="POST" action="" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-1.5">Assessment Title <span class="text-rose-500">*</span></label>
                <input type="text" id="modal-assign-title" name="title" required class="w-full bg-white dark:bg-slate-950 border border-slate-300 dark:border-slate-800 text-slate-900 dark:text-white text-xs rounded-xl px-3.5 py-2.5 focus:outline-none focus:border-indigo-500 transition-colors">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-1.5">Test Type <span class="text-rose-500">*</span></label>
                    <select id="modal-assign-type" name="test_type" required class="w-full bg-white dark:bg-slate-950 border border-slate-300 dark:border-slate-800 text-slate-900 dark:text-white text-xs rounded-xl px-3.5 py-2.5 focus:outline-none focus:border-indigo-500 transition-colors">
                        <option value="toeic">TOEIC</option>
                        <option value="toefl">TOEFL</option>
                        <option value="ielts">IELTS</option>
                        <option value="general">General</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-1.5">Assign to Teacher <span class="text-rose-500">*</span></label>
                    <select name="teacher_id" required class="w-full bg-white dark:bg-slate-950 border border-slate-300 dark:border-slate-800 text-slate-900 dark:text-white text-xs rounded-xl px-3.5 py-2.5 focus:outline-none focus:border-indigo-500 transition-colors">
                        <option value="">-- Select Teacher --</option>
                        @foreach($teachers as $t)
                        <option value="{{ $t->id }}">{{ $t->name }} ({{ $t->email }})</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-1.5">Duration (Minutes)</label>
                    <input type="number" name="duration_minutes" value="120" min="1" required class="w-full bg-white dark:bg-slate-950 border border-slate-300 dark:border-slate-800 text-slate-900 dark:text-white text-xs rounded-xl px-3.5 py-2.5 focus:outline-none focus:border-indigo-500 transition-colors">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-1.5">Pass Threshold (Score)</label>
                    <input type="number" name="pass_score" value="700" min="0" required class="w-full bg-white dark:bg-slate-950 border border-slate-300 dark:border-slate-800 text-slate-900 dark:text-white text-xs rounded-xl px-3.5 py-2.5 focus:outline-none focus:border-indigo-500 transition-colors">
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="closeAssignDraftModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-xl text-xs font-bold transition-colors">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold shadow transition-colors">Create Draft &amp; Assign</button>
            </div>
        </form>
    </div>
</div>

<script>
function openCreateRequestModal() {
    const modal = document.getElementById('create-request-modal');
    if (modal) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }
}
function closeCreateRequestModal(e) {
    if (!e || e.target === document.getElementById('create-request-modal')) {
        const modal = document.getElementById('create-request-modal');
        if (modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
    }
}
function openAssignDraftModal(req) {
    const modal = document.getElementById('assign-draft-modal');
    const form = document.getElementById('assign-draft-form');
    if (modal && form) {
        form.action = `/admin/repository-manager/assessment-requests/${req.id}/create-draft`;
        document.getElementById('modal-assign-title').value = req.title || '';
        document.getElementById('modal-assign-type').value = req.test_type || 'toeic';
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }
}
function closeAssignDraftModal(e) {
    if (!e || e.target === document.getElementById('assign-draft-modal')) {
        const modal = document.getElementById('assign-draft-modal');
        if (modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
    }
}
</script>
@endsection
