@extends('layouts.admin')

@section('title', 'My Academic Workspace — iC.edu Platform')

@push('styles')
<style>
/* ──────────────────────────────────────────
   MY ACADEMIC WORKSPACE — OWNER VISION BLUEPRINT
────────────────────────────────────────── */
.aw-page { display: flex; flex-direction: column; gap: 1.5rem; }

/* ── Header ── */
.aw-header {
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
.aw-header::before {
    content: '';
    position: absolute;
    inset: 0;
    background: radial-gradient(ellipse at 90% 50%, rgba(99,102,241,.15) 0%, transparent 60%);
    pointer-events: none;
}
.aw-header__title { font-size: 1.5rem; font-weight: 800; color: #fff; margin: 0 0 .3rem; }
.aw-header__sub   { font-size: .85rem; color: #94a3b8; margin: 0; }

/* ── Metrics Summary ── */
.aw-metrics-bar {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
}
@media (max-width: 800px) { .aw-metrics-bar { grid-template-columns: 1fr; } }

.aw-metric-card {
    background: #0f172a;
    border: 1px solid #1e293b;
    border-radius: 1rem;
    padding: 1.15rem 1.25rem;
}
.aw-met-lbl { font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .07em; color: #64748b; }
.aw-met-val { font-size: 1.85rem; font-weight: 900; margin-top: .25rem; line-height: 1; color: #818cf8; }
.aw-met-sub { font-size: .7rem; color: #475569; margin-top: .25rem; }

/* ── Workspace Cards Grid ── */
.aw-cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
    gap: 1.25rem;
}

.aw-card {
    background: #0f172a;
    border: 1px solid #1e293b;
    border-radius: 1.25rem;
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    gap: 1.1rem;
    transition: border-color .2s, transform .15s;
    position: relative;
}
.aw-card:hover { border-color: #6366f1; transform: translateY(-2px); }

.aw-card__head { display: flex; justify-content: space-between; align-items: center; }
.aw-code { font-family: monospace; font-weight: 800; font-size: .75rem; color: #818cf8; background: rgba(99,102,241,.12); padding: .2rem .6rem; border-radius: .4rem; border: 1px solid rgba(99,102,241,.25); }
.aw-badge { display: inline-block; padding: .2rem .65rem; border-radius: 99px; font-size: .65rem; font-weight: 800; text-transform: uppercase; letter-spacing: .05em; border: 1px solid; }
.aw-badge--active { background: rgba(52,211,153,.12); color: #34d399; border-color: rgba(52,211,153,.3); }

.aw-card__title { font-size: 1.15rem; font-weight: 800; color: #f1f5f9; margin: 0 0 .25rem; }
.aw-assigned    { font-size: .72rem; color: #64748b; font-weight: 600; }
.aw-card__desc  { font-size: .78rem; color: #94a3b8; line-height: 1.45; margin-top: .4rem; }

.aw-card__section-label { font-size: .68rem; font-weight: 800; text-transform: uppercase; letter-spacing: .07em; color: #475569; margin-bottom: .4rem; }

.aw-pill-group { display: flex; gap: .35rem; flex-wrap: wrap; }
.aw-pill {
    padding: .22rem .6rem;
    border-radius: .4rem;
    font-size: .7rem;
    font-weight: 700;
    background: #1e293b;
    color: #cbd5e1;
    border: 1px solid #334155;
}

.aw-card-stats {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: .75rem;
    padding-top: .75rem;
    border-top: 1px solid #1e293b;
}
.aw-stat-num { font-size: 1rem; font-weight: 900; color: #34d399; }
.aw-stat-lbl { font-size: .67rem; color: #64748b; text-transform: uppercase; letter-spacing: .05em; }

.aw-open-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    padding: .75rem;
    border-radius: .75rem;
    background: #6366f1;
    color: #fff;
    font-size: .88rem;
    font-weight: 800;
    text-decoration: none;
    transition: background .15s, transform .12s;
    box-sizing: border-box;
}
.aw-open-btn:hover { background: #4f46e5; }

/* ── Modal for Admin ── */
.aw-modal-bg {
    position: fixed; inset: 0;
    background: rgba(2,6,23,.75);
    backdrop-filter: blur(6px);
    z-index: 900;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
}
.aw-modal {
    background: #0f172a;
    border: 1px solid #1e293b;
    border-radius: 1.25rem;
    max-width: 500px;
    width: 100%;
    padding: 2rem;
    box-shadow: 0 24px 64px rgba(0,0,0,.6);
}
.aw-modal__head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
.aw-modal__title { font-size: 1.05rem; font-weight: 800; color: #f1f5f9; }
.aw-modal__close { color: #475569; font-size: 1.35rem; cursor: pointer; background: none; border: none; line-height: 1; }
.aw-form-group { margin-bottom: 1.1rem; }
.aw-form-label { display: block; font-size: .78rem; font-weight: 600; color: #94a3b8; margin-bottom: .4rem; }
.aw-form-input, .aw-form-select, .aw-form-textarea {
    width: 100%; background: #1e293b; border: 1px solid #334155;
    border-radius: .6rem; color: #e2e8f0; font-size: .85rem;
    padding: .6rem .9rem; outline: none; transition: border-color .15s; box-sizing: border-box;
}
.aw-form-submit {
    width: 100%; padding: .75rem; background: #6366f1; color: #fff;
    font-size: .88rem; font-weight: 700; border: none; border-radius: .65rem;
    cursor: pointer; transition: background .15s; margin-top: .5rem;
}
</style>
@endpush

@section('content')
<div class="aw-page">

    {{-- Status Flash Alert --}}
    @if(session('status'))
    <div style="padding:.85rem 1.1rem;border-radius:.75rem;background:rgba(52,211,153,.08);border:1px solid rgba(52,211,153,.2);color:#34d399;font-size:.82rem;font-weight:600;">
        ✅ {{ session('status') === 'academic-course-created' ? 'Master Course created successfully.' : session('status') }}
    </div>
    @endif

    {{-- Header --}}
    <div class="aw-header">
        <div>
            <h1 class="aw-header__title">My Academic Workspace</h1>
            <p class="aw-header__sub">Your assigned academic libraries and assessment resources.</p>
        </div>
        @if(!Auth::user()?->hasRole('teacher'))
        <button type="button" onclick="openMasterCourseModal()" style="padding:.65rem 1.35rem;background:#6366f1;color:#fff;border:none;border-radius:.65rem;font-size:.85rem;font-weight:700;cursor:pointer;">
            ＋ Create Master Course
        </button>
        @endif
    </div>

    {{-- Summary Metrics --}}
    <div class="aw-metrics-bar">
        <div class="aw-metric-card">
            <div class="aw-met-lbl">Assigned Programs</div>
            <div class="aw-met-val">{{ $courses->total() }}</div>
            <div class="aw-met-sub">Institutional Master Courses</div>
        </div>
        <div class="aw-metric-card">
            <div class="aw-met-lbl">Academic Coverage</div>
            <div class="aw-met-val" style="color:#818cf8;">{{ $healthData['breakdown']['coverage'] }}%</div>
            <div class="aw-met-sub">Target question completion</div>
        </div>
        <div class="aw-metric-card">
            <div class="aw-met-lbl">Content Health Score</div>
            <div class="aw-met-val" style="color:#34d399;">{{ $healthData['score'] }}/100</div>
            <div class="aw-met-sub">Grade {{ $healthData['grade'] }} Repository Quality</div>
        </div>
    </div>

    {{-- Workspace Cards Grid --}}
    <div class="aw-cards-grid">
        @foreach($courses as $course)
        @php
            $codeUpper = strtoupper($course->code ?? '');
            $titleUpper = strtoupper($course->title ?? '');
            $isToefl = str_contains($codeUpper, 'TOEFL') || str_contains($titleUpper, 'TOEFL');
            $isToeic = str_contains($codeUpper, 'TOEIC') || str_contains($titleUpper, 'TOEIC');
            $isIelts = str_contains($codeUpper, 'IELTS') || str_contains($titleUpper, 'IELTS');
        @endphp
        <div class="aw-card">
            <div>
                <div class="aw-card__head">
                    <span class="aw-code">{{ $course->code }}</span>
                    <span class="aw-badge aw-badge--active">ACTIVE</span>
                </div>
                <h3 class="aw-card__title" style="margin-top:.75rem;">{{ $course->title }}</h3>
                <div class="aw-assigned">Assigned by: Academic Administration</div>
                <p class="aw-card__desc">{{ Str::limit($course->description ?? 'Institutional curriculum program assigned for authoring and content maintenance.', 100) }}</p>

                <div style="margin-top:1rem;">
                    <div class="aw-card__section-label">Libraries &amp; Blueprint</div>
                    <div class="aw-pill-group">
                        @if($isToefl)
                            <span class="aw-pill">📖 Reading</span>
                            <span class="aw-pill">🎧 Listening</span>
                            <span class="aw-pill">✍️ Structure</span>
                            <span class="aw-pill">🖼 Media</span>
                            <span class="aw-pill">📋 Blueprint</span>
                        @elseif($isToeic)
                            <span class="aw-pill">🖼 Part 1-4 Listening</span>
                            <span class="aw-pill">📝 Part 5-7 Reading</span>
                            <span class="aw-pill">🖼 Media</span>
                            <span class="aw-pill">📋 Blueprint</span>
                        @elseif($isIelts)
                            <span class="aw-pill">🎧 Listening</span>
                            <span class="aw-pill">📚 Reading</span>
                            <span class="aw-pill">✍️ Writing</span>
                            <span class="aw-pill">🎙 Speaking</span>
                            <span class="aw-pill">📋 Blueprint</span>
                        @else
                            <span class="aw-pill">📊 Core Questions</span>
                            <span class="aw-pill">🧩 Grammar</span>
                            <span class="aw-pill">🔤 Vocabulary</span>
                            <span class="aw-pill">🖼 Media</span>
                            <span class="aw-pill">📋 Blueprint</span>
                        @endif
                    </div>
                </div>
            </div>

            <div>
                <div class="aw-card-stats">
                    <div>
                        <div class="aw-stat-num">{{ $healthData['breakdown']['coverage'] }}%</div>
                        <div class="aw-stat-lbl">Coverage</div>
                    </div>
                    <div>
                        <div class="aw-stat-num" style="color:#818cf8;">{{ $healthData['score'] }}/100</div>
                        <div class="aw-stat-lbl">Health Score</div>
                    </div>
                </div>

                <a href="{{ route('admin.academic.workspace', $course->id) }}" class="aw-open-btn" style="margin-top:1rem;">
                    [ Open Workspace ] →
                </a>
            </div>
        </div>
        @endforeach
    </div>

</div>

{{-- Admin Modal --}}
@if(!Auth::user()?->hasRole('teacher'))
<div id="create-master-course-modal" class="aw-modal-bg" style="display:none;" onclick="closeMasterCourseModal(event)">
    <div class="aw-modal" onclick="event.stopPropagation()">
        <div class="aw-modal__head">
            <div class="aw-modal__title">🎓 Create Master Course</div>
            <button type="button" class="aw-modal__close" onclick="closeMasterCourseModal()">×</button>
        </div>
        <form action="{{ route('admin.academic.courses.store') }}" method="POST">
            @csrf
            <div class="aw-form-group">
                <label class="aw-form-label">Course Code <span style="color:#fb7185;">*</span></label>
                <input type="text" name="code" required class="aw-form-input" style="text-transform:uppercase;" placeholder="e.g. TOEFL-INT-101">
            </div>
            <div class="aw-form-group">
                <label class="aw-form-label">Course Title <span style="color:#fb7185;">*</span></label>
                <input type="text" name="title" required class="aw-form-input" placeholder="e.g. TOEFL iBT Intensive Master Program">
            </div>
            <div class="aw-form-group">
                <label class="aw-form-label">Category</label>
                <select name="category_id" class="aw-form-select">
                    <option value="">— General Category —</option>
                    @foreach($categories as $cat)
                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="aw-form-group">
                <label class="aw-form-label">Description</label>
                <textarea name="description" class="aw-form-textarea" rows="3" placeholder="Program overview and authoring scope…"></textarea>
            </div>
            <button type="submit" class="aw-form-submit">Save Master Course</button>
        </form>
    </div>
</div>
@endif

@endsection

@push('scripts')
<script>
function openMasterCourseModal() {
    const modal = document.getElementById('create-master-course-modal');
    if (modal) modal.style.display = 'flex';
}
function closeMasterCourseModal(e) {
    if (!e || e.target === document.getElementById('create-master-course-modal')) {
        const modal = document.getElementById('create-master-course-modal');
        if (modal) modal.style.display = 'none';
    }
}
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeMasterCourseModal();
});
</script>
@endpush
