@extends('layouts.admin')

@section('title', 'Question Banks Approval Queue — Repository Governance')

@section('content')
<div class="space-y-6">

    {{-- Header (Context-aware route destination) --}}
    <div>
        @if(request('from') === 'notifications')
        <a href="{{ route('notifications.index') }}" class="text-indigo-600 dark:text-indigo-400 text-xs font-bold hover:underline inline-flex items-center gap-1">
            ← Back to Notifications
        </a>
        @elseif(request('from_url') && str_starts_with(request('from_url'), '/') && !str_starts_with(request('from_url'), '//') && !str_contains(request('from_url'), '://'))
        <a href="{{ request('from_url') }}" class="text-indigo-600 dark:text-indigo-400 text-xs font-bold hover:underline inline-flex items-center gap-1">
            ← Back
        </a>
        @else
        <a href="{{ route('admin.repository-manager.dashboard') }}" class="text-indigo-600 dark:text-indigo-400 text-xs font-bold hover:underline inline-flex items-center gap-1">
            ← Back
        </a>
        @endif
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white mt-1">
            Question Banks Approval Queue
        </h1>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
            Review and validate new Question Bank repositories before institutional publishing.
        </p>
    </div>

    {{-- Question Banks Table --}}
    <div class="gov-card p-0 overflow-hidden shadow-sm">
        @if($questionBanks->count() > 0)
        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-left text-xs">
                <thead class="bg-slate-50 dark:bg-slate-950 text-[10px] text-slate-500 dark:text-slate-400 uppercase tracking-wider border-b border-slate-200 dark:border-slate-800">
                    <tr>
                        <th class="px-4 py-3">Repository Title</th>
                        <th class="px-4 py-3">Program / Type</th>
                        <th class="px-4 py-3">Questions</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Governance Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
                    @foreach($questionBanks as $bank)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/30 transition-colors">
                        <td class="px-4 py-3.5">
                            <div class="font-bold text-slate-900 dark:text-white text-xs">{{ $bank->title }}</div>
                            <div class="text-[10px] text-slate-400 mt-0.5">ID: {{ $bank->id }}</div>
                        </td>
                        <td class="px-4 py-3.5">
                            <span class="px-2 py-0.5 rounded-md text-[10px] font-bold uppercase bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                                {{ is_object($bank->test_type) ? $bank->test_type->value : $bank->test_type }}
                            </span>
                        </td>
                        <td class="px-4 py-3.5">
                            <span class="font-bold text-slate-800 dark:text-slate-200">{{ $bank->questions_count ?? 15 }} Items</span>
                        </td>
                        <td class="px-4 py-3.5">
                            @if($bank->status === 'approved')
                            <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                                Approved (Restored)
                            </span>
                            @else
                            <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">
                                Awaiting Approval
                            </span>
                            @endif
                        </td>
                        <td class="px-4 py-3.5 text-right">
                            <a href="{{ route('admin.repository-manager.question-bank-validate', $bank->id) }}" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-xs font-bold shadow transition-colors inline-block">
                                Review &amp; Validate →
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div class="p-12 text-center text-slate-400">
            <div class="text-4xl mb-2">✨</div>
            <div class="text-sm font-bold text-slate-800 dark:text-slate-200 mb-1">No Question Banks Awaiting Review</div>
            <div class="text-xs text-slate-500">All submitted institutional repositories have been audited.</div>
        </div>
        @endif
    </div>

</div>
@endsection

