@extends('layouts.admin')

@section('title', 'Institutional Academic Library — iC.edu Platform')

@push('styles')
<style>
.al-workspace { display: flex; flex-direction: column; gap: 1.75rem; }

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
}
.al-hero__title { font-size: 1.5rem; font-weight: 800; color: #fff; margin: 0 0 .3rem; }
.al-hero__sub   { font-size: .85rem; color: #94a3b8; margin: 0; }

.al-health-panel {
    background: #080f1d;
    border: 1px solid #1e293b;
    border-radius: 1rem;
    padding: 1.35rem 1.75rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1.5rem;
    flex-wrap: wrap;
}

.al-section-title {
    font-size: 1.05rem;
    font-weight: 800;
    color: #f1f5f9;
    margin-bottom: .85rem;
    display: flex;
    align-items: center;
    gap: .5rem;
}

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
.al-card__title { font-size: 1rem; font-weight: 800; color: #f1f5f9; }
.al-card__desc  { font-size: .75rem; color: #64748b; margin-top: .2rem; line-height: 1.35; }
.al-card__pct   { font-size: 1.15rem; font-weight: 900; color: #818cf8; }

.al-card__bar-bg { width: 100%; height: 6px; background: #1e293b; border-radius: 99px; overflow: hidden; margin-top: .4rem; }
.al-card__bar-fill { height: 100%; background: linear-gradient(90deg, #6366f1 0%, #34d399 100%); border-radius: 99px; }

.al-card__footer { display: flex; justify-content: space-between; font-size: .72rem; color: #475569; font-weight: 600; }

/* Modal limits max-height:80vh; overflow-y:auto; */
.al-modal-bg { position: fixed; inset: 0; background: rgba(2,6,23,.75); backdrop-filter: blur(6px); z-index: 900; display: flex; align-items: center; justify-content: center; padding: 1rem; }
.al-modal { background: #0f172a; border: 1px solid #1e293b; border-radius: 1.25rem; max-width: 480px; max-height: 80vh; overflow-y: auto; width: 100%; padding: 2rem; box-shadow: 0 24px 64px rgba(0,0,0,.6); text-align: center; }
</style>
@endpush

@section('content')
<div class="al-workspace">

    @if(Auth::user()?->hasRole('repository-manager'))
    <div>
        <a href="{{ route('admin.repository-manager.dashboard') }}" onclick="if (document.referrer && document.referrer !== window.location.href) { history.back(); return false; }" style="color:#818cf8;font-size:.8rem;font-weight:700;text-decoration:none;display:inline-block;margin-bottom:.5rem;">
            ← Back
        </a>
    </div>
    @endif

    {{-- Hero Header --}}
    <div class="al-hero">
        <div>
            <h1 class="al-hero__title">🏛 Institutional Academic Library</h1>
            <p class="al-hero__sub">Institutional repository overview, coverage tracking, and academic library navigation.</p>
        </div>
        <div style="display:flex;gap:.65rem;flex-wrap:wrap;">
            @unless(Auth::user()->hasRole('teacher'))
            <a href="{{ route('admin.academic-library.quality') }}" class="acl-btn" style="font-size:.85rem;padding:.65rem 1.25rem;background:#1e1b4b;border:1px solid #6366f140;color:#818cf8;text-decoration:none;border-radius:.6rem;font-weight:700;">
                🛡 IRQA Quality Audit
            </a>
            @endunless
            <a href="{{ route('admin.question-banks.index') }}" class="acl-btn" style="font-size:.85rem;padding:.65rem 1.25rem;background:#1e293b;border:1px solid #334155;color:#e2e8f0;text-decoration:none;border-radius:.6rem;font-weight:700;">
                ✏️ Authoring Workspace
            </a>
        </div>
    </div>

    {{-- Content Health Score Card (Informational Only) --}}
    <div class="al-health-panel">
        <div>
            <div style="font-size:.7rem;font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:#818cf8;">Content Health Score</div>
            <div style="font-size:2.35rem;font-weight:900;color:#f1f5f9;margin-top:.2rem;">
                {{ $healthData['score'] }} <span style="font-size:.85rem;color:#475569;">/ 100</span>
                <span style="font-size:.75rem;padding:.2rem .65rem;border-radius:99px;background:rgba(52,211,153,.15);color:#34d399;border:1px solid rgba(52,211,153,.3);margin-left:.75rem;">Grade {{ $healthData['grade'] }}</span>
            </div>
        </div>
        <div style="display:flex;gap:2rem;flex-wrap:wrap;">
            <div>
                <div style="font-size:.7rem;color:#64748b;font-weight:700;text-transform:uppercase;">Overall Coverage</div>
                <div style="font-size:1.15rem;font-weight:800;color:#cbd5e1;margin-top:2px;">{{ $healthData['breakdown']['coverage'] }}%</div>
            </div>
            <div>
                <div style="font-size:.7rem;color:#64748b;font-weight:700;text-transform:uppercase;">Approved Ratio</div>
                <div style="font-size:1.15rem;font-weight:800;color:#34d399;margin-top:2px;">{{ $healthData['breakdown']['approved_ratio'] }}%</div>
            </div>
            <div>
                <div style="font-size:.7rem;color:#64748b;font-weight:700;text-transform:uppercase;">Maintenance Score</div>
                <div style="font-size:1.15rem;font-weight:800;color:#818cf8;margin-top:2px;">{{ $healthData['breakdown']['recency_score'] }}%</div>
            </div>
        </div>
    </div>

    {{-- Academic Category Grids grouped by Program --}}
    @foreach($groupedCoverage as $groupTitle => $items)
    @if(count($items) > 0)
    <div>
        <div class="al-section-title">
            <span>📚 {{ $groupTitle }} Libraries</span>
        </div>
        <div class="al-grid">
            @foreach($items as $cov)
            @php
                $hasItems = ($cov['approved_questions'] > 0) || ($cov['approved_banks'] > 0);
            @endphp

            @if($hasItems)
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
                    <span>Approved: {{ $cov['approved_questions'] }} items</span>
                    <span>Target: {{ $cov['target'] }}</span>
                </div>
            </a>
            @else
            <div onclick="openNoRepoModal('{{ addslashes($cov['category']->name) }}', '{{ $cov['category']->id }}')" class="al-card" style="cursor:pointer;border-color:rgba(251,113,133,.25);" title="No repository exists yet">
                <div>
                    <div class="al-card__head">
                        <div>
                            <div class="al-card__title">{{ $cov['category']->icon }} {{ $cov['category']->name }}</div>
                            <div class="al-card__desc">{{ $cov['category']->description }}</div>
                        </div>
                        <div class="al-card__pct" style="color:#64748b;">0%</div>
                    </div>
                    <div class="al-card__bar-bg">
                        <div class="al-card__bar-fill" style="width: 0%;"></div>
                    </div>
                </div>
                <div class="al-card__footer">
                    <span style="color:#fb7185;font-weight:700;">No repository exists yet</span>
                    <span>Target: {{ $cov['target'] }}</span>
                </div>
            </div>
            @endif

            @endforeach
        </div>
    </div>
    @endif
    @endforeach

</div>

{{-- Floating Dialog for Zero Repositories --}}
<div id="no-repo-modal" class="al-modal-bg" style="display:none;" onclick="closeNoRepoModal(event)">
    <div class="al-modal" onclick="event.stopPropagation()">
        <div style="font-size:2.75rem;margin-bottom:.5rem;">📭</div>
        <div style="font-size:1.15rem;font-weight:800;color:#f1f5f9;margin-bottom:.5rem;">No repository exists yet.</div>
        <p style="font-size:.85rem;color:#94a3b8;margin-bottom:1.5rem;line-height:1.5;">
            There are currently no approved question bank repositories inside <strong id="no-repo-cat-name" style="color:#818cf8;">Category</strong>.
        </p>
        <div style="display:flex;gap:.75rem;">
            <button type="button" onclick="closeNoRepoModal()" style="flex:1;padding:.75rem;background:#1e293b;border:1px solid #334155;color:#e2e8f0;border-radius:.6rem;font-weight:700;cursor:pointer;">Close</button>
            <a id="no-repo-create-btn" href="{{ route('admin.question-banks.index') }}" style="flex:1.5;padding:.75rem;background:#6366f1;color:#fff;border-radius:.6rem;font-weight:700;text-decoration:none;display:inline-block;">Create Question Bank</a>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
let selectedCatId = null;

function openNoRepoModal(catName, catId) {
    selectedCatId = catId;
    document.getElementById('no-repo-cat-name').innerText = catName;
    const createBtn = document.getElementById('no-repo-create-btn');
    if (createBtn) createBtn.href = "{{ route('admin.question-banks.index') }}?create=1&category_id=" + catId;
    document.getElementById('no-repo-modal').style.display = 'flex';
}

function closeNoRepoModal(e) {
    if (!e || e.target === document.getElementById('no-repo-modal')) {
        document.getElementById('no-repo-modal').style.display = 'none';
    }
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeNoRepoModal();
});
</script>
@endpush
