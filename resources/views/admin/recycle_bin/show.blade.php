@extends('layouts.admin')

@section('title', 'View Trashed Repository — Recycle Bin')

@push('styles')
<style>
.rbs-container { display: flex; flex-direction: column; gap: 1.5rem; width: 100%; max-width: 950px; margin: 0 auto; }
.rbs-hero {
    background: linear-gradient(135deg, #0f172a 0%, #31102b 50%, #0f172a 100%);
    border: 1px solid #4c1d95;
    border-radius: 1.25rem;
    padding: 1.75rem 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1.25rem;
}
.rbs-card {
    background: #0f172a;
    border: 1px solid #1e293b;
    border-radius: 1.25rem;
    padding: 1.75rem;
}
.rbs-question-item {
    background: #080f1d;
    border: 1px solid #1e293b;
    border-radius: .75rem;
    padding: 1rem 1.25rem;
    margin-bottom: .75rem;
}
</style>
@endpush

@section('content')
<div class="rbs-container">

    {{-- Breadcrumbs --}}
    <div>
        <a href="{{ route('admin.recycle-bin.index') }}" style="color:#c084fc;font-size:.84rem;font-weight:700;text-decoration:none;display:inline-flex;align-items:center;gap:.3rem;">
            ← Back to Recycle Bin
        </a>
    </div>

    {{-- Hero Header --}}
    <div class="rbs-hero">
        <div>
            <div style="font-size:.72rem;font-weight:800;color:#c084fc;text-transform:uppercase;letter-spacing:.08em;margin-bottom:.3rem;">Recycle Bin • Trashed Repository Inspection</div>
            <h1 style="font-size:1.65rem;font-weight:900;color:#fff;margin:0 0 .3rem;">{{ $questionBank->title }}</h1>
            <div style="font-size:.82rem;color:#cbd5e1;">
                Author: <strong>{{ $questionBank->creator?->name ?? 'Teacher Author' }}</strong> •
                Version v{{ $questionBank->current_version ?? '1.0' }} •
                Deleted {{ $questionBank->deleted_at?->format('F d, Y \a\t H:i') }} ({{ $questionBank->deleted_at?->diffForHumans() }})
            </div>
        </div>
        <div>
            <form action="{{ route('admin.recycle-bin.restore', $questionBank->id) }}" method="POST"
                  onsubmit="event.preventDefault(); iapConfirm({ title: 'Restore this repository from the Recycle Bin?', message: 'Restore \'{{ addslashes($questionBank->title) }}\' from the Recycle Bin back to Archived status? Expected destination after restore: ARCHIVED.', confirmText: 'Restore to Archived', variant: 'warning', form: this });">
                @csrf
                <button type="submit"
                        style="padding:.75rem 1.5rem;background:#059669;color:#fff;border:none;border-radius:.65rem;font-size:.85rem;font-weight:800;cursor:pointer;box-shadow:0 4px 14px rgba(5,150,105,0.4);display:inline-flex;align-items:center;gap:.4rem;">
                    ↩ Restore to Archived
                </button>
            </form>
        </div>
    </div>

    {{-- Repository Details --}}
    <div class="rbs-card">
        <h3 style="font-size:1.1rem;font-weight:800;color:#f8fafc;margin:0 0 1rem;">Repository Overview</h3>
        <p style="font-size:.88rem;color:#94a3b8;line-height:1.5;margin:0 0 1.25rem;">
            {{ $questionBank->description ?: 'No description provided.' }}
        </p>

        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:1rem;background:#080f1d;padding:1rem 1.25rem;border-radius:.75rem;border:1px solid #1e293b;margin-bottom:1.5rem;font-size:.8rem;">
            <div>
                <span style="color:#64748b;display:block;font-size:.72rem;font-weight:700;text-transform:uppercase;">Test Type</span>
                <strong style="color:#f1f5f9;">{{ is_object($questionBank->test_type) ? $questionBank->test_type->label() : strtoupper($questionBank->test_type?->value ?? 'General') }}</strong>
            </div>
            <div>
                <span style="color:#64748b;display:block;font-size:.72rem;font-weight:700;text-transform:uppercase;">Category</span>
                <strong style="color:#f1f5f9;">{{ $questionBank->category?->name ?? 'General' }}</strong>
            </div>
            <div>
                <span style="color:#64748b;display:block;font-size:.72rem;font-weight:700;text-transform:uppercase;">Questions Preserved</span>
                <strong style="color:#34d399;">{{ $questionBank->questions->count() }} Questions</strong>
            </div>
            <div>
                <span style="color:#64748b;display:block;font-size:.72rem;font-weight:700;text-transform:uppercase;">Destination on Restore</span>
                <strong style="color:#fbbf24;">ARCHIVED</strong>
            </div>
        </div>

        {{-- Question List --}}
        <h4 style="font-size:.95rem;font-weight:800;color:#cbd5e1;margin:0 0 .75rem;">Preserved Questions ({{ $questionBank->questions->count() }})</h4>
        @forelse($questionBank->questions as $idx => $q)
        <div class="rbs-question-item">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.5rem;">
                <strong style="font-size:.82rem;color:#818cf8;">#{{ $idx + 1 }} • {{ strtoupper(str_replace('_', ' ', $q->question_type ?? 'Single Choice')) }}</strong>
                <span style="font-size:.74rem;color:#64748b;">{{ $q->points ?? 1 }} Pt(s)</span>
            </div>
            <div style="font-size:.85rem;color:#f1f5f9;margin-bottom:.5rem;">
                {!! nl2br(e($q->prompt)) !!}
            </div>
            @if($q->choices && $q->choices->count() > 0)
            <div style="display:flex;flex-direction:column;gap:.25rem;padding-left:.5rem;">
                @foreach($q->choices as $c)
                <div style="font-size:.78rem;color:{{ $c->is_correct ? '#34d399' : '#94a3b8' }};">
                    {{ $c->label }}. {{ $c->content }} {{ $c->is_correct ? '✓ (Correct)' : '' }}
                </div>
                @endforeach
            </div>
            @endif
        </div>
        @empty
        <div style="padding:1.5rem;text-align:center;color:#64748b;font-size:.82rem;">
            No questions in this repository.
        </div>
        @endforelse
    </div>

</div>
@endsection
