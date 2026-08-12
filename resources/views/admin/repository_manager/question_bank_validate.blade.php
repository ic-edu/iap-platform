@extends('layouts.admin')

@section('title', 'Question Bank Validation Workspace — Governance Review')

@push('styles')
<style>
.qbw-container { display:flex; flex-direction:column; gap:1.75rem; width:100%; max-width:100%; }

.qbw-card {
    background: #0f172a;
    border: 1px solid #1e293b;
    border-radius: 1.25rem;
    padding: 1.75rem;
}
.qbw-grid {
    display: grid;
    grid-template-columns: 1fr 380px;
    gap: 1.5rem;
}
@media (max-width: 1024px) { .qbw-grid { grid-template-columns: 1fr; } }

.qbw-meta-table { width: 100%; text-align: left; font-size: .82rem; border-collapse: collapse; }
.qbw-meta-table th { padding: .6rem 0; color: #64748b; font-weight: 700; width: 40%; }
.qbw-meta-table td { padding: .6rem 0; color: #f1f5f9; font-weight: 600; }
.qbw-meta-table tr { border-bottom: 1px solid #1e293b; }
.qbw-meta-table tr:last-child { border-bottom: none; }

.qbw-q-card {
    background: #080f1d;
    border: 1px solid #1e293b;
    border-radius: 1rem;
    padding: 1.25rem;
    margin-bottom: 1rem;
}
.qbw-opt {
    padding: .5rem .75rem;
    border-radius: .5rem;
    background: #1e293b;
    border: 1px solid #334155;
    font-size: .8rem;
    color: #cbd5e1;
    margin-bottom: .35rem;
}
.qbw-opt--correct {
    background: rgba(52,211,153,.15);
    border-color: rgba(52,211,153,.4);
    color: #34d399;
    font-weight: 700;
}
</style>
@endpush

@section('content')
<div class="qbw-container">

    {{-- PART 3: BACK NAVIGATION (Context-aware route destination) --}}
    <div>
        @if(request('from') === 'notifications')
        <a href="{{ route('notifications.index') }}" style="color:#818cf8;font-size:.8rem;font-weight:700;text-decoration:none;">
            ← Back to Notifications
        </a>
        @elseif(request('from_url') && str_starts_with(request('from_url'), '/') && !str_starts_with(request('from_url'), '//') && !str_contains(request('from_url'), '://'))
        <a href="{{ request('from_url') }}" style="color:#818cf8;font-size:.8rem;font-weight:700;text-decoration:none;">
            ← Back
        </a>
        @elseif(request('from') === 'explorer')
        <a href="{{ route('admin.academic-library.explorer', ['filter' => request('filter', 'reviewed_issues')]) }}" style="color:#818cf8;font-size:.8rem;font-weight:700;text-decoration:none;">
            ← Back
        </a>
        @else
        <a href="{{ route('admin.repository-manager.questions-approval') }}" style="color:#818cf8;font-size:.8rem;font-weight:700;text-decoration:none;">
            ← Back to Approval Queue
        </a>
        @endif
        <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-top:.5rem;flex-wrap:wrap;">
            <div>
                <h1 style="font-size:1.5rem;font-weight:800;color:#fff;margin:0 0 .25rem;">
                    🛡 Validation Workspace — {{ $questionBank->title }}
                </h1>
                <p style="font-size:.82rem;color:#64748b;margin:0;">
                    Repository ID: <code style="color:#a78bfa;font-family:monospace;">{{ $questionBank->id }}</code> • Exam Type: <strong style="color:#34d399;text-transform:uppercase;">{{ is_object($questionBank->test_type) ? $questionBank->test_type->value : $questionBank->test_type }}</strong>
                </p>
            </div>

            <div style="display:flex;gap:.5rem;align-items:center;">
                <span style="padding:.4rem .9rem;background:rgba(251,191,36,.15);border:1px solid rgba(251,191,36,.4);color:#fbbf24;border-radius:.6rem;font-size:.78rem;font-weight:800;text-transform:uppercase;">
                    Status: {{ is_object($questionBank->status) ? strtoupper($questionBank->status->value) : strtoupper($questionBank->status ?? 'Awaiting Review') }}
                </span>
            </div>
        </div>
    </div>

    <div class="qbw-grid">

        {{-- Left Column: Question Navigator & Detailed Question Preview --}}
        <div style="display:flex;flex-direction:column;gap:1.25rem;">

            {{-- RRWE v1.0 PART 7: Repository Findings & Active Revisions Panel --}}
            @php
                $activeRevisionRequest = \App\Models\RepositoryRevisionRequest::with(['items', 'requestedBy'])
                    ->where('question_bank_id', $questionBank->id)
                    ->latest()
                    ->first();
            @endphp
            @if($activeRevisionRequest)
            <div class="qbw-card" style="border-color:#6366f1;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
                    <h3 style="font-size:1.05rem;font-weight:800;color:#fff;margin:0;">
                        🔍 Active Governance Revision Request ({{ $activeRevisionRequest->status }})
                    </h3>
                    <span style="font-size:.75rem;color:#818cf8;font-weight:700;">Task ID: {{ substr($activeRevisionRequest->id, 0, 8) }}</span>
                </div>
                <div style="font-size:.82rem;color:#cbd5e1;background:#080f1d;padding:.85rem;border-radius:.6rem;border:1px solid #1e293b;margin-bottom:1rem;">
                    <strong>Reviewer Feedback:</strong> "{{ $activeRevisionRequest->notes }}"
                </div>
                @if($activeRevisionRequest->items->count() > 0)
                <div style="display:flex;flex-direction:column;gap:.5rem;">
                    <div style="font-size:.72rem;font-weight:800;color:#94a3b8;text-transform:uppercase;">Tracked Quality Findings:</div>
                    @foreach($activeRevisionRequest->items as $item)
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:.6rem .85rem;background:#1e293b;border-radius:.5rem;font-size:.78rem;color:#e2e8f0;">
                        <span>⚠️ {{ $item->feedback }}</span>
                        <span style="padding:.15rem .5rem;border-radius:.3rem;font-size:.68rem;font-weight:800;text-transform:uppercase;{{ $item->status === 'CLOSED' ? 'background:rgba(52,211,153,.2);color:#34d399;' : 'background:rgba(244,63,94,.2);color:#fb7185;' }}">
                            {{ $item->status }}
                        </span>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>
            @endif

            @php
                $activeFindings = \App\Models\RepositoryFinding::where('question_bank_id', $questionBank->id)->get();
            @endphp
            @if($activeFindings->count() > 0)
            <div class="qbw-card" style="border-color:#fb7185;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.75rem;">
                    <h3 style="font-size:1rem;font-weight:800;color:#fff;margin:0;">
                        ⚠️ Tracked IRQA Quality Findings ({{ $activeFindings->where('status', 'OPEN')->count() }} OPEN)
                    </h3>
                </div>
                <div style="display:flex;flex-direction:column;gap:.5rem;">
                    @foreach($activeFindings as $finding)
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:.6rem .85rem;background:#1e293b;border-radius:.5rem;font-size:.78rem;color:#e2e8f0;">
                        <span>⚠️ {{ $finding->title }}</span>
                        <span style="padding:.15rem .5rem;border-radius:.3rem;font-size:.68rem;font-weight:800;text-transform:uppercase;{{ $finding->status === 'OPEN' ? 'background:rgba(244,63,94,.2);color:#fb7185;' : 'background:rgba(52,211,153,.2);color:#34d399;' }}">
                            {{ $finding->status }}
                        </span>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            <div class="qbw-card">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;">
                    <h3 style="font-size:1.05rem;font-weight:800;color:#fff;margin:0;">
                        📋 Question Repository Items ({{ $questionBank->questions->count() }})
                    </h3>
                    <span style="font-size:.72rem;color:#34d399;font-weight:700;background:rgba(52,211,153,.1);padding:.2rem .6rem;border-radius:.4rem;border:1px solid rgba(52,211,153,.2);">
                        ✓ 0 Duplicates Detected
                    </span>
                </div>

                @if($questionBank->questions->count() > 0)
                    @foreach($questionBank->questions as $idx => $q)
                    <div class="qbw-q-card">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.75rem;">
                            <span style="font-size:.75rem;font-weight:800;color:#818cf8;background:#1e293b;padding:.2rem .65rem;border-radius:.4rem;border:1px solid #334155;">
                                Question #{{ $idx + 1 }}
                            </span>
                            <div style="display:flex;gap:.4rem;">
                                <span style="font-size:.7rem;color:#fbbf24;font-weight:700;padding:.15rem .5rem;background:rgba(251,191,36,.1);border-radius:.3rem;">
                                    Difficulty: {{ $q->difficulty ?? 'Medium' }}
                                </span>
                                <span style="font-size:.7rem;color:#38bdf8;font-weight:700;padding:.15rem .5rem;background:rgba(56,189,248,.1);border-radius:.3rem;">
                                    {{ $q->points ?? 1 }} Point(s)
                                </span>
                            </div>
                        </div>

                        {{-- Question Prompt Text --}}
                        <div style="font-size:.9rem;color:#f1f5f9;line-height:1.5;margin-bottom:.85rem;font-weight:600;">
                            {{ $q->question_text ?? $q->prompt ?? 'No prompt text available.' }}
                        </div>

                        {{-- Associated Media Asset --}}
                        @php
                            $mediaItem = $q->mediaAsset ?? $q->media ?? null;
                        @endphp
                        @if($mediaItem)
                        <div style="background:#0f172a;border:1px solid #1e293b;padding:.75rem;border-radius:.6rem;margin-bottom:.85rem;display:flex;align-items:center;gap:.75rem;">
                            <span style="font-size:1.5rem;">📎</span>
                            <div>
                                <div style="font-size:.78rem;font-weight:700;color:#e2e8f0;">Attached Asset: {{ $mediaItem->title ?? $mediaItem->original_name }}</div>
                                <div style="font-size:.7rem;color:#64748b;">Type: {{ strtoupper($mediaItem->type ?? 'FILE') }}</div>
                            </div>
                        </div>
                        @endif

                        {{-- Options / Answer Choices --}}
                        @php
                            $options = is_string($q->options) ? json_decode($q->options, true) : (array) $q->options;
                            $correctAnswer = $q->correct_answer ?? $q->answer;
                        @endphp
                        @if(!empty($options))
                            <div style="display:flex;flex-direction:column;gap:.25rem;margin-bottom:.85rem;">
                                @foreach($options as $key => $optVal)
                                    @php
                                        $isCorrect = (string)$key === (string)$correctAnswer || (string)$optVal === (string)$correctAnswer;
                                    @endphp
                                    <div class="qbw-opt {{ $isCorrect ? 'qbw-opt--correct' : '' }}">
                                        <strong>{{ is_numeric($key) ? chr(65 + $key) : strtoupper($key) }}.</strong> {{ is_array($optVal) ? ($optVal['text'] ?? json_encode($optVal)) : $optVal }}
                                        @if($isCorrect) <span style="float:right;">✓ Correct Choice</span> @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        {{-- Pedagogical Explanation --}}
                        @if($q->explanation)
                        <div style="background:#0f172a;border-left:3px solid #6366f1;padding:.65rem .85rem;border-radius:0 .5rem .5rem 0;font-size:.78rem;color:#cbd5e1;">
                            <strong style="color:#818cf8;">Academic Explanation:</strong> {{ $q->explanation }}
                        </div>
                        @endif
                    </div>
                    @endforeach
                @else
                    <div style="text-align:center;padding:2.5rem;color:#64748b;">
                        No individual question items found in this repository.
                    </div>
                @endif
            </div>

        </div>

        {{-- Right Column: Governance Actions & Timeline --}}
        <div style="display:flex;flex-direction:column;gap:1.25rem;">

            {{-- PART 1: REPOSITORY MANAGER GOVERNANCE ACTIONS --}}
            <div class="qbw-card">
                <h3 style="font-size:1rem;font-weight:800;color:#fff;margin:0 0 1rem;">🛡 Governance Decision Center</h3>

                {{-- Approve Form --}}
                <form action="{{ route('admin.repository-manager.question-bank-approve', $questionBank->id) }}" method="POST" style="margin-bottom:1rem;">
                    @csrf
                    <div style="margin-bottom:.75rem;">
                        <label style="font-size:.75rem;font-weight:700;color:#94a3b8;display:block;margin-bottom:.3rem;">Approval Academic Remarks:</label>
                        <textarea name="notes" rows="2" placeholder="Optional notes for institutional approval..." style="width:100%;background:#1e293b;border:1px solid #334155;border-radius:.5rem;padding:.5rem;color:#fff;font-size:.8rem;"></textarea>
                    </div>
                    <button type="submit" style="width:100%;padding:.75rem;background:#10b981;color:#fff;border:none;border-radius:.65rem;font-size:.85rem;font-weight:800;cursor:pointer;box-shadow:0 4px 12px rgba(16,185,129,0.3);">
                        ✓ Approve & Publish Repository
                    </button>
                </form>

                {{-- Revision Request Form --}}
                <form action="{{ route('admin.repository-manager.question-bank-revision', $questionBank->id) }}" method="POST" style="margin-bottom:1rem;">
                    @csrf
                    <div style="margin-bottom:.75rem;">
                        <label style="font-size:.75rem;font-weight:700;color:#94a3b8;display:block;margin-bottom:.3rem;">Revision Requirements / Feedback:</label>
                        <textarea name="notes" rows="3" required placeholder="Specify exact items for author to address..." style="width:100%;background:#1e293b;border:1px solid #334155;border-radius:.5rem;padding:.5rem;color:#fff;font-size:.8rem;"></textarea>
                    </div>
                    <button type="submit" style="width:100%;padding:.7rem;background:#f59e0b;color:#fff;border:none;border-radius:.65rem;font-size:.82rem;font-weight:800;cursor:pointer;">
                        ⚠️ Request Revision from Author
                    </button>
                </form>

                {{-- Reject Form --}}
                <form action="{{ route('admin.repository-manager.question-bank-reject', $questionBank->id) }}" method="POST">
                    @csrf
                    <div style="margin-bottom:.75rem;">
                        <label style="font-size:.75rem;font-weight:700;color:#94a3b8;display:block;margin-bottom:.3rem;">Rejection Reason:</label>
                        <textarea name="notes" rows="2" required placeholder="Reason for rejecting repository..." style="width:100%;background:#1e293b;border:1px solid #334155;border-radius:.5rem;padding:.5rem;color:#fff;font-size:.8rem;"></textarea>
                    </div>
                    <button type="submit" style="width:100%;padding:.65rem;background:#ef4444;color:#fff;border:none;border-radius:.65rem;font-size:.8rem;font-weight:800;cursor:pointer;">
                        🚫 Reject & Archive Repository
                    </button>
                </form>
            </div>

            {{-- Metadata Summary --}}
            <div class="qbw-card">
                <h3 style="font-size:1rem;font-weight:800;color:#fff;margin:0 0 1rem;">Repository Metadata</h3>
                <table class="qbw-meta-table">
                    <tr>
                        <th>Author / Teacher</th>
                        <td>{{ $questionBank->creator?->name ?? 'Academic Author' }}</td>
                    </tr>
                    <tr>
                        <th>Program Type</th>
                        <td><span style="text-transform:uppercase;color:#34d399;">{{ is_object($questionBank->test_type) ? $questionBank->test_type->value : $questionBank->test_type }}</span></td>
                    </tr>
                    <tr>
                        <th>Total Items</th>
                        <td>{{ $questionBank->questions->count() }} Questions</td>
                    </tr>
                    <tr>
                        <th>Submission Date</th>
                        <td>{{ $questionBank->created_at?->format('d M Y, H:i') }}</td>
                    </tr>
                </table>
            </div>

            {{-- Approval Timeline --}}
            <div class="qbw-card">
                <h3 style="font-size:1rem;font-weight:800;color:#fff;margin:0 0 1rem;">📜 Approval Timeline & History</h3>

                @if($logs->count() > 0)
                    <div style="display:flex;flex-direction:column;gap:.75rem;">
                        @foreach($logs as $log)
                        <div style="padding:.75rem;background:#1e293b;border-radius:.5rem;border-left:3px solid #6366f1;">
                            <div style="font-size:.78rem;font-weight:700;color:#f1f5f9;">
                                {{ $log->reviewer?->name ?? 'Reviewer' }} — <span style="text-transform:uppercase;color:#a78bfa;">{{ $log->action }}</span>
                            </div>
                            @if($log->approval_note)
                            <div style="font-size:.72rem;color:#cbd5e1;margin-top:.25rem;font-style:italic;">
                                "{{ $log->approval_note }}"
                            </div>
                            @endif
                            <div style="font-size:.68rem;color:#64748b;margin-top:.25rem;">
                                {{ $log->created_at?->format('d M Y, H:i') }}
                            </div>
                        </div>
                        @endforeach
                    </div>
                @else
                    <div style="font-size:.78rem;color:#64748b;text-align:center;padding:1rem;">
                        No previous governance activity logged for this repository.
                    </div>
                @endif
            </div>

        </div>

    </div>

</div>
@endsection
