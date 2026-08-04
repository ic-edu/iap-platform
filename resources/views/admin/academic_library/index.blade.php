@extends('layouts.admin')

@section('title', 'Academic Library — iC.edu Platform')

@push('styles')
<style>
.al-workspace { display: flex; flex-direction: column; gap: 1.5rem; }

.al-hero {
    background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #0f172a 100%);
    border: 1px solid #1e293b;
    border-radius: 1.25rem;
    padding: 1.75rem 2rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1.5rem;
    flex-wrap: wrap;
    position: relative;
    overflow: hidden;
}
.al-hero__title { font-size: 1.5rem; font-weight: 800; color: #fff; margin: 0 0 .3rem; }
.al-hero__sub   { font-size: .85rem; color: #94a3b8; margin: 0; }

.al-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1.1rem;
}

.al-card {
    background: #0f172a;
    border: 1px solid #1e293b;
    border-radius: 1rem;
    padding: 1.25rem;
    text-decoration: none;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    gap: 1rem;
    transition: border-color .2s, transform .15s, box-shadow .2s;
}
.al-card:hover {
    border-color: #6366f1;
    transform: translateY(-2px);
    box-shadow: 0 12px 32px rgba(99,102,241,.15);
}
.al-card__head { display: flex; justify-content: space-between; align-items: flex-start; gap: .5rem; }
.al-card__title { font-size: 1.05rem; font-weight: 800; color: #f1f5f9; }
.al-card__desc  { font-size: .78rem; color: #64748b; margin-top: .25rem; line-height: 1.4; }
.al-card__pct   { font-size: 1.1rem; font-weight: 900; color: #818cf8; }

.al-card__bar-bg { width: 100%; height: 6px; background: #1e293b; border-radius: 99px; overflow: hidden; margin-top: .4rem; }
.al-card__bar-fill { height: 100%; background: linear-gradient(90deg, #6366f1 0%, #34d399 100%); border-radius: 99px; }

.al-card__footer { display: flex; justify-content: space-between; font-size: .72rem; color: #475569; font-weight: 600; }
</style>
@endpush

@section('content')
<div class="al-workspace">

    {{-- Hero Header --}}
    <div class="al-hero">
        <div>
            <h1 class="al-hero__title">📚 Institutional Academic Library</h1>
            <p class="al-hero__sub">Official academic repository structure. Select a category library to explore approved question banks and assets.</p>
        </div>
        <div>
            <a href="{{ route('admin.question-banks.index') }}" class="acl-btn acl-btn--secondary" style="font-size:.85rem;padding:.6rem 1.2rem;background:#1e293b;border:1px solid #334155;color:#e2e8f0;text-decoration:none;border-radius:.6rem;font-weight:700;">
                📂 All Repositories
            </a>
        </div>
    </div>

    {{-- Informational Health Score Card --}}
    <div style="background:#080f1d;border:1px solid #1e293b;border-radius:1rem;padding:1.25rem 1.5rem;display:flex;align-items:center;justify-content:space-between;gap:1.5rem;flex-wrap:wrap;">
        <div>
            <div style="font-size:.7rem;font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:#818cf8;">Content Health Score</div>
            <div style="font-size:2.25rem;font-weight:900;color:#f1f5f9;margin-top:.2rem;">{{ $healthData['score'] }} <span style="font-size:.85rem;color:#475569;">/ 100</span></div>
        </div>
        <div style="display:flex;gap:1.5rem;flex-wrap:wrap;">
            <div>
                <div style="font-size:.7rem;color:#64748b;">Overall Coverage</div>
                <div style="font-size:1.1rem;font-weight:800;color:#cbd5e1;">{{ $healthData['breakdown']['coverage'] }}%</div>
            </div>
            <div>
                <div style="font-size:.7rem;color:#64748b;">Approved Ratio</div>
                <div style="font-size:1.1rem;font-weight:800;color:#34d399;">{{ $healthData['breakdown']['approved_ratio'] }}%</div>
            </div>
            <div>
                <div style="font-size:.7rem;color:#64748b;">Maintenance Recency</div>
                <div style="font-size:1.1rem;font-weight:800;color:#818cf8;">{{ $healthData['breakdown']['recency_score'] }}%</div>
            </div>
        </div>
    </div>

    {{-- Dedicated Library Categories Grid (PART 2) --}}
    <div style="font-size:.95rem;font-weight:800;color:#f1f5f9;">🏛 Academic Library Categories</div>
    
    <div class="al-grid">
        @foreach($coverageReport as $cov)
        <a href="{{ route('admin.academic-library.show', $cov['category']->slug) }}" class="al-card">
            <div>
                <div class="al-card__head">
                    <div>
                        <div class="al-card__title">{{ $cov['category']->icon }} {{ $cov['category']->name }}</div>
                        <div class="al-card__desc">{{ $cov['category']->description }}</div>
                    </div>
                    <div class="al-card__pct">{{ $cov['percentage'] }}%</div>
                </div>
                <div class="al-card__bar-bg">
                    <div class="al-card__bar-fill" style="width: {{ $cov['percentage'] }}%;"></div>
                </div>
            </div>
            <div class="al-card__footer">
                <span>Approved: {{ $cov['approved_questions'] }} questions</span>
                <span>Target: {{ $cov['target'] }}</span>
            </div>
        </a>
        @endforeach
    </div>

</div>
@endsection
