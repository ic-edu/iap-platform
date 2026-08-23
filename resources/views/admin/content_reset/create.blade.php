@extends('layouts.admin')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-white flex items-center gap-2">
                <span>🛡️</span> New Content Refresh / Reset Request
            </h1>
            <p class="text-xs text-slate-400 mt-1">Configure scoped targets for forensic analysis and dual approval review.</p>
        </div>
        <div>
            <a href="{{ route('admin.content-reset.index') }}" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 font-semibold text-xs rounded-xl border border-slate-700 transition-colors">
                &larr; Back to Registry
            </a>
        </div>
    </div>

    <div class="grid lg:grid-cols-3 gap-6">
        <!-- Main Form -->
        <div class="lg:col-span-2 space-y-6">
            <form action="{{ route('admin.content-reset.store') }}" method="POST" class="p-6 bg-slate-900 border border-slate-800 rounded-2xl space-y-6 shadow-sm">
                @csrf

                <!-- Mode Selection -->
                <div>
                    <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-2">Reset Mode</label>
                    <div class="grid sm:grid-cols-2 gap-4">
                        <label class="p-4 rounded-xl border border-slate-800 bg-slate-950/60 flex items-start gap-3 cursor-pointer hover:border-indigo-500/50 transition-colors">
                            <input type="radio" name="mode" value="refresh" checked class="mt-1 text-indigo-600 focus:ring-indigo-500">
                            <div>
                                <span class="font-bold text-white text-xs block">Mode A: Content Refresh</span>
                                <span class="text-xxs text-slate-400 block mt-0.5 leading-relaxed">Routine UAT cleanup, draft cleanup, and transient content purging. Single administrative gate.</span>
                            </div>
                        </label>

                        <label class="p-4 rounded-xl border border-slate-800 bg-slate-950/60 flex items-start gap-3 cursor-pointer hover:border-rose-500/50 transition-colors">
                            <input type="radio" name="mode" value="hard_reset" class="mt-1 text-rose-600 focus:ring-rose-500">
                            <div>
                                <span class="font-bold text-rose-400 text-xs block">Mode B: Content Hard Reset</span>
                                <span class="text-xxs text-slate-400 block mt-0.5 leading-relaxed">Major clean-slate content reset. Requires Dual Approval (CEO + Super Admin) and immutable snapshot.</span>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Scope Selection -->
                <div>
                    <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-2">Content Domains In Scope</label>
                    <div class="grid sm:grid-cols-2 gap-2.5">
                        @foreach($resettableDomains as $domain)
                            <label class="p-2.5 rounded-lg border border-slate-800 bg-slate-950/40 flex items-center gap-2.5 text-xs text-slate-300 hover:text-white cursor-pointer">
                                <input type="checkbox" name="scope[]" value="{{ $domain }}" {{ in_array($domain, ['draft_assessments', 'transient_uat_attempts']) ? 'checked' : '' }} class="rounded border-slate-700 text-indigo-600 focus:ring-indigo-500">
                                <span>{{ ucwords(str_replace('_', ' ', $domain)) }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <!-- Target Assessments (Optional) -->
                <div>
                    <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-2">Target Specific Assessments (Optional)</label>
                    <select name="target_assessments[]" multiple class="w-full h-32 p-3 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-300 focus:border-indigo-500 focus:outline-none">
                        @foreach($availableTests as $test)
                            <option value="{{ $test->id }}">
                                [{{ strtoupper($test->status) }}] {{ $test->title }}
                            </option>
                        @endforeach
                    </select>
                    <span class="text-xxs text-slate-500 block mt-1">Leave empty to target all assessments matching selected scope domains.</span>
                </div>

                <!-- Reason -->
                <div>
                    <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-2">Operational Reason &amp; Business Justification</label>
                    <textarea name="reason" rows="3" required placeholder="Describe the reason for content reset/refresh..." class="w-full p-3 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white placeholder-slate-500 focus:border-indigo-500 focus:outline-none"></textarea>
                </div>

                <div class="pt-4 border-t border-slate-800 flex items-center justify-end gap-3">
                    <a href="{{ route('admin.content-reset.index') }}" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 font-semibold text-xs rounded-xl transition-colors">
                        Cancel
                    </a>
                    <button type="submit" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs rounded-xl shadow-md transition-colors">
                        🛡️ Submit &amp; Run Forensic Audit
                    </button>
                </div>
            </form>
        </div>

        <!-- Protected Domains Sidebar -->
        <div class="space-y-6">
            <div class="p-6 bg-slate-900 border border-slate-800 rounded-2xl shadow-sm">
                <h2 class="text-sm font-bold text-emerald-400 flex items-center gap-2 mb-3">
                    <span>🔒</span> Absolutely Protected Domains
                </h2>
                <p class="text-xxs text-slate-400 mb-4 leading-relaxed">
                    The reset architecture enforces a strict deny-list. The following platform assets are hard-protected and will NEVER be touched:
                </p>
                <ul class="space-y-2 text-xs text-slate-300">
                    <li class="p-2 rounded bg-slate-950 border border-slate-800 flex items-center gap-2">
                        <span>💰</span> Finance (Payments, Invoices, Orders)
                    </li>
                    <li class="p-2 rounded bg-slate-950 border border-slate-800 flex items-center gap-2">
                        <span>👥</span> HR &amp; Users (Staff, Roles, Permissions)
                    </li>
                    <li class="p-2 rounded bg-slate-950 border border-slate-800 flex items-center gap-2">
                        <span>📜</span> Audit &amp; Activity Logs (Immutable History)
                    </li>
                    <li class="p-2 rounded bg-slate-950 border border-slate-800 flex items-center gap-2">
                        <span>⚙️</span> Platform Engines &amp; Core Logic
                    </li>
                    <li class="p-2 rounded bg-slate-950 border border-slate-800 flex items-center gap-2">
                        <span>🎨</span> UI/UX, Themes &amp; Blade Views
                    </li>
                </ul>
            </div>
        </div>
    </div>
@endsection
