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

.al-cat-kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
}
@media (max-width: 900px) { .al-cat-kpi-grid { grid-template-columns: repeat(2, 1fr); } }

.al-cat-kpi {
    background: #0f172a;
    border: 1px solid #1e293b;
    border-radius: 1rem;
    padding: 1.1rem 1.25rem;
    display: flex;
    flex-direction: column;
    gap: .25rem;
}
.al-cat-kpi__count { font-size: 1.85rem; font-weight: 900; line-height: 1; }
.al-cat-kpi__label { font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .07em; color: #64748b; }

.al-panel {
    background: #0f172a;
    border: 1px solid #1e293b;
    border-radius: 1rem;
    overflow: hidden;
}
.al-panel__head {
    padding: 1rem 1.25rem;
    border-bottom: 1px solid #1e293b;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
}
.al-panel__title { font-size: .9rem; font-weight: 700; color: #f1f5f9; }

.al-filter-bar {
    padding: .75rem 1.25rem;
    border-bottom: 1px solid #1e293b;
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

.al-table { width: 100%; border-collapse: collapse; font-size: .82rem; }
.al-table th { padding: .7rem 1rem; background: #080f1d; font-size: .67rem; font-weight: 700; text-transform: uppercase; letter-spacing: .07em; color: #64748b; text-align: left; border-bottom: 1px solid #1e293b; }
.al-table td { padding: .85rem 1rem; border-bottom: 1px solid #1e293b; vertical-align: middle; }

/* ── Modal (Viewport limit max-height:80vh; overflow-y:auto;) ── */
.al-modal-bg { position: fixed; inset: 0; background: rgba(2,6,23,.75); backdrop-filter: blur(6px); z-index: 900; display: flex; align-items: center; justify-content: center; padding: 1rem; }
.al-modal { background: #0f172a; border: 1px solid #1e293b; border-radius: 1.25rem; max-width: 520px; max-height: 80vh; overflow-y: auto; width: 100%; padding: 2rem; box-shadow: 0 24px 64px rgba(0,0,0,.6); }
.al-modal__head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
.al-modal__title { font-size: 1.05rem; font-weight: 800; color: #f1f5f9; }
.al-modal__close { color: #475569; font-size: 1.35rem; cursor: pointer; background: none; border: none; line-height: 1; }

.al-form-group { margin-bottom: 1.1rem; }
.al-form-label { display: block; font-size: .78rem; font-weight: 600; color: #94a3b8; margin-bottom: .4rem; }
.al-form-input, .al-form-select, .al-form-textarea { width: 100%; background: #1e293b; border: 1px solid #334155; border-radius: .6rem; color: #e2e8f0; font-size: .85rem; padding: .6rem .9rem; outline: none; box-sizing: border-box; }
.al-form-submit { width: 100%; padding: .75rem; background: #6366f1; color: #fff; font-size: .88rem; font-weight: 700; border: none; border-radius: .65rem; cursor: pointer; }
</style>
@endpush

@section('content')
<div class="al-cat-workspace">

    {{-- Breadcrumb & Back --}}
    <div>
        <a href="{{ route('admin.academic-library.index') }}" style="color:#818cf8;font-size:.82rem;font-weight:700;text-decoration:none;">
            ← Back to Academic Library
        </a>
    </div>

    {{-- Hero Header --}}
    <div class="al-cat-hero">
        <div>
            <h1 class="al-cat-hero__title">{{ $category->icon }} {{ $category->name }}</h1>
            <p class="al-cat-hero__sub">{{ $category->description }}</p>
        </div>
        @if(Auth::user()?->hasRole('teacher'))
        <div>
            <button type="button" onclick="openCreateModal()" class="acl-btn acl-btn--primary" style="font-size:.88rem;padding:.65rem 1.35rem;background:#6366f1;color:#fff;border:none;border-radius:.65rem;font-weight:700;cursor:pointer;">
                ＋ New Question Bank
            </button>
        </div>
        @endif
    </div>

    {{-- Category Metrics Grid --}}
    <div class="al-cat-kpi-grid">
        <div class="al-cat-kpi">
            <div class="al-cat-kpi__count" style="color:#818cf8;">{{ $catTotal }}</div>
            <div class="al-cat-kpi__label">Total Repositories</div>
        </div>
        <div class="al-cat-kpi">
            <div class="al-cat-kpi__count" style="color:#34d399;">{{ $catPublished }}</div>
            <div class="al-cat-kpi__label">Published Repositories</div>
        </div>
        <div class="al-cat-kpi">
            <div class="al-cat-kpi__count" style="color:#94a3b8;">{{ $catDraft }}</div>
            <div class="al-cat-kpi__label">Draft Banks</div>
        </div>
        <div class="al-cat-kpi">
            <div class="al-cat-kpi__count" style="color:#fbbf24;">{{ $catPending }}</div>
            <div class="al-cat-kpi__label">Pending Approval</div>
        </div>
    </div>

    {{-- Repository Table Panel --}}
    <div class="al-panel">
        <div class="al-panel__head">
            <div>
                <span class="al-panel__title">Institutional Question Bank Repository</span>
                <p style="font-size:.75rem;color:#64748b;margin:.2rem 0 0;">
                    Official institutional repositories approved by Super Admin. These repositories belong to iC.edu and serve as reusable academic assets for assessments.
                </p>
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
                    <a href="{{ route('admin.academic-library.show', [$category->slug, 'status' => 'archived']) }}" class="al-fpill {{ request('status') === 'archived' ? 'al-fpill--active' : '' }}">Archived</a>
                </div>

                <button type="submit" style="padding:.4rem .9rem;background:#1e293b;border:1px solid #334155;border-radius:.5rem;color:#94a3b8;font-size:.75rem;font-weight:600;cursor:pointer;">Filter</button>
            </form>
        </div>

        {{-- Listing Table / Empty State --}}
        @if($banks->isEmpty())
        <div style="padding:3.5rem 1.5rem;text-align:center;display:flex;flex-direction:column;align-items:center;gap:.75rem;">
            <div style="font-size:2.75rem;opacity:.5;">📭</div>
            <div style="font-size:1.1rem;font-weight:800;color:#f1f5f9;">No approved Question Banks available.</div>
            <p style="font-size:.85rem;color:#94a3b8;max-width:440px;line-height:1.5;">
                There are no published question banks inside {{ $category->name }} yet. You can create a new Question Bank or request institutional library setup.
            </p>
            <div style="display:flex;gap:.75rem;margin-top:.5rem;">
                <button type="button" onclick="openCreateModal()" style="padding:.6rem 1.25rem;background:#6366f1;color:#fff;border:none;border-radius:.5rem;font-size:.82rem;font-weight:700;cursor:pointer;">
                    ＋ Create New Question Bank
                </button>
                <button type="button" onclick="alert('Request sent to Super Admin to initialize {{ addslashes($category->name) }} institutional library.');" style="padding:.6rem 1.25rem;background:#1e293b;border:1px solid #334155;color:#e2e8f0;border-radius:.5rem;font-size:.82rem;font-weight:700;cursor:pointer;">
                    🏛 Request Institutional Library
                </button>
            </div>
        </div>
        @else
        <div style="overflow-x:auto;">
            <table class="al-table">
                <thead>
                    <tr>
                        <th>Repository Title</th>
                        <th>Workflow Status</th>
                        <th style="text-align:center;">Questions</th>
                        <th>Author</th>
                        <th>Last Updated</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($banks as $bank)
                    @php
                        $st = $bank->status ?? 'draft';
                        $badgeColor = match($st) {
                            'published', 'approved' => '#34d399',
                            'pending_approval'      => '#fbbf24',
                            'rejected'              => '#fb7185',
                            default                 => '#94a3b8',
                        };
                    @endphp
                    <tr>
                        <td>
                            <a href="{{ route('admin.question-banks.show', $bank->id) }}" style="font-weight:700;color:#818cf8;text-decoration:none;display:block;">
                                {{ $bank->title }}
                            </a>
                            <div style="font-size:.7rem;color:#64748b;margin-top:2px;">Version {{ $bank->current_version ?? '1.0' }}</div>
                        </td>
                        <td>
                            <span style="padding:.2rem .65rem;border-radius:99px;font-size:.65rem;font-weight:800;text-transform:uppercase;background:rgba(255,255,255,.05);color:{{ $badgeColor }};border:1px solid {{ $badgeColor }}40;">
                                {{ ucfirst(str_replace('_', ' ', $st)) }}
                            </span>
                        </td>
                        <td style="text-align:center;font-weight:700;color:#f1f5f9;">{{ $bank->questions->count() }}</td>
                        <td style="color:#cbd5e1;">{{ $bank->creator?->name ?? 'Institutional' }}</td>
                        <td style="color:#64748b;font-size:.75rem;">{{ $bank->updated_at?->diffForHumans() }}</td>
                        <td style="text-align:right;">
                            <a href="{{ route('admin.question-banks.show', $bank->id) }}" style="font-size:.75rem;font-weight:700;color:#818cf8;text-decoration:none;padding:.2rem .5rem;background:rgba(99,102,241,.1);border-radius:.35rem;">
                                ✏️ Author →
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div style="padding:1rem 1.25rem;">
            {{ $banks->links() }}
        </div>
        @endif
    </div>

</div>

{{-- New Question Bank Modal --}}
<div id="al-create-modal" class="al-modal-bg" style="display:none;" onclick="closeCreateModal(event)">
    <div class="al-modal" onclick="event.stopPropagation()">
        <div class="al-modal__head">
            <div class="al-modal__title">📂 New Question Bank — {{ $category->name }}</div>
            <button type="button" class="al-modal__close" onclick="closeCreateModal()">×</button>
        </div>
        <form method="POST" action="{{ route('admin.question-banks.store') }}">
            @csrf
            <input type="hidden" name="acl_category_id" value="{{ $category->id }}">
            <div class="al-form-group">
                <label class="al-form-label" for="modal-title">Repository Title <span style="color:#fb7185;">*</span></label>
                <input type="text" id="modal-title" name="title" required class="al-form-input" placeholder="e.g. {{ $category->name }} Core Pool">
            </div>
            <div class="al-form-group">
                <label class="al-form-label" for="modal-test-type">Test Type</label>
                <select id="modal-test-type" name="test_type" class="al-form-select">
                    <option value="{{ $category->test_type }}">{{ strtoupper($category->test_type) }}</option>
                    <option value="general">General Assessment</option>
                </select>
            </div>
            <div class="al-form-group">
                <label class="al-form-label" for="modal-description">Description</label>
                <textarea id="modal-description" name="description" class="al-form-textarea" rows="3" placeholder="Describe the purpose of this question bank repository…"></textarea>
            </div>
            <button type="submit" class="al-form-submit">Create Question Bank</button>
        </form>
    </div>
</div>

@endsection

@push('scripts')
<script>
function openCreateModal() {
    const modal = document.getElementById('al-create-modal');
    if (modal) {
        modal.style.display = 'flex';
        document.getElementById('modal-title')?.focus();
    }
}
function closeCreateModal(e) {
    if (!e || e.target === document.getElementById('al-create-modal')) {
        const modal = document.getElementById('al-create-modal');
        if (modal) modal.style.display = 'none';
    }
}
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeCreateModal();
});
</script>
@endpush
