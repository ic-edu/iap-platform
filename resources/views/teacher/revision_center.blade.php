@extends('layouts.admin')

@section('title', 'Teacher Revision Center — Returned Items')

@section('content')
<div style="padding: 1.5rem 0;">
    {{-- Header --}}
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
        <div>
            <h1 style="font-size:1.6rem;font-weight:800;color:#fff;margin:0 0 .3rem;">⚠️ Teacher Revision Center</h1>
            <p style="font-size:.88rem;color:#94a3b8;margin:0;">Review Repository Manager feedback notes and revise returned Question Banks and Assessments.</p>
        </div>
        <div>
            <a href="{{ route('teacher.dashboard') }}" style="padding:.6rem 1.1rem;background:#1e293b;border:1px solid #334155;color:#fff;border-radius:.6rem;font-size:.82rem;font-weight:700;text-decoration:none;display:inline-flex;align-items:center;gap:.4rem;">
                ⬅ Back to Teacher Dashboard
            </a>
        </div>
    </div>

    {{-- Question Banks Needing Revision Section --}}
    <div style="background:#0f172a;border:1px solid #1e293b;border-radius:1.25rem;overflow:hidden;margin-bottom:2rem;">
        <div style="padding:1.25rem 1.5rem;border-bottom:1px solid #1e293b;display:flex;justify-content:space-between;align-items:center;">
            <h3 style="font-size:1rem;font-weight:800;color:#fff;margin:0;display:flex;align-items:center;gap:.5rem;">
                📂 Question Banks Needing Revision ({{ $revisionQuestionBanks->count() }})
            </h3>
        </div>

        @if($revisionQuestionBanks->isEmpty())
            <div style="padding:3rem 2rem;text-align:center;color:#64748b;">
                <div style="font-size:2rem;margin-bottom:.5rem;">🎉</div>
                <div style="font-size:.92rem;font-weight:700;color:#cbd5e1;">No Question Banks Awaiting Revision</div>
                <div style="font-size:.8rem;margin-top:.2rem;">All your question banks are either in draft, pending approval, or approved.</div>
            </div>
        @else
            <div style="divide-y:1px solid #1e293b;">
                @foreach($revisionQuestionBanks as $bank)
                <div style="padding:1.5rem;border-bottom:1px solid #1e293b;">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:1rem;margin-bottom:1rem;">
                        <div>
                            <div style="display:flex;align-items:center;gap:.6rem;margin-bottom:.35rem;">
                                <span style="background:rgba(245,158,11,.15);color:#fbbf24;border:1px solid rgba(245,158,11,.3);padding:.2rem .65rem;border-radius:.4rem;font-size:.72rem;font-weight:800;">
                                    ⚠️ Needs Revision
                                </span>
                                <span style="font-size:.75rem;color:#818cf8;font-weight:700;text-transform:uppercase;">
                                    {{ is_object($bank->test_type) ? $bank->test_type->value : $bank->test_type }}
                                </span>
                            </div>
                            <h4 style="font-size:1.15rem;font-weight:800;color:#fff;margin:0 0 .25rem;">{{ $bank->title }}</h4>
                            <div style="font-size:.78rem;color:#64748b;">
                                📝 {{ $bank->questions->count() }} Questions • Returned by <strong style="color:#cbd5e1;">{{ $bank->reviewer_name }}</strong> {{ $bank->returned_at?->diffForHumans() }}
                            </div>
                        </div>
                        <div>
                            <a href="{{ route('admin.question-banks.show', $bank->id) }}" style="padding:.65rem 1.25rem;background:#6366f1;color:#fff;border-radius:.6rem;font-size:.85rem;font-weight:800;text-decoration:none;display:inline-flex;align-items:center;gap:.4rem;box-shadow:0 4px 12px rgba(99,102,241,.3);">
                                ✏️ Continue Revision
                            </a>
                        </div>
                    </div>

                    {{-- Prominent Repository Manager Feedback Box (TASK 4) --}}
                    <div style="background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.25);border-radius:.75rem;padding:1rem;margin-top:.75rem;">
                        <div style="font-size:.72rem;font-weight:800;color:#fbbf24;text-transform:uppercase;letter-spacing:.05em;margin-bottom:.35rem;">
                            💬 Repository Manager Feedback
                        </div>
                        <div style="font-size:.85rem;color:#e2e8f0;line-height:1.5;">
                            "{{ $bank->latest_feedback }}"
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Assessments Needing Revision Section --}}
    @if($revisionAssessments->isNotEmpty())
    <div style="background:#0f172a;border:1px solid #1e293b;border-radius:1.25rem;overflow:hidden;">
        <div style="padding:1.25rem 1.5rem;border-bottom:1px solid #1e293b;">
            <h3 style="font-size:1rem;font-weight:800;color:#fff;margin:0;">
                📋 Assessment Tests Needing Revision ({{ $revisionAssessments->count() }})
            </h3>
        </div>
        <div style="divide-y:1px solid #1e293b;">
            @foreach($revisionAssessments as $test)
            <div style="padding:1.5rem;border-bottom:1px solid #1e293b;">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:1rem;margin-bottom:1rem;">
                    <div>
                        <div style="display:flex;align-items:center;gap:.6rem;margin-bottom:.35rem;">
                            <span style="background:rgba(245,158,11,.15);color:#fbbf24;border:1px solid rgba(245,158,11,.3);padding:.2rem .65rem;border-radius:.4rem;font-size:.72rem;font-weight:800;">
                                ⚠️ Needs Revision
                            </span>
                            <span style="font-size:.75rem;color:#818cf8;font-weight:700;text-transform:uppercase;">
                                {{ is_object($test->test_type) ? $test->test_type->value : $test->test_type }}
                            </span>
                        </div>
                        <h4 style="font-size:1.15rem;font-weight:800;color:#fff;margin:0 0 .25rem;">{{ $test->title }}</h4>
                        <div style="font-size:.78rem;color:#64748b;">
                            ⏱ {{ $test->duration_minutes }} Mins • Returned by <strong style="color:#cbd5e1;">{{ $test->reviewer_name }}</strong> {{ $test->returned_at?->diffForHumans() }}
                        </div>
                    </div>
                    <div>
                        <a href="{{ route('admin.tests.show', $test->id) }}" style="padding:.65rem 1.25rem;background:#4338ca;color:#fff;border-radius:.6rem;font-size:.85rem;font-weight:800;text-decoration:none;display:inline-flex;align-items:center;gap:.4rem;">
                            ✏️ Revise Assessment
                        </a>
                    </div>
                </div>

                {{-- Feedback Box --}}
                <div style="background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.25);border-radius:.75rem;padding:1rem;margin-top:.75rem;">
                    <div style="font-size:.72rem;font-weight:800;color:#fbbf24;text-transform:uppercase;letter-spacing:.05em;margin-bottom:.35rem;">
                        💬 Repository Manager Feedback
                    </div>
                    <div style="font-size:.85rem;color:#e2e8f0;line-height:1.5;">
                        "{{ $test->latest_feedback }}"
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif
</div>
@endsection
