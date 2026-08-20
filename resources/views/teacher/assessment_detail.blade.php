@extends('layouts.admin')

@section('title', 'Assessment Revision Summary — ' . $test->title)

@section('content')
<div style="padding: 1.5rem 0;">
    {{-- Header & Navigation --}}
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
        <div>
            <div style="display:flex;align-items:center;gap:.65rem;margin-bottom:.35rem;">
                <span style="background:rgba(99,102,241,.15);color:#818cf8;border:1px solid rgba(99,102,241,.3);padding:.2rem .65rem;border-radius:.4rem;font-size:.75rem;font-weight:800;text-transform:uppercase;">
                    {{ is_object($test->test_type) ? $test->test_type->value : strtoupper($test->test_type ?? 'TOEIC') }}
                </span>
                @if(in_array($test->status, ['needs_revision', 'revision_requested']))
                    <span style="background:rgba(245,158,11,.15);color:#fbbf24;border:1px solid rgba(245,158,11,.3);padding:.2rem .65rem;border-radius:.4rem;font-size:.75rem;font-weight:800;">
                        ⚠️ Needs Revision
                    </span>
                @elseif(in_array($test->status, ['pending', 'pending_approval']))
                    <span style="background:rgba(251,191,36,.15);color:#fbbf24;border:1px solid rgba(251,191,36,.3);padding:.2rem .65rem;border-radius:.4rem;font-size:.75rem;font-weight:800;">
                        ⏳ Pending Approval
                    </span>
                @elseif($test->status === 'approved' || $test->is_published)
                    <span style="background:rgba(52,211,153,.15);color:#34d399;border:1px solid rgba(52,211,153,.3);padding:.2rem .65rem;border-radius:.4rem;font-size:.75rem;font-weight:800;">
                        🟢 Published & Live
                    </span>
                @else
                    <span style="background:rgba(148,163,184,.15);color:#cbd5e1;border:1px solid rgba(148,163,184,.3);padding:.2rem .65rem;border-radius:.4rem;font-size:.75rem;font-weight:800;">
                        📝 Draft
                    </span>
                @endif
            </div>
            <h1 style="font-size:1.75rem;font-weight:800;color:#fff;margin:0;">{{ $test->title }}</h1>
        </div>
        <div style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:center;">
            @if(request('from') === 'revision_center')
            <a href="{{ route('teacher.revision-center') }}" style="padding:.6rem 1.1rem;background:#1e293b;border:1px solid #334155;color:#fff;border-radius:.6rem;font-size:.82rem;font-weight:700;text-decoration:none;display:inline-flex;align-items:center;gap:.4rem;">
                ← Back to Revision Center
            </a>
            @else
            <a href="{{ route('teacher.tests.index') }}" style="padding:.6rem 1.1rem;background:#1e293b;border:1px solid #334155;color:#fff;border-radius:.6rem;font-size:.82rem;font-weight:700;text-decoration:none;display:inline-flex;align-items:center;gap:.4rem;">
                ← Back to Assessments
            </a>
            @endif
            <a href="{{ route('teacher.dashboard') }}" style="padding:.6rem 1.1rem;background:#6366f1;color:#fff;border-radius:.6rem;font-size:.82rem;font-weight:700;text-decoration:none;display:inline-flex;align-items:center;gap:.4rem;">
                🏠 Dashboard
            </a>
        </div>
    </div>

    @if(session('status'))
    <div style="background:rgba(52,211,153,.12);border:1px solid rgba(52,211,153,.3);color:#34d399;padding:1rem 1.25rem;border-radius:.75rem;font-size:.88rem;font-weight:700;margin-bottom:1.5rem;">
        ✅ {{ session('status') }}
    </div>
    @endif

    @if(session('error'))
    <div style="background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.3);color:#f87171;padding:1rem 1.25rem;border-radius:.75rem;font-size:.88rem;font-weight:700;margin-bottom:1.5rem;">
        ⚠️ {{ session('error') }}
    </div>
    @endif

    @if(session('info'))
    <div style="background:rgba(99,102,241,.12);border:1px solid rgba(99,102,241,.3);color:#818cf8;padding:1rem 1.25rem;border-radius:.75rem;font-size:.88rem;font-weight:700;margin-bottom:1.5rem;">
        ℹ️ {{ session('info') }}
    </div>
    @endif

    {{-- TASK 3: Validation Assistant Summary Box --}}
    @if(isset($validationResult))
    <div style="background:{{ $validationResult['is_valid'] ? 'rgba(52,211,153,.08)' : 'rgba(239,68,68,.08)' }};border:1px solid {{ $validationResult['is_valid'] ? 'rgba(52,211,153,.25)' : 'rgba(239,68,68,.25)' }};border-radius:1rem;padding:1.25rem;margin-bottom:1.5rem;">
        <div style="display:flex;justify-content:space-between;align-items:center;">
            <h4 style="font-size:.95rem;font-weight:800;color:{{ $validationResult['is_valid'] ? '#34d399' : '#f87171' }};margin:0;display:flex;align-items:center;gap:.5rem;">
                {{ $validationResult['is_valid'] ? '✅ Validation Assistant Passed' : '⚠️ Assessment Validation Errors Detected' }}
            </h4>
            <span style="font-size:.78rem;font-weight:700;color:#94a3b8;">
                {{ count($validationResult['questions']) }} Total Linked Questions
            </span>
        </div>
        @if(!$validationResult['is_valid'])
        <div style="margin-top:.75rem;font-size:.82rem;color:#cbd5e1;display:flex;flex-direction:column;gap:.35rem;">
            @foreach($validationResult['errors'] as $err)
            <div>• {{ $err }}</div>
            @endforeach
        </div>
        @else
        <div style="margin-top:.35rem;font-size:.8rem;color:#94a3b8;">
            All questions contain valid prompt stems, choice options, and correct answer selections. Ready for submission.
        </div>
        @endif
    </div>
    @endif

    {{-- Main 2-Column Grid Layout --}}
    <div style="display:grid;grid-template-columns:1fr 340px;gap:1.5rem;align-items:start;">

        {{-- Left Primary Column: Metadata & Revision Summary --}}
        <div>
            {{-- Repository Manager Feedback Callout --}}
            @if(in_array($test->status, ['needs_revision', 'revision_requested']) || $latestFeedbackLog)
            <div style="background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.3);border-radius:1rem;padding:1.35rem;margin-bottom:1.5rem;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.5rem;">
                    <div style="font-size:.78rem;font-weight:800;color:#fbbf24;text-transform:uppercase;letter-spacing:.05em;display:flex;align-items:center;gap:.4rem;">
                        💬 Repository Manager Reviewer Feedback
                    </div>
                    <div style="font-size:.75rem;color:#94a3b8;">
                        {{ $latestFeedbackLog?->created_at?->diffForHumans() ?? 'Recently' }}
                    </div>
                </div>
                <div style="font-size:.95rem;color:#f1f5f9;line-height:1.6;font-weight:500;">
                    "{{ $latestFeedbackLog?->approval_note ?? 'Revision requested by Repository Manager. Please review test duration, section structures, and question answer keys before resubmitting.' }}"
                </div>
                <div style="font-size:.78rem;color:#cbd5e1;margin-top:.75rem;display:flex;align-items:center;gap:.4rem;">
                    👤 Reviewer: <strong>{{ $latestFeedbackLog?->reviewer?->name ?? 'Repository Manager' }}</strong>
                </div>
            </div>
            @endif

            {{-- Assessment Metadata Editor (TASK 1) --}}
            <div style="background:#0f172a;border:1px solid #1e293b;border-radius:1.25rem;padding:1.5rem;margin-bottom:1.5rem;">
                <h3 style="font-size:1.1rem;font-weight:800;color:#fff;margin:0 0 1.25rem;display:flex;align-items:center;gap:.5rem;">
                    ✏️ Assessment Authoring Editor
                </h3>

                <form id="assessment-settings-form" method="POST" action="{{ route('teacher.tests.update', $test->id) }}" style="display:flex;flex-direction:column;gap:1.25rem;">
                    @csrf
                    @method('PUT')

                    <div>
                        <label style="display:block;font-size:.82rem;font-weight:700;color:#cbd5e1;margin-bottom:.4rem;">Assessment Title</label>
                        <input type="text" name="title" value="{{ old('title', $test->title) }}" required style="width:100%;padding:.75rem 1rem;background:#1e293b;border:1px solid #334155;border-radius:.6rem;color:#fff;font-size:.9rem;font-weight:600;">
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                        <div>
                            <label style="display:block;font-size:.82rem;font-weight:700;color:#cbd5e1;margin-bottom:.4rem;">Assessment Type</label>
                            <select name="test_type" style="width:100%;padding:.75rem 1rem;background:#1e293b;border:1px solid #334155;border-radius:.6rem;color:#fff;font-size:.9rem;font-weight:600;">
                                <option value="toeic" {{ $test->test_type === 'toeic' ? 'selected' : '' }}>TOEIC Simulation</option>
                                <option value="toefl" {{ $test->test_type === 'toefl' ? 'selected' : '' }}>TOEFL iBT / ITP</option>
                                <option value="ielts" {{ $test->test_type === 'ielts' ? 'selected' : '' }}>IELTS Academic</option>
                                <option value="general" {{ $test->test_type === 'general' ? 'selected' : '' }}>General English</option>
                            </select>
                        </div>
                        <div>
                            <label style="display:block;font-size:.82rem;font-weight:700;color:#cbd5e1;margin-bottom:.4rem;">Duration (Minutes)</label>
                            <input type="number" name="duration_minutes" value="{{ old('duration_minutes', $test->duration_minutes) }}" required min="1" style="width:100%;padding:.75rem 1rem;background:#1e293b;border:1px solid #334155;border-radius:.6rem;color:#fff;font-size:.9rem;font-weight:600;">
                        </div>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                        <div>
                            <label style="display:block;font-size:.82rem;font-weight:700;color:#cbd5e1;margin-bottom:.4rem;">Pass Score Threshold</label>
                            <input type="number" name="pass_score" value="{{ old('pass_score', $test->pass_score) }}" required min="0" style="width:100%;padding:.75rem 1rem;background:#1e293b;border:1px solid #334155;border-radius:.6rem;color:#fff;font-size:.9rem;font-weight:600;">
                        </div>
                        <div>
                            <label style="display:block;font-size:.82rem;font-weight:700;color:#cbd5e1;margin-bottom:.4rem;">Scoring Method</label>
                            <select name="scoring_method" style="width:100%;padding:.75rem 1rem;background:#1e293b;border:1px solid #334155;border-radius:.6rem;color:#fff;font-size:.9rem;font-weight:600;">
                                <option value="automatic" {{ ($test->scoring_method?->value ?? $test->scoring_method ?? 'automatic') === 'automatic' ? 'selected' : '' }}>Automatic (Automatically scored; no examiner required)</option>
                                <option value="human" {{ ($test->scoring_method?->value ?? $test->scoring_method) === 'human' ? 'selected' : '' }}>Human (Requires examiner evaluation before final result)</option>
                                <option value="hybrid" {{ ($test->scoring_method?->value ?? $test->scoring_method) === 'hybrid' ? 'selected' : '' }}>Hybrid (Automatic scoring plus examiner evaluation)</option>
                            </select>
                        </div>
                    </div>

                    <div style="display:flex;gap:1rem;margin-top:.5rem;flex-wrap:wrap;align-items:center;">
                        {{-- Save Draft Button --}}
                        <button type="submit" style="padding:.75rem 1.5rem;background:#334155;color:#fff;border:none;border-radius:.65rem;font-size:.88rem;font-weight:800;cursor:pointer;display:inline-flex;align-items:center;gap:.4rem;">
                            💾 Save Settings Draft
                        </button>
                    </form>

                    {{-- Resubmit Button --}}
                    @if(in_array($test->status, ['needs_revision', 'revision_requested', 'draft']))
                    <form id="resubmit-assessment-form" method="POST" action="{{ route('teacher.tests.resubmit', $test->id) }}" style="display:inline;">
                        @csrf
                        @if($validationResult['is_valid'])
                        <button type="button" 
                                onclick="openResubmitModal()" 
                                style="padding:.75rem 1.5rem;background:#6366f1;color:#fff;border:none;border-radius:.65rem;font-size:.88rem;font-weight:800;cursor:pointer;display:inline-flex;align-items:center;gap:.4rem;box-shadow:0 4px 14px rgba(99,102,241,.35);">
                            🚀 {{ $test->status === 'draft' ? 'Submit for Review' : 'Resubmit for Review' }}
                        </button>
                        @else
                        <button type="button" disabled style="padding:.75rem 1.5rem;background:#1e293b;color:#64748b;border:1px solid #334155;border-radius:.65rem;font-size:.88rem;font-weight:700;cursor:not-allowed;" title="Resolve all validation issues to enable submission.">
                            🚫 Submission Disabled (Validation Required)
                        </button>
                        @endif
                    </form>
                    @endif
                    </div>
            </div>

            {{-- TASK 2 & TASK 4: Revision Summary & Lazy Question Loading Explorer --}}
            <div style="background:#0f172a;border:1px solid #1e293b;border-radius:1.25rem;padding:1.5rem;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;flex-wrap:wrap;gap:1rem;">
                    <div>
                        <h3 style="font-size:1.1rem;font-weight:800;color:#fff;margin:0;display:flex;align-items:center;gap:.5rem;">
                            📋 Assessment Questions &amp; Sections — Progressive Revision Summary
                        </h3>
                        <p style="font-size:.78rem;color:#94a3b8;margin:.25rem 0 0;">
                            Organize institutional master questions and authored items for this assessment.
                        </p>
                    </div>

                    @if(in_array($test->status, ['draft', 'needs_revision', 'revision_requested', 'rejected']))
                    <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
                        <button type="button" onclick="openAttachMasterModal()" style="padding:.5rem .9rem;background:#4f46e5;color:#fff;font-weight:800;font-size:.78rem;border:none;border-radius:.55rem;cursor:pointer;display:inline-flex;align-items:center;gap:.35rem;box-shadow:0 2px 8px rgba(79,70,229,.3);">
                            🏛️ + Add from Question Bank
                        </button>
                        <button type="button" onclick="openCreateAuthoredQuestionModal()" style="padding:.5rem .9rem;background:#10b981;color:#fff;font-weight:800;font-size:.78rem;border:none;border-radius:.55rem;cursor:pointer;display:inline-flex;align-items:center;gap:.35rem;box-shadow:0 2px 8px rgba(16,185,129,.3);">
                            ✏️ + Add New Question
                        </button>
                        <button type="button" onclick="openAddSectionModal()" style="padding:.5rem .9rem;background:#334155;color:#e2e8f0;font-weight:700;font-size:.78rem;border:1px solid #475569;border-radius:.55rem;cursor:pointer;display:inline-flex;align-items:center;gap:.35rem;">
                            📑 + Add Section
                        </button>
                    </div>
                    @endif
                </div>

                @if(isset($validationResult) && count($validationResult['questions']) > 0)
                <div style="display:flex;flex-direction:column;gap:1rem;">
                    @php
                        $revisionQuestions = in_array($test->status, ['needs_revision', 'revision_requested']) 
                            ? array_filter($validationResult['questions'], fn($item) => !empty($item['warnings']))
                            : $validationResult['questions'];
                        if (empty($revisionQuestions)) {
                            $revisionQuestions = $validationResult['questions'];
                        }
                    @endphp
                    @foreach($revisionQuestions as $qItem)
                    @php
                        $q = $qItem['question'];
                        $hasWarning = !empty($qItem['warnings']);
                        $isMaster = !empty($q->question_bank_id);
                    @endphp
                    <div style="background:#1e293b;border:1px solid {{ $hasWarning ? 'rgba(245,158,11,.45)' : '#334155' }};border-radius:.85rem;padding:1.1rem;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;">
                        <div style="flex:1;min-width:260px;">
                            <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.35rem;flex-wrap:wrap;">
                                <span style="font-size:.78rem;font-weight:800;color:#818cf8;">Question #{{ $qItem['number'] }}</span>
                                <span style="font-size:.72rem;color:#cbd5e1;background:#0f172a;padding:.15rem .45rem;border-radius:.3rem;">Section: {{ $qItem['section']->title }}</span>
                                @if($isMaster)
                                <span style="font-size:.72rem;font-weight:700;color:#a5b4fc;background:rgba(99,102,241,.15);border:1px solid rgba(99,102,241,.3);padding:.15rem .45rem;border-radius:.3rem;">
                                    🏛️ Governed Master Question
                                </span>
                                @else
                                <span style="font-size:.72rem;font-weight:700;color:#38bdf8;background:rgba(56,189,248,.15);border:1px solid rgba(56,189,248,.3);padding:.15rem .45rem;border-radius:.3rem;">
                                    ✍️ Assessment-Authored
                                </span>
                                @endif
                                @if($hasWarning)
                                <span style="font-size:.72rem;font-weight:800;color:#fbbf24;background:rgba(245,158,11,.15);border:1px solid rgba(245,158,11,.3);padding:.15rem .45rem;border-radius:.3rem;">
                                    🟡 {{ implode(' | ', $qItem['warnings']) }}
                                </span>
                                @else
                                <span style="font-size:.72rem;font-weight:700;color:#34d399;background:rgba(52,211,153,.15);padding:.15rem .45rem;border-radius:.3rem;">
                                    🟢 Valid
                                </span>
                                @endif
                            </div>
                            <div style="font-size:.88rem;font-weight:700;color:#f1f5f9;">
                                {{ \Illuminate\Support\Str::limit($q->prompt ?? '(Empty Stem)', 75) }}
                            </div>
                        </div>
                        <div style="display:flex;align-items:center;gap:.45rem;flex-wrap:wrap;">
                            @if($isMaster)
                                <button type="button" onclick="openTeacherRequestRevisionModal('{{ $q->question_bank_id }}', '{{ $q->id }}', '{{ addslashes(Str::limit($q->prompt, 60)) }}')" style="padding:.45rem .8rem;background:rgba(245,158,11,.15);color:#fbbf24;border:1px solid rgba(245,158,11,.4);border-radius:.55rem;font-size:.75rem;font-weight:800;cursor:pointer;display:inline-flex;align-items:center;gap:.3rem;">
                                    🛠 Request Master Revision
                                </button>
                                <a href="{{ route('teacher.tests.edit-question', ['test' => $test->id, 'question' => $q->id]) }}" style="padding:.45rem .8rem;background:#334155;color:#e2e8f0;border:1px solid #475569;border-radius:.55rem;font-size:.75rem;font-weight:700;text-decoration:none;display:inline-flex;align-items:center;gap:.3rem;">
                                    🔒 Governed Master
                                </a>
                            @else
                                <a href="{{ route('teacher.tests.edit-question', ['test' => $test->id, 'question' => $q->id]) }}" style="padding:.45rem .85rem;background:{{ $hasWarning ? '#f59e0b' : '#6366f1' }};color:#fff;border-radius:.55rem;font-size:.75rem;font-weight:800;text-decoration:none;display:inline-flex;align-items:center;gap:.3rem;box-shadow:0 2px 8px rgba(0,0,0,.25);">
                                    {{ $hasWarning ? '✏️ Fix Issue' : '✏️ Edit Question' }}
                                </a>
                            @endif

                            @if(in_array($test->status, ['draft', 'needs_revision', 'revision_requested', 'rejected']))
                            <form action="{{ route('teacher.tests.destroy-question', ['test' => $test->id, 'question' => $q->id]) }}" method="POST" style="display:inline;" onsubmit="event.preventDefault(); iapConfirm({ title: 'Remove Question?', message: 'Remove this question from the assessment?', confirmText: 'Remove', variant: 'danger', form: this });">
                                @csrf
                                @method('DELETE')
                                <button type="submit" style="padding:.45rem .65rem;background:rgba(239,68,68,.15);color:#f87171;border:1px solid rgba(239,68,68,.3);border-radius:.55rem;font-size:.75rem;font-weight:700;cursor:pointer;">
                                    🗑 Remove
                                </button>
                            </form>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
                @else
                <div style="font-size:.85rem;color:#94a3b8;padding:2.5rem 1rem;text-align:center;background:#1e293b;border-radius:.85rem;border:1px dashed #334155;">
                    <div style="font-size:2rem;margin-bottom:.5rem;">📝</div>
                    <div style="font-weight:700;color:#cbd5e1;margin-bottom:.35rem;">No questions linked to this assessment yet</div>
                    <p style="font-size:.78rem;color:#64748b;max-width:400px;margin:0 auto 1rem;">
                        Attach existing Master Questions from institutional Question Banks or author new custom questions for this test.
                    </p>
                    @if(in_array($test->status, ['draft', 'needs_revision', 'revision_requested', 'rejected']))
                    <div style="display:flex;justify-content:center;gap:.5rem;">
                        <button type="button" onclick="openAttachMasterModal()" style="padding:.45rem .9rem;background:#4f46e5;color:#fff;font-weight:800;font-size:.78rem;border:none;border-radius:.5rem;cursor:pointer;">
                            🏛️ + Add from Question Bank
                        </button>
                        <button type="button" onclick="openCreateAuthoredQuestionModal()" style="padding:.45rem .9rem;background:#10b981;color:#fff;font-weight:800;font-size:.78rem;border:none;border-radius:.5rem;cursor:pointer;">
                            ✏️ + Add New Question
                        </button>
                    </div>
                    @endif
                </div>
                @endif
            </div>
        </div>

        {{-- Right Sidebar: Metadata & Workflow History Timeline --}}
        <div>
            {{-- Assessment Summary Card --}}
            <div style="background:#0f172a;border:1px solid #1e293b;border-radius:1.25rem;padding:1.35rem;margin-bottom:1.5rem;">
                <h4 style="font-size:.9rem;font-weight:800;color:#fff;margin:0 0 1rem;text-transform:uppercase;letter-spacing:.05em;">
                    📊 Assessment Metrics
                </h4>

                <div style="display:flex;flex-direction:column;gap:.85rem;font-size:.82rem;">
                    <div style="display:flex;justify-content:space-between;border-bottom:1px solid #1e293b;padding-bottom:.6rem;">
                        <span style="color:#94a3b8;">Questions</span>
                        <strong style="color:#818cf8;">{{ $test->sections->sum(fn($s) => $s->testQuestions->count()) }}</strong>
                    </div>
                    <div style="display:flex;justify-content:space-between;border-bottom:1px solid #1e293b;padding-bottom:.6rem;">
                        <span style="color:#94a3b8;">Sections</span>
                        <strong style="color:#cbd5e1;">{{ $test->sections->count() }}</strong>
                    </div>
                    <div style="display:flex;justify-content:space-between;border-bottom:1px solid #1e293b;padding-bottom:.6rem;">
                        <span style="color:#94a3b8;">Duration</span>
                        <strong style="color:#cbd5e1;">{{ $test->duration_minutes }} Mins</strong>
                    </div>
                    <div style="display:flex;justify-content:space-between;border-bottom:1px solid #1e293b;padding-bottom:.6rem;">
                        <span style="color:#94a3b8;">Pass Threshold</span>
                        <strong style="color:#cbd5e1;">{{ $test->pass_score }} Points</strong>
                    </div>
                    <div style="display:flex;justify-content:space-between;border-bottom:1px solid #1e293b;padding-bottom:.6rem;">
                        <span style="color:#94a3b8;">Author</span>
                        <strong style="color:#cbd5e1;">{{ $test->creator?->name ?? 'Teacher' }}</strong>
                    </div>
                    <div style="display:flex;justify-content:space-between;">
                        <span style="color:#94a3b8;">Last Updated</span>
                        <strong style="color:#cbd5e1;">{{ $test->updated_at?->diffForHumans() }}</strong>
                    </div>
                </div>
            </div>

            {{-- Workflow History Timeline --}}
            <div style="background:#0f172a;border:1px solid #1e293b;border-radius:1.25rem;padding:1.35rem;">
                <h4 style="font-size:.9rem;font-weight:800;color:#fff;margin:0 0 1.25rem;text-transform:uppercase;letter-spacing:.05em;">
                    ⏱ Governance Timeline
                </h4>

                <div style="display:flex;flex-direction:column;gap:1.25rem;position:relative;padding-left:1.2rem;border-left:2px solid #1e293b;">
                    @foreach($workflowTimeline as $item)
                    <div style="position:relative;">
                        <div style="position:absolute;left:-1.65rem;top:.2rem;width:.8rem;height:.8rem;border-radius:50%;background:{{ $item['status'] === 'active' ? '#fbbf24' : ($item['status'] === 'completed' ? '#34d399' : '#334155') }};border:2px solid #0f172a;"></div>
                        <div style="font-size:.85rem;font-weight:700;color:{{ $item['status'] === 'active' ? '#fbbf24' : ($item['status'] === 'completed' ? '#f1f5f9' : '#64748b') }};">
                            {{ $item['step'] }}
                        </div>
                        @if($item['date'])
                        <div style="font-size:.72rem;color:#64748b;margin-top:.15rem;">
                            {{ $item['date'] }}
                        </div>
                        @endif
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Custom IAP Resubmission Confirmation Modal --}}
<div id="resubmit-confirmation-modal" 
     class="hidden fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4"
     role="dialog"
     aria-modal="true"
     aria-labelledby="resubmit-modal-title">
    
    <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-lg w-full p-6 shadow-2xl space-y-5 text-left transform transition-all">
        
        {{-- Modal Header --}}
        <div class="flex justify-between items-start">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-indigo-600/20 border border-indigo-500/30 text-indigo-400 flex items-center justify-center font-extrabold text-lg shadow-inner flex-shrink-0">
                    🚀
                </div>
                <div>
                    <h3 id="resubmit-modal-title" class="text-base font-bold text-white leading-tight">
                        Resubmit Assessment for Review?
                    </h3>
                    <p class="text-xs text-indigo-400 font-semibold mt-0.5">
                        {{ $test->title }}
                    </p>
                </div>
            </div>
            <button type="button" 
                    onclick="closeResubmitModal()" 
                    class="text-slate-400 hover:text-white transition-colors p-1 rounded-lg hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-700" 
                    aria-label="Close modal">
                ✕
            </button>
        </div>

        {{-- Modal Body Messages --}}
        <div class="space-y-3 text-xs text-slate-300">
            <p class="leading-relaxed">
                Your assessment has passed the Validation Assistant checks and is ready to be resubmitted to the Repository Manager for governance review.
            </p>
            <div class="p-3 bg-slate-950/70 border border-slate-800 rounded-xl text-slate-400 flex items-start gap-2.5">
                <span class="text-indigo-400 text-sm flex-shrink-0">ℹ️</span>
                <span class="leading-normal text-[11px]">
                    After resubmission, the Repository Manager will review the assessment and its linked repository requirements.
                </span>
            </div>
        </div>

        {{-- Modal Footer Actions --}}
        <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-800/80">
            <button type="button" 
                    onclick="closeResubmitModal()" 
                    class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-200 font-semibold text-xs rounded-xl border border-slate-700 transition-colors focus:outline-none focus:ring-2 focus:ring-slate-600">
                Cancel
            </button>
            <button type="button" 
                    id="confirm-resubmit-btn"
                    onclick="confirmAndSubmitResubmit()" 
                    class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-500 active:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-lg shadow-indigo-600/30 transition-all focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:ring-offset-2 focus:ring-offset-slate-900 inline-flex items-center gap-1.5">
                🚀 {{ $test->status === 'draft' ? 'Submit for Review' : 'Resubmit for Review' }}
            </button>
        </div>
    </div>
</div>

{{-- Modal 1: Attach Master Question from Question Bank --}}
<div id="attach-master-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:9999;align-items:center;justify-content:center;padding:1rem;" onclick="closeAttachMasterModal(event)">
    <div style="background:#0f172a;border:1px solid #334155;border-radius:1rem;max-width:600px;width:100%;padding:1.75rem;box-shadow:0 20px 50px rgba(0,0,0,.5);" onclick="event.stopPropagation()">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;">
            <div style="font-size:1.15rem;font-weight:800;color:#fff;display:flex;align-items:center;gap:.5rem;">
                <span>🏛️</span> Attach Master Question from Question Bank
            </div>
            <button type="button" onclick="closeAttachMasterModal()" style="background:none;border:none;color:#94a3b8;font-size:1.25rem;cursor:pointer;">×</button>
        </div>

        <form method="POST" action="{{ route('teacher.tests.attach-master-question', $test->id) }}">
            @csrf
            <div style="margin-bottom:1rem;">
                <label style="display:block;font-size:.75rem;font-weight:700;color:#cbd5e1;margin-bottom:.3rem;">Target Section <span style="color:#f43f5e;">*</span></label>
                <select name="test_section_id" required style="width:100%;padding:.6rem;background:#1e293b;border:1px solid #334155;border-radius:.5rem;color:#fff;font-size:.82rem;">
                    @foreach($test->sections as $sec)
                    <option value="{{ $sec->id }}">{{ $sec->title }} ({{ $sec->testQuestions->count() }} items)</option>
                    @endforeach
                </select>
            </div>

            <div style="margin-bottom:1.5rem;">
                <label style="display:block;font-size:.75rem;font-weight:700;color:#cbd5e1;margin-bottom:.3rem;">Select Master Question <span style="color:#f43f5e;">*</span></label>
                <select name="question_id" required style="width:100%;padding:.6rem;background:#1e293b;border:1px solid #334155;border-radius:.5rem;color:#fff;font-size:.82rem;">
                    <option value="">-- Choose from Published Question Banks --</option>
                    @if(isset($publishedQuestionBanks))
                    @foreach($publishedQuestionBanks as $bank)
                        @php $bankType = is_object($bank->test_type) ? $bank->test_type->value : (string) $bank->test_type; @endphp
                        <optgroup label="🏛️ {{ $bank->title }} ({{ strtoupper($bankType) }})">
                            @foreach($bank->questions as $bq)
                            <option value="{{ $bq->id }}">[{{ strtoupper($bq->difficulty?->value ?? $bq->difficulty ?? 'MED') }}] {{ \Illuminate\Support\Str::limit($bq->prompt, 70) }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                    @endif
                </select>
            </div>

            <div style="display:flex;justify-content:flex-end;gap:.75rem;">
                <button type="button" onclick="closeAttachMasterModal()" style="padding:.6rem 1.1rem;background:#334155;color:#fff;border:none;border-radius:.5rem;font-size:.82rem;font-weight:700;cursor:pointer;">Cancel</button>
                <button type="submit" style="padding:.6rem 1.25rem;background:#4f46e5;color:#fff;border:none;border-radius:.5rem;font-size:.82rem;font-weight:800;cursor:pointer;box-shadow:0 4px 12px rgba(79,70,229,.3);">Attach Master Question</button>
            </div>
        </form>
    </div>
</div>

{{-- Modal 2: Create Assessment-Authored Question --}}
<div id="create-authored-question-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:9999;align-items:center;justify-content:center;padding:1rem;" onclick="closeCreateAuthoredQuestionModal(event)">
    <div style="background:#0f172a;border:1px solid #334155;border-radius:1rem;max-width:650px;width:100%;padding:1.75rem;box-shadow:0 20px 50px rgba(0,0,0,.5);max-height:90vh;overflow-y:auto;" onclick="event.stopPropagation()">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;">
            <div style="font-size:1.15rem;font-weight:800;color:#fff;display:flex;align-items:center;gap:.5rem;">
                <span>✏️</span> Create Assessment-Authored Question
            </div>
            <button type="button" onclick="closeCreateAuthoredQuestionModal()" style="background:none;border:none;color:#94a3b8;font-size:1.25rem;cursor:pointer;">×</button>
        </div>

        <form method="POST" action="{{ route('teacher.tests.create-question', $test->id) }}">
            @csrf
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem;">
                <div>
                    <label style="display:block;font-size:.75rem;font-weight:700;color:#cbd5e1;margin-bottom:.3rem;">Target Section <span style="color:#f43f5e;">*</span></label>
                    <select name="test_section_id" required style="width:100%;padding:.6rem;background:#1e293b;border:1px solid #334155;border-radius:.5rem;color:#fff;font-size:.82rem;">
                        @foreach($test->sections as $sec)
                        <option value="{{ $sec->id }}">{{ $sec->title }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="display:block;font-size:.75rem;font-weight:700;color:#cbd5e1;margin-bottom:.3rem;">Question Type <span style="color:#f43f5e;">*</span></label>
                    <select name="question_type" required style="width:100%;padding:.6rem;background:#1e293b;border:1px solid #334155;border-radius:.5rem;color:#fff;font-size:.82rem;">
                        <option value="multiple_choice">Multiple Choice</option>
                        <option value="single_choice">Single Choice</option>
                        <option value="short_answer">Short Answer</option>
                        <option value="essay">Essay</option>
                    </select>
                </div>
            </div>

            <div style="margin-bottom:1rem;">
                <label style="display:block;font-size:.75rem;font-weight:700;color:#cbd5e1;margin-bottom:.3rem;">Question Prompt / Stem <span style="color:#f43f5e;">*</span></label>
                <textarea name="prompt" required rows="3" placeholder="Enter the complete question prompt..." style="width:100%;padding:.6rem;background:#1e293b;border:1px solid #334155;border-radius:.5rem;color:#fff;font-size:.82rem;"></textarea>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem;">
                <div>
                    <label style="display:block;font-size:.75rem;font-weight:700;color:#cbd5e1;margin-bottom:.3rem;">Difficulty</label>
                    <select name="difficulty" style="width:100%;padding:.6rem;background:#1e293b;border:1px solid #334155;border-radius:.5rem;color:#fff;font-size:.82rem;">
                        <option value="easy">Easy</option>
                        <option value="medium" selected>Medium</option>
                        <option value="hard">Hard</option>
                    </select>
                </div>
                <div>
                    <label style="display:block;font-size:.75rem;font-weight:700;color:#cbd5e1;margin-bottom:.3rem;">Points</label>
                    <input type="number" name="points" value="1" min="1" required style="width:100%;padding:.6rem;background:#1e293b;border:1px solid #334155;border-radius:.5rem;color:#fff;font-size:.82rem;">
                </div>
            </div>

            <div style="margin-bottom:1rem;background:#1e293b;padding:1rem;border-radius:.75rem;border:1px solid #334155;">
                <label style="display:block;font-size:.75rem;font-weight:800;color:#e2e8f0;margin-bottom:.5rem;">Multiple Choice Options &amp; Correct Answer</label>
                <div style="display:flex;flex-direction:column;gap:.5rem;">
                    <div style="display:flex;align-items:center;gap:.5rem;">
                        <input type="radio" name="correct_choice" value="0" checked style="accent-color:#10b981;">
                        <span style="font-weight:800;color:#818cf8;font-size:.8rem;width:20px;">A</span>
                        <input type="text" name="choices[]" placeholder="Option A text" required style="flex:1;padding:.5rem;background:#0f172a;border:1px solid #334155;border-radius:.4rem;color:#fff;font-size:.8rem;">
                    </div>
                    <div style="display:flex;align-items:center;gap:.5rem;">
                        <input type="radio" name="correct_choice" value="1" style="accent-color:#10b981;">
                        <span style="font-weight:800;color:#818cf8;font-size:.8rem;width:20px;">B</span>
                        <input type="text" name="choices[]" placeholder="Option B text" required style="flex:1;padding:.5rem;background:#0f172a;border:1px solid #334155;border-radius:.4rem;color:#fff;font-size:.8rem;">
                    </div>
                    <div style="display:flex;align-items:center;gap:.5rem;">
                        <input type="radio" name="correct_choice" value="2" style="accent-color:#10b981;">
                        <span style="font-weight:800;color:#818cf8;font-size:.8rem;width:20px;">C</span>
                        <input type="text" name="choices[]" placeholder="Option C text" style="flex:1;padding:.5rem;background:#0f172a;border:1px solid #334155;border-radius:.4rem;color:#fff;font-size:.8rem;">
                    </div>
                    <div style="display:flex;align-items:center;gap:.5rem;">
                        <input type="radio" name="correct_choice" value="3" style="accent-color:#10b981;">
                        <span style="font-weight:800;color:#818cf8;font-size:.8rem;width:20px;">D</span>
                        <input type="text" name="choices[]" placeholder="Option D text" style="flex:1;padding:.5rem;background:#0f172a;border:1px solid #334155;border-radius:.4rem;color:#fff;font-size:.8rem;">
                    </div>
                </div>
            </div>

            <div style="margin-bottom:1.5rem;">
                <label style="display:block;font-size:.75rem;font-weight:700;color:#cbd5e1;margin-bottom:.3rem;">Answer Explanation / Rationale</label>
                <textarea name="explanation" rows="2" placeholder="Optional explanation..." style="width:100%;padding:.6rem;background:#1e293b;border:1px solid #334155;border-radius:.5rem;color:#fff;font-size:.82rem;"></textarea>
            </div>

            <div style="display:flex;justify-content:flex-end;gap:.75rem;">
                <button type="button" onclick="closeCreateAuthoredQuestionModal()" style="padding:.6rem 1.1rem;background:#334155;color:#fff;border:none;border-radius:.5rem;font-size:.82rem;font-weight:700;cursor:pointer;">Cancel</button>
                <button type="submit" style="padding:.6rem 1.25rem;background:#10b981;color:#fff;border:none;border-radius:.5rem;font-size:.82rem;font-weight:800;cursor:pointer;box-shadow:0 4px 12px rgba(16,185,129,.3);">Save Question</button>
            </div>
        </form>
    </div>
</div>

{{-- Modal 3: Add Section --}}
<div id="add-section-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:9999;align-items:center;justify-content:center;padding:1rem;" onclick="closeAddSectionModal(event)">
    <div style="background:#0f172a;border:1px solid #334155;border-radius:1rem;max-width:480px;width:100%;padding:1.75rem;box-shadow:0 20px 50px rgba(0,0,0,.5);" onclick="event.stopPropagation()">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;">
            <div style="font-size:1.15rem;font-weight:800;color:#fff;display:flex;align-items:center;gap:.5rem;">
                <span>📑</span> Add Assessment Section
            </div>
            <button type="button" onclick="closeAddSectionModal()" style="background:none;border:none;color:#94a3b8;font-size:1.25rem;cursor:pointer;">×</button>
        </div>

        <form method="POST" action="{{ route('teacher.tests.add-section', $test->id) }}">
            @csrf
            <div style="margin-bottom:1.5rem;">
                <label style="display:block;font-size:.75rem;font-weight:700;color:#cbd5e1;margin-bottom:.3rem;">Section Title <span style="color:#f43f5e;">*</span></label>
                <input type="text" name="title" required placeholder="e.g. Section 2: Reading Comprehension" style="width:100%;padding:.6rem;background:#1e293b;border:1px solid #334155;border-radius:.5rem;color:#fff;font-size:.82rem;">
            </div>

            <div style="display:flex;justify-content:flex-end;gap:.75rem;">
                <button type="button" onclick="closeAddSectionModal()" style="padding:.6rem 1.1rem;background:#334155;color:#fff;border:none;border-radius:.5rem;font-size:.82rem;font-weight:700;cursor:pointer;">Cancel</button>
                <button type="submit" style="padding:.6rem 1.25rem;background:#6366f1;color:#fff;border:none;border-radius:.5rem;font-size:.82rem;font-weight:800;cursor:pointer;">Add Section</button>
            </div>
        </form>
    </div>
</div>

{{-- Modal 4: Teacher Request Repository Revision on Master Question --}}
<div id="teacher-request-revision-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:9999;align-items:center;justify-content:center;padding:1rem;" onclick="closeTeacherRequestRevisionModal(event)">
    <div style="background:#0f172a;border:1px solid #334155;border-radius:1rem;max-width:520px;width:100%;padding:1.75rem;box-shadow:0 20px 50px rgba(0,0,0,.5);" onclick="event.stopPropagation()">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;">
            <div style="font-size:1.15rem;font-weight:800;color:#fff;display:flex;align-items:center;gap:.5rem;">
                <span>🛠</span> Request Repository Revision
            </div>
            <button type="button" onclick="closeTeacherRequestRevisionModal()" style="background:none;border:none;color:#94a3b8;font-size:1.25rem;cursor:pointer;">×</button>
        </div>

        <form method="POST" action="{{ route('teacher.repository-revisions.request') }}">
            @csrf
            <input type="hidden" id="tr-modal-bank-id" name="question_bank_id" value="">
            <input type="hidden" id="tr-modal-question-id" name="question_id" value="">

            <div style="margin-bottom:1rem;">
                <label style="display:block;font-size:.75rem;font-weight:700;color:#cbd5e1;margin-bottom:.3rem;">Target Question Item</label>
                <div id="tr-modal-target-title" style="padding:.6rem;background:#1e293b;border:1px solid #334155;border-radius:.5rem;color:#818cf8;font-size:.8rem;font-weight:700;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"></div>
            </div>

            <div style="margin-bottom:1.5rem;">
                <label style="display:block;font-size:.75rem;font-weight:700;color:#cbd5e1;margin-bottom:.3rem;">Revision Rationale / Specific Feedback <span style="color:#f43f5e;">*</span></label>
                <textarea name="notes" required rows="4" placeholder="Explain the specific issue with this Master Question (typo, wrong key, flawed passage)..." style="width:100%;padding:.6rem;background:#1e293b;border:1px solid #334155;border-radius:.5rem;color:#fff;font-size:.82rem;"></textarea>
            </div>

            <div style="display:flex;justify-content:flex-end;gap:.75rem;">
                <button type="button" onclick="closeTeacherRequestRevisionModal()" style="padding:.6rem 1.1rem;background:#334155;color:#fff;border:none;border-radius:.5rem;font-size:.82rem;font-weight:700;cursor:pointer;">Cancel</button>
                <button type="submit" style="padding:.6rem 1.25rem;background:#f59e0b;color:#fff;border:none;border-radius:.5rem;font-size:.82rem;font-weight:800;cursor:pointer;">Submit Revision Request</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openAttachMasterModal() {
        const modal = document.getElementById('attach-master-modal');
        if (modal) modal.style.display = 'flex';
    }
    function closeAttachMasterModal(e) {
        if (!e || e.target === document.getElementById('attach-master-modal')) {
            const modal = document.getElementById('attach-master-modal');
            if (modal) modal.style.display = 'none';
        }
    }

    function openCreateAuthoredQuestionModal() {
        const modal = document.getElementById('create-authored-question-modal');
        if (modal) modal.style.display = 'flex';
    }
    function closeCreateAuthoredQuestionModal(e) {
        if (!e || e.target === document.getElementById('create-authored-question-modal')) {
            const modal = document.getElementById('create-authored-question-modal');
            if (modal) modal.style.display = 'none';
        }
    }

    function openAddSectionModal() {
        const modal = document.getElementById('add-section-modal');
        if (modal) modal.style.display = 'flex';
    }
    function closeAddSectionModal(e) {
        if (!e || e.target === document.getElementById('add-section-modal')) {
            const modal = document.getElementById('add-section-modal');
            if (modal) modal.style.display = 'none';
        }
    }

    function openTeacherRequestRevisionModal(bankId, questionId, targetTitle) {
        document.getElementById('tr-modal-bank-id').value = bankId || '';
        document.getElementById('tr-modal-question-id').value = questionId || '';
        document.getElementById('tr-modal-target-title').innerText = targetTitle || 'Master Question Item';
        const modal = document.getElementById('teacher-request-revision-modal');
        if (modal) modal.style.display = 'flex';
    }
    function closeTeacherRequestRevisionModal(e) {
        if (!e || e.target === document.getElementById('teacher-request-revision-modal')) {
            const modal = document.getElementById('teacher-request-revision-modal');
            if (modal) modal.style.display = 'none';
        }
    }

    function openResubmitModal() {
        const modal = document.getElementById('resubmit-confirmation-modal');
        if (modal) {
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            const confirmBtn = document.getElementById('confirm-resubmit-btn');
            if (confirmBtn) confirmBtn.focus();
        }
    }

    function closeResubmitModal() {
        const modal = document.getElementById('resubmit-confirmation-modal');
        if (modal) {
            modal.classList.add('hidden');
            document.body.style.overflow = '';
        }
    }

    function confirmAndSubmitResubmit() {
        const form = document.getElementById('resubmit-assessment-form');
        const btn = document.getElementById('confirm-resubmit-btn');
        if (btn) {
            btn.disabled = true;
            btn.classList.add('opacity-75', 'cursor-not-allowed');
            btn.innerHTML = '🚀 Submitting...';
        }
        if (form) {
            form.submit();
        }
    }

    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeResubmitModal();
            closeAttachMasterModal();
            closeCreateAuthoredQuestionModal();
            closeAddSectionModal();
            closeTeacherRequestRevisionModal();
        }
    });
</script>
@endsection
