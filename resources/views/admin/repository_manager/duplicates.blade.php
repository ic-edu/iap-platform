@extends('layouts.admin')

@section('title', 'Duplicate Detection & Similarity Center — Repository Governance')

@section('content')
<div class="space-y-6">

    {{-- Header --}}
    <div>
        <a href="{{ route('admin.repository-manager.dashboard') }}" onclick="if (document.referrer && document.referrer !== window.location.href) { history.back(); return false; }" class="text-indigo-600 dark:text-indigo-400 text-xs font-bold hover:underline inline-flex items-center gap-1">
            ← Back
        </a>
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white mt-1">
            🔍 Duplicate Detection &amp; Content Similarity Center
        </h1>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
            AI-assisted similarity audit to prevent redundant questions, passages, and media assets in institutional repositories.
        </p>
    </div>

    {{-- Duplicates Scanner Overview --}}
    <div class="gov-card space-y-4">
        <div class="flex justify-between items-center flex-wrap gap-3">
            <div>
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Simulated Duplicate Flagged Items</h3>
                <div class="text-xs text-slate-500 dark:text-slate-400">High-similarity content detected across TOEFL and IELTS repositories.</div>
            </div>
            <button type="button" onclick="iapAlert({ title: 'Full Repository Scan', message: '⚡ Automated Full Repository Scan Triggered. Scanning 1,420 items...', variant: 'info' })" class="px-3.5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-xs font-bold shadow transition-colors cursor-pointer inline-flex items-center gap-1.5">
                ⚡ Run Full Repository Scan
            </button>
        </div>

        <div class="p-4 rounded-xl border border-rose-200 dark:border-rose-900/40 bg-rose-50/50 dark:bg-rose-950/20 space-y-3">
            <div class="flex justify-between items-center flex-wrap gap-2">
                <span class="text-xs font-bold text-rose-700 dark:text-rose-400">⚠️ Flagged Duplicate Question Item #Q-8902</span>
                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20">
                    98.4% Similarity Match
                </span>
            </div>
            <div class="text-xs text-slate-800 dark:text-slate-200 leading-relaxed">
                <strong>Original:</strong> "According to paragraph 2, why did early Mesopotamian civilizations construct ziggurats?"<br>
                <strong class="text-amber-700 dark:text-amber-400">Duplicate Candidate:</strong> "Based on paragraph 2, what was the primary reason early Mesopotamian societies built ziggurats?"
            </div>
            <div class="flex gap-2 pt-1">
                <button type="button" onclick="iapAlert({ title: 'Items Merged', message: 'Merged candidate into master question item.', variant: 'success' })" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-xs font-bold shadow-sm transition-colors cursor-pointer">
                    Merge Items
                </button>
                <button type="button" onclick="iapAlert({ title: 'Flag Dismissed', message: 'Flag dismissed as false positive.', variant: 'info' })" class="px-3 py-1.5 gov-btn-secondary text-xs font-bold rounded-lg cursor-pointer">
                    Dismiss Flag
                </button>
            </div>
        </div>

        <div class="p-4 rounded-xl border border-amber-200 dark:border-amber-900/40 bg-amber-50/50 dark:bg-amber-950/20 space-y-3">
            <div class="flex justify-between items-center flex-wrap gap-2">
                <span class="text-xs font-bold text-amber-700 dark:text-amber-400">⚠️ Duplicate Reading Passage #P-4011</span>
                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">
                    92.1% Similarity Match
                </span>
            </div>
            <div class="text-xs text-slate-800 dark:text-slate-200 leading-relaxed">
                <strong>Original:</strong> "The Economic Implications of Industrial Micro-Grids in Renewable Energy Systems"<br>
                <strong class="text-amber-700 dark:text-amber-400">Duplicate Candidate:</strong> "Micro-Grid Financial Models for Industrial Renewable Power Integration"
            </div>
            <div class="flex gap-2 pt-1">
                <button type="button" onclick="iapAlert({ title: 'Passages Merged', message: 'Merged passage versions.', variant: 'success' })" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-xs font-bold shadow-sm transition-colors cursor-pointer">
                    Merge Passages
                </button>
                <button type="button" onclick="iapAlert({ title: 'Flag Dismissed', message: 'Flag dismissed.', variant: 'info' })" class="px-3 py-1.5 gov-btn-secondary text-xs font-bold rounded-lg cursor-pointer">
                    Dismiss Flag
                </button>
            </div>
        </div>

    </div>

</div>
@endsection

