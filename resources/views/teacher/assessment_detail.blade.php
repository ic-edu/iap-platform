@extends('layouts.admin')

@section('title', 'Assessment Authoring Detail — ' . $test->title)

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
        <div style="display:flex;gap:.75rem;flex-wrap:wrap;">
            <a href="{{ route('teacher.revision-center') }}" style="padding:.6rem 1.1rem;background:#1e293b;border:1px solid #334155;color:#fff;border-radius:.6rem;font-size:.82rem;font-weight:700;text-decoration:none;display:inline-flex;align-items:center;gap:.4rem;">
                ⬅ Revision Center
            </a>
            <a href="{{ route('teacher.tests.index') }}" style="padding:.6rem 1.1rem;background:#1e293b;border:1px solid #334155;color:#fff;border-radius:.6rem;font-size:.82rem;font-weight:700;text-decoration:none;display:inline-flex;align-items:center;gap:.4rem;">
                📋 All Assessments
            </a>
        </div>
    </div>

    @if(session('status'))
    <div style="background:rgba(52,211,153,.12);border:1px solid rgba(52,211,153,.3);color:#34d399;padding:1rem 1.25rem;border-radius:.75rem;font-size:.88rem;font-weight:700;margin-bottom:1.5rem;">
        ✅ {{ session('status') }}
    </div>
    @endif

    {{-- Main 2-Column Grid Layout --}}
    <div style="display:grid;grid-template-columns:1fr 340px;gap:1.5rem;align-items:start;">

        {{-- Left Primary Column: Details & Editor --}}
        <div>
            {{-- Repository Manager Feedback Callout (TASK 5) --}}
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

            {{-- Assessment Editor Form (TASK 4, 6, 7) --}}
            <div style="background:#0f172a;border:1px solid #1e293b;border-radius:1.25rem;padding:1.5rem;margin-bottom:1.5rem;">
                <h3 style="font-size:1.1rem;font-weight:800;color:#fff;margin:0 0 1.25rem;display:flex;align-items:center;gap:.5rem;">
                    ✏️ Assessment Authoring Editor
                </h3>

                <form method="POST" action="{{ route('teacher.tests.update', $test->id) }}" style="display:flex;flex-direction:column;gap:1.25rem;">
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

                    <div>
                        <label style="display:block;font-size:.82rem;font-weight:700;color:#cbd5e1;margin-bottom:.4rem;">Pass Score Threshold</label>
                        <input type="number" name="pass_score" value="{{ old('pass_score', $test->pass_score) }}" required min="0" style="width:100%;padding:.75rem 1rem;background:#1e293b;border:1px solid #334155;border-radius:.6rem;color:#fff;font-size:.9rem;font-weight:600;">
                    </div>

                    <div style="display:flex;gap:1rem;margin-top:.5rem;flex-wrap:wrap;">
                        {{-- Save Draft Button (TASK 6) --}}
                        <button type="submit" style="padding:.75rem 1.5rem;background:#334155;color:#fff;border:none;border-radius:.65rem;font-size:.88rem;font-weight:800;cursor:pointer;display:inline-flex;align-items:center;gap:.4rem;">
                            💾 Save Draft
                        </button>
                    </form>

                    {{-- Submit Again Button (TASK 7) --}}
                    @if(in_array($test->status, ['needs_revision', 'revision_requested', 'draft']))
                    <form method="POST" action="{{ route('teacher.tests.resubmit', $test->id) }}" style="display:inline;">
                        @csrf
                        <button type="submit" onclick="return confirm('Resubmit this assessment to the Repository Manager for governance review?')" style="padding:.75rem 1.5rem;background:#6366f1;color:#fff;border:none;border-radius:.65rem;font-size:.88rem;font-weight:800;cursor:pointer;display:inline-flex;align-items:center;gap:.4rem;box-shadow:0 4px 14px rgba(99,102,241,.35);">
                            🚀 Submit Again for Review
                        </button>
                    </form>
                    @endif
                    </div>
            </div>

            {{-- Assessment Test Sections & Questions Breakdown --}}
            <div style="background:#0f172a;border:1px solid #1e293b;border-radius:1.25rem;padding:1.5rem;">
                <h3 style="font-size:1.1rem;font-weight:800;color:#fff;margin:0 0 1rem;display:flex;align-items:center;gap:.5rem;">
                    🧩 Test Structure & Linked Sections ({{ $test->sections->count() }})
                </h3>

                @foreach($test->sections as $section)
                <div style="background:#1e293b;border:1px solid #334155;border-radius:.75rem;padding:1rem;margin-bottom:1rem;">
                    <div style="font-size:.92rem;font-weight:800;color:#fff;margin-bottom:.4rem;">
                        Section {{ $section->order }}: {{ $section->title }}
                    </div>
                    <div style="font-size:.8rem;color:#94a3b8;">
                        📝 {{ $section->testQuestions->count() }} Questions linked in this section
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Right Sidebar: Metadata & Workflow History Timeline (TASK 8) --}}
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

            {{-- Workflow History Timeline (TASK 8) --}}
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
@endsection
