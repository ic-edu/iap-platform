@extends(Auth::user()?->hasRole('student') ? 'layouts.candidate' : 'layouts.admin')

@section('title', 'Appearance Settings — Theme & Visual Preferences')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">

    {{-- Page Header --}}
    <div class="pb-4 border-b border-slate-800">
        <div class="flex items-center gap-2 mb-1">
            <span class="px-2.5 py-0.5 rounded text-[11px] font-bold tracking-wider uppercase bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                USER PREFERENCES
            </span>
        </div>
        <h1 class="text-2xl font-black text-white tracking-tight">Appearance &amp; Theme Settings</h1>
        <p class="text-sm text-slate-400">Customize your visual interface preference. Your settings persist across sessions.</p>
    </div>

    @if (session('status') === 'theme-updated')
        <div class="p-4 rounded-xl bg-emerald-950/60 border border-emerald-500/30 text-emerald-300 text-xs font-semibold flex items-center gap-2 shadow-lg">
            ✅ Appearance preference updated successfully.
        </div>
    @endif

    {{-- Theme Selection Form --}}
    <form method="POST" action="{{ route('settings.appearance.update') }}" id="appearance-theme-form" class="space-y-6">
        @csrf
        <input type="hidden" name="theme" id="selected-theme-input" value="{{ $currentTheme }}">

        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">

            {{-- 1. Dark Mode (Default) --}}
            <div onclick="selectTheme('dark')" id="theme-card-dark" class="cursor-pointer rounded-2xl border-2 p-5 transition-all relative overflow-hidden group {{ $currentTheme === 'dark' ? 'border-indigo-500 bg-slate-900/90 shadow-lg shadow-indigo-950/50' : 'border-slate-800 bg-slate-950/60 hover:border-slate-700 hover:bg-slate-900/40' }}">
                <div class="flex items-center justify-between mb-4">
                    <span class="text-2xl">🌙</span>
                    <span id="badge-dark" class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider {{ $currentTheme === 'dark' ? 'bg-indigo-500 text-white' : 'bg-slate-800 text-slate-400' }}">
                        {{ $currentTheme === 'dark' ? 'Active' : 'Dark' }}
                    </span>
                </div>
                <h3 class="text-sm font-bold text-white mb-1">Dark Mode</h3>
                <p class="text-xs text-slate-400 leading-relaxed mb-4">High contrast, slate-dark background optimized for low-light environments and focused assessment operations.</p>
                
                {{-- Mini Preview Box --}}
                <div class="rounded-lg bg-slate-950 border border-slate-800 p-2.5 space-y-1.5 pointer-events-none">
                    <div class="h-2 w-16 bg-indigo-500 rounded"></div>
                    <div class="h-1.5 w-full bg-slate-800 rounded"></div>
                    <div class="h-1.5 w-3/4 bg-slate-800 rounded"></div>
                </div>
            </div>

            {{-- 2. Light Mode --}}
            <div onclick="selectTheme('light')" id="theme-card-light" class="cursor-pointer rounded-2xl border-2 p-5 transition-all relative overflow-hidden group {{ $currentTheme === 'light' ? 'border-indigo-500 bg-slate-900/90 shadow-lg shadow-indigo-950/50' : 'border-slate-800 bg-slate-950/60 hover:border-slate-700 hover:bg-slate-900/40' }}">
                <div class="flex items-center justify-between mb-4">
                    <span class="text-2xl">☀️</span>
                    <span id="badge-light" class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider {{ $currentTheme === 'light' ? 'bg-indigo-500 text-white' : 'bg-slate-800 text-slate-400' }}">
                        {{ $currentTheme === 'light' ? 'Active' : 'Light' }}
                    </span>
                </div>
                <h3 class="text-sm font-bold text-white mb-1">Light Mode</h3>
                <p class="text-xs text-slate-400 leading-relaxed mb-4">Clean, high-visibility bright appearance designed for daylight environments with crisp typography.</p>
                
                {{-- Mini Preview Box --}}
                <div class="rounded-lg bg-slate-100 border border-slate-300 p-2.5 space-y-1.5 pointer-events-none">
                    <div class="h-2 w-16 bg-indigo-600 rounded"></div>
                    <div class="h-1.5 w-full bg-slate-300 rounded"></div>
                    <div class="h-1.5 w-3/4 bg-slate-300 rounded"></div>
                </div>
            </div>

            {{-- 3. System Mode --}}
            <div onclick="selectTheme('system')" id="theme-card-system" class="cursor-pointer rounded-2xl border-2 p-5 transition-all relative overflow-hidden group {{ $currentTheme === 'system' ? 'border-indigo-500 bg-slate-900/90 shadow-lg shadow-indigo-950/50' : 'border-slate-800 bg-slate-950/60 hover:border-slate-700 hover:bg-slate-900/40' }}">
                <div class="flex items-center justify-between mb-4">
                    <span class="text-2xl">💻</span>
                    <span id="badge-system" class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider {{ $currentTheme === 'system' ? 'bg-indigo-500 text-white' : 'bg-slate-800 text-slate-400' }}">
                        {{ $currentTheme === 'system' ? 'Active' : 'System' }}
                    </span>
                </div>
                <h3 class="text-sm font-bold text-white mb-1">System Default</h3>
                <p class="text-xs text-slate-400 leading-relaxed mb-4">Automatically switches between Dark and Light mode matching your operating system or browser preference.</p>
                
                {{-- Mini Preview Box --}}
                <div class="rounded-lg bg-slate-900 border border-slate-700 p-2.5 flex gap-1.5 pointer-events-none">
                    <div class="w-1/2 space-y-1.5 pr-1 border-r border-slate-700">
                        <div class="h-2 w-8 bg-indigo-500 rounded"></div>
                        <div class="h-1.5 w-full bg-slate-800 rounded"></div>
                    </div>
                    <div class="w-1/2 space-y-1.5 pl-1">
                        <div class="h-2 w-8 bg-indigo-600 rounded"></div>
                        <div class="h-1.5 w-full bg-slate-300 rounded"></div>
                    </div>
                </div>
            </div>

        </div>

        {{-- Policy & Security Note --}}
        <div class="p-4 rounded-xl bg-slate-950/80 border border-slate-800/80 text-xs text-slate-400 leading-relaxed space-y-1">
            <p class="font-semibold text-slate-300 flex items-center gap-1.5">
                <span>🛡️</span> Secure Exam Appearance Policy
            </p>
            <p>
                During an active <strong>Secure Real Test</strong> session, appearance switching is locked to the official high-integrity testing view. Your personalized appearance preference is automatically restored immediately upon completing or exiting the assessment.
            </p>
        </div>

        <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-800">
            <button type="submit" class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold shadow-lg shadow-indigo-600/30 transition-all cursor-pointer">
                Save Appearance Preferences
            </button>
        </div>
    </form>

</div>

<script>
function selectTheme(mode) {
    document.getElementById('selected-theme-input').value = mode;

    ['dark', 'light', 'system'].forEach(function(m) {
        var card = document.getElementById('theme-card-' + m);
        var badge = document.getElementById('badge-' + m);

        if (m === mode) {
            card.className = 'cursor-pointer rounded-2xl border-2 p-5 transition-all relative overflow-hidden group border-indigo-500 bg-slate-900/90 shadow-lg shadow-indigo-950/50';
            badge.className = 'px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-indigo-500 text-white';
            badge.textContent = 'Selected';
        } else {
            card.className = 'cursor-pointer rounded-2xl border-2 p-5 transition-all relative overflow-hidden group border-slate-800 bg-slate-950/60 hover:border-slate-700 hover:bg-slate-900/40';
            badge.className = 'px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-slate-800 text-slate-400';
            badge.textContent = m.charAt(0).toUpperCase() + m.slice(1);
        }
    });

    // Apply immediate local preview
    if (typeof window.applyIapTheme === 'function') {
        window.applyIapTheme(mode);
    }
}
</script>
@endsection
