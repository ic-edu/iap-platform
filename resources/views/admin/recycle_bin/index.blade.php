@extends('layouts.admin')

@section('title', 'Recycle Bin — Super Admin Governance Platform')

@push('styles')
<style>
.srb-container { display: flex; flex-direction: column; gap: 1.5rem; width: 100%; }
.srb-hero {
    background: linear-gradient(135deg, #0f172a 0%, #31102b 50%, #0f172a 100%);
    border: 1px solid #4c1d95;
    border-radius: 1.25rem;
    padding: 1.75rem 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1.25rem;
    box-shadow: 0 15px 30px -10px rgba(76,29,149,0.3);
}
.srb-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 1.25rem;
}
.srb-card {
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
.srb-card:hover {
    border-color: #a855f7;
    transform: translateY(-2px);
    box-shadow: 0 12px 25px -8px rgba(168,85,247,0.2);
}
.srb-badge {
    padding: .25rem .7rem;
    border-radius: 99px;
    font-size: .7rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .05em;
    background: rgba(244,63,94,.15);
    color: #fb7185;
    border: 1px solid rgba(244,63,94,.3);
}
</style>
@endpush

@section('content')
<div class="srb-container">

    {{-- Hero Header --}}
    <div class="srb-hero">
        <div>
            <div style="font-size:.72rem;font-weight:800;color:#c084fc;text-transform:uppercase;letter-spacing:.08em;margin-bottom:.3rem;">Super Admin Recovery Vault • Safe Lifecycle</div>
            <h1 style="font-size:1.65rem;font-weight:900;color:#fff;margin:0 0 .3rem;">🗑 Recycle Bin</h1>
            <p style="font-size:.85rem;color:#cbd5e1;margin:0;">Soft-deleted question banks. Repositories can be restored back to ARCHIVED status at any time.</p>
        </div>
        <div style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap;">
            <a href="{{ route('admin.archived-repositories.index') }}" style="padding:.5rem 1rem;background:#1e293b;border:1px solid #334155;color:#e2e8f0;border-radius:.6rem;font-size:.8rem;font-weight:700;text-decoration:none;display:inline-flex;align-items:center;gap:.4rem;">
                📦 Archived Repositories →
            </a>
            <form action="{{ route('admin.recycle-bin.index') }}" method="GET" style="display:flex;gap:.5rem;">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search recycle bin..." style="background:#0f172a;border:1px solid #334155;border-radius:.6rem;padding:.5rem .85rem;color:#fff;font-size:.8rem;min-width:200px;">
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

    {{-- Recycle Bin Cards Grid --}}
    @if($trashedBanks->count() > 0)
    <div class="srb-grid">
        @foreach($trashedBanks as $bank)
        <div class="srb-card">
            <div>
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:.75rem;margin-bottom:.75rem;">
                    <div>
                        <h3 style="font-size:1.1rem;font-weight:800;color:#f1f5f9;margin:0 0 .25rem;">
                            {{ $bank->title }}
                        </h3>
                        <div style="font-size:.74rem;color:#64748b;">
                            Deleted {{ $bank->deleted_at?->diffForHumans() }} • Author: {{ $bank->creator?->name ?? 'Teacher Author' }}
                        </div>
                    </div>
                    <span class="srb-badge">
                        RECYCLE BIN
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

            {{-- Actions: View Trashed + Restore from Recycle Bin --}}
            <div style="display:flex;gap:.75rem;align-items:center;border-top:1px solid #1e293b;padding-top:1rem;">
                <a href="{{ route('admin.recycle-bin.show', $bank->id) }}"
                   style="flex:1;text-align:center;padding:.6rem;background:#1e293b;border:1px solid #334155;color:#f1f5f9;border-radius:.6rem;font-size:.8rem;font-weight:700;text-decoration:none;transition:background .15s;">
                    👁 View
                </a>

                <form action="{{ route('admin.recycle-bin.restore', $bank->id) }}" method="POST" style="flex:1;"
                      onsubmit="event.preventDefault(); iapConfirm({ title: 'Restore this repository from the Recycle Bin?', message: 'Restore \'{{ addslashes($bank->title) }}\' from the Recycle Bin back to Archived status? Expected destination after restore: ARCHIVED.', confirmText: 'Restore to Archived', variant: 'warning', form: this });">
                    @csrf
                    <button type="submit"
                            style="width:100%;padding:.6rem;background:#059669;color:#fff;border:none;border-radius:.6rem;font-size:.8rem;font-weight:800;cursor:pointer;box-shadow:0 4px 12px rgba(5,150,105,0.3);">
                        ↩ Restore
                    </button>
                </form>
            </div>
        </div>
        @endforeach
    </div>

    <div style="margin-top:1rem;">
        {{ $trashedBanks->links() }}
    </div>
    @else
    <div style="text-align:center;padding:4rem 2rem;background:#0f172a;border:1px solid #1e293b;border-radius:1.25rem;color:#64748b;">
        <div style="font-size:2.5rem;margin-bottom:1rem;">✨</div>
        <h3 style="font-size:1.2rem;font-weight:800;color:#fff;margin:0 0 .5rem;">Recycle Bin is Empty</h3>
        <p style="font-size:.85rem;color:#94a3b8;margin:0;">No repositories have been moved to the recycle bin.</p>
    </div>
    @endif

</div>
@endsection
