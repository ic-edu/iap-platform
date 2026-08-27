@extends('layouts.admin')

@section('content')
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <span>🛡️</span> Super Admin Content Approval Command Center
            </h1>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">High-level governance summary and authorization command center for institutional assessments, question banks, staff creations, deletion requests, and commercial price change proposals.</p>
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
    @if ($errors->any())
        <div class="mb-6 p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-600 dark:text-rose-400 text-xs font-medium space-y-1">
            <span class="font-bold block">Please resolve the following errors:</span>
            <ul class="list-disc pl-5 space-y-0.5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
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

        <!-- 7. Pending Price Changes -->
        <div class="gov-card p-5 flex items-center justify-between transition-all">
            <div>
                <span class="text-xs text-slate-500 dark:text-slate-400 font-bold uppercase tracking-wider block">Pending Price Changes</span>
                <span class="text-3xl font-extrabold text-amber-500 dark:text-amber-400 my-1 block">{{ $pendingPriceChangeCount ?? 0 }}</span>
                <span class="text-xs text-amber-600 dark:text-amber-400 font-semibold flex items-center gap-1">
                    Commercial Governance &bull; SA Approval
                </span>
            </div>
            <span class="text-4xl p-3 bg-amber-500/10 rounded-xl border border-amber-500/20">🏷️</span>
        </div>
    </div>

    <!-- Commercial Price Change Proposals Section -->
    <div class="gov-card p-6 shadow-sm mb-8">
        <div class="flex items-center justify-between pb-4 mb-4 border-b border-slate-200 dark:border-slate-800">
            <div>
                <h2 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                    <span>🏷️</span> Commercial Price Change Proposals Requiring SA Approval ({{ $pendingPriceChangeCount }})
                </h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Operational Admin price change proposals for institutional assessment packages.</p>
            </div>
        </div>

        @if($pendingPriceChangeRequests->isEmpty())
            <p class="text-xs text-slate-500 dark:text-slate-400 italic py-4">No commercial price change proposals currently pending approval.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-600 dark:text-slate-300">
                    <thead class="bg-slate-50 dark:bg-slate-950 uppercase text-[10px] text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
                        <tr>
                            <th class="p-3">Product / Package</th>
                            <th class="p-3">Family</th>
                            <th class="p-3">Current Price</th>
                            <th class="p-3">Proposed Price</th>
                            <th class="p-3">Requested By</th>
                            <th class="p-3">Reason / Justification</th>
                            <th class="p-3">Date</th>
                            <th class="p-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach($pendingPriceChangeRequests as $req)
                            <tr>
                                <td class="p-3 font-bold text-slate-900 dark:text-white">{{ $req->product?->title }}</td>
                                <td class="p-3 uppercase text-[10px] font-bold text-indigo-600 dark:text-indigo-400">{{ $req->product?->getEffectiveFamily() ?? 'GENERAL' }}</td>
                                <td class="p-3 font-mono text-slate-500">IDR {{ number_format($req->current_price_snapshot) }}</td>
                                <td class="p-3 font-mono font-bold text-emerald-600 dark:text-emerald-400">IDR {{ number_format($req->proposed_price) }}</td>
                                <td class="p-3">{{ $req->requester?->name }} <span class="text-[10px] text-slate-400 block">{{ $req->requester?->email }}</span></td>
                                <td class="p-3 max-w-xs truncate text-slate-600 dark:text-slate-400" title="{{ $req->reason }}">{{ $req->reason }}</td>
                                <td class="p-3 text-slate-400">{{ $req->created_at?->format('d M Y, H:i') }}</td>
                                <td class="p-3 text-right">
                                    <div class="inline-flex items-center gap-2">
                                        <form action="{{ route('admin.approvals.price-changes.approve', $req->id) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs shadow-sm transition-all">
                                                Approve Price Change
                                            </button>
                                        </form>
                                        <button type="button" onclick="openRejectPriceChangeModal('{{ $req->id }}', '{{ addslashes($req->product?->title) }}')" class="px-3 py-1.5 rounded-lg bg-rose-600 hover:bg-rose-500 text-white font-bold text-xs shadow-sm transition-all">
                                            Reject
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
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

    <!-- Reject Price Change Modal -->
    <div id="reject-price-change-modal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm hidden flex items-center justify-center p-4 z-50">
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 max-w-md w-full shadow-2xl">
            <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-2">Reject Price Change Proposal</h3>
            <p id="reject-modal-product-title" class="text-xs text-slate-500 dark:text-slate-400 mb-4"></p>
            <form id="reject-price-change-form" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Rejection Reason *</label>
                    <textarea name="rejection_reason" required rows="3" placeholder="Provide a justification for rejecting this proposal..." class="w-full p-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-900 dark:text-white"></textarea>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" onclick="document.getElementById('reject-price-change-modal').classList.add('hidden')" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-xs font-semibold rounded-xl">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-rose-600 hover:bg-rose-500 text-white text-xs font-bold rounded-xl shadow">Confirm Rejection</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openRejectPriceChangeModal(id, title) {
            const modal = document.getElementById('reject-price-change-modal');
            const form = document.getElementById('reject-price-change-form');
            const titleEl = document.getElementById('reject-modal-product-title');
            if (modal && form) {
                form.action = '/admin/approvals/price-changes/' + id + '/reject';
                if (titleEl) titleEl.textContent = 'Package: ' + title;
                modal.classList.remove('hidden');
            }
        }
    </script>
@endsection
