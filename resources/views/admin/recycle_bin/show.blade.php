@extends('layouts.admin')

@section('title', 'View Trashed Repository — Recycle Bin')

@section('content')
<div class="space-y-6 max-w-5xl mx-auto">

    {{-- Breadcrumbs --}}
    <div>
        <a href="{{ route('admin.recycle-bin.index') }}" class="text-purple-600 dark:text-purple-400 text-xs font-bold hover:underline inline-flex items-center gap-1.5">
            ← Back to Recycle Bin
        </a>
    </div>

    {{-- Hero Header --}}
    <div class="gov-hero-purple rounded-2xl p-6 sm:p-7 flex justify-between items-center flex-wrap gap-5">
        <div>
            <div class="text-[11px] font-extrabold uppercase tracking-wider text-purple-600 dark:text-purple-400 mb-1">Recycle Bin • Trashed Repository Inspection</div>
            <h1 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white mb-1">{{ $questionBank->title }}</h1>
            <div class="text-xs text-slate-600 dark:text-slate-400">
                Author: <strong class="text-slate-800 dark:text-slate-200">{{ $questionBank->creator?->name ?? 'Teacher Author' }}</strong> •
                Version v{{ $questionBank->current_version ?? '1.0' }} •
                Deleted {{ $questionBank->deleted_at?->format('F d, Y \a\t H:i') }} ({{ $questionBank->deleted_at?->diffForHumans() }})
            </div>
        </div>
        <div>
            <form action="{{ route('admin.recycle-bin.restore', $questionBank->id) }}" method="POST"
                  onsubmit="event.preventDefault(); iapConfirm({ title: 'Restore this repository from the Recycle Bin?', message: 'Restore \'{{ addslashes($questionBank->title) }}\' from the Recycle Bin back to Archived status? Expected destination after restore: ARCHIVED.', confirmText: 'Restore to Archived', variant: 'warning', form: this });">
                @csrf
                <button type="submit"
                        class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold rounded-xl shadow transition-colors inline-flex items-center gap-1.5">
                    ↩ Restore to Archived
                </button>
            </form>
        </div>
    </div>

    {{-- Repository Details --}}
    <div class="gov-card space-y-5">
        <div>
            <h3 class="text-base font-bold text-slate-900 dark:text-white mb-2">Repository Overview</h3>
            <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
                {{ $questionBank->description ?: 'No description provided.' }}
            </p>
        </div>

        <div class="gov-stats-strip grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs text-slate-600 dark:text-slate-400">
            <div>
                <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Test Type</span>
                <strong class="text-slate-900 dark:text-white mt-0.5 block">{{ is_object($questionBank->test_type) ? $questionBank->test_type->label() : strtoupper($questionBank->test_type?->value ?? 'General') }}</strong>
            </div>
            <div>
                <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Category</span>
                <strong class="text-slate-900 dark:text-white mt-0.5 block">{{ $questionBank->category?->name ?? 'General' }}</strong>
            </div>
            <div>
                <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Questions Preserved</span>
                <strong class="text-emerald-600 dark:text-emerald-400 mt-0.5 block">{{ $questionBank->questions->count() }} Questions</strong>
            </div>
            <div>
                <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Destination on Restore</span>
                <strong class="text-amber-600 dark:text-amber-400 mt-0.5 block">ARCHIVED</strong>
            </div>
        </div>

        {{-- Question List --}}
        <div>
            <h4 class="text-sm font-bold text-slate-800 dark:text-slate-200 mb-3">Preserved Questions ({{ $questionBank->questions->count() }})</h4>
            <div class="space-y-3">
                @forelse($questionBank->questions as $idx => $q)
                <div class="bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl p-4 space-y-2">
                    <div class="flex justify-between items-center">
                        <strong class="text-xs text-indigo-600 dark:text-indigo-400">#{{ $idx + 1 }} • {{ strtoupper(str_replace('_', ' ', $q->question_type ?? 'Single Choice')) }}</strong>
                        <span class="text-[11px] text-slate-500 dark:text-slate-400">{{ $q->points ?? 1 }} Pt(s)</span>
                    </div>
                    <div class="text-xs text-slate-900 dark:text-white leading-relaxed">
                        {!! nl2br(e($q->prompt)) !!}
                    </div>
                    @if($q->choices && $q->choices->count() > 0)
                    <div class="space-y-1 pl-2 pt-1">
                        @foreach($q->choices as $c)
                        <div class="text-xs {{ $c->is_correct ? 'text-emerald-600 dark:text-emerald-400 font-bold' : 'text-slate-500 dark:text-slate-400' }}">
                            {{ $c->label }}. {{ $c->content }} {{ $c->is_correct ? '✓ (Correct)' : '' }}
                        </div>
                        @endforeach
                    </div>
                    @endif
                </div>
                @empty
                <div class="p-6 text-center text-xs text-slate-400">
                    No questions in this repository.
                </div>
                @endforelse
            </div>
        </div>
    </div>

</div>
@endsection

