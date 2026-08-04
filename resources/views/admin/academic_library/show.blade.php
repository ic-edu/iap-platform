@extends('layouts.admin')

@section('title', $category->name . ' — Academic Library')

@push('styles')
<style>
.al-cat-workspace { display: flex; flex-direction: column; gap: 1.5rem; }

.al-cat-hero {
    background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #0f172a 100%);
    border: 1px solid #1e293b;
    border-radius: 1.25rem;
    padding: 1.75rem 2rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1.5rem;
    flex-wrap: wrap;
}
.al-cat-hero__title { font-size: 1.5rem; font-weight: 800; color: #fff; margin: 0 0 .3rem; }
.al-cat-hero__sub   { font-size: .85rem; color: #94a3b8; margin: 0; }

.al-repo-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 1.1rem;
}

.al-repo-card {
    background: #0f172a;
    border: 1px solid #1e293b;
    border-radius: 1rem;
    padding: 1.35rem;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    gap: 1.1rem;
    transition: border-color .2s, box-shadow .2s;
}
.al-repo-card:hover {
    border-color: #6366f1;
    box-shadow: 0 12px 32px rgba(99,102,241,.12);
}

.al-repo-card__head { display: flex; justify-content: space-between; align-items: flex-start; gap: .75rem; }
.al-repo-card__title { font-size: 1.05rem; font-weight: 800; color: #f1f5f9; text-decoration: none; }
.al-repo-card__title:hover { color: #818cf8; }

.al-repo-meta { display: grid; grid-template-columns: repeat(2, 1fr); gap: .75rem; background: #080f1d; padding: .75rem 1rem; border-radius: .65rem; border: 1px solid #1e293b; font-size: .78rem; }
.al-repo-meta__item { display: flex; flex-direction: column; gap: 2px; }
.al-repo-meta__label { font-size: .65rem; color: #64748b; font-weight: 700; text-transform: uppercase; }
.al-repo-meta__val { font-weight: 700; color: #e2e8f0; }

.al-filter-bar {
    padding: .75rem 1.25rem;
    background: #0f172a;
    border: 1px solid #1e293b;
    border-radius: 1rem;
    display: flex;
    gap: .65rem;
    align-items: center;
    flex-wrap: wrap;
}
.al-search {
    flex: 1;
    min-width: 180px;
    background: #1e293b;
    border: 1px solid #334155;
    border-radius: .5rem;
    color: #e2e8f0;
    font-size: .8rem;
    padding: .45rem .85rem;
    outline: none;
}
.al-filter-pills { display: flex; gap: .4rem; flex-wrap: wrap; }
.al-fpill {
    padding: .28rem .75rem;
    border-radius: 99px;
    font-size: .68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .05em;
    border: 1px solid #334155;
    color: #94a3b8;
    background: #1e293b;
    text-decoration: none;
}
.al-fpill:hover, .al-fpill--active { background: #6366f1; color: #fff; border-color: #6366f1; }
</style>
@endpush

@section('content')
<div class="al-cat-workspace">

    {{-- Breadcrumbs --}}
    <div style="font-size:.82rem;color:#64748b;">
        <a href="{{ route('admin.academic-library.index') }}" style="color:#818cf8;font-weight:700;text-decoration:none;">
            Academic Library
        </a>
        <span style="margin: 0 .4rem;">→</span>
        <span style="color:#e2e8f0;font-weight:700;">{{ $category->name }}</span>
    </div>

    {{-- Hero Header --}}
    <div class="al-cat-hero">
        <div>
            <h1 class="al-cat-hero__title">{{ $category->icon }} {{ $category->name }}</h1>
            <p class="al-cat-hero__sub">{{ $category->description }} — Institutional Category Repository</p>
        </div>
        <div>
            <a href="{{ route('admin.question-banks.index', ['category_id' => $category->id]) }}" style="font-size:.85rem;padding:.65rem 1.35rem;background:#6366f1;color:#fff;border:none;border-radius:.65rem;font-weight:700;text-decoration:none;display:inline-block;">
                ✏️ Authoring Workspace
            </a>
        </div>
    </div>

    {{-- Filter Bar --}}
    <div class="al-filter-bar">
        <form method="GET" action="{{ route('admin.academic-library.show', $category->slug) }}" style="display:flex;gap:.65rem;flex:1;flex-wrap:wrap;align-items:center;">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search repositories in {{ $category->name }}…" class="al-search">
            
            <div class="al-filter-pills">
                <a href="{{ route('admin.academic-library.show', $category->slug) }}" class="al-fpill {{ !request('status') ? 'al-fpill--active' : '' }}">All</a>
                <a href="{{ route('admin.academic-library.show', [$category->slug, 'status' => 'published']) }}" class="al-fpill {{ request('status') === 'published' ? 'al-fpill--active' : '' }}">Published</a>
                <a href="{{ route('admin.academic-library.show', [$category->slug, 'status' => 'draft']) }}" class="al-fpill {{ request('status') === 'draft' ? 'al-fpill--active' : '' }}">Draft</a>
                <a href="{{ route('admin.academic-library.show', [$category->slug, 'status' => 'pending_approval']) }}" class="al-fpill {{ request('status') === 'pending_approval' ? 'al-fpill--active' : '' }}">Pending</a>
                <a href="{{ route('admin.academic-library.show', [$category->slug, 'status' => 'rejected']) }}" class="al-fpill {{ request('status') === 'rejected' ? 'al-fpill--active' : '' }}">Needs Revision</a>
            </div>

            <button type="submit" style="padding:.45rem .9rem;background:#1e293b;border:1px solid #334155;border-radius:.5rem;color:#94a3b8;font-size:.75rem;font-weight:600;cursor:pointer;">Filter</button>
        </form>
    </div>

    {{-- Repository Cards Grid (PAGE 2) --}}
    @if($banks->isEmpty())
    <div style="background:#0f172a;border:1px solid #1e293b;border-radius:1rem;padding:3.5rem 1.5rem;text-align:center;display:flex;flex-direction:column;align-items:center;gap:.75rem;">
        <div style="font-size:2.75rem;opacity:.5;">📭</div>
        <div style="font-size:1.15rem;font-weight:800;color:#f1f5f9;">No approved Question Banks available.</div>
        <p style="font-size:.85rem;color:#94a3b8;max-width:440px;line-height:1.5;">
            There are no published question banks inside {{ $category->name }} yet.
        </p>
        <div style="display:flex;gap:.75rem;margin-top:.5rem;">
            <a href="{{ route('admin.question-banks.index') }}" style="padding:.6rem 1.25rem;background:#6366f1;color:#fff;border-radius:.5rem;font-size:.82rem;font-weight:700;text-decoration:none;">
                ＋ Create Question Bank
            </a>
            <button type="button" onclick="alert('Request sent to Super Admin to initialize {{ addslashes($category->name) }} institutional library.');" style="padding:.6rem 1.25rem;background:#1e293b;border:1px solid #334155;color:#e2e8f0;border-radius:.5rem;font-size:.82rem;font-weight:700;cursor:pointer;">
                🏛 Request Institutional Library
            </button>
        </div>
    </div>
    @else
    <div class="al-repo-grid">
        @foreach($banks as $bank)
        @php
            $st = is_object($bank->status) ? $bank->status->value : (string) ($bank->status ?? 'draft');
            $badgeColor = match($st) {
                'published', 'approved' => '#34d399',
                'pending_approval'      => '#fbbf24',
                'rejected'              => '#fb7185',
                default                 => '#94a3b8',
            };
        @endphp
        <div class="al-repo-card">
            <div>
                <div class="al-repo-card__head">
                    <a href="{{ route('admin.question-banks.show', $bank->id) }}" class="al-repo-card__title">
                        {{ $bank->title }}
                    </a>
                    <span style="padding:.2rem .6rem;border-radius:99px;font-size:.65rem;font-weight:800;text-transform:uppercase;background:rgba(255,255,255,.05);color:{{ $badgeColor }};border:1px solid {{ $badgeColor }}40;white-space:nowrap;">
                        {{ ucfirst(str_replace('_', ' ', $st)) }}
                    </span>
                </div>
                <div style="font-size:.78rem;color:#64748b;margin-top:.35rem;line-height:1.4;">
                    {{ Str::limit($bank->description, 90) ?: 'Institutional question bank repository asset.' }}
                </div>
            </div>

            <div class="al-repo-meta">
                <div class="al-repo-meta__item">
                    <span class="al-repo-meta__label">Question Count</span>
                    <span class="al-repo-meta__val">{{ $bank->questions->count() }} items</span>
                </div>
                <div class="al-repo-meta__item">
                    <span class="al-repo-meta__label">Version</span>
                    <span class="al-repo-meta__val">v{{ $bank->current_version ?? '1.0' }}</span>
                </div>
                <div class="al-repo-meta__item">
                    <span class="al-repo-meta__label">Owner</span>
                    <span class="al-repo-meta__val">{{ $bank->creator?->name ?? 'Institutional' }}</span>
                </div>
                <div class="al-repo-meta__item">
                    <span class="al-repo-meta__label">Last Updated</span>
                    <span class="al-repo-meta__val">{{ $bank->updated_at?->diffForHumans() }}</span>
                </div>
            </div>

            <a href="{{ route('admin.question-banks.show', $bank->id) }}" style="display:block;text-align:center;padding:.6rem;background:#1e293b;border:1px solid #334155;border-radius:.6rem;color:#818cf8;font-size:.8rem;font-weight:700;text-decoration:none;transition:background .15s;">
                Open Repository →
            </a>
        </div>
        @endforeach
    </div>

    <div style="margin-top:1rem;">
        {{ $banks->links() }}
    </div>
    @endif

</div>
@endsection
