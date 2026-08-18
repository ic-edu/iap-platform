@extends('layouts.admin')

@section('title', 'Archived Repositories — Governance Platform')

@push('styles')
<style>
.sar-container { display: flex; flex-direction: column; gap: 1.5rem; width: 100%; }
.sar-hero {
    background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #0f172a 100%);
    border: 1px solid #334155;
    border-radius: 1.25rem;
    padding: 1.75rem 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1.25rem;
    box-shadow: 0 15px 30px -10px rgba(15,23,42,0.5);
}
.sar-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 1.25rem;
}
.sar-card {
    background: #0f172a;
    border: 1px solid #1e293b;
    border-radius: 1.25rem;
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    gap: 1.25rem;
    transition: all .2s ease;
}
.sar-card:hover {
    border-color: #6366f1;
    transform: translateY(-2px);
    box-shadow: 0 12px 25px -8px rgba(99,102,241,0.2);
}
.sar-badge {
    padding: .25rem .7rem;
    border-radius: 99px;
    font-size: .7rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .05em;
}
.sar-badge--archived { background: rgba(148,163,184,.12); color: #94a3b8; border: 1px solid rgba(148,163,184,.3); }
</style>
@endpush

@section('content')
<div class="sar-container">

    {{-- Hero Header --}}
    <div class="sar-hero">
        <div>
            <div style="font-size:.72rem;font-weight:800;color:#818cf8;text-transform:uppercase;letter-spacing:.08em;margin-bottom:.3rem;">Super Admin Governance • Repository Lifecycle</div>
            <h1 style="font-size:1.65rem;font-weight:900;color:#fff;margin:0 0 .3rem;">📦 Archived Repositories</h1>
            <p style="font-size:.85rem;color:#94a3b8;margin:0;">Institutional question bank repositories in archived status. Move to Recycle Bin for safe lifecycle management.</p>
        </div>
        <div style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap;">
            <a href="{{ route('admin.recycle-bin.index') }}" style="padding:.5rem 1rem;background:#1e293b;border:1px solid #334155;color:#e2e8f0;border-radius:.6rem;font-size:.8rem;font-weight:700;text-decoration:none;display:inline-flex;align-items:center;gap:.4rem;">
                🗑 Open Recycle Bin →
            </a>
            <form action="{{ route('admin.archived-repositories.index') }}" method="GET" style="display:flex;gap:.5rem;">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search archived repositories..." style="background:#0f172a;border:1px solid #334155;border-radius:.6rem;padding:.5rem .85rem;color:#fff;font-size:.8rem;min-width:220px;">
                <button type="submit" style="padding:.5rem 1rem;background:#334155;color:#fff;border:none;border-radius:.6rem;font-size:.8rem;font-weight:700;cursor:pointer;">
                    Search
                </button>
            </form>
        </div>
    </div>

    @if(session('status'))
    <div style="background:rgba(52,211,153,.12);border:1px solid rgba(52,211,153,.3);color:#34d399;padding:1rem 1.25rem;border-radius:.75rem;font-size:.88rem;font-weight:700;">
        ✅ {{ session('status') }}
    </div>
    @endif

    @if(session('danger'))
    <div style="background:rgba(244,63,94,.12);border:1px solid rgba(244,63,94,.3);color:#fb7185;padding:1rem 1.25rem;border-radius:.75rem;font-size:.88rem;font-weight:700;">
        ⚠️ {{ session('danger') }}
    </div>
    @endif

    {{-- Archived Cards Grid --}}
    @if($archivedBanks->count() > 0)
    <div class="sar-grid">
        @foreach($archivedBanks as $bank)
        <div class="sar-card">
            <div>
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:.75rem;margin-bottom:.75rem;">
                    <div>
                        <h3 style="font-size:1.1rem;font-weight:800;color:#f1f5f9;margin:0 0 .25rem;">
                            {{ $bank->title }}
                        </h3>
                        <div style="font-size:.74rem;color:#64748b;">
                            Version v{{ $bank->current_version ?? '1.0' }} • Author: {{ $bank->creator?->name ?? 'Teacher Author' }}
                        </div>
                    </div>
                    <span class="sar-badge sar-badge--archived">
                        ARCHIVED
                    </span>
                </div>

                <div style="font-size:.82rem;color:#94a3b8;margin-bottom:1rem;line-height:1.4;">
                    {{ Str::limit($bank->description, 100) ?: 'No description provided.' }}
                </div>

                <div style="display:flex;gap:1rem;flex-wrap:wrap;font-size:.76rem;color:#64748b;background:#080f1d;padding:.75rem 1rem;border-radius:.65rem;border:1px solid #1e293b;">
                    <span>📝 <strong>{{ $bank->questions->count() }}</strong> Questions</span>
                    <span>🏷 <strong>{{ is_object($bank->test_type) ? $bank->test_type->label() : strtoupper($bank->test_type?->value ?? 'General') }}</strong></span>
                </div>
            </div>

            {{-- Actions: View + Move to Recycle Bin ONLY (Soft Delete, Never Hard Delete) --}}
            <div style="display:flex;gap:.75rem;align-items:center;border-top:1px solid #1e293b;padding-top:1rem;">
                <a href="{{ route('admin.question-banks.show', ['questionBank' => $bank->id, 'from' => 'archived_repositories']) }}"
                   style="flex:1;text-align:center;padding:.6rem;background:#1e293b;border:1px solid #334155;color:#f1f5f9;border-radius:.6rem;font-size:.8rem;font-weight:700;text-decoration:none;transition:background .15s;">
                    👁 View
                </a>

                <form action="{{ route('admin.archived-repositories.move-to-recycle-bin', $bank->id) }}" method="POST" style="flex:1;"
                      onsubmit="event.preventDefault(); iapConfirm({ title: 'Move this repository to the Recycle Bin?', message: 'Move \'{{ addslashes($bank->title) }}\' to the Recycle Bin? The repository record, questions, metadata, and audit history will be preserved and can be restored later.', confirmText: 'Move to Recycle Bin', variant: 'danger', form: this });">
                    @csrf
                    <button type="submit"
                            style="width:100%;padding:.6rem;background:#e11d48;color:#fff;border:none;border-radius:.6rem;font-size:.8rem;font-weight:800;cursor:pointer;box-shadow:0 4px 12px rgba(225,29,72,0.3);">
                        🗑 Move to Recycle Bin
                    </button>
                </form>
            </div>
        </div>
        @endforeach
    </div>

    <div style="margin-top:1rem;">
        {{ $archivedBanks->links() }}
    </div>
    @else
    <div style="text-align:center;padding:4rem 2rem;background:#0f172a;border:1px solid #1e293b;border-radius:1.25rem;color:#64748b;">
        <div style="font-size:2.5rem;margin-bottom:1rem;">📦</div>
        <h3 style="font-size:1.2rem;font-weight:800;color:#fff;margin:0 0 .5rem;">No Archived Repositories</h3>
        <p style="font-size:.85rem;color:#94a3b8;margin:0;">There are currently no question banks in archived status across the platform.</p>
    </div>
    @endif

</div>
@endsection
