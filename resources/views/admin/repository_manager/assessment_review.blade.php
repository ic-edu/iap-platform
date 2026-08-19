@extends('layouts.admin')

@section('title', 'Review Assessment — Repository Smart Review Engine')

@push('styles')
<style>
@media (max-width: 1024px) {
    .sticky-governance-sidebar {
        position: static !important;
    }
}
#btn-back-to-top:hover {
    background: #1e1b4b !important;
    border-color: #818cf8 !important;
    transform: translateY(-2px);
    box-shadow: 0 12px 30px -5px rgba(99,102,241,.7) !important;
}
</style>
@endpush

@section('content')
<div style="padding: 1.5rem 0;">
    {{-- Header --}}
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
        <div>
            <h1 style="font-size:1.6rem;font-weight:800;color:#fff;margin:0 0 .3rem;">⚡ Repository Smart Review Engine</h1>
            <p style="font-size:.88rem;color:#94a3b8;margin:0;">Review by Exception: All questions are implicitly <strong>Default OK</strong>. Interact only with questions requiring attention.</p>
        </div>
        <div>
            <a href="{{ route('admin.repository-manager.assessment-approval') }}" onclick="if (document.referrer && document.referrer !== window.location.href) { history.back(); return false; }" style="padding:.6rem 1.1rem;background:#1e293b;border:1px solid #334155;color:#fff;border-radius:.6rem;font-size:.82rem;font-weight:700;text-decoration:none;display:inline-flex;align-items:center;gap:.4rem;">
                ← Back
            </a>
        </div>
    </div>

    {{-- Floating Back to Top Button (TASK 1, 2, 3, 4) --}}
    <button id="btn-back-to-top" type="button" title="Back to Top" onclick="window.scrollTo({top:0, behavior:'smooth'})" style="position:fixed;bottom:1.5rem;right:1.5rem;z-index:9999;width:3rem;height:3rem;border-radius:9999px;background:#0f172a;border:1px solid #6366f1;color:#fff;font-size:1.25rem;font-weight:900;display:flex;align-items:center;justify-content:center;cursor:pointer;box-shadow:0 10px 25px -5px rgba(99,102,241,.5);opacity:0;pointer-events:none;transition:opacity .3s ease, transform .2s ease;">
        ↑
    </button>

    {{-- Toast Notification Container (Positioned above Back to Top button to prevent overlap) --}}
    <div id="smart-review-toast" style="position:fixed;bottom:5.25rem;right:1.5rem;z-index:99999;background:#10b981;color:#fff;padding:.75rem 1.25rem;border-radius:.75rem;font-size:.85rem;font-weight:800;box-shadow:0 20px 25px -5px rgba(0,0,0,.5);display:none;align-items:center;gap:.5rem;transition:opacity .3s ease;">
        ✨ <span id="toast-message">Review saved.</span>
    </div>

    @if(session('success'))
    <div style="background:rgba(52,211,153,.12);border:1px solid rgba(52,211,153,.3);color:#34d399;padding:1rem 1.25rem;border-radius:.75rem;font-size:.88rem;font-weight:700;margin-bottom:1.5rem;">
        ✅ {{ session('success') }}
    </div>
    @endif

    @if(session('warning'))
    <div style="background:rgba(245,158,11,.12);border:1px solid rgba(245,158,11,.3);color:#fbbf24;padding:1rem 1.25rem;border-radius:.75rem;font-size:.88rem;font-weight:700;margin-bottom:1.5rem;">
        ⚠️ {{ session('warning') }}
    </div>
    @endif

    @if(session('error'))
    <div style="background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.3);color:#f87171;padding:1rem 1.25rem;border-radius:.75rem;font-size:.88rem;font-weight:700;margin-bottom:1.5rem;">
        🚫 {{ session('error') }}
    </div>
    @endif

    {{-- Review by Exception Summary Banner --}}
    @if(isset($reviewProgress))
    <div style="background:#0f172a;border:1px solid #1e293b;border-radius:1.25rem;padding:1.35rem;margin-bottom:1.5rem;">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;">
            <div>
                <div style="font-size:.78rem;font-weight:800;color:#818cf8;text-transform:uppercase;letter-spacing:.05em;margin-bottom:.15rem;">
                    ⚡ Review by Exception Mode
                </div>
                <div style="font-size:1.1rem;font-weight:800;color:#fff;">
                    Total Assessment Questions: {{ $reviewProgress['total'] }}
                </div>
            </div>
            <div style="display:flex;gap:.75rem;font-size:.82rem;flex-wrap:wrap;">
                <div style="background:#1e293b;padding:.4rem .75rem;border-radius:.5rem;border:1px solid #334155;color:#34d399;font-weight:700;">
                    🟢 Default OK: <span id="summary-default-ok-count">{{ $reviewProgress['default_ok'] }}</span>
                </div>
                <div style="background:#1e293b;padding:.4rem .75rem;border-radius:.5rem;border:1px solid #334155;color:#fbbf24;font-weight:700;">
                    🟡 Flagged Revisions: <span id="summary-flagged-count">{{ $reviewProgress['flagged'] }}</span>
                </div>
                @if(($reviewProgress['critical'] ?? 0) > 0)
                <div style="background:#1e293b;padding:.4rem .75rem;border-radius:.5rem;border:1px solid #334155;color:#f87171;font-weight:700;">
                    🔴 Critical Blockers: <span id="summary-critical-count">{{ $reviewProgress['critical'] }}</span>
                </div>
                @endif
            </div>
        </div>
    </div>
    @endif

    @if(isset($reviewProgress) && $reviewProgress['total'] === 0)
    <div style="background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.3);color:#f87171;padding:1rem 1.25rem;border-radius:.75rem;font-size:.88rem;font-weight:700;margin-bottom:1.5rem;">
        ⚠️ Incomplete Assessment: Contains 0 questions. This assessment cannot be approved and must be returned/rejected for authoring.
    </div>
    @endif

    <div style="display:grid;grid-template-columns:2fr 1fr;gap:1.5rem;align-items:start;">
        {{-- Left: Assessment Details & Question Cards with Smart Review Panel --}}
        <div style="display:flex;flex-direction:column;gap:1.25rem;">
            <div style="background:#0f172a;border:1px solid #1e293b;border-radius:1.25rem;padding:1.75rem;">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:1rem;">
                    <div>
                        <span style="font-size:.72rem;font-weight:800;color:#818cf8;text-transform:uppercase;letter-spacing:.06em;background:#1e293b;padding:.2rem .65rem;border-radius:.4rem;border:1px solid #334155;">
                            {{ is_object($test->test_type) ? $test->test_type->value : $test->test_type }}
                        </span>
                        <h2 style="font-size:1.4rem;font-weight:900;color:#fff;margin:.5rem 0 .25rem;">{{ $test->title }}</h2>
                        <div style="font-size:.82rem;color:#94a3b8;">Author: <strong style="color:#cbd5e1;">{{ $test->creator?->name ?? 'System' }}</strong> ({{ $test->creator?->email }})</div>
                    </div>
                    <div>
                        @if(in_array($test->status, ['pending', 'pending_approval']))
                            <span style="background:rgba(251,191,36,.12);color:#fbbf24;border:1px solid rgba(251,191,36,.3);padding:.35rem .85rem;border-radius:.5rem;font-size:.8rem;font-weight:800;">
                                ⏳ Pending Review
                            </span>
                        @elseif($test->status === 'approved')
                            <span style="background:rgba(52,211,153,.12);color:#34d399;border:1px solid rgba(52,211,153,.3);padding:.35rem .85rem;border-radius:.5rem;font-size:.8rem;font-weight:800;">
                                ✓ Approved & Active
                            </span>
                        @else
                            <span style="background:rgba(251,113,133,.12);color:#fb7185;border:1px solid rgba(251,113,133,.3);padding:.35rem .85rem;border-radius:.5rem;font-size:.8rem;font-weight:800;">
                                ⚠️ Needs Revision
                            </span>
                        @endif
                    </div>
                </div>

                <div style="display:grid;grid-template-columns:repeat(3, 1fr);gap:1rem;background:#1e293b;padding:1rem;border-radius:.75rem;margin-top:1rem;">
                    <div>
                        <div style="font-size:.7rem;color:#94a3b8;font-weight:700;text-transform:uppercase;">Duration</div>
                        <div style="font-size:1.1rem;font-weight:800;color:#fff;margin-top:.2rem;">⏱ {{ $test->duration_minutes }} Mins</div>
                    </div>
                    <div>
                        <div style="font-size:.7rem;color:#94a3b8;font-weight:700;text-transform:uppercase;">Pass Score</div>
                        <div style="font-size:1.1rem;font-weight:800;color:#fff;margin-top:.2rem;">🎯 {{ $test->pass_score }} pts</div>
                    </div>
                    <div>
                        <div style="font-size:.7rem;color:#94a3b8;font-weight:700;text-transform:uppercase;">Sections</div>
                        <div style="font-size:1.1rem;font-weight:800;color:#fff;margin-top:.2rem;">📚 {{ $test->sections->count() }} Sections</div>
                    </div>
                </div>
            </div>

            {{-- Sections Breakdown & Question Cards --}}
            <div style="background:#0f172a;border:1px solid #1e293b;border-radius:1.25rem;padding:1.75rem;">
                <h3 style="font-size:1.05rem;font-weight:800;color:#fff;margin:0 0 1rem;">Question Inspection & Annotation Workspace</h3>

                @if($test->sections->isEmpty())
                    <div style="color:#64748b;font-size:.85rem;text-align:center;padding:2rem;">No test sections created yet.</div>
                @else
                    @php $qIdxGlobal = 1; @endphp
                    @foreach($test->sections as $sec)
                    <div style="background:#1e293b;border:1px solid #334155;border-radius:.85rem;padding:1.1rem;margin-bottom:1.25rem;">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.75rem;">
                            <div style="font-weight:800;color:#fff;font-size:1rem;">Section {{ $sec->order }}: {{ $sec->title ?? 'Section' }}</div>
                            <span style="font-size:.78rem;color:#a5b4fc;font-weight:700;background:rgba(99,102,241,.15);padding:.2rem .6rem;border-radius:.4rem;">
                                {{ $sec->testQuestions->count() }} Questions
                            </span>
                        </div>

                        @if($sec->testQuestions->isNotEmpty())
                            <div style="display:flex;flex-direction:column;gap:1.25rem;margin-top:1rem;">
                                @foreach($sec->testQuestions as $idx => $tq)
                                    @php
                                        $q = $tq->question;
                                        $qRev = $questionReviews[$q?->id] ?? null;
                                        $isFlagged = $qRev && in_array($qRev->status, ['needs_revision', 'critical_issue']);
                                        $qStatus = $isFlagged ? $qRev->status : 'default_ok';
                                    @endphp
                                    @if($q)
                                    <div id="question-card-{{ $q->id }}" class="question-card" data-question-id="{{ $q->id }}" style="background:#0f172a;border:1px solid {{ $qStatus === 'critical_issue' ? 'rgba(239,68,68,.5)' : ($qStatus === 'needs_revision' ? 'rgba(245,158,11,.5)' : '#334155') }};border-radius:.75rem;padding:1.1rem;transition:border-color .2s ease;">
                                        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:.5rem;margin-bottom:.5rem;">
                                            <div>
                                                <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.35rem;">
                                                    <span style="font-size:.75rem;font-weight:800;color:#cbd5e1;background:#334155;padding:.15rem .55rem;border-radius:.3rem;">
                                                        Q#{{ $qIdxGlobal }}
                                                    </span>
                                                    <span style="font-size:.7rem;color:#94a3b8;background:#1e293b;padding:.15rem .45rem;border-radius:.3rem;text-transform:uppercase;">{{ $q->question_type }}</span>
                                                    @php $diffVal = is_object($q->difficulty) ? $q->difficulty->value : $q->difficulty; @endphp
                                                    <span style="font-size:.7rem;color:#a78bfa;background:rgba(167,139,250,.12);padding:.15rem .45rem;border-radius:.3rem;text-transform:uppercase;">{{ $diffVal ?? 'easy' }}</span>

                                                    {{-- Status Badge (BUSINESS RULE 4: 🟢 Default OK, 🟡 Needs Revision, 🔴 Critical) --}}
                                                    <span id="badge-status-{{ $q->id }}" style="font-size:.72rem;font-weight:800;{{ $qStatus === 'critical_issue' ? 'color:#f87171;background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.3);' : ($qStatus === 'needs_revision' ? 'color:#fbbf24;background:rgba(245,158,11,.15);border:1px solid rgba(245,158,11,.3);' : 'color:#34d399;background:rgba(52,211,153,.15);border:1px solid rgba(52,211,153,.3);') }}padding:.15rem .55rem;border-radius:.3rem;">
                                                        @if($qStatus === 'critical_issue')
                                                            🔴 Critical Issue ({{ ucfirst($qRev->field ?? 'General') }})
                                                        @elseif($qStatus === 'needs_revision')
                                                            🟡 Needs Revision ({{ ucfirst($qRev->field ?? 'General') }})
                                                        @else
                                                            🟢 Default OK
                                                        @endif
                                                    </span>
                                                </div>

                                                <div style="font-weight:700;color:#fff;font-size:.9rem;line-height:1.4;">
                                                    {{ $q->prompt ?? '(Empty Prompt Stem)' }}
                                                </div>
                                            </div>

                                            <div>
                                                <button type="button" id="btn-toggle-flag-{{ $q->id }}" onclick="toggleInlineFlag('{{ $q->id }}')" style="padding:.35rem .75rem;background:{{ $isFlagged ? '#ef4444' : '#1e293b' }};color:#fff;border:1px solid {{ $isFlagged ? '#ef4444' : '#334155' }};border-radius:.4rem;font-size:.75rem;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:.3rem;">
                                                    {{ $isFlagged ? '✖ Flagged (Click to Edit)' : '⚠️ Flag Revision' }}
                                                </button>
                                            </div>
                                        </div>

                                        {{-- Choices Inspection --}}
                                        @if($q->choices && $q->choices->isNotEmpty())
                                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:.5rem;margin-top:.75rem;">
                                                @foreach($q->choices as $cIdx => $choice)
                                                    <div style="font-size:.78rem;padding:.45rem .7rem;background:#1e293b;border:1px solid {{ $choice->is_correct ? '#10b981' : '#334155' }};border-radius:.4rem;color:{{ $choice->is_correct ? '#34d399' : '#cbd5e1' }};font-weight:{{ $choice->is_correct ? '800' : '400' }};">
                                                        {{ chr(65 + $cIdx) }}. {{ $choice->content ?? $choice->choice_text }} {{ $choice->is_correct ? '✓ (Correct)' : '' }}
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif

                                        {{-- Rationale --}}
                                        @if($q->explanation)
                                            <div style="font-size:.78rem;color:#94a3b8;margin-top:.65rem;background:#1e293b;padding:.5rem .75rem;border-radius:.4rem;border-left:3px solid #818cf8;">
                                                <strong>Rationale:</strong> {{ $q->explanation }}
                                            </div>
                                        @endif

                                        {{-- Displayed Feedback Comment if Flagged --}}
                                        <div id="feedback-display-{{ $q->id }}" style="display:{{ $isFlagged && $qRev?->comment ? 'block' : 'none' }};font-size:.78rem;color:{{ $qStatus === 'critical_issue' ? '#f87171' : '#fbbf24' }};margin-top:.65rem;background:rgba(15,23,42,.6);padding:.5rem .75rem;border-radius:.4rem;border:1px solid {{ $qStatus === 'critical_issue' ? 'rgba(239,68,68,.3)' : 'rgba(245,158,11,.3)' }};">
                                            💬 <strong>Reviewer Feedback (<span id="feedback-field-{{ $q->id }}">{{ ucfirst($qRev?->field ?? 'general') }}</span>):</strong> "<span id="feedback-text-{{ $q->id }}">{{ $qRev?->comment }}</span>"
                                        </div>

                                        {{-- Inline Expandable Annotation Panel (BUSINESS RULES 2 & 3 & UX NO RELOAD) --}}
                                        <div id="inline-rev-panel-{{ $q->id }}" style="display:{{ $isFlagged ? 'block' : 'none' }};margin-top:1rem;padding:1rem;background:#1e293b;border:1px solid #334155;border-radius:.65rem;">
                                            <div style="font-size:.78rem;font-weight:800;color:#cbd5e1;margin-bottom:.75rem;text-transform:uppercase;letter-spacing:.05em;display:flex;justify-content:space-between;align-items:center;">
                                                <span>📋 Question Revision Annotation</span>
                                                <button type="button" onclick="clearQuestionFlag('{{ $q->id }}')" style="font-size:.72rem;color:#34d399;background:none;border:none;cursor:pointer;font-weight:700;text-decoration:underline;">
                                                    ✓ Clear Flag & Mark OK
                                                </button>
                                            </div>

                                            <form id="form-annotation-{{ $q->id }}" onsubmit="submitQuestionAnnotation(event, '{{ $q->id }}')" style="display:flex;flex-direction:column;gap:.75rem;">
                                                @csrf
                                                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
                                                    <div>
                                                        <label style="display:block;font-size:.75rem;font-weight:700;color:#cbd5e1;margin-bottom:.25rem;">Target Field *</label>
                                                        <select name="field" required style="width:100%;padding:.45rem;background:#0f172a;border:1px solid #334155;border-radius:.4rem;color:#fff;font-size:.8rem;">
                                                            <option value="stem" {{ ($qRev?->field === 'stem') ? 'selected' : '' }}>Stem / Prompt Text</option>
                                                            <option value="choices" {{ ($qRev?->field === 'choices') ? 'selected' : '' }}>Choices / Options</option>
                                                            <option value="correct_answer" {{ ($qRev?->field === 'correct_answer') ? 'selected' : '' }}>Correct Answer Selection</option>
                                                            <option value="explanation" {{ ($qRev?->field === 'explanation') ? 'selected' : '' }}>Explanation / Rationale</option>
                                                            <option value="media" {{ ($qRev?->field === 'media') ? 'selected' : '' }}>Media Attachment</option>
                                                            <option value="general" {{ ($qRev?->field === 'general') ? 'selected' : '' }}>General Question Quality</option>
                                                        </select>
                                                    </div>

                                                    <div>
                                                        <label style="display:block;font-size:.75rem;font-weight:700;color:#cbd5e1;margin-bottom:.25rem;">Severity</label>
                                                        <select name="severity" style="width:100%;padding:.45rem;background:#0f172a;border:1px solid #334155;border-radius:.4rem;color:#fff;font-size:.8rem;">
                                                            <option value="warning" {{ ($qRev?->severity === 'warning') ? 'selected' : '' }}>Warning (Requires Fix)</option>
                                                            <option value="critical" {{ ($qRev?->severity === 'critical' || $qStatus === 'critical_issue') ? 'selected' : '' }}>Critical Blocker</option>
                                                        </select>
                                                    </div>
                                                </div>

                                                <div>
                                                    <label style="display:block;font-size:.75rem;font-weight:700;color:#cbd5e1;margin-bottom:.25rem;">Reviewer Annotation Comment *</label>
                                                    <textarea name="comment" rows="2" required placeholder="Describe the specific correction required for the author..." style="width:100%;padding:.5rem;background:#0f172a;border:1px solid #334155;border-radius:.4rem;color:#fff;font-size:.8rem;">{{ $qRev?->comment }}</textarea>
                                                </div>

                                                <div style="display:flex;justify-content:flex-end;gap:.5rem;">
                                                    <button type="button" onclick="document.getElementById('inline-rev-panel-{{ $q->id }}').style.display='none';" style="padding:.45rem .85rem;background:#334155;color:#fff;border:none;border-radius:.4rem;font-size:.78rem;font-weight:700;cursor:pointer;">Close</button>
                                                    <button type="submit" style="padding:.45rem 1rem;background:#f59e0b;color:#fff;border:none;border-radius:.4rem;font-size:.78rem;font-weight:800;cursor:pointer;display:inline-flex;align-items:center;gap:.3rem;">
                                                        💾 Save Question Review
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                    @php $qIdxGlobal++; @endphp
                                    @endif
                                @endforeach
                            </div>
                        @endif
                    </div>
                    @endforeach
                @endif
            </div>
        </div>

        {{-- Right: Question Navigator, Governance Decision Form & Audit Log (Sticky Sidebar TASK 5) --}}
        <div class="sticky-governance-sidebar" style="display:flex;flex-direction:column;gap:1.25rem;position:sticky;top:1.5rem;">
            
            {{-- BUSINESS RULE 4: Question Navigator Sidebar Widget --}}
            <div style="background:#0f172a;border:1px solid #1e293b;border-radius:1.25rem;padding:1.25rem;">
                <div style="font-size:.85rem;font-weight:800;color:#fff;margin-bottom:.75rem;display:flex;align-items:center;justify-content:space-between;">
                    <span>🧭 Question Navigator</span>
                    <span style="font-size:.72rem;color:#94a3b8;font-weight:600;">Jump to question</span>
                </div>
                
                <div style="display:flex;flex-wrap:wrap;gap:.45rem;max-height:180px;overflow-y:auto;padding-right:.25rem;">
                    @php $navQIdx = 1; @endphp
                    @foreach($test->sections as $sec)
                        @foreach($sec->testQuestions as $tq)
                            @if($tq->question)
                            @php
                                $qNavRev = $questionReviews[$tq->question->id] ?? null;
                                $qNavStatus = ($qNavRev && in_array($qNavRev->status, ['needs_revision', 'critical_issue'])) ? $qNavRev->status : 'default_ok';
                                $badgeSymbol = $qNavStatus === 'critical_issue' ? '🔴' : ($qNavStatus === 'needs_revision' ? '🟡' : '🟢');
                                $bgBorder = $qNavStatus === 'critical_issue' ? 'background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.4);color:#f87171;' : ($qNavStatus === 'needs_revision' ? 'background:rgba(245,158,11,.15);border:1px solid rgba(245,158,11,.4);color:#fbbf24;' : 'background:rgba(52,211,153,.15);border:1px solid rgba(52,211,153,.4);color:#34d399;');
                            @endphp
                            <a id="nav-pill-{{ $tq->question->id }}" href="#question-card-{{ $tq->question->id }}" onclick="document.getElementById('question-card-{{ $tq->question->id }}').scrollIntoView({behavior:'smooth'});return false;" style="padding:.3rem .6rem;border-radius:.4rem;font-size:.75rem;font-weight:800;text-decoration:none;display:inline-flex;align-items:center;gap:.3rem;{{ $bgBorder }}">
                                <span id="nav-pill-symbol-{{ $tq->question->id }}">{{ $badgeSymbol }}</span> Q{{ $navQIdx }}
                            </a>
                            @php $navQIdx++; @endphp
                            @endif
                        @endforeach
                    @endforeach
                </div>
            </div>

            <div style="background:#0f172a;border:1px solid #1e293b;border-radius:1.25rem;padding:1.75rem;">
                <h3 style="font-size:1.05rem;font-weight:800;color:#fff;margin:0 0 1rem;">Governance Decision</h3>

                {{-- BUSINESS RULE 5: Approve Form with Review by Exception Guard --}}
                <form action="{{ route('admin.repository-manager.assessment-approve', $test->id) }}" method="POST" style="margin-bottom:1rem;">
                    @csrf
                    <div style="margin-bottom:.75rem;">
                        <label style="font-size:.75rem;font-weight:700;color:#94a3b8;display:block;margin-bottom:.3rem;">Approval Notes (Optional)</label>
                        <input type="text" name="notes" placeholder="e.g. Assessment meets institutional quality standards." style="width:100%;background:#1e293b;border:1px solid #334155;color:#fff;padding:.6rem;border-radius:.5rem;font-size:.82rem;">
                    </div>
                    
                    <button id="btn-approve-assessment" type="submit" {{ (isset($reviewProgress) && $reviewProgress['is_allowed']) ? '' : 'disabled' }} style="width:100%;padding:.75rem;background:{{ (isset($reviewProgress) && $reviewProgress['is_allowed']) ? '#10b981' : '#1e293b' }};color:{{ (isset($reviewProgress) && $reviewProgress['is_allowed']) ? '#fff' : '#64748b' }};font-weight:800;border:1px solid {{ (isset($reviewProgress) && $reviewProgress['is_allowed']) ? '#10b981' : '#334155' }};border-radius:.6rem;cursor:{{ (isset($reviewProgress) && $reviewProgress['is_allowed']) ? 'pointer' : 'not-allowed' }};font-size:.85rem;display:flex;align-items:center;justify-content:center;gap:.4rem;box-shadow:0 4px 14px rgba(0,0,0,.2);">
                        ✓ Approve Assessment
                    </button>
                    <div id="approve-guard-hint" style="font-size:.72rem;color:#f59e0b;margin-top:.4rem;text-align:center;display:{{ (isset($reviewProgress) && $reviewProgress['is_allowed']) ? 'none' : 'block' }};">
                        All flagged questions must be resolved before approval.
                    </div>
                </form>

                <hr style="border:none;border-top:1px solid #1e293b;margin:1.25rem 0;">

                {{-- BUSINESS RULE 6: Return Assessment Revision Form with Flagged Badge --}}
                <form action="{{ route('admin.repository-manager.assessment-revision', $test->id) }}" method="POST" style="margin-bottom:1rem;">
                    @csrf
                    <div style="margin-bottom:.75rem;">
                        <label style="font-size:.75rem;font-weight:700;color:#94a3b8;display:block;margin-bottom:.3rem;">Overall Revision Summary</label>
                        <textarea name="notes" rows="3" placeholder="Provide overall review notes for teacher..." style="width:100%;background:#1e293b;border:1px solid #334155;color:#fff;padding:.6rem;border-radius:.5rem;font-size:.82rem;"></textarea>
                    </div>

                    <button id="btn-request-revision" type="submit" {{ (isset($reviewProgress) && $reviewProgress['is_revision_allowed']) ? '' : 'disabled' }} style="width:100%;padding:.75rem;background:{{ (isset($reviewProgress) && $reviewProgress['is_revision_allowed']) ? '#f59e0b' : '#1e293b' }};color:{{ (isset($reviewProgress) && $reviewProgress['is_revision_allowed']) ? '#fff' : '#64748b' }};font-weight:800;border:1px solid {{ (isset($reviewProgress) && $reviewProgress['is_revision_allowed']) ? '#f59e0b' : '#334155' }};border-radius:.6rem;cursor:{{ (isset($reviewProgress) && $reviewProgress['is_revision_allowed']) ? 'pointer' : 'not-allowed' }};font-size:.85rem;display:flex;align-items:center;justify-content:center;gap:.4rem;">
                        ⚠️ Request Assessment Revision
                    </button>
                    <div id="revision-badge-container" style="font-size:.72rem;color:#94a3b8;margin-top:.4rem;text-align:center;">
                        <span id="revision-badge-text" style="font-weight:700;color:{{ (isset($reviewProgress) && $reviewProgress['is_revision_allowed']) ? '#fbbf24' : '#64748b' }};">
                            {{ (isset($reviewProgress) && $reviewProgress['flagged'] > 0) ? $reviewProgress['flagged'] . ' Questions Flagged' : '0 Questions Flagged — No revisions needed' }}
                        </span>
                    </div>
                </form>

                <hr style="border:none;border-top:1px solid #1e293b;margin:1.25rem 0;">

                {{-- Reject Form --}}
                <form action="{{ route('admin.repository-manager.assessment-reject', $test->id) }}" method="POST" onsubmit="event.preventDefault(); iapConfirm({ title: 'Reject Assessment Test?', message: 'Are you sure you want to reject this assessment test? Rejecting will return it for author revision.', confirmText: 'Reject Assessment', variant: 'danger', form: this });">
                    @csrf
                    <button type="submit" style="width:100%;padding:.6rem;background:#ef4444;color:#fff;font-weight:800;border:none;border-radius:.6rem;cursor:pointer;font-size:.8rem;">
                        ✖ Reject Assessment
                    </button>
                </form>
            </div>

            {{-- Audit Logs Widget --}}
            <div style="background:#0f172a;border:1px solid #1e293b;border-radius:1.25rem;padding:1.5rem;">
                <h4 style="font-size:.9rem;font-weight:800;color:#fff;margin:0 0 1rem;">📜 Activity Audit History</h4>
                @if($logs->isEmpty())
                    <div style="font-size:.78rem;color:#64748b;">No prior activity logged.</div>
                @else
                    @foreach($logs as $l)
                    <div style="border-bottom:1px solid #1e293b;padding:.6rem 0;font-size:.78rem;">
                        <div style="font-weight:700;color:#e2e8f0;">{{ ucfirst($l->action) }}</div>
                        <div style="color:#94a3b8;font-size:.72rem;">{{ $l->approval_note }}</div>
                        <div style="color:#64748b;font-size:.68rem;margin-top:.15rem;">By {{ $l->reviewer?->name ?? $l->actor?->name ?? 'System' }} • {{ $l->created_at?->diffForHumans() }}</div>
                    </div>
                    @endforeach
                @endif
            </div>
        </div>
    </div>
</div>

{{-- UX & ASYNCHRONOUS UPDATE SCRIPT (NO FULL PAGE RELOAD) --}}
<script>
function showToast(message) {
    const toast = document.getElementById('smart-review-toast');
    const msgEl = document.getElementById('toast-message');
    if (!toast || !msgEl) return;
    msgEl.textContent = message;
    toast.style.display = 'flex';
    toast.style.opacity = '1';
    setTimeout(() => {
        toast.style.opacity = '0';
        setTimeout(() => { toast.style.display = 'none'; }, 300);
    }, 2500);
}

function toggleInlineFlag(questionId) {
    const panel = document.getElementById('inline-rev-panel-' + questionId);
    if (!panel) return;
    panel.style.display = (panel.style.display === 'none' || panel.style.display === '') ? 'block' : 'none';
}

async function submitQuestionAnnotation(e, questionId) {
    e.preventDefault();
    const form = document.getElementById('form-annotation-' + questionId);
    if (!form) return;

    const formData = new FormData(form);
    const url = "{{ route('admin.repository-manager.question-request-revision', ['test' => $test->id, 'question' => 'Q_ID']) }}".replace('Q_ID', questionId);

    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value
            },
            body: formData
        });

        const data = await response.json();
        if (data.success) {
            updateQuestionUI(questionId, data);
            showToast("Review saved.");
        }
    } catch(err) {
        form.submit(); // fallback
    }
}

async function clearQuestionFlag(questionId) {
    const url = "{{ route('admin.repository-manager.question-review-ok', ['test' => $test->id, 'question' => 'Q_ID']) }}".replace('Q_ID', questionId);

    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value
            }
        });

        const data = await response.json();
        if (data.success) {
            updateQuestionUI(questionId, data);
            showToast("Flag cleared — Question marked OK.");
        }
    } catch(err) {
        window.location.reload(); // fallback
    }
}

function updateQuestionUI(questionId, data) {
    const isCritical = (data.status === 'critical_issue' || data.severity === 'critical');
    const isFlagged = (data.status !== 'default_ok');

    // 1. Update Card Border
    const card = document.getElementById('question-card-' + questionId);
    if (card) {
        card.style.borderColor = isCritical ? 'rgba(239,68,68,.5)' : (isFlagged ? 'rgba(245,158,11,.5)' : '#334155');
    }

    // 2. Update Badge Status
    const badge = document.getElementById('badge-status-' + questionId);
    if (badge) {
        if (isCritical) {
            badge.style.color = '#f87171'; badge.style.background = 'rgba(239,68,68,.15)'; badge.style.border = '1px solid rgba(239,68,68,.3)';
            badge.textContent = '🔴 Critical Issue (' + capitalize(data.field || 'general') + ')';
        } else if (isFlagged) {
            badge.style.color = '#fbbf24'; badge.style.background = 'rgba(245,158,11,.15)'; badge.style.border = '1px solid rgba(245,158,11,.3)';
            badge.textContent = '🟡 Needs Revision (' + capitalize(data.field || 'general') + ')';
        } else {
            badge.style.color = '#34d399'; badge.style.background = 'rgba(52,211,153,.15)'; badge.style.border = '1px solid rgba(52,211,153,.3)';
            badge.textContent = '🟢 Default OK';
        }
    }

    // 3. Update Toggle Button
    const btnToggle = document.getElementById('btn-toggle-flag-' + questionId);
    if (btnToggle) {
        btnToggle.style.background = isFlagged ? '#ef4444' : '#1e293b';
        btnToggle.style.borderColor = isFlagged ? '#ef4444' : '#334155';
        btnToggle.textContent = isFlagged ? '✖ Flagged (Click to Edit)' : '⚠️ Flag Revision';
    }

    // 4. Update Display Feedback Note
    const feedbackBox = document.getElementById('feedback-display-' + questionId);
    const feedbackField = document.getElementById('feedback-field-' + questionId);
    const feedbackText = document.getElementById('feedback-text-' + questionId);
    if (feedbackBox && isFlagged) {
        feedbackBox.style.display = 'block';
        if (feedbackField) feedbackField.textContent = capitalize(data.field || 'general');
        if (feedbackText) feedbackText.textContent = data.comment || '';
    } else if (feedbackBox) {
        feedbackBox.style.display = 'none';
    }

    // 5. Update Navigator Pill
    const navPill = document.getElementById('nav-pill-' + questionId);
    const navSymbol = document.getElementById('nav-pill-symbol-' + questionId);
    if (navPill && navSymbol) {
        if (isCritical) {
            navSymbol.textContent = '🔴'; navPill.style.background = 'rgba(239,68,68,.15)'; navPill.style.border = '1px solid rgba(239,68,68,.4)'; navPill.style.color = '#f87171';
        } else if (isFlagged) {
            navSymbol.textContent = '🟡'; navPill.style.background = 'rgba(245,158,11,.15)'; navPill.style.border = '1px solid rgba(245,158,11,.4)'; navPill.style.color = '#fbbf24';
        } else {
            navSymbol.textContent = '🟢'; navPill.style.background = 'rgba(52,211,153,.15)'; navPill.style.border = '1px solid rgba(52,211,153,.4)'; navPill.style.color = '#34d399';
        }
    }

    // 6. Update Summary Banner Counters
    const defOkCountEl = document.getElementById('summary-default-ok-count');
    const flaggedCountEl = document.getElementById('summary-flagged-count');
    if (defOkCountEl) defOkCountEl.textContent = data.default_ok_count;
    if (flaggedCountEl) flaggedCountEl.textContent = data.flagged_count;

    // 7. Update Approve & Revision Buttons State
    const btnApprove = document.getElementById('btn-approve-assessment');
    const approveHint = document.getElementById('approve-guard-hint');
    if (btnApprove) {
        btnApprove.disabled = !data.is_allowed;
        btnApprove.style.background = data.is_allowed ? '#10b981' : '#1e293b';
        btnApprove.style.borderColor = data.is_allowed ? '#10b981' : '#334155';
        btnApprove.style.color = data.is_allowed ? '#fff' : '#64748b';
        btnApprove.style.cursor = data.is_allowed ? 'pointer' : 'not-allowed';
    }
    if (approveHint) approveHint.style.display = data.is_allowed ? 'none' : 'block';

    const btnRevision = document.getElementById('btn-request-revision');
    const revisionBadgeText = document.getElementById('revision-badge-text');
    if (btnRevision) {
        btnRevision.disabled = !data.is_revision_allowed;
        btnRevision.style.background = data.is_revision_allowed ? '#f59e0b' : '#1e293b';
        btnRevision.style.borderColor = data.is_revision_allowed ? '#f59e0b' : '#334155';
        btnRevision.style.color = data.is_revision_allowed ? '#fff' : '#64748b';
        btnRevision.style.cursor = data.is_revision_allowed ? 'pointer' : 'not-allowed';
    }
    if (revisionBadgeText) {
        revisionBadgeText.style.color = data.is_revision_allowed ? '#fbbf24' : '#64748b';
        revisionBadgeText.textContent = data.is_revision_allowed ? (data.flagged_count + ' Questions Flagged') : '0 Questions Flagged — No revisions needed';
    }
}

function capitalize(s) {
    return s ? s.charAt(0).toUpperCase() + s.slice(1) : '';
}

// Back to Top button scroll visibility listener (TASK 1 & 3)
window.addEventListener('scroll', function() {
    const btn = document.getElementById('btn-back-to-top');
    if (!btn) return;
    if (window.scrollY > 500) {
        btn.style.opacity = '1';
        btn.style.pointerEvents = 'auto';
    } else {
        btn.style.opacity = '0';
        btn.style.pointerEvents = 'none';
    }
});
</script>
@endsection
