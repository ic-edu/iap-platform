@extends('layouts.admin')

@section('title', 'Review Assessment — Repository Governance')

@section('content')
<div style="padding: 1.5rem 0;">
    {{-- Header --}}
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
        <div>
            <h1 style="font-size:1.6rem;font-weight:800;color:#fff;margin:0 0 .3rem;">🔍 Assessment Governance Review</h1>
            <p style="font-size:.88rem;color:#94a3b8;margin:0;">Validate test structure, sections, questions, choices, and answer keys before approving for institutional use.</p>
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

    {{-- TASK 4: Question Review Progress Bar & Counters --}}
    @if(isset($reviewProgress))
    <div style="background:#0f172a;border:1px solid #1e293b;border-radius:1.25rem;padding:1.35rem;margin-bottom:1.5rem;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.85rem;flex-wrap:wrap;gap:1rem;">
            <div>
                <div style="font-size:.78rem;font-weight:800;color:#818cf8;text-transform:uppercase;letter-spacing:.05em;margin-bottom:.15rem;">
                    📊 Question Review Progress
                </div>
                <div style="font-size:1.1rem;font-weight:800;color:#fff;">
                    {{ $reviewProgress['reviewed_ok'] }} of {{ $reviewProgress['total'] }} Questions Reviewed OK ({{ $reviewProgress['percentage'] }}%)
                </div>
            </div>
            <div style="display:flex;gap:1rem;font-size:.82rem;">
                <div style="background:#1e293b;padding:.4rem .75rem;border-radius:.5rem;border:1px solid #334155;color:#34d399;font-weight:700;">
                    🟢 Reviewed OK: {{ $reviewProgress['reviewed_ok'] }}
                </div>
                <div style="background:#1e293b;padding:.4rem .75rem;border-radius:.5rem;border:1px solid #334155;color:#fbbf24;font-weight:700;">
                    🟡 Needs Revision: {{ $reviewProgress['needs_revision'] }}
                </div>
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
        {{-- Left: Assessment Details & Section Question Review --}}
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

            {{-- Sections Breakdown & Question-Level Structured Review --}}
            <div style="background:#0f172a;border:1px solid #1e293b;border-radius:1.25rem;padding:1.75rem;">
                <h3 style="font-size:1.05rem;font-weight:800;color:#fff;margin:0 0 1rem;">Section & Question Governance Review</h3>

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
                            <div style="display:flex;flex-direction:column;gap:1rem;margin-top:1rem;">
                                @foreach($sec->testQuestions as $idx => $tq)
                                    @php
                                        $q = $tq->question;
                                        $qRev = $questionReviews[$q?->id] ?? null;
                                        $qStatus = $qRev?->status ?? 'not_reviewed';
                                    @endphp
                                    @if($q)
                                    <div style="background:#0f172a;border:1px solid {{ $qStatus === 'needs_revision' ? 'rgba(245,158,11,.5)' : ($qStatus === 'reviewed_ok' ? 'rgba(52,211,153,.3)' : '#334155') }};border-radius:.75rem;padding:1.1rem;">
                                        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:.5rem;margin-bottom:.5rem;">
                                            <div>
                                                <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.35rem;">
                                                    <span style="font-size:.75rem;font-weight:800;color:#cbd5e1;background:#334155;padding:.15rem .55rem;border-radius:.3rem;">
                                                        Q#{{ $qIdxGlobal }}
                                                    </span>
                                                    <span style="font-size:.7rem;color:#94a3b8;background:#1e293b;padding:.15rem .45rem;border-radius:.3rem;text-transform:uppercase;">{{ $q->question_type }}</span>
                                                    @php $diffVal = is_object($q->difficulty) ? $q->difficulty->value : $q->difficulty; @endphp
                                                    <span style="font-size:.7rem;color:#a78bfa;background:rgba(167,139,250,.12);padding:.15rem .45rem;border-radius:.3rem;text-transform:uppercase;">{{ $diffVal ?? 'easy' }}</span>

                                                    {{-- Question Review Status Badge --}}
                                                    @if($qStatus === 'reviewed_ok')
                                                        <span style="font-size:.72rem;font-weight:800;color:#34d399;background:rgba(52,211,153,.15);border:1px solid rgba(52,211,153,.3);padding:.15rem .55rem;border-radius:.3rem;">
                                                            🟢 Reviewed OK
                                                        </span>
                                                    @elseif($qStatus === 'needs_revision')
                                                        <span style="font-size:.72rem;font-weight:800;color:#fbbf24;background:rgba(245,158,11,.15);border:1px solid rgba(245,158,11,.3);padding:.15rem .55rem;border-radius:.3rem;">
                                                            🟡 Needs Revision ({{ $qRev->field ?? 'General' }})
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
                                            <div style="font-size:.78rem;color:#fbbf24;margin-top:.65rem;background:rgba(245,158,11,.1);padding:.5rem .75rem;border-radius:.4rem;border:1px solid rgba(245,158,11,.3);">
                                                💬 <strong>Reviewer Feedback ({{ ucfirst($qRev->field) }}):</strong> "{{ $qRev->comment }}"
                                            </div>
                                        @endif

                                        {{-- TASK 2: Review Decision Panel --}}
                                        <div style="background:#1e293b;border:1px solid #334155;border-radius:.65rem;padding:.85rem;margin-top:1rem;">
                                            <div style="font-size:.78rem;font-weight:800;color:#cbd5e1;margin-bottom:.5rem;text-transform:uppercase;letter-spacing:.05em;">
                                                📋 Question Governance Review Decision
                                            </div>
                                            
                                            <div style="display:flex;gap:1rem;align-items:center;flex-wrap:wrap;">
                                                {{-- Radio Option: Reviewed OK --}}
                                                <label style="display:flex;align-items:center;gap:.4rem;font-size:.82rem;font-weight:700;color:#34d399;cursor:pointer;background:#0f172a;padding:.4rem .75rem;border-radius:.4rem;border:1px solid {{ $qStatus === 'reviewed_ok' ? '#34d399' : '#334155' }};">
                                                    <input type="radio" name="q_decision_{{ $q->id }}" value="ok" {{ $qStatus === 'reviewed_ok' ? 'checked' : '' }}
                                                           onchange="document.getElementById('q-ok-form-{{ $q->id }}').submit();" style="accent-color:#34d399;">
                                                    🟢 Reviewed OK
                                                </label>

                                                {{-- Radio Option: Needs Revision (TASK 3 Conditional Trigger) --}}
                                                <label style="display:flex;align-items:center;gap:.4rem;font-size:.82rem;font-weight:700;color:#fbbf24;cursor:pointer;background:#0f172a;padding:.4rem .75rem;border-radius:.4rem;border:1px solid {{ $qStatus === 'needs_revision' ? '#fbbf24' : '#334155' }};">
                                                    <input type="radio" name="q_decision_{{ $q->id }}" value="revision" {{ $qStatus === 'needs_revision' ? 'checked' : '' }}
                                                           onchange="document.getElementById('q-rev-modal-{{ $q->id }}').classList.remove('hidden');" style="accent-color:#fbbf24;">
                                                    🟡 Needs Revision
                                                </label>
                                            </div>
                                            
                                            <div style="font-size:.72rem;color:#64748b;margin-top:.4rem;">
                                                Reviewed OK = Academic quality verified. Needs Revision = Opens structured feedback for author.
                                            </div>

                                            {{-- Hidden form to post Reviewed OK --}}
                                            <form id="q-ok-form-{{ $q->id }}" method="POST" action="{{ route('admin.repository-manager.question-review-ok', ['test' => $test->id, 'question' => $q->id]) }}" style="display:none;">
                                                @csrf
                                            </form>
                                        </div>

                                        {{-- TASK 3: Conditional Structured Question Revision Modal --}}
                                        <div id="q-rev-modal-{{ $q->id }}" class="hidden" style="position:fixed;inset:0;z-index:9999;background:rgba(15,23,42,.85);display:flex;align-items:center;justify-content:center;padding:1.5rem;">
                                            <div style="background:#0f172a;border:1px solid #334155;border-radius:1.25rem;max-width:500px;width:100%;padding:1.5rem;box-shadow:0 25px 50px -12px rgba(0,0,0,.7);">
                                                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;border-bottom:1px solid #1e293b;padding-bottom:.75rem;">
                                                    <h4 style="font-size:1rem;font-weight:800;color:#fff;margin:0;">
                                                        ⚠️ Request Revision on Q#{{ $qIdxGlobal }}
                                                    </h4>
                                                    <button type="button" onclick="document.getElementById('q-rev-modal-{{ $q->id }}').classList.add('hidden')" style="background:none;border:none;color:#94a3b8;font-size:1.2rem;cursor:pointer;">✕</button>
                                                </div>

                                                <form method="POST" action="{{ route('admin.repository-manager.question-request-revision', ['test' => $test->id, 'question' => $q->id]) }}" style="display:flex;flex-direction:column;gap:1rem;">
                                                    @csrf
                                                    <div>
                                                        <label style="display:block;font-size:.8rem;font-weight:700;color:#cbd5e1;margin-bottom:.35rem;">Target Revision Field *</label>
                                                        <select name="field" required style="width:100%;padding:.6rem;background:#1e293b;border:1px solid #334155;border-radius:.5rem;color:#fff;font-size:.85rem;">
                                                            <option value="stem">Stem / Prompt Text</option>
                                                            <option value="choices">Choices / Options</option>
                                                            <option value="correct_answer">Correct Answer Selection</option>
                                                            <option value="explanation">Explanation / Rationale</option>
                                                            <option value="media">Media Attachment</option>
                                                            <option value="general">General Question Quality</option>
                                                        </select>
                                                    </div>

                                                    <div>
                                                        <label style="display:block;font-size:.8rem;font-weight:700;color:#cbd5e1;margin-bottom:.35rem;">Reviewer Feedback Comment *</label>
                                                        <textarea name="comment" rows="3" required placeholder="Describe the specific issue and required correction for the author..." style="width:100%;padding:.65rem;background:#1e293b;border:1px solid #334155;border-radius:.5rem;color:#fff;font-size:.85rem;"></textarea>
                                                    </div>

                                                    <div>
                                                        <label style="display:block;font-size:.8rem;font-weight:700;color:#cbd5e1;margin-bottom:.35rem;">Severity</label>
                                                        <select name="severity" style="width:100%;padding:.6rem;background:#1e293b;border:1px solid #334155;border-radius:.5rem;color:#fff;font-size:.85rem;">
                                                            <option value="warning">Warning (Requires Fix)</option>
                                                            <option value="critical">Critical Blocker</option>
                                                        </select>
                                                    </div>

                                                    <div style="display:flex;justify-content:flex-end;gap:.75rem;margin-top:.75rem;border-top:1px solid #1e293b;padding-top:.85rem;">
                                                        <button type="button" onclick="document.getElementById('q-rev-modal-{{ $q->id }}').classList.add('hidden')" style="padding:.55rem 1rem;background:#334155;color:#fff;border:none;border-radius:.5rem;font-size:.8rem;font-weight:700;cursor:pointer;">Cancel</button>
                                                        <button type="submit" style="padding:.55rem 1.25rem;background:#f59e0b;color:#fff;border:none;border-radius:.5rem;font-size:.8rem;font-weight:800;cursor:pointer;">⚠️ Submit Question Revision</button>
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

        {{-- Right: Repository Governance Action Form & Audit Log (TASK 5) --}}
        <div style="display:flex;flex-direction:column;gap:1.25rem;">
            <div style="background:#0f172a;border:1px solid #1e293b;border-radius:1.25rem;padding:1.75rem;">
                <h3 style="font-size:1.05rem;font-weight:800;color:#fff;margin:0 0 1rem;">Governance Decision</h3>

                {{-- TASK 5: Approve Form with Completion Guard --}}
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

                {{-- TASK 5: Return Revision Form --}}
                <form action="{{ route('admin.repository-manager.assessment-revision', $test->id) }}" method="POST" style="margin-bottom:1rem;">
                    @csrf
                    <div style="margin-bottom:.75rem;">
                        <label style="font-size:.75rem;font-weight:700;color:#94a3b8;display:block;margin-bottom:.3rem;">Overall Revision Summary</label>
                        <textarea name="notes" rows="3" placeholder="Provide overall review notes for teacher..." style="width:100%;background:#1e293b;border:1px solid #334155;color:#fff;padding:.6rem;border-radius:.5rem;font-size:.82rem;"></textarea>
                    </div>

                    @if(isset($reviewProgress) && $reviewProgress['needs_revision'] > 0)
                    <button type="submit" style="width:100%;padding:.75rem;background:#f59e0b;color:#fff;font-weight:800;border:none;border-radius:.6rem;cursor:pointer;font-size:.85rem;display:flex;align-items:center;justify-content:center;gap:.4rem;box-shadow:0 4px 14px rgba(245,158,11,.35);">
                        ⚠️ Return Revision to Author
                    </button>
                    @else
                    <button type="button" disabled style="width:100%;padding:.75rem;background:#1e293b;color:#64748b;font-weight:700;border:1px solid #334155;border-radius:.6rem;cursor:not-allowed;font-size:.85rem;" title="At least one question must be marked Needs Revision.">
                        ⚠️ Return Revision Disabled
                    </button>
                    <div style="font-size:.72rem;color:#94a3b8;margin-top:.4rem;text-align:center;">
                        Mark at least one question Needs Revision to send back.
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
