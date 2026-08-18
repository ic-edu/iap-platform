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
                    <h3 style="font-size:1.1rem;font-weight:800;color:#fff;margin:0;display:flex;align-items:center;gap:.5rem;">
                        📋 Progressive Revision Summary
                    </h3>
                    <div style="font-size:.78rem;color:#818cf8;background:rgba(99,102,241,.12);padding:.3rem .75rem;border-radius:.5rem;font-weight:700;">
                        ⚡ Lightweight On-Demand Question Loading
                    </div>
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
                    @endphp
                    <div style="background:#1e293b;border:1px solid {{ $hasWarning ? 'rgba(245,158,11,.45)' : '#334155' }};border-radius:.85rem;padding:1.1rem;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;">
                        <div>
                            <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.35rem;">
                                <span style="font-size:.78rem;font-weight:800;color:#818cf8;">Question #{{ $qItem['number'] }}</span>
                                <span style="font-size:.72rem;color:#cbd5e1;background:#0f172a;padding:.15rem .45rem;border-radius:.3rem;">Section: {{ $qItem['section']->title }}</span>
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
                                {{ \Illuminate\Support\Str::limit($q->prompt ?? '(Empty Stem)', 60) }}
                            </div>
                        </div>
                        <div>
                            {{-- TASK 6: Direct Jump to Question Editor --}}
                            <a href="{{ route('teacher.tests.edit-question', ['test' => $test->id, 'question' => $q->id]) }}" style="padding:.5rem 1rem;background:{{ $hasWarning ? '#f59e0b' : '#6366f1' }};color:#fff;border-radius:.55rem;font-size:.78rem;font-weight:800;text-decoration:none;display:inline-flex;align-items:center;gap:.4rem;box-shadow:0 2px 8px rgba(0,0,0,.25);">
                                {{ $hasWarning ? '✏️ Edit Question' : '🔍 Open Question' }}
                            </a>
                        </div>
                    </div>
                    @endforeach
                </div>
                @else
                <div style="font-size:.85rem;color:#94a3b8;padding:1rem;text-align:center;">
                    No questions linked to this assessment yet.
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

<script>
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
            const modal = document.getElementById('resubmit-confirmation-modal');
            if (modal && !modal.classList.contains('hidden')) {
                closeResubmitModal();
            }
        }
    });
</script>
@endsection
