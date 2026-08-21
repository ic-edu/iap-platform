@extends('layouts.admin')

@section('title', 'Recycle Bin — Super Admin Governance Platform')

@section('content')
<div class="space-y-6">

    {{-- Hero Header --}}
    <div class="gov-hero-purple rounded-2xl p-6 sm:p-7 flex justify-between items-center flex-wrap gap-5">
        <div>
            <div class="text-[11px] font-extrabold uppercase tracking-wider text-purple-600 dark:text-purple-400 mb-1">Super Admin Recovery Vault • Safe Lifecycle</div>
            <h1 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white mb-1">🗑 Recycle Bin</h1>
            <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-400 max-w-2xl">Soft-deleted question banks. Repositories can be restored back to ARCHIVED status at any time.</p>
        </div>
        <div class="flex gap-3 items-center flex-wrap">
            <a href="{{ route('admin.archived-repositories.index') }}" class="gov-btn-white px-4 py-2.5 text-xs font-bold flex items-center gap-1.5">
                📦 Archived Repositories →
            </a>
            <form action="{{ route('admin.recycle-bin.index') }}" method="GET" class="flex gap-2">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search recycle bin..." class="bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white rounded-xl px-3.5 py-2 text-xs min-w-[200px] focus:outline-none focus:border-purple-500 transition-colors">
                <button type="submit" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white text-xs font-bold rounded-xl shadow transition-colors">
                    Search
                </button>
            </form>
        </div>
    </div>

    @if(session('status'))
    <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-400 text-xs font-bold">
        ✅ {{ session('status') }}
    </div>
    @endif

    @if(session('danger'))
    <div class="p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-600 dark:text-rose-400 text-xs font-bold">
        ⚠️ {{ session('danger') }}
    </div>
    @endif

    {{-- Recycle Bin Cards Grid --}}
    @if($trashedBanks->count() > 0)
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        @foreach($trashedBanks as $bank)
        <div class="gov-card flex flex-col justify-between gap-4">
            <div class="space-y-3">
                <div class="flex justify-between items-start gap-3">
                    <div>
                        <h3 class="text-base font-bold text-slate-900 dark:text-white">
                            {{ $bank->title }}
                        </h3>
                        <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                            Deleted {{ $bank->deleted_at?->diffForHumans() }} • Author: {{ $bank->creator?->name ?? 'Teacher Author' }}
                        </div>
                    </div>
                    <span class="gov-badge-recycle shrink-0">
                        RECYCLE BIN
                    </span>
                </div>

                <div class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
                    {{ Str::limit($bank->description, 100) ?: 'No description provided.' }}
                </div>

                <div class="gov-stats-strip flex gap-4 flex-wrap text-xs text-slate-600 dark:text-slate-400">
                    <span>📝 <strong>{{ $bank->questions->count() }}</strong> Questions</span>
                    <span>🏷 <strong>{{ is_object($bank->test_type) ? $bank->test_type->label() : strtoupper($bank->test_type?->value ?? 'General') }}</strong></span>
                </div>
            </div>

            {{-- Actions: View Trashed + Restore from Recycle Bin --}}
            <div class="flex gap-3 items-center border-t border-slate-200 dark:border-slate-800 pt-3.5">
                <a href="{{ route('admin.recycle-bin.show', $bank->id) }}"
                   class="gov-btn-secondary flex-1 text-center py-2 text-xs font-bold">
                    👁 View
                </a>

                <form action="{{ route('admin.recycle-bin.restore', $bank->id) }}" method="POST" class="flex-1"
                      onsubmit="event.preventDefault(); iapConfirm({ title: 'Restore this repository from the Recycle Bin?', message: 'Restore \'{{ addslashes($bank->title) }}\' from the Recycle Bin back to Archived status? Expected destination after restore: ARCHIVED.', confirmText: 'Restore to Archived', variant: 'warning', form: this });">
                    @csrf
                    <button type="submit"
                            class="w-full py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold rounded-xl shadow transition-colors">
                        ↩ Restore
                    </button>
                </form>
            </div>
        </div>
        @endforeach
    </div>

    <div class="mt-4">
        {{ $trashedBanks->links() }}
    </div>
    @else
    <div class="gov-empty-state">
        <div class="text-4xl mb-3">✨</div>
        <h3 class="text-base font-bold text-slate-900 dark:text-white mb-1">Recycle Bin is Empty</h3>
        <p class="text-xs text-slate-500 dark:text-slate-400">No repositories have been moved to the recycle bin.</p>
    </div>
    @endif

</div>
@endsection

