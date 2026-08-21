@extends('layouts.admin')

@section('content')
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <span>🛡️</span> Super Admin Content Approval Command Center
            </h1>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">High-level governance summary and authorization command center for institutional assessments, question banks, staff creations, and deletion requests.</p>
        </div>
    </div>

    <!-- Status Alert -->
    @if (session('status'))
        <div class="mb-6 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-400 text-xs font-medium">
            ✅ {{ session('status') }}
        </div>
    @endif
    @if (session('error'))
        <div class="mb-6 p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-600 dark:text-rose-400 text-xs font-medium">
            ⚠️ {{ session('error') }}
        </div>
    @endif

    <!-- Command Center Metrics Summary Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5 mb-8">
        <!-- 1. Pending Assessments -->
        <a href="{{ route('admin.approvals.assessments') }}" class="gov-card p-5 hover:border-amber-500/50 flex items-center justify-between transition-all group no-underline">
            <div>
                <span class="text-xs text-slate-500 dark:text-slate-400 font-bold uppercase tracking-wider block">Pending Assessments</span>
                <span class="text-3xl font-extrabold text-amber-500 dark:text-amber-400 my-1 block">{{ $pendingCount }}</span>
                <span class="text-xs text-amber-600 dark:text-amber-400 group-hover:underline font-semibold flex items-center gap-1">
                    Assessment Queue &rarr;
                </span>
            </div>
            <span class="text-4xl p-3 bg-amber-500/10 rounded-xl border border-amber-500/20">⏳</span>
        </a>

        <!-- 2. Pending Question Banks -->
        <a href="{{ route('admin.approvals.question-banks') }}" class="gov-card p-5 hover:border-indigo-500/50 flex items-center justify-between transition-all group no-underline">
            <div>
                <span class="text-xs text-slate-500 dark:text-slate-400 font-bold uppercase tracking-wider block">Pending Question Banks</span>
                <span class="text-3xl font-extrabold text-indigo-600 dark:text-indigo-400 my-1 block">{{ $pendingQuestionBankCount ?? 0 }}</span>
                <span class="text-xs text-indigo-600 dark:text-indigo-400 group-hover:underline font-semibold flex items-center gap-1">
                    Bank Queue &rarr;
                </span>
            </div>
            <span class="text-4xl p-3 bg-indigo-500/10 rounded-xl border border-indigo-500/20">📂</span>
        </a>

        <!-- 3. Pending Restorations -->
        <a href="{{ route('admin.approvals.question-bank-restorations') }}" class="gov-card p-5 hover:border-indigo-500/50 flex items-center justify-between transition-all group no-underline">
            <div>
                <span class="text-xs text-slate-500 dark:text-slate-400 font-bold uppercase tracking-wider block">Pending Restorations</span>
                <span class="text-3xl font-extrabold text-indigo-600 dark:text-indigo-400 my-1 block">{{ $pendingRestorationCount ?? 0 }}</span>
                <span class="text-xs text-indigo-600 dark:text-indigo-400 group-hover:underline font-semibold flex items-center gap-1">
                    Restoration Queue &rarr;
                </span>
            </div>
            <span class="text-4xl p-3 bg-indigo-500/10 rounded-xl border border-indigo-500/20">♻️</span>
        </a>

        <!-- 4. Pending Bank Archives -->
        <a href="{{ route('admin.approvals.question-bank-archives') }}" class="gov-card p-5 hover:border-amber-500/50 flex items-center justify-between transition-all group no-underline">
            <div>
                <span class="text-xs text-slate-500 dark:text-slate-400 font-bold uppercase tracking-wider block">Pending Bank Archives</span>
                <span class="text-3xl font-extrabold text-amber-600 dark:text-amber-500 my-1 block">{{ $pendingQuestionBankArchiveCount ?? 0 }}</span>
                <span class="text-xs text-amber-600 dark:text-amber-400 group-hover:underline font-semibold flex items-center gap-1">
                    Archive Queue &rarr;
                </span>
            </div>
            <span class="text-4xl p-3 bg-amber-500/10 rounded-xl border border-amber-500/20">📦</span>
        </a>

        <!-- 5. Pending Staff Creations -->
        <a href="{{ route('admin.approvals.staff-creations') }}" class="gov-card p-5 hover:border-purple-500/50 flex items-center justify-between transition-all group no-underline">
            <div>
                <span class="text-xs text-slate-500 dark:text-slate-400 font-bold uppercase tracking-wider block">Pending Staff Creations</span>
                <span class="text-3xl font-extrabold text-purple-600 dark:text-purple-400 my-1 block">{{ $pendingUserCreationCount ?? 0 }}</span>
                <span class="text-xs text-purple-600 dark:text-purple-400 group-hover:underline font-semibold flex items-center gap-1">
                    Staff Queue &rarr;
                </span>
            </div>
            <span class="text-4xl p-3 bg-purple-500/10 rounded-xl border border-purple-500/20">👤</span>
        </a>

        <!-- 6. Pending Deletions -->
        <a href="{{ route('admin.approvals.user-deletions') }}" class="gov-card p-5 hover:border-rose-500/50 flex items-center justify-between transition-all group no-underline">
            <div>
                <span class="text-xs text-slate-500 dark:text-slate-400 font-bold uppercase tracking-wider block">Pending Deletions</span>
                <span class="text-3xl font-extrabold text-rose-600 dark:text-rose-400 my-1 block">{{ $pendingUserDeletionCount ?? 0 }}</span>
                <span class="text-xs text-rose-600 dark:text-rose-400 group-hover:underline font-semibold flex items-center gap-1">
                    Deletion Queue &rarr;
                </span>
            </div>
            <span class="text-4xl p-3 bg-rose-500/10 rounded-xl border border-rose-500/20">📩</span>
        </a>
    </div>

    <!-- Quick Navigation Guidance Banner -->
    <div class="gov-card p-6 shadow-sm">
        <h2 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2 mb-2">
            <span>🛡️</span> Governance Execution Workspaces
        </h2>
        <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed max-w-3xl">
            Select any summary card above to open its dedicated governance approval workspace. Each dedicated queue contains detailed records, full audit trails, and execution controls for Super Admin authorization.
        </p>
    </div>
@endsection

