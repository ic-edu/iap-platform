@extends('layouts.admin')

@section('title', 'Review Assessment — Repository Governance')

@section('content')
<div style="padding: 1.5rem 0;">
    {{-- Header --}}
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
        <div>
            <h1 style="font-size:1.6rem;font-weight:800;color:#fff;margin:0 0 .3rem;">🔍 Question Annotation & Governance Workspace</h1>
            <p style="font-size:.88rem;color:#94a3b8;margin:0;">Inspect test questions, annotate per-question review feedback inline, and manage assessment approval.</p>
        </div>
        <div>
            <a href="{{ route('admin.repository-manager.assessment-approval') }}" style="padding:.6rem 1.1rem;background:#1e293b;border:1px solid #334155;color:#fff;border-radius:.6rem;font-size:.82rem;font-weight:700;text-decoration:none;display:inline-flex;align-items:center;gap:.4rem;">
                ⬅ Back to Assessment Queue
            </a>
        </div>
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

    {{-- Question Review Progress Bar & Counters --}}
    @if(isset($reviewProgress))
    <div style="background:#0f172a;border:1px solid #1e293b;border-radius:1.25rem;padding:1.35rem;margin-bottom:1.5rem;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.85rem;flex-wrap:wrap;gap:1rem;">
            <div>
                <div style="font-size:.78rem;font-weight:800;color:#818cf8;text-transform:uppercase;letter-spacing:.05em;margin-bottom:.15rem;">
                    📊 Question Annotation Progress
                </div>
                <div style="font-size:1.1rem;font-weight:800;color:#fff;">
                    {{ $reviewProgress['reviewed_ok'] }} of {{ $reviewProgress['total'] }} Questions Reviewed OK ({{ $reviewProgress['percentage'] }}%)
                </div>
            </div>
            <div style="display:flex;gap:.75rem;font-size:.82rem;flex-wrap:wrap;">
                <div style="background:#1e293b;padding:.4rem .75rem;border-radius:.5rem;border:1px solid #334155;color:#34d399;font-weight:700;">
                    🟢 Reviewed OK: {{ $reviewProgress['reviewed_ok'] }}
                </div>
                <div style="background:#1e293b;padding:.4rem .75rem;border-radius:.5rem;border:1px solid #334155;color:#fbbf24;font-weight:700;">
                    🟡 Needs Revision: {{ $reviewProgress['needs_revision'] }}
                </div>
                @if(($reviewProgress['critical'] ?? 0) > 0)
                <div style="background:#1e293b;padding:.4rem .75rem;border-radius:.5rem;border:1px solid #334155;color:#f87171;font-weight:700;">
                    🔴 Critical Issues: {{ $reviewProgress['critical'] }}
                </div>
                @endif
                <div style="background:#1e293b;padding:.4rem .75rem;border-radius:.5rem;border:1px solid #334155;color:#94a3b8;font-weight:700;">
                    ⚪ Not Reviewed: {{ $reviewProgress['not_reviewed'] }}
                </div>
            </div>
        </div>

        {{-- Progress Bar --}}
        <div style="width:100%;height:8px;background:#1e293b;border-radius:4px;overflow:hidden;">
            <div style="width:{{ $reviewProgress['percentage'] }}%;height:100%;background:{{ $reviewProgress['is_allowed'] ? '#34d399' : '#818cf8' }};transition:width .3s ease;"></div>
        </div>
    </div>
    @endif

    <div style="display:grid;grid-template-columns:2fr 1fr;gap:1.5rem;align-items:start;">
        {{-- Left: Assessment Details & Question Cards with Inline Review Panel --}}
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

            {{-- Sections Breakdown & Question Cards with Inline Review Panel --}}
            <div style="background:#0f172a;border:1px solid #1e293b;border-radius:1.25rem;padding:1.75rem;">
                <h3 style="font-size:1.05rem;font-weight:800;color:#fff;margin:0 0 1rem;">Question Annotation Workspace</h3>

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
                                        $qStatus = $qRev?->status ?? 'not_reviewed';
                                    @endphp
                                    @if($q)
                                    <div id="question-card-{{ $q->id }}" style="background:#0f172a;border:1px solid {{ $qStatus === 'critical_issue' ? 'rgba(239,68,68,.5)' : ($qStatus === 'needs_revision' ? 'rgba(245,158,11,.5)' : ($qStatus === 'reviewed_ok' ? 'rgba(52,211,153,.3)' : '#334155')) }};border-radius:.75rem;padding:1.1rem;">
                                        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:.5rem;margin-bottom:.5rem;">
                                            <div>
                                                <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.35rem;">
                                                    <span style="font-size:.75rem;font-weight:800;color:#cbd5e1;background:#334155;padding:.15rem .55rem;border-radius:.3rem;">
                                                        Q#{{ $qIdxGlobal }}
                                                    </span>
                                                    <span style="font-size:.7rem;color:#94a3b8;background:#1e293b;padding:.15rem .45rem;border-radius:.3rem;text-transform:uppercase;">{{ $q->question_type }}</span>
                                                    @php $diffVal = is_object($q->difficulty) ? $q->difficulty->value : $q->difficulty; @endphp
                                                    <span style="font-size:.7rem;color:#a78bfa;background:rgba(167,139,250,.12);padding:.15rem .45rem;border-radius:.3rem;text-transform:uppercase;">{{ $diffVal ?? 'easy' }}</span>

                                                    {{-- Question Review Status Badges (TASK 1) --}}
                                                    @if($qStatus === 'reviewed_ok')
                                                        <span style="font-size:.72rem;font-weight:800;color:#34d399;background:rgba(52,211,153,.15);border:1px solid rgba(52,211,153,.3);padding:.15rem .55rem;border-radius:.3rem;">
                                                            🟢 Reviewed OK
                                                        </span>
                                                    @elseif($qStatus === 'critical_issue')
                                                        <span style="font-size:.72rem;font-weight:800;color:#f87171;background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.3);padding:.15rem .55rem;border-radius:.3rem;">
                                                            🔴 Critical Issue ({{ ucfirst($qRev->field ?? 'General') }})
                                                        </span>
                                                    @elseif($qStatus === 'needs_revision')
                                                        <span style="font-size:.72rem;font-weight:800;color:#fbbf24;background:rgba(245,158,11,.15);border:1px solid rgba(245,158,11,.3);padding:.15rem .55rem;border-radius:.3rem;">
                                                            🟡 Needs Revision ({{ ucfirst($qRev->field ?? 'General') }})
                                                        </span>
                                                    @else
                                                        <span style="font-size:.72rem;font-weight:800;color:#94a3b8;background:rgba(148,163,184,.15);border:1px solid rgba(148,163,184,.3);padding:.15rem .55rem;border-radius:.3rem;">
                                                            ⚪ Not Reviewed
                                                        </span>
                                                    @endif
                                                </div>

                                                <div style="font-weight:700;color:#fff;font-size:.9rem;line-height:1.4;">
                                                    {{ $q->prompt ?? '(Empty Prompt Stem)' }}
                                                </div>
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

                                        {{-- Feedback Note if any --}}
                                        @if($qRev && $qRev->comment)
                                            <div style="font-size:.78rem;color:{{ $qStatus === 'critical_issue' ? '#f87171' : '#fbbf24' }};margin-top:.65rem;background:rgba(15,23,42,.6);padding:.5rem .75rem;border-radius:.4rem;border:1px solid {{ $qStatus === 'critical_issue' ? 'rgba(239,68,68,.3)' : 'rgba(245,158,11,.3)' }};">
                                                💬 <strong>Reviewer Feedback ({{ ucfirst($qRev->field) }}):</strong> "{{ $qRev->comment }}"
                                            </div>
                                        @endif

                                        {{-- TASK 2: Inline Review Panel (NO POPUP MODAL) --}}
                                        <div style="background:#1e293b;border:1px solid #334155;border-radius:.65rem;padding:1rem;margin-top:1rem;">
                                            <div style="font-size:.78rem;font-weight:800;color:#cbd5e1;margin-bottom:.65rem;text-transform:uppercase;letter-spacing:.05em;">
                                                📋 Question Review Decision
                                            </div>
                                            
                                            <div style="display:flex;gap:.85rem;align-items:center;flex-wrap:wrap;margin-bottom:.75rem;">
                                                {{-- Radio Option: Reviewed OK --}}
                                                <label style="display:flex;align-items:center;gap:.4rem;font-size:.82rem;font-weight:700;color:#34d399;cursor:pointer;background:#0f172a;padding:.45rem .8rem;border-radius:.4rem;border:1px solid {{ $qStatus === 'reviewed_ok' ? '#34d399' : '#334155' }};">
                                                    <input type="radio" name="q_decision_{{ $q->id }}" value="ok" {{ $qStatus === 'reviewed_ok' ? 'checked' : '' }}
                                                           onchange="document.getElementById('q-ok-form-{{ $q->id }}').submit();" style="accent-color:#34d399;">
                                                    🟢 Reviewed OK
                                                </label>

                                                {{-- Radio Option: Needs Revision (Expands Inline Form) --}}
                                                <label style="display:flex;align-items:center;gap:.4rem;font-size:.82rem;font-weight:700;color:#fbbf24;cursor:pointer;background:#0f172a;padding:.45rem .8rem;border-radius:.4rem;border:1px solid {{ $qStatus === 'needs_revision' ? '#fbbf24' : '#334155' }};">
                                                    <input type="radio" name="q_decision_{{ $q->id }}" value="revision" {{ $qStatus === 'needs_revision' ? 'checked' : '' }}
                                                           onchange="document.getElementById('inline-rev-form-{{ $q->id }}').style.display = 'block';" style="accent-color:#fbbf24;">
                                                    🟡 Needs Revision
                                                </label>

                                                {{-- Radio Option: Critical Issue (Expands Inline Form) --}}
                                                <label style="display:flex;align-items:center;gap:.4rem;font-size:.82rem;font-weight:700;color:#f87171;cursor:pointer;background:#0f172a;padding:.45rem .8rem;border-radius:.4rem;border:1px solid {{ $qStatus === 'critical_issue' ? '#f87171' : '#334155' }};">
                                                    <input type="radio" name="q_decision_{{ $q->id }}" value="critical" {{ $qStatus === 'critical_issue' ? 'checked' : '' }}
                                                           onchange="document.getElementById('inline-rev-form-{{ $q->id }}').style.display = 'block';" style="accent-color:#f87171;">
                                                    🔴 Critical Issue
                                                </label>

                                                {{-- Radio Option: Skip --}}
                                                <label style="display:flex;align-items:center;gap:.4rem;font-size:.82rem;font-weight:700;color:#94a3b8;cursor:pointer;background:#0f172a;padding:.45rem .8rem;border-radius:.4rem;border:1px solid {{ $qStatus === 'not_reviewed' ? '#94a3b8' : '#334155' }};">
                                                    <input type="radio" name="q_decision_{{ $q->id }}" value="skip" {{ $qStatus === 'not_reviewed' ? 'checked' : '' }}
                                                           onchange="document.getElementById('inline-rev-form-{{ $q->id }}').style.display = 'none';" style="accent-color:#94a3b8;">
                                                    ⚪ Skip
                                                </label>
                                            </div>

                                            {{-- Hidden Form for Reviewed OK --}}
                                            <form id="q-ok-form-{{ $q->id }}" method="POST" action="{{ route('admin.repository-manager.question-review-ok', ['test' => $test->id, 'question' => $q->id]) }}" style="display:none;">
                                                @csrf
                                            </form>

                                            {{-- TASK 2: Inline Expandable Review Form (NO MODAL POPUP) --}}
                                            <div id="inline-rev-form-{{ $q->id }}" style="display:{{ in_array($qStatus, ['needs_revision', 'critical_issue']) ? 'block' : 'none' }};margin-top:.85rem;padding-top:.85rem;border-top:1px solid #334155;">
                                                <form method="POST" action="{{ route('admin.repository-manager.question-request-revision', ['test' => $test->id, 'question' => $q->id]) }}" style="display:flex;flex-direction:column;gap:.75rem;">
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
                                                        <button type="submit" style="padding:.45rem 1rem;background:#f59e0b;color:#fff;border:none;border-radius:.4rem;font-size:.78rem;font-weight:800;cursor:pointer;display:inline-flex;align-items:center;gap:.3rem;">
                                                            💾 Save Question Review
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
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

        {{-- Right: Question Navigator, Governance Decision Form & Audit Log (TASK 3 & TASK 4) --}}
        <div style="display:flex;flex-direction:column;gap:1.25rem;">
            
            {{-- TASK 3: Live Colored Question Navigator Sidebar Widget --}}
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
                                $qNavStatus = $qNavRev?->status ?? 'not_reviewed';
                                $badgeSymbol = $qNavStatus === 'reviewed_ok' ? '🟢' : ($qNavStatus === 'critical_issue' ? '🔴' : ($qNavStatus === 'needs_revision' ? '🟡' : '⚪'));
                                $bgBorder = $qNavStatus === 'reviewed_ok' ? 'background:rgba(52,211,153,.15);border:1px solid rgba(52,211,153,.4);color:#34d399;' : ($qNavStatus === 'critical_issue' ? 'background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.4);color:#f87171;' : ($qNavStatus === 'needs_revision' ? 'background:rgba(245,158,11,.15);border:1px solid rgba(245,158,11,.4);color:#fbbf24;' : 'background:#1e293b;border:1px solid #334155;color:#94a3b8;'));
                            @endphp
                            <a href="#question-card-{{ $tq->question->id }}" onclick="document.getElementById('question-card-{{ $tq->question->id }}').scrollIntoView({behavior:'smooth'});return false;" style="padding:.3rem .6rem;border-radius:.4rem;font-size:.75rem;font-weight:800;text-decoration:none;display:inline-flex;align-items:center;gap:.3rem;{{ $bgBorder }}">
                                {{ $badgeSymbol }} Q{{ $navQIdx }}
                            </a>
                            @php $navQIdx++; @endphp
                            @endif
                        @endforeach
                    @endforeach
                </div>
            </div>

            <div style="background:#0f172a;border:1px solid #1e293b;border-radius:1.25rem;padding:1.75rem;">
                <h3 style="font-size:1.05rem;font-weight:800;color:#fff;margin:0 0 1rem;">Governance Decision</h3>

                {{-- Approve Form --}}
                <form action="{{ route('admin.repository-manager.assessment-approve', $test->id) }}" method="POST" style="margin-bottom:1rem;">
                    @csrf
                    <div style="margin-bottom:.75rem;">
                        <label style="font-size:.75rem;font-weight:700;color:#94a3b8;display:block;margin-bottom:.3rem;">Approval Notes (Optional)</label>
                        <input type="text" name="notes" placeholder="e.g. Assessment meets institutional quality standards." style="width:100%;background:#1e293b;border:1px solid #334155;color:#fff;padding:.6rem;border-radius:.5rem;font-size:.82rem;">
                    </div>
                    
                    @if(isset($reviewProgress) && $reviewProgress['is_allowed'])
                    <button type="submit" style="width:100%;padding:.75rem;background:#10b981;color:#fff;font-weight:800;border:none;border-radius:.6rem;cursor:pointer;font-size:.85rem;display:flex;align-items:center;justify-content:center;gap:.4rem;box-shadow:0 4px 14px rgba(16,185,129,.35);">
                        ✓ Approve Assessment
                    </button>
                    @else
                    <button type="button" disabled style="width:100%;padding:.75rem;background:#1e293b;color:#64748b;font-weight:700;border:1px solid #334155;border-radius:.6rem;cursor:not-allowed;font-size:.85rem;" title="All questions must be marked Reviewed OK first.">
                        🚫 Approve Disabled (Review Pending)
                    </button>
                    <div style="font-size:.72rem;color:#f59e0b;margin-top:.4rem;text-align:center;">
                        Mark 100% of questions Reviewed OK to enable approval.
                    </div>
                    @endif
                </form>

                <hr style="border:none;border-top:1px solid #1e293b;margin:1.25rem 0;">

                {{-- TASK 4: Return Assessment Revision Form with Assessment Revision Guard --}}
                <form action="{{ route('admin.repository-manager.assessment-revision', $test->id) }}" method="POST" style="margin-bottom:1rem;">
                    @csrf
                    <div style="margin-bottom:.75rem;">
                        <label style="font-size:.75rem;font-weight:700;color:#94a3b8;display:block;margin-bottom:.3rem;">Overall Revision Summary</label>
                        <textarea name="notes" rows="3" placeholder="Provide overall review notes for teacher..." style="width:100%;background:#1e293b;border:1px solid #334155;color:#fff;padding:.6rem;border-radius:.5rem;font-size:.82rem;"></textarea>
                    </div>

                    @if(isset($reviewProgress) && $reviewProgress['needs_revision'] > 0)
                    <button type="submit" style="width:100%;padding:.75rem;background:#f59e0b;color:#fff;font-weight:800;border:none;border-radius:.6rem;cursor:pointer;font-size:.85rem;display:flex;align-items:center;justify-content:center;gap:.4rem;box-shadow:0 4px 14px rgba(245,158,11,.35);">
                        ⚠️ Request Assessment Revision
                    </button>
                    @else
                    <button type="button" disabled style="width:100%;padding:.75rem;background:#1e293b;color:#64748b;font-weight:700;border:1px solid #334155;border-radius:.6rem;cursor:not-allowed;font-size:.85rem;">
                        ⚠️ Revision Disabled
                    </button>
                    <div style="font-size:.72rem;color:#94a3b8;margin-top:.4rem;text-align:center;">
                        No Question has been marked for revision.
                    </div>
                    @endif
                </form>

                <hr style="border:none;border-top:1px solid #1e293b;margin:1.25rem 0;">

                {{-- Reject Form --}}
                <form action="{{ route('admin.repository-manager.assessment-reject', $test->id) }}" method="POST">
                    @csrf
                    <button type="submit" style="width:100%;padding:.6rem;background:#ef4444;color:#fff;font-weight:800;border:none;border-radius:.6rem;cursor:pointer;font-size:.8rem;" onclick="return confirm('Are you sure you want to reject this assessment test?')">
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
@endsection
