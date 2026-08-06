@extends('layouts.admin')

@section('title', 'Question Banks Approval Queue — Repository Governance')

@push('styles')
<style>
.qa-container { display:flex; flex-direction:column; gap:1.5rem; width:100%; max-width:100%; }
.qa-card { background:#0f172a; border:1px solid #1e293b; border-radius:1.25rem; padding:1.5rem; }
.qa-table { width:100%; border-collapse:collapse; font-size:.84rem; text-align:left; }
.qa-table th { padding:.75rem 1rem; background:#1e293b; color:#94a3b8; font-weight:700; border-bottom:1px solid #334155; }
.qa-table td { padding:.85rem 1rem; border-bottom:1px solid #1e293b; color:#e2e8f0; }
.qa-table tr:hover td { background:rgba(30,41,59,.5); }
</style>
@endpush

@section('content')
<div class="qa-container">

    {{-- Header --}}
    <div>
        <a href="{{ route('admin.repository-manager.dashboard') }}" onclick="if (document.referrer && document.referrer !== window.location.href) { history.back(); return false; }" style="color:#818cf8;font-size:.8rem;font-weight:700;text-decoration:none;">
            ← Back
        </a>
        <h1 style="font-size:1.5rem;font-weight:800;color:#fff;margin:.25rem 0 0;">
            Question Banks Approval Queue
        </h1>
        <p style="font-size:.84rem;color:#94a3b8;margin:0;">
            Review and validate new Question Bank repositories before institutional publishing.
        </p>
    </div>

    {{-- Question Banks Table --}}
    <div class="qa-card">
        @if($questionBanks->count() > 0)
            <table class="qa-table">
                <thead>
                    <tr>
                        <th>Repository Title</th>
                        <th>Program / Type</th>
                        <th>Questions</th>
                        <th>Status</th>
                        <th>Governance Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($questionBanks as $bank)
                    <tr>
                        <td>
                            <div style="font-weight:700;color:#fff;">{{ $bank->title }}</div>
                            <div style="font-size:.7rem;color:#64748b;">ID: {{ $bank->id }}</div>
                        </td>
                        <td>
                            <span style="padding:.2rem .6rem;background:#1e293b;border:1px solid #334155;color:#34d399;border-radius:.4rem;font-size:.72rem;font-weight:700;text-transform:uppercase;">
                                {{ is_object($bank->test_type) ? $bank->test_type->value : $bank->test_type }}
                            </span>
                        </td>
                        <td>
                            <span style="font-weight:700;color:#e2e8f0;">{{ $bank->questions_count ?? 15 }} Items</span>
                        </td>
                        <td>
                            <span style="padding:.2rem .6rem;background:rgba(251,191,36,.1);border:1px solid rgba(251,191,36,.3);color:#fbbf24;border-radius:.4rem;font-size:.72rem;font-weight:700;">
                                Awaiting Approval
                            </span>
                        </td>
                        <td>
                            <a href="{{ route('admin.repository-manager.question-bank-validate', $bank->id) }}" style="padding:.4rem .85rem;background:#6366f1;color:#fff;border-radius:.45rem;font-size:.75rem;font-weight:800;text-decoration:none;">
                                Review & Validate →
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div style="text-align:center;padding:3rem;color:#64748b;">
                <div style="font-size:2.5rem;margin-bottom:.5rem;">✨</div>
                <div style="font-size:1rem;font-weight:700;color:#e2e8f0;">No Question Banks Awaiting Review</div>
                <div style="font-size:.8rem;">All submitted institutional repositories have been audited.</div>
            </div>
        @endif
    </div>

</div>
@endsection
