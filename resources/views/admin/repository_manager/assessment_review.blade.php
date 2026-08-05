@extends('layouts.admin')

@section('title', 'Review Assessment — Repository Governance')

@section('content')
<div style="padding: 1.5rem 0;">
    {{-- Header --}}
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
        <div>
            <h1 style="font-size:1.6rem;font-weight:800;color:#fff;margin:0 0 .3rem;">🔍 Assessment Governance Review</h1>
            <p style="font-size:.88rem;color:#94a3b8;margin:0;">Validate test structure, sections, questions, and duration before approving for institutional use.</p>
        </div>
        <div>
            <a href="{{ route('admin.repository-manager.assessment-approval') }}" style="padding:.6rem 1.1rem;background:#1e293b;border:1px solid #334155;color:#fff;border-radius:.6rem;font-size:.82rem;font-weight:700;text-decoration:none;display:inline-flex;align-items:center;gap:.4rem;">
                ⬅ Back to Assessment Queue
            </a>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:2fr 1fr;gap:1.5rem;">
        {{-- Left: Assessment Details & Structure --}}
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
                        <div style="font-size:1.1rem;font-weight:800;color:#fff;margin-top:.2rem;">🎯 {{ $test->pass_score }}%</div>
                    </div>
                    <div>
                        <div style="font-size:.7rem;color:#94a3b8;font-weight:700;text-transform:uppercase;">Sections</div>
                        <div style="font-size:1.1rem;font-weight:800;color:#fff;margin-top:.2rem;">📚 {{ $test->sections->count() }} Sections</div>
                    </div>
                </div>
            </div>

            {{-- Sections Breakdown --}}
            <div style="background:#0f172a;border:1px solid #1e293b;border-radius:1.25rem;padding:1.75rem;">
                <h3 style="font-size:1.05rem;font-weight:800;color:#fff;margin:0 0 1rem;">Section & Item Composition</h3>

                @if($test->sections->isEmpty())
                    <div style="color:#64748b;font-size:.85rem;text-align:center;padding:2rem;">No test sections created yet.</div>
                @else
                    @foreach($test->sections as $sec)
                    <div style="background:#1e293b;border:1px solid #334155;border-radius:.75rem;padding:1rem;margin-bottom:.75rem;">
                        <div style="display:flex;justify-content:space-between;align-items:center;">
                            <div style="font-weight:800;color:#fff;font-size:.9rem;">{{ $sec->title ?? 'Section' }}</div>
                            <span style="font-size:.75rem;color:#a5b4fc;font-weight:700;">{{ $sec->testQuestions->count() }} Questions</span>
                        </div>
                        @if($sec->description)
                            <div style="font-size:.78rem;color:#94a3b8;margin-top:.3rem;">{{ $sec->description }}</div>
                        @endif
                    </div>
                    @endforeach
                @endif
            </div>
        </div>

        {{-- Right: Repository Governance Action Form --}}
        <div style="display:flex;flex-direction:column;gap:1.25rem;">
            <div style="background:#0f172a;border:1px solid #1e293b;border-radius:1.25rem;padding:1.75rem;">
                <h3 style="font-size:1.05rem;font-weight:800;color:#fff;margin:0 0 1rem;">Governance Decision</h3>

                {{-- Approve Form --}}
                <form action="{{ route('admin.repository-manager.assessment-approve', $test->id) }}" method="POST" style="margin-bottom:1rem;">
                    @csrf
                    <div style="margin-bottom:.75rem;">
                        <label style="font-size:.75rem;font-weight:700;color:#94a3b8;display:block;margin-bottom:.3rem;">Approval Notes (Optional)</label>
                        <input type="text" name="notes" placeholder="e.g. Assessment meets institutional quality standards." style="width:100%;background:#1e293b;border:1px solid #334155;color:#fff;padding:.6rem;border-radius:.5rem;font-size:.82rem;">
                    </div>
                    <button type="submit" style="width:100%;padding:.75rem;background:#10b981;color:#fff;font-weight:800;border:none;border-radius:.6rem;cursor:pointer;font-size:.85rem;display:flex;align-items:center;justify-content:center;gap:.4rem;">
                        ✓ Approve Assessment
                    </button>
                </form>

                <hr style="border:none;border-top:1px solid #1e293b;margin:1.25rem 0;">

                {{-- Request Revision Form --}}
                <form action="{{ route('admin.repository-manager.assessment-revision', $test->id) }}" method="POST" style="margin-bottom:1rem;">
                    @csrf
                    <div style="margin-bottom:.75rem;">
                        <label style="font-size:.75rem;font-weight:700;color:#94a3b8;display:block;margin-bottom:.3rem;">Revision Requirements *</label>
                        <textarea name="notes" rows="3" placeholder="Provide clear feedback for the author..." style="width:100%;background:#1e293b;border:1px solid #334155;color:#fff;padding:.6rem;border-radius:.5rem;font-size:.82rem;" required></textarea>
                    </div>
                    <button type="submit" style="width:100%;padding:.75rem;background:#f59e0b;color:#fff;font-weight:800;border:none;border-radius:.6rem;cursor:pointer;font-size:.85rem;display:flex;align-items:center;justify-content:center;gap:.4rem;">
                        ⚠️ Request Revision
                    </button>
                </form>

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
                <h4 style="font-size:.9rem;font-weight:800;color:#fff;margin:0 0 drop-1rem;">📜 Activity Audit History</h4>
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
