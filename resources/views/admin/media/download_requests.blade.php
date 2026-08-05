@extends('layouts.admin')

@section('title', 'Download Requests Queue — Academic Governance')

@push('styles')
<style>
.drq-page { display:flex; flex-direction:column; gap:1.5rem; }

.drq-header {
    background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #0f172a 100%);
    border: 1px solid #1e293b;
    border-radius: 1.25rem;
    padding: 1.5rem 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
}

.drq-card {
    background: #0f172a;
    border: 1px solid #1e293b;
    border-radius: 1.1rem;
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.drq-table {
    width: 100%;
    border-collapse: collapse;
    font-size: .82rem;
    text-align: left;
}
.drq-table th { padding: .75rem 1rem; background: #1e293b; color: #94a3b8; font-weight: 700; text-transform: uppercase; font-size: .7rem; }
.drq-table td { padding: .85rem 1rem; border-bottom: 1px solid #1e293b; color: #f1f5f9; }
.drq-table tr:last-child td { border-bottom: none; }
</style>
@endpush

@section('content')
<div class="drq-page">

    {{-- Top Header --}}
    <div class="drq-header">
        <div>
            <div style="font-size:.78rem;color:#818cf8;font-weight:700;margin-bottom:.2rem;">
                <a href="{{ route('admin.media.index') }}" style="color:#818cf8;text-decoration:none;">← Institutional Media Repository</a>
            </div>
            <h1 style="font-size:1.4rem;font-weight:800;color:#fff;margin:0;display:flex;align-items:center;gap:.5rem;">
                📥 Download Requests Queue (Super Admin Governance)
            </h1>
            <div style="font-size:.82rem;color:#94a3b8;margin-top:.2rem;">
                Super Admin authorization required to generate temporary signed download URLs.
            </div>
        </div>
    </div>

    @if(session('status'))
    <div style="padding:1rem 1.25rem;background:rgba(16,185,129,.15);border:1px solid rgba(16,185,129,.3);color:#34d399;border-radius:.75rem;font-size:.85rem;font-weight:700;">
        ✔ {{ session('status') }}
    </div>
    @endif

    {{-- Download Requests Queue Table --}}
    <div class="drq-card">
        <h3 style="font-size:1rem;font-weight:800;color:#fff;margin:0;">Pending Download Requests</h3>

        <table class="drq-table">
            <thead>
                <tr>
                    <th>Requested Asset</th>
                    <th>Requester</th>
                    <th>Purpose</th>
                    <th>Reason / Details</th>
                    <th>Date</th>
                    <th>Governance Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($downloadRequests as $req)
                <tr>
                    <td><strong style="color:#38bdf8;">{{ $req->changes_data['asset_title'] ?? "Asset #{$req->resource_id}" }}</strong></td>
                    <td>{{ $req->submitter?->name ?? 'User' }}</td>
                    <td>
                        <span style="padding:.2rem .6rem;border-radius:99px;font-size:.7rem;font-weight:800;background:#1e293b;color:#a5b4fc;border:1px solid #334155;">
                            {{ $req->changes_data['purpose'] ?? 'Audit' }}
                        </span>
                    </td>
                    <td>{{ $req->changes_data['reason'] ?? 'No reason given' }}</td>
                    <td style="color:#94a3b8;">{{ $req->created_at?->format('d M Y, H:i') }}</td>
                    <td>
                        <div style="display:flex;gap:.5rem;">
                            <form action="{{ route('admin.media.approve-download', $req->id) }}" method="POST">
                                @csrf
                                <button type="submit" style="padding:.35rem .75rem;background:#059669;color:#fff;border:none;border-radius:.4rem;font-size:.75rem;font-weight:800;cursor:pointer;">
                                    Approve & Sign URL
                                </button>
                            </form>
                            <form action="{{ route('admin.media.reject-download', $req->id) }}" method="POST">
                                @csrf
                                <button type="submit" style="padding:.35rem .75rem;background:#dc2626;color:#fff;border:none;border-radius:.4rem;font-size:.75rem;font-weight:800;cursor:pointer;">
                                    Reject
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" style="text-align:center;color:#64748b;font-style:italic;padding:2rem;">
                        No pending download requests in queue.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

</div>
@endsection
