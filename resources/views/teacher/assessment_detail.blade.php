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

                    <div>
                        <label style="display:block;font-size:.82rem;font-weight:700;color:#cbd5e1;margin-bottom:.4rem;">
                            General Assessment Introduction / Candidate Instructions
                            <span style="font-size:.72rem;color:#94a3b8;font-weight:normal;margin-left:.4rem;">(Shown to candidates on pre-test instruction screen before timed session)</span>
                        </label>
                        <textarea name="instructions" rows="4" placeholder="e.g. Welcome to the TOEIC Listening & Reading Test. Please ensure your headphones are connected..." style="width:100%;padding:.75rem 1rem;background:#1e293b;border:1px solid #334155;border-radius:.6rem;color:#fff;font-size:.88rem;line-height:1.5;">{{ old('instructions', $test->instructions) }}</textarea>
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

                {{-- Assessment Sections & Directions Strip --}}
                @if($test->sections->isNotEmpty())
                <div style="background:#1e293b;border:1px solid #334155;border-radius:1rem;padding:1.25rem;margin-bottom:1.5rem;">
                    <div style="font-size:.8rem;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:#94a3b8;margin-bottom:.75rem;display:flex;align-items:center;gap:.4rem;">
                        <span>📑</span> Sections &amp; Directions Structure
                    </div>
                    <div style="display:flex;flex-direction:column;gap:.75rem;">
                        @foreach($test->sections as $sec)
                        <div style="background:#0f172a;border:1px solid #334155;border-radius:.75rem;padding:.9rem 1.1rem;display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:.75rem;">
                            <div style="flex:1;min-width:240px;">
                                <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.25rem;flex-wrap:wrap;">
                                    <span style="font-size:.88rem;font-weight:800;color:#fff;">{{ $sec->title }}</span>
                                    <span style="font-size:.72rem;font-weight:700;padding:.15rem .5rem;border-radius:.35rem;background:rgba(99,102,241,.15);color:#818cf8;border:1px solid rgba(99,102,241,.3);text-transform:uppercase;">
                                        {{ is_object($sec->section_type) ? $sec->section_type->label() : strtoupper($sec->section_type ?? 'Reading') }}
                                    </span>
                                    <span style="font-size:.72rem;color:#94a3b8;">
                                        • {{ $sec->testQuestions->count() }} question(s)
                                    </span>
                                </div>
                                @if($sec->instructions)
                                <div style="font-size:.78rem;color:#cbd5e1;line-height:1.45;background:#1e293b;padding:.5rem .75rem;border-radius:.5rem;border-left:3px solid #6366f1;margin-top:.4rem;">
                                    {{ $sec->instructions }}
                                </div>
                                @else
                                <div style="font-size:.75rem;color:#64748b;font-style:italic;margin-top:.2rem;">
                                    No section directions configured (optional).
                                </div>
                                @endif

                                {{-- Attached Section Media Assets --}}
                                <div style="margin-top:.75rem;padding-top:.6rem;border-top:1px solid #1e293b;">
                                    <div style="font-size:.72rem;font-weight:800;color:#94a3b8;text-transform:uppercase;letter-spacing:.05em;margin-bottom:.4rem;display:flex;align-items:center;gap:.35rem;">
                                        <span>📂</span> Section Media Assets ({{ $sec->mediaAssets->count() }})
                                    </div>
                                    @if($sec->mediaAssets->isNotEmpty())
                                    <div style="display:flex;flex-direction:column;gap:.5rem;">
                                        @foreach($sec->mediaAssets as $media)
                                        <div style="background:#1e293b;border:1px solid #334155;border-radius:.55rem;padding:.5rem .75rem;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.5rem;">
                                            <div style="display:flex;align-items:center;gap:.5rem;flex:1;min-width:200px;">
                                                <span style="font-size:1.1rem;">{{ $media->typeIcon() }}</span>
                                                <div>
                                                    <div style="font-size:.8rem;font-weight:700;color:#f8fafc;">
                                                        {{ $media->title ?? $media->original_name }}
                                                    </div>
                                                    <div style="display:flex;align-items:center;gap:.4rem;margin-top:.15rem;font-size:.7rem;color:#94a3b8;flex-wrap:wrap;">
                                                        <span style="text-transform:uppercase;font-weight:700;color:#818cf8;">{{ $media->type }}</span>
                                                        <span>• Order: {{ $media->pivot->order }}</span>
                                                        @if($media->pivot->caption)
                                                        <span style="color:#cbd5e1;font-style:italic;">• Caption: "{{ $media->pivot->caption }}"</span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                            <div style="display:flex;align-items:center;gap:.4rem;">
                                                <button type="button" 
                                                        onclick="previewAssetModal('{{ $media->id }}', '{{ addslashes($media->title ?? $media->original_name) }}', '{{ $media->type }}', '{{ route('media.preview', $media->id) }}')" 
                                                        style="padding:.3rem .6rem;background:#0f172a;color:#cbd5e1;border:1px solid #334155;border-radius:.4rem;font-size:.72rem;font-weight:700;cursor:pointer;">
                                                    👁️ Preview
                                                </button>
                                                @if(in_array($test->status, ['draft', 'needs_revision', 'revision_requested', 'rejected']))
                                                <form method="POST" action="{{ route('teacher.tests.sections.media.detach', ['test' => $test->id, 'section' => $sec->id, 'media' => $media->id]) }}" style="display:inline;" onsubmit="event.preventDefault(); iapConfirm({ title: 'Remove Media Asset?', message: 'Remove this media asset from the section?', confirmText: 'Remove', variant: 'danger', form: this });">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" style="padding:.3rem .6rem;background:rgba(244,63,94,.1);color:#fb7185;border:1px solid rgba(244,63,94,.3);border-radius:.4rem;font-size:.72rem;font-weight:700;cursor:pointer;">
                                                        ✕ Remove
                                                    </button>
                                                </form>
                                                @endif
                                            </div>
                                        </div>
                                        @endforeach
                                    </div>
                                    @else
                                    <div style="font-size:.72rem;color:#64748b;font-style:italic;">
                                        No media assets attached to this section.
                                    </div>
                                    @endif
                                </div>
                            </div>
                            @if(in_array($test->status, ['draft', 'needs_revision', 'revision_requested', 'rejected']))
                            <div style="display:flex;gap:.4rem;flex-wrap:wrap;">
                                <button type="button" 
                                        onclick="openAttachSectionMediaModal('{{ $sec->id }}', '{{ addslashes($sec->title) }}', '{{ is_object($sec->section_type) ? $sec->section_type->value : $sec->section_type }}')"
                                        style="padding:.4rem .8rem;background:#4f46e5;color:#fff;border:none;border-radius:.5rem;font-size:.75rem;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:.3rem;">
                                    📎 + Attach Media
                                </button>
                                <button type="button" 
                                        onclick="openEditSectionModal('{{ $sec->id }}', '{{ addslashes($sec->title) }}', '{{ is_object($sec->section_type) ? $sec->section_type->value : $sec->section_type }}', '{{ addslashes($sec->instructions ?? '') }}')"
                                        style="padding:.4rem .8rem;background:#334155;color:#e2e8f0;border:1px solid #475569;border-radius:.5rem;font-size:.75rem;font-weight:700;cursor:pointer;">
                                    ✏️ Edit Section
                                </button>
                            </div>
                            @endif
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

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
            <div style="margin-bottom:1.15rem;">
                <label style="display:block;font-size:.75rem;font-weight:700;color:#cbd5e1;margin-bottom:.3rem;">Section Title <span style="color:#f43f5e;">*</span></label>
                <input type="text" name="title" required placeholder="e.g. Part 1: Photographs" style="width:100%;padding:.6rem;background:#1e293b;border:1px solid #334155;border-radius:.5rem;color:#fff;font-size:.82rem;">
            </div>

            <div style="margin-bottom:1.5rem;">
                <label style="display:block;font-size:.75rem;font-weight:700;color:#cbd5e1;margin-bottom:.3rem;">Section Type</label>
                <select name="section_type" style="width:100%;padding:.6rem;background:#1e293b;border:1px solid #334155;border-radius:.5rem;color:#fff;font-size:.82rem;">
                    <option value="">⚡ Auto-detect from Section Title (Recommended)</option>
                    <option value="listening">🎧 Listening Section</option>
                    <option value="reading">📖 Reading Section</option>
                    <option value="speaking">🎙 Speaking Section</option>
                    <option value="writing">✍️ Writing Section</option>
                </select>
                <span style="font-size:.7rem;color:#64748b;display:block;margin-top:.3rem;">
                    If left on auto-detect, the system will infer the section type from standard exam terminology (e.g. Part 1-4 &rarr; Listening, Part 5-7 &rarr; Reading).
                </span>
            </div>

            <div style="margin-bottom:1.25rem;">
                <label style="display:block;font-size:.75rem;font-weight:700;color:#cbd5e1;margin-bottom:.3rem;">Section Instructions / Directions (Optional)</label>
                <textarea name="instructions" rows="3" placeholder="e.g. Directions: For each question in this part, you will hear four statements about a picture. Select the statement that best describes what you see." style="width:100%;padding:.6rem;background:#1e293b;border:1px solid #334155;border-radius:.5rem;color:#fff;font-size:.82rem;line-height:1.45;"></textarea>
                <span style="font-size:.7rem;color:#64748b;display:block;margin-top:.3rem;">
                    Directions shown to candidates upon entering this section.
                </span>
            </div>

            <div style="display:flex;justify-content:flex-end;gap:.75rem;">
                <button type="button" onclick="closeAddSectionModal()" style="padding:.6rem 1.1rem;background:#334155;color:#fff;border:none;border-radius:.5rem;font-size:.82rem;font-weight:700;cursor:pointer;">Cancel</button>
                <button type="submit" style="padding:.6rem 1.25rem;background:#6366f1;color:#fff;border:none;border-radius:.5rem;font-size:.82rem;font-weight:800;cursor:pointer;">Add Section</button>
            </div>
        </form>
    </div>
</div>

{{-- Modal 3b: Edit Section --}}
<div id="edit-section-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:9999;align-items:center;justify-content:center;padding:1rem;" onclick="closeEditSectionModal(event)">
    <div style="background:#0f172a;border:1px solid #334155;border-radius:1rem;max-width:480px;width:100%;padding:1.75rem;box-shadow:0 20px 50px rgba(0,0,0,.5);" onclick="event.stopPropagation()">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;">
            <div style="font-size:1.15rem;font-weight:800;color:#fff;display:flex;align-items:center;gap:.5rem;">
                <span>✏️</span> Edit Assessment Section &amp; Directions
            </div>
            <button type="button" onclick="closeEditSectionModal()" style="background:none;border:none;color:#94a3b8;font-size:1.25rem;cursor:pointer;">×</button>
        </div>

        <form id="edit-section-form" method="POST" action="">
            @csrf
            @method('PUT')
            <div style="margin-bottom:1.15rem;">
                <label style="display:block;font-size:.75rem;font-weight:700;color:#cbd5e1;margin-bottom:.3rem;">Section Title <span style="color:#f43f5e;">*</span></label>
                <input type="text" id="edit-section-title" name="title" required placeholder="e.g. Part 1: Photographs" style="width:100%;padding:.6rem;background:#1e293b;border:1px solid #334155;border-radius:.5rem;color:#fff;font-size:.82rem;">
            </div>

            <div style="margin-bottom:1.15rem;">
                <label style="display:block;font-size:.75rem;font-weight:700;color:#cbd5e1;margin-bottom:.3rem;">Section Type</label>
                <select id="edit-section-type" name="section_type" style="width:100%;padding:.6rem;background:#1e293b;border:1px solid #334155;border-radius:.5rem;color:#fff;font-size:.82rem;">
                    <option value="">⚡ Auto-detect from Section Title (Recommended)</option>
                    <option value="listening">🎧 Listening Section</option>
                    <option value="reading">📖 Reading Section</option>
                    <option value="speaking">🎙 Speaking Section</option>
                    <option value="writing">✍️ Writing Section</option>
                </select>
            </div>

            <div style="margin-bottom:1.25rem;">
                <label style="display:block;font-size:.75rem;font-weight:700;color:#cbd5e1;margin-bottom:.3rem;">Section Instructions / Directions (Optional)</label>
                <textarea id="edit-section-instructions" name="instructions" rows="3" placeholder="e.g. Directions: For each question in this part, you will hear four statements..." style="width:100%;padding:.6rem;background:#1e293b;border:1px solid #334155;border-radius:.5rem;color:#fff;font-size:.82rem;line-height:1.45;"></textarea>
                <span style="font-size:.7rem;color:#64748b;display:block;margin-top:.3rem;">
                    Directions displayed to candidates upon entering this section.
                </span>
            </div>

            <div style="display:flex;justify-content:flex-end;gap:.75rem;">
                <button type="button" onclick="closeEditSectionModal()" style="padding:.6rem 1.1rem;background:#334155;color:#fff;border:none;border-radius:.5rem;font-size:.82rem;font-weight:700;cursor:pointer;">Cancel</button>
                <button type="submit" style="padding:.6rem 1.25rem;background:#6366f1;color:#fff;border:none;border-radius:.5rem;font-size:.82rem;font-weight:800;cursor:pointer;">Update Section</button>
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

{{-- Modal 5: Attach Section Media from Library or Direct Upload --}}
<div id="attach-section-media-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:9999;align-items:center;justify-content:center;padding:1rem;" onclick="closeAttachSectionMediaModal(event)">
    <div style="background:#0f172a;border:1px solid #334155;border-radius:1rem;max-width:720px;width:100%;max-height:92vh;display:flex;flex-direction:column;padding:1.5rem;box-shadow:0 20px 50px rgba(0,0,0,.5);" onclick="event.stopPropagation()">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.85rem;border-bottom:1px solid #1e293b;padding-bottom:.75rem;">
            <div>
                <div style="font-size:1.1rem;font-weight:800;color:#fff;display:flex;align-items:center;gap:.5rem;">
                    <span>📎</span> Attach Media Asset to Section
                </div>
                <div id="asm-target-section-title" style="font-size:.75rem;color:#818cf8;margin-top:.2rem;font-weight:600;"></div>
            </div>
            <button type="button" onclick="closeAttachSectionMediaModal()" style="background:none;border:none;color:#94a3b8;font-size:1.25rem;cursor:pointer;">×</button>
        </div>

        {{-- Top Navigation: Choose from Library vs Upload New Media --}}
        <div style="display:flex;gap:.5rem;margin-bottom:1rem;background:#090d16;padding:.35rem;border-radius:.6rem;border:1px solid #1e293b;">
            <button type="button" id="asm-tab-library" onclick="switchAsmMode('library')" style="flex:1;padding:.45rem .85rem;background:#6366f1;color:#fff;border:none;border-radius:.45rem;font-size:.78rem;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;gap:.35rem;transition:all .15s ease;">
                <span>📚</span> Choose from Media Library
            </button>
            <button type="button" id="asm-tab-upload" onclick="switchAsmMode('upload')" style="flex:1;padding:.45rem .85rem;background:transparent;color:#94a3b8;border:none;border-radius:.45rem;font-size:.78rem;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;gap:.35rem;transition:all .15s ease;">
                <span>⬆️</span> + Upload New Media
            </button>
        </div>

        {{-- Mode A: Direct Media Upload Panel --}}
        <div id="asm-upload-panel" style="display:none;background:#090d16;border:1px solid #1e293b;border-radius:.75rem;padding:1.15rem;margin-bottom:1rem;">
            <div style="font-size:.82rem;font-weight:800;color:#f8fafc;margin-bottom:.75rem;display:flex;align-items:center;gap:.4rem;">
                <span>🚀</span> Upload Media to Institutional Library
            </div>

            <div id="asm-upload-feedback" style="display:none;padding:.6rem .8rem;border-radius:.5rem;font-size:.75rem;font-weight:700;margin-bottom:.75rem;"></div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-bottom:.85rem;">
                <div>
                    <label style="display:block;font-size:.72rem;font-weight:700;color:#cbd5e1;margin-bottom:.25rem;">
                        Select Local File <span style="color:#f43f5e;">*</span>
                    </label>
                    <input type="file" id="asm-upload-file" accept=".jpg,.jpeg,.png,.webp,.mp3,.wav,.m4a,.pdf,image/*,audio/*,application/pdf" style="width:100%;padding:.45rem .6rem;background:#1e293b;border:1px solid #334155;border-radius:.45rem;color:#cbd5e1;font-size:.75rem;">
                </div>
                <div>
                    <label style="display:block;font-size:.72rem;font-weight:700;color:#cbd5e1;margin-bottom:.25rem;">
                        Media Title (Optional)
                    </label>
                    <input type="text" id="asm-upload-title" placeholder="e.g. Part 1 Listening Directions Audio" style="width:100%;padding:.5rem .75rem;background:#1e293b;border:1px solid #334155;border-radius:.45rem;color:#fff;font-size:.78rem;">
                </div>
            </div>

            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.5rem;">
                <div style="font-size:.68rem;color:#64748b;">
                    Supported: JPG, PNG, WebP, MP3, WAV, M4A, PDF (Max: 10 MB)
                </div>
                <button type="button" id="asm-upload-btn" onclick="uploadSectionMediaFile()" style="padding:.5rem 1.1rem;background:#10b981;color:#fff;border:none;border-radius:.45rem;font-size:.78rem;font-weight:800;cursor:pointer;display:inline-flex;align-items:center;gap:.35rem;box-shadow:0 2px 8px rgba(16,185,129,.3);">
                    <span>⬆️</span> Upload &amp; Select Asset
                </button>
            </div>
        </div>

        <form id="attach-section-media-form" method="POST" action="" style="display:flex;flex-direction:column;flex:1;min-height:0;gap:1rem;">
            @csrf
            <input type="hidden" id="asm-media-asset-id" name="media_asset_id" value="" required>

            {{-- Selected Media Badge --}}
            <div id="asm-selected-preview" style="display:none;padding:.6rem .8rem;background:rgba(99,102,241,.15);border:1px solid rgba(99,102,241,.4);border-radius:.55rem;align-items:center;justify-content:space-between;">
                <div style="display:flex;align-items:center;gap:.5rem;">
                    <span id="asm-selected-icon" style="font-size:1.2rem;">📎</span>
                    <div>
                        <div id="asm-selected-title" style="font-size:.82rem;font-weight:800;color:#fff;"></div>
                        <div id="asm-selected-meta" style="font-size:.7rem;color:#a5b4fc;"></div>
                    </div>
                </div>
                <button type="button" onclick="clearSelectedSectionMedia()" style="padding:.25rem .6rem;background:#ef4444;color:#fff;border:none;border-radius:.35rem;font-size:.7rem;font-weight:700;cursor:pointer;">Clear</button>
            </div>

            {{-- Section-Specific Caption and Order Inputs --}}
            <div style="display:grid;grid-template-columns:2fr 1fr;gap:.75rem;">
                <div>
                    <label style="display:block;font-size:.72rem;font-weight:700;color:#cbd5e1;margin-bottom:.25rem;">
                        Section Media Caption / Directions Label (Optional)
                    </label>
                    <input type="text" id="asm-caption" name="caption" placeholder="e.g. Listening Directions Audio, Reference Photograph" style="width:100%;padding:.5rem .75rem;background:#1e293b;border:1px solid #334155;border-radius:.45rem;color:#fff;font-size:.8rem;">
                </div>
                <div>
                    <label style="display:block;font-size:.72rem;font-weight:700;color:#cbd5e1;margin-bottom:.25rem;">Display Order</label>
                    <input type="number" id="asm-order" name="order" min="1" placeholder="Auto (Next)" style="width:100%;padding:.5rem .75rem;background:#1e293b;border:1px solid #334155;border-radius:.45rem;color:#fff;font-size:.8rem;">
                </div>
            </div>

            {{-- Mode B: Library Browser Panel --}}
            <div id="asm-library-panel" style="display:flex;flex-direction:column;flex:1;min-height:0;gap:.75rem;">
                {{-- Media Filter Tabs & Search --}}
                <div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center;justify-content:space-between;">
                    <div style="display:flex;gap:.35rem;flex-wrap:wrap;">
                        <button type="button" onclick="filterSectionMediaModal('all')" class="asm-filter-btn active" style="padding:.35rem .7rem;background:#6366f1;color:#fff;border:none;border-radius:.45rem;font-size:.72rem;font-weight:700;cursor:pointer;">All Media</button>
                        <button type="button" onclick="filterSectionMediaModal('audio')" class="asm-filter-btn" style="padding:.35rem .7rem;background:#1e293b;color:#cbd5e1;border:1px solid #334155;border-radius:.45rem;font-size:.72rem;font-weight:700;cursor:pointer;">🎵 Audio Tracks</button>
                        <button type="button" onclick="filterSectionMediaModal('image')" class="asm-filter-btn" style="padding:.35rem .7rem;background:#1e293b;color:#cbd5e1;border:1px solid #334155;border-radius:.45rem;font-size:.72rem;font-weight:700;cursor:pointer;">🖼️ Images</button>
                        <button type="button" onclick="filterSectionMediaModal('passage')" class="asm-filter-btn" style="padding:.35rem .7rem;background:#1e293b;color:#cbd5e1;border:1px solid #334155;border-radius:.45rem;font-size:.72rem;font-weight:700;cursor:pointer;">📖 Passages</button>
                        <button type="button" onclick="filterSectionMediaModal('pdf')" class="asm-filter-btn" style="padding:.35rem .7rem;background:#1e293b;color:#cbd5e1;border:1px solid #334155;border-radius:.45rem;font-size:.72rem;font-weight:700;cursor:pointer;">📄 PDFs</button>
                    </div>
                    <input type="text" id="asm-search-input" onkeyup="searchSectionMediaModal(this.value)" placeholder="Search media by title..." style="padding:.35rem .7rem;background:#1e293b;border:1px solid #334155;border-radius:.45rem;color:#fff;font-size:.75rem;min-width:180px;">
                </div>

                {{-- Media Grid Container --}}
                <div id="asm-media-list-container" style="flex:1;min-height:200px;max-height:260px;overflow-y:auto;background:#090d16;border:1px solid #1e293b;border-radius:.65rem;padding:.75rem;display:grid;grid-template-columns:repeat(auto-fill, minmax(200px, 1fr));gap:.6rem;">
                    <div style="grid-column:1/-1;text-align:center;color:#64748b;font-size:.75rem;padding:2rem;">Loading media library...</div>
                </div>
            </div>

            <div style="display:flex;justify-content:flex-end;gap:.75rem;border-top:1px solid #1e293b;padding-top:.75rem;">
                <button type="button" onclick="closeAttachSectionMediaModal()" style="padding:.6rem 1.1rem;background:#334155;color:#fff;border:none;border-radius:.5rem;font-size:.82rem;font-weight:700;cursor:pointer;">Cancel</button>
                <button type="submit" id="asm-submit-btn" disabled style="padding:.6rem 1.25rem;background:#6366f1;color:#fff;border:none;border-radius:.5rem;font-size:.82rem;font-weight:800;cursor:not-allowed;opacity:.5;">Attach Selected Media</button>
            </div>
        </form>
    </div>
</div>

{{-- Modal 6: General Asset Preview Modal --}}
<div id="asset-preview-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.85);z-index:10000;align-items:center;justify-content:center;padding:1.5rem;" onclick="closeAssetPreviewModal(event)">
    <div style="background:#0f172a;border:1px solid #334155;border-radius:1rem;max-width:680px;width:100%;max-height:85vh;overflow-y:auto;padding:1.5rem;box-shadow:0 25px 60px rgba(0,0,0,.6);" onclick="event.stopPropagation()">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;border-bottom:1px solid #1e293b;padding-bottom:.6rem;">
            <div id="apm-title" style="font-size:1rem;font-weight:800;color:#fff;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">Preview Asset</div>
            <button type="button" onclick="closeAssetPreviewModal()" style="background:none;border:none;color:#94a3b8;font-size:1.4rem;cursor:pointer;">×</button>
        </div>
        <div id="apm-content" style="display:flex;justify-content:center;align-items:center;min-height:180px;">
        </div>
    </div>
</div>

<script>
    let sectionMediaLibrary = [];
    let currentAsmSectionId = null;

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

    function openEditSectionModal(sectionId, title, sectionType, instructions) {
        const form = document.getElementById('edit-section-form');
        if (form) {
            form.action = `/teacher/assessments/{{ $test->id }}/sections/${sectionId}`;
        }
        const titleInput = document.getElementById('edit-section-title');
        if (titleInput) titleInput.value = title || '';
        const typeSelect = document.getElementById('edit-section-type');
        if (typeSelect) typeSelect.value = sectionType || '';
        const instructionsInput = document.getElementById('edit-section-instructions');
        if (instructionsInput) instructionsInput.value = instructions || '';

        const modal = document.getElementById('edit-section-modal');
        if (modal) modal.style.display = 'flex';
    }
    function closeEditSectionModal(e) {
        if (!e || e.target === document.getElementById('edit-section-modal')) {
            const modal = document.getElementById('edit-section-modal');
            if (modal) modal.style.display = 'none';
        }
    }

    function openAttachSectionMediaModal(sectionId, title, sectionType) {
        currentAsmSectionId = sectionId;
        const form = document.getElementById('attach-section-media-form');
        if (form) {
            form.action = `/teacher/assessments/{{ $test->id }}/sections/${sectionId}/media`;
        }
        const titleEl = document.getElementById('asm-target-section-title');
        if (titleEl) {
            titleEl.textContent = `Target Section: ${title} (${sectionType ? sectionType.toUpperCase() : 'GENERAL'})`;
        }
        clearSelectedSectionMedia();
        document.getElementById('asm-caption').value = '';
        document.getElementById('asm-order').value = '';
        document.getElementById('asm-upload-file').value = '';
        document.getElementById('asm-upload-title').value = '';
        hideAsmUploadFeedback();
        switchAsmMode('library');

        const modal = document.getElementById('attach-section-media-modal');
        if (modal) modal.style.display = 'flex';

        fetchSectionMediaLibrary(sectionType);
    }

    function closeAttachSectionMediaModal(e) {
        if (!e || e.target === document.getElementById('attach-section-media-modal')) {
            const modal = document.getElementById('attach-section-media-modal');
            if (modal) modal.style.display = 'none';
        }
    }

    function switchAsmMode(mode) {
        const tabLib = document.getElementById('asm-tab-library');
        const tabUpload = document.getElementById('asm-tab-upload');
        const uploadPanel = document.getElementById('asm-upload-panel');

        if (mode === 'upload') {
            tabUpload.style.background = '#10b981';
            tabUpload.style.color = '#fff';
            tabLib.style.background = 'transparent';
            tabLib.style.color = '#94a3b8';
            uploadPanel.style.display = 'block';
        } else {
            tabLib.style.background = '#6366f1';
            tabLib.style.color = '#fff';
            tabUpload.style.background = 'transparent';
            tabUpload.style.color = '#94a3b8';
            uploadPanel.style.display = 'none';
        }
    }

    function showAsmUploadFeedback(message, isSuccess = false) {
        const el = document.getElementById('asm-upload-feedback');
        if (el) {
            el.style.display = 'block';
            el.style.background = isSuccess ? 'rgba(16,185,129,.15)' : 'rgba(244,63,94,.15)';
            el.style.border = isSuccess ? '1px solid rgba(16,185,129,.4)' : '1px solid rgba(244,63,94,.4)';
            el.style.color = isSuccess ? '#34d399' : '#fb7185';
            el.textContent = message;
        }
    }

    function hideAsmUploadFeedback() {
        const el = document.getElementById('asm-upload-feedback');
        if (el) {
            el.style.display = 'none';
            el.textContent = '';
        }
    }

    function uploadSectionMediaFile() {
        const fileInput = document.getElementById('asm-upload-file');
        const titleInput = document.getElementById('asm-upload-title');
        const uploadBtn = document.getElementById('asm-upload-btn');

        if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
            showAsmUploadFeedback('Please choose a file to upload.', false);
            return;
        }

        const file = fileInput.files[0];
        if (file.size > 10 * 1024 * 1024) {
            showAsmUploadFeedback('File size exceeds the 10 MB limit.', false);
            return;
        }

        const formData = new FormData();
        formData.append('file', file);
        if (titleInput && titleInput.value.trim()) {
            formData.append('title', titleInput.value.trim());
        }
        formData.append('_token', '{{ csrf_token() }}');

        uploadBtn.disabled = true;
        uploadBtn.style.opacity = '.6';
        uploadBtn.innerHTML = '⏳ Uploading...';
        hideAsmUploadFeedback();

        fetch('{{ route('admin.media.store') }}', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(async (res) => {
            const data = await res.json();
            if (!res.ok || !data.success) {
                const msg = data.message || (data.errors ? Object.values(data.errors).flat().join(' ') : 'Upload failed.');
                throw new Error(msg);
            }
            return data;
        })
        .then(data => {
            const icon = data.type === 'audio' ? '🎵' : (data.type === 'image' ? '🖼️' : (data.type === 'pdf' ? '📄' : (data.type === 'passage' ? '📖' : '📎')));
            
            // Add new asset to the beginning of library array
            const newAsset = {
                id: data.id,
                title: data.title || data.filename,
                name: data.filename,
                type: data.type,
                size: data.size,
                url: data.url
            };
            sectionMediaLibrary.unshift(newAsset);

            // Re-render library grid
            renderSectionMediaGrid(sectionMediaLibrary);

            // Select this newly uploaded asset
            selectSectionMediaItem(data.id, data.title || data.filename, data.type, icon);

            // Reset inputs & switch to library view with success confirmation
            fileInput.value = '';
            titleInput.value = '';
            switchAsmMode('library');
            showAsmUploadFeedback(`✅ "${data.title || data.filename}" uploaded & selected!`, true);
        })
        .catch(err => {
            showAsmUploadFeedback(`⚠️ ${err.message}`, false);
        })
        .finally(() => {
            uploadBtn.disabled = false;
            uploadBtn.style.opacity = '1';
            uploadBtn.innerHTML = '<span>⬆️</span> Upload &amp; Select Asset';
        });
    }

    function fetchSectionMediaLibrary(preferredType) {
        const container = document.getElementById('asm-media-list-container');
        if (sectionMediaLibrary.length > 0) {
            renderSectionMediaGrid(sectionMediaLibrary, preferredType);
            return;
        }

        container.innerHTML = '<div style="grid-column:1/-1;text-align:center;color:#64748b;font-size:.75rem;padding:2rem;">Loading media library...</div>';
        fetch('/admin/media/list')
            .then(res => res.json())
            .then(data => {
                if (data.success && Array.isArray(data.data)) {
                    sectionMediaLibrary = data.data;
                    renderSectionMediaGrid(sectionMediaLibrary, preferredType);
                } else {
                    container.innerHTML = '<div style="grid-column:1/-1;text-align:center;color:#f43f5e;font-size:.75rem;padding:2rem;">Failed to load media library.</div>';
                }
            })
            .catch(() => {
                container.innerHTML = '<div style="grid-column:1/-1;text-align:center;color:#f43f5e;font-size:.75rem;padding:2rem;">Error communicating with media server.</div>';
            });
    }

    function filterSectionMediaModal(type) {
        const buttons = document.querySelectorAll('.asm-filter-btn');
        buttons.forEach(btn => {
            btn.style.background = '#1e293b';
            btn.style.color = '#cbd5e1';
            btn.style.border = '1px solid #334155';
        });
        if (event && event.target) {
            event.target.style.background = '#6366f1';
            event.target.style.color = '#fff';
            event.target.style.border = 'none';
        }

        const query = (document.getElementById('asm-search-input')?.value || '').toLowerCase();
        let filtered = sectionMediaLibrary;
        if (type !== 'all') {
            filtered = filtered.filter(item => item.type === type);
        }
        if (query) {
            filtered = filtered.filter(item => (item.title || item.name || '').toLowerCase().includes(query));
        }
        renderSectionMediaGrid(filtered);
    }

    function searchSectionMediaModal(query) {
        query = query.toLowerCase();
        const activeBtn = Array.from(document.querySelectorAll('.asm-filter-btn')).find(b => b.style.background === 'rgb(99, 102, 241)' || b.style.background === '#6366f1');
        let filtered = sectionMediaLibrary;
        if (query) {
            filtered = filtered.filter(item => (item.title || item.name || '').toLowerCase().includes(query));
        }
        renderSectionMediaGrid(filtered);
    }

    function renderSectionMediaGrid(items, preferredType) {
        const container = document.getElementById('asm-media-list-container');
        if (!items || items.length === 0) {
            container.innerHTML = '<div style="grid-column:1/-1;text-align:center;color:#64748b;font-size:.75rem;padding:2rem;">No media assets found in library.</div>';
            return;
        }

        container.innerHTML = '';
        items.forEach(media => {
            const card = document.createElement('div');
            card.style.background = '#0f172a';
            card.style.border = '1px solid #1e293b';
            card.style.borderRadius = '.55rem';
            card.style.padding = '.65rem';
            card.style.display = 'flex';
            card.style.flexDirection = 'column';
            card.style.justifyContent = 'space-between';
            card.style.gap = '.45rem';

            const icon = media.type === 'audio' ? '🎵' : (media.type === 'image' ? '🖼️' : (media.type === 'pdf' ? '📄' : (media.type === 'passage' ? '📖' : '📎')));

            card.innerHTML = `
                <div>
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.3rem;">
                        <span style="font-size:.68rem;font-weight:800;color:#818cf8;background:rgba(99,102,241,.15);padding:.15rem .4rem;border-radius:.3rem;text-transform:uppercase;">${icon} ${media.type}</span>
                        <span style="font-size:.65rem;color:#64748b;">${media.size || ''}</span>
                    </div>
                    <div style="font-size:.78rem;font-weight:700;color:#f8fafc;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="${media.title || media.name}">
                        ${media.title || media.name}
                    </div>
                </div>
                <div style="display:flex;justify-content:space-between;align-items:center;margin-top:.35rem;border-top:1px solid #1e293b;padding-top:.4rem;">
                    <button type="button" onclick="previewAssetModal('${media.id}', '${(media.title || media.name || '').replace(/'/g, "\\'")}', '${media.type}', '${media.url}')" style="padding:.25rem .5rem;background:#1e293b;color:#cbd5e1;border:1px solid #334155;border-radius:.35rem;font-size:.68rem;cursor:pointer;">👁️ Preview</button>
                    <button type="button" onclick="selectSectionMediaItem('${media.id}', '${(media.title || media.name || '').replace(/'/g, "\\'")}', '${media.type}', '${icon}')" style="padding:.25rem .6rem;background:#4f46e5;color:#fff;border:none;border-radius:.35rem;font-size:.68rem;font-weight:700;cursor:pointer;">Select</button>
                </div>
            `;
            container.appendChild(card);
        });
    }

    function selectSectionMediaItem(id, title, type, icon) {
        document.getElementById('asm-media-asset-id').value = id;
        document.getElementById('asm-selected-title').textContent = title;
        document.getElementById('asm-selected-meta').textContent = `Type: ${type.toUpperCase()}`;
        document.getElementById('asm-selected-icon').textContent = icon;
        document.getElementById('asm-selected-preview').style.display = 'flex';

        const submitBtn = document.getElementById('asm-submit-btn');
        submitBtn.disabled = false;
        submitBtn.style.cursor = 'pointer';
        submitBtn.style.opacity = '1';
    }

    function clearSelectedSectionMedia() {
        document.getElementById('asm-media-asset-id').value = '';
        document.getElementById('asm-selected-preview').style.display = 'none';
        const submitBtn = document.getElementById('asm-submit-btn');
        submitBtn.disabled = true;
        submitBtn.style.cursor = 'not-allowed';
        submitBtn.style.opacity = '.5';
    }

    function previewAssetModal(id, title, type, url) {
        document.getElementById('apm-title').textContent = `${type.toUpperCase()}: ${title}`;
        const contentEl = document.getElementById('apm-content');

        if (type === 'image') {
            contentEl.innerHTML = `<img src="${url}" alt="${title}" style="max-width:100%;max-height:450px;border-radius:.6rem;object-fit:contain;">`;
        } else if (type === 'audio') {
            contentEl.innerHTML = `
                <div style="width:100%;text-align:center;padding:1.5rem;background:#090d16;border-radius:.75rem;">
                    <div style="font-size:3rem;margin-bottom:.5rem;">🎵</div>
                    <audio controls controlsList="nodownload noplaybackrate" src="${url}" preload="metadata" style="width:100%;max-width:480px;accent-color:#6366f1;"></audio>
                </div>`;
        } else if (type === 'pdf') {
            contentEl.innerHTML = `<iframe src="${url}#toolbar=0" style="width:100%;height:450px;border:none;border-radius:.6rem;background:#fff;"></iframe>`;
        } else {
            contentEl.innerHTML = `<div style="padding:1.5rem;color:#cbd5e1;font-size:.85rem;line-height:1.6;white-space:pre-wrap;background:#090d16;border-radius:.6rem;width:100%;">Reading / text passage preview...</div>`;
        }

        const modal = document.getElementById('asset-preview-modal');
        if (modal) modal.style.display = 'flex';
    }

    function closeAssetPreviewModal(e) {
        if (!e || e.target === document.getElementById('asset-preview-modal')) {
            const modal = document.getElementById('asset-preview-modal');
            if (modal) {
                modal.style.display = 'none';
                document.getElementById('apm-content').innerHTML = '';
            }
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
            closeEditSectionModal();
            closeAttachSectionMediaModal();
            closeAssetPreviewModal();
            closeTeacherRequestRevisionModal();
        }
    });
</script>
@endsection
