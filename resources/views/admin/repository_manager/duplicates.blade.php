@extends('layouts.admin')

@section('title', 'Duplicate Detection & Similarity Center — Repository Governance')

@push('styles')
<style>
.dd-container { display:flex; flex-direction:column; gap:1.5rem; width:100%; max-width:100%; }
.dd-card { background:#0f172a; border:1px solid #1e293b; border-radius:1.25rem; padding:1.5rem; }
.dd-box { background:#1e293b; border:1px solid #334155; border-radius:.85rem; padding:1.25rem; margin-bottom:1rem; }
</style>
@endpush

@section('content')
<div class="dd-container">

    {{-- Header --}}
    <div>
        <a href="{{ route('admin.repository-manager.dashboard') }}" onclick="if (document.referrer && document.referrer !== window.location.href) { history.back(); return false; }" style="color:#818cf8;font-size:.8rem;font-weight:700;text-decoration:none;">
            ← Back
        </a>
        <h1 style="font-size:1.5rem;font-weight:800;color:#fff;margin:.25rem 0 0;">
            🔍 Duplicate Detection & Content Similarity Center
        </h1>
        <p style="font-size:.84rem;color:#94a3b8;margin:0;">
            AI-assisted similarity audit to prevent redundant questions, passages, and media assets in institutional repositories.
        </p>
    </div>

    {{-- Duplicates Scanner Overview --}}
    <div class="dd-card">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;flex-wrap:wrap;gap:1rem;">
            <div>
                <h3 style="font-size:1.05rem;font-weight:800;color:#fff;margin:0;">Simulated Duplicate Flagged Items</h3>
                <div style="font-size:.78rem;color:#94a3b8;">High-similarity content detected across TOEFL and IELTS repositories.</div>
            </div>
            <button type="button" onclick="iapAlert({ title: 'Full Repository Scan', message: '⚡ Automated Full Repository Scan Triggered. Scanning 1,420 items...', variant: 'info' })" style="padding:.55rem 1.1rem;background:#6366f1;color:#fff;border:none;border-radius:.6rem;font-size:.82rem;font-weight:800;cursor:pointer;">
                ⚡ Run Full Repository Scan
            </button>
        </div>

        <div class="dd-box">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.5rem;">
                <span style="font-size:.82rem;font-weight:800;color:#f87171;">⚠️ Flagged Duplicate Question Item #Q-8902</span>
                <span style="font-size:.72rem;font-weight:800;color:#f87171;background:rgba(239,68,68,.1);padding:.2rem .6rem;border-radius:99px;border:1px solid rgba(239,68,68,.3);">
                    98.4% Similarity Match
                </span>
            </div>
            <div style="font-size:.85rem;color:#e2e8f0;line-height:1.5;">
                <strong>Original:</strong> "According to paragraph 2, why did early Mesopotamian civilizations construct ziggurats?"<br>
                <strong style="color:#fb923c;">Duplicate Candidate:</strong> "Based on paragraph 2, what was the primary reason early Mesopotamian societies built ziggurats?"
            </div>
            <div style="display:flex;gap:.5rem;margin-top:1rem;">
                <button type="button" onclick="iapAlert({ title: 'Items Merged', message: 'Merged candidate into master question item.', variant: 'success' })" style="padding:.35rem .8rem;background:#10b981;color:#fff;border:none;border-radius:.4rem;font-size:.75rem;font-weight:700;cursor:pointer;">Merge Items</button>
                <button type="button" onclick="iapAlert({ title: 'Flag Dismissed', message: 'Flag dismissed as false positive.', variant: 'info' })" style="padding:.35rem .8rem;background:#1e293b;border:1px solid #334155;color:#94a3b8;border-radius:.4rem;font-size:.75rem;font-weight:700;cursor:pointer;">Dismiss Flag</button>
            </div>
        </div>

        <div class="dd-box">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.5rem;">
                <span style="font-size:.82rem;font-weight:800;color:#fbbf24;">⚠️ Duplicate Reading Passage #P-4011</span>
                <span style="font-size:.72rem;font-weight:800;color:#fbbf24;background:rgba(251,191,36,.1);padding:.2rem .6rem;border-radius:99px;border:1px solid rgba(251,191,36,.3);">
                    92.1% Similarity Match
                </span>
            </div>
            <div style="font-size:.85rem;color:#e2e8f0;line-height:1.5;">
                <strong>Original:</strong> "The Economic Implications of Industrial Micro-Grids in Renewable Energy Systems"<br>
                <strong style="color:#fb923c;">Duplicate Candidate:</strong> "Micro-Grid Financial Models for Industrial Renewable Power Integration"
            </div>
            <div style="display:flex;gap:.5rem;margin-top:1rem;">
                <button type="button" onclick="iapAlert({ title: 'Passages Merged', message: 'Merged passage versions.', variant: 'success' })" style="padding:.35rem .8rem;background:#10b981;color:#fff;border:none;border-radius:.4rem;font-size:.75rem;font-weight:700;cursor:pointer;">Merge Passages</button>
                <button type="button" onclick="iapAlert({ title: 'Flag Dismissed', message: 'Flag dismissed.', variant: 'info' })" style="padding:.35rem .8rem;background:#1e293b;border:1px solid #334155;color:#94a3b8;border-radius:.4rem;font-size:.75rem;font-weight:700;cursor:pointer;">Dismiss Flag</button>
            </div>
        </div>

    </div>

</div>
@endsection
