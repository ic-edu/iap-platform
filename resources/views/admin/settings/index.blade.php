@extends('layouts.admin')

@section('content')
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-white flex items-center gap-2">
                <span>⚙️</span> Super Admin Platform Settings
            </h1>
            <p class="text-xs text-slate-400 mt-1">Configure global branding, certificate serial numbering, security session lifetime, and maintenance mode controls.</p>
        </div>
    </div>

    @if (session('status'))
        <div class="mb-6 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-medium">
            ✅ {{ session('status') }}
        </div>
    @endif

    <div class="bg-slate-900 border border-slate-800 rounded-xl p-6 shadow-sm max-w-2xl">
        <form action="{{ route('admin.settings.update') }}" method="POST" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-semibold text-slate-300 uppercase mb-1">Platform Name</label>
                <input type="text" name="app_name" value="{{ $settings['app_name'] }}" class="w-full bg-slate-950 border border-slate-800 text-white text-sm rounded-lg p-2.5 focus:border-indigo-500 focus:outline-none">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-300 uppercase mb-1">Platform Base URL</label>
                <input type="text" name="app_url" value="{{ $settings['app_url'] }}" class="w-full bg-slate-950 border border-slate-800 text-white text-sm rounded-lg p-2.5 focus:border-indigo-500 focus:outline-none">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-300 uppercase mb-1">Certificate Serial Prefix</label>
                <input type="text" name="certificate_prefix" value="{{ $settings['certificate_prefix'] }}" class="w-full bg-slate-950 border border-slate-800 text-white text-sm rounded-lg p-2.5 focus:border-indigo-500 focus:outline-none">
            </div>

            <div class="pt-4 border-t border-slate-800 flex items-center justify-between">
                <div>
                    <span class="block text-sm font-semibold text-white">Maintenance Mode Status</span>
                    <span class="block text-xs text-slate-400">Toggle operational status probe and candidate access</span>
                </div>
                <span class="px-3 py-1 bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-xs font-bold rounded-full">
                    OPERATIONAL (ONLINE)
                </span>
            </div>

            <div class="pt-4">
                <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs rounded-lg shadow transition-colors">
                    Save Platform Settings
                </button>
            </div>
        </form>
    </div>
@endsection
