<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ Auth::user()?->getThemePreference() ?? session('theme_preference', 'dark') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Organization Portal' }} — iC.edu Assessment Platform</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

    <!-- Early Theme Initialization to prevent flash of wrong theme -->
    <script>
        (function() {
            var preference = '{{ Auth::user()?->getThemePreference() ?? session('theme_preference', 'dark') }}';
            function resolveTheme(pref) {
                if (pref === 'system') {
                    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
                }
                return pref === 'light' ? 'light' : 'dark';
            }
            var activeTheme = resolveTheme(preference);
            var root = document.documentElement;
            root.setAttribute('data-theme', activeTheme);
            root.setAttribute('data-preference', preference);
            if (activeTheme === 'dark') {
                root.classList.add('dark');
                root.classList.remove('light');
            } else {
                root.classList.add('light');
                root.classList.remove('dark');
            }
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100 min-h-screen">
    <div class="flex min-h-screen">
        <!-- Sidebar Navigation -->
        <aside class="w-64 bg-white dark:bg-slate-900 border-r border-slate-200 dark:border-slate-800 flex flex-col fixed inset-y-0 z-30">
            <!-- Brand & Organization Context -->
            <div class="p-5 border-b border-slate-200 dark:border-slate-800">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-indigo-600 text-white font-bold flex items-center justify-center shadow-sm">
                        {{ strtoupper(substr($currentOrganization->name ?? 'IAP', 0, 2)) }}
                    </div>
                    <div class="overflow-hidden">
                        <div class="font-bold text-sm text-slate-900 dark:text-white truncate">
                            {{ $currentOrganization->name ?? 'Organization' }}
                        </div>
                        <div class="text-xs text-slate-500 dark:text-slate-400 flex items-center gap-1.5">
                            <span class="inline-block w-2 h-2 rounded-full {{ ($currentOrganization->status->value ?? '') === 'active' ? 'bg-emerald-500' : 'bg-amber-500' }}"></span>
                            <span class="capitalize">{{ $currentOrganization->organization_type->label() ?? 'Organization' }}</span>
                        </div>
                    </div>
                </div>

                @if(isset($currentMembership))
                    <div class="mt-3 px-2.5 py-1 rounded bg-slate-100 dark:bg-slate-800/60 text-[11px] font-medium text-slate-600 dark:text-slate-300 flex items-center justify-between">
                        <span>Role:</span>
                        <span class="font-semibold text-indigo-600 dark:text-indigo-400 capitalize">{{ $currentMembership->role->label() }}</span>
                    </div>
                @endif
            </div>

            <!-- Nav Links -->
            <nav class="flex-1 p-4 space-y-1 overflow-y-auto">
                @php
                    $orgSlug = $currentOrganization->slug ?? '';
                @endphp
                <a href="{{ route('organization.dashboard', $orgSlug) }}"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors {{ request()->routeIs('organization.dashboard') ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/50 dark:text-indigo-300' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800' }}">
                    <svg class="w-5 h-5 opacity-75" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                    Dashboard
                </a>

                <a href="{{ route('organization.candidates', $orgSlug) }}"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors {{ request()->routeIs('organization.candidates*') ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/50 dark:text-indigo-300' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800' }}">
                    <svg class="w-5 h-5 opacity-75" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                    Members / Candidates
                </a>

                <a href="{{ route('organization.groups', $orgSlug) }}"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors {{ request()->routeIs('organization.groups*') ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/50 dark:text-indigo-300' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800' }}">
                    <svg class="w-5 h-5 opacity-75" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    Groups / Cohorts
                </a>

                <a href="{{ route('organization.purchases', $orgSlug) }}"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors {{ request()->routeIs('organization.purchases*') ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/50 dark:text-indigo-300' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800' }}">
                    <svg class="w-5 h-5 opacity-75" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                    Purchases &amp; Orders
                </a>

                <a href="{{ route('organization.entitlements', $orgSlug) }}"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors {{ request()->routeIs('organization.entitlements*') ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/50 dark:text-indigo-300' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800' }}">
                    <svg class="w-5 h-5 opacity-75" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>
                    Seats &amp; Entitlements
                </a>

                <a href="{{ route('organization.profile', $orgSlug) }}"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors {{ request()->routeIs('organization.profile') ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/50 dark:text-indigo-300' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800' }}">
                    <svg class="w-5 h-5 opacity-75" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    Organization Profile
                </a>
            </nav>

            <!-- Bottom User & Switcher Area -->
            <div class="p-4 border-t border-slate-200 dark:border-slate-800 space-y-3">
                <!-- Theme Selector: [ ☼ Light ] [ ☾ Dark ] [ ▣ System ] -->
                <div class="space-y-1.5">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 block">Theme &amp; Appearance</span>
                    <div class="grid grid-cols-3 gap-1.5 p-1 bg-slate-100 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl" role="group" aria-label="Theme selector">
                        <button type="button"
                                onclick="setIapTheme('light')"
                                id="theme-btn-light"
                                class="theme-switcher-btn flex items-center justify-center gap-1 py-1.5 px-2 rounded-lg text-xs font-bold text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white transition-all cursor-pointer"
                                title="Light theme"
                                aria-label="Select Light theme">
                            <span class="text-sm">☼</span>
                            <span class="text-[11px]">Light</span>
                        </button>
                        <button type="button"
                                onclick="setIapTheme('dark')"
                                id="theme-btn-dark"
                                class="theme-switcher-btn flex items-center justify-center gap-1 py-1.5 px-2 rounded-lg text-xs font-bold text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white transition-all cursor-pointer"
                                title="Dark theme"
                                aria-label="Select Dark theme">
                            <span class="text-sm">☾</span>
                            <span class="text-[11px]">Dark</span>
                        </button>
                        <button type="button"
                                onclick="setIapTheme('system')"
                                id="theme-btn-system"
                                class="theme-switcher-btn flex items-center justify-center gap-1 py-1.5 px-2 rounded-lg text-xs font-bold text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white transition-all cursor-pointer"
                                title="System theme"
                                aria-label="Select System theme">
                            <span class="text-sm">▣</span>
                            <span class="text-[11px]">System</span>
                        </button>
                    </div>
                </div>

                <!-- Switch Organization -->
                <a href="{{ route('organization.select') }}" class="w-full flex items-center justify-between px-3 py-2 text-xs font-medium text-slate-600 dark:text-slate-400 bg-slate-100 dark:bg-slate-800/70 rounded-lg hover:bg-slate-200 dark:hover:bg-slate-800 transition">
                    <span>Switch Organization</span>
                    <svg class="w-4 h-4 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4"/></svg>
                </a>

                <div class="flex items-center justify-between pt-1">
                    <div class="flex items-center gap-2 overflow-hidden">
                        <div class="w-8 h-8 rounded-full bg-slate-200 dark:bg-slate-700 flex items-center justify-center text-xs font-bold text-slate-700 dark:text-slate-300">
                            {{ strtoupper(substr(Auth::user()->name ?? 'U', 0, 1)) }}
                        </div>
                        <div class="truncate text-xs font-medium text-slate-700 dark:text-slate-300">
                            {{ Auth::user()->name ?? 'User' }}
                        </div>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" title="Log Out" class="p-1.5 text-slate-400 hover:text-rose-600 dark:hover:text-rose-400 transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        <!-- Main Content Area -->
        <div class="flex-1 ml-64 flex flex-col">
            <!-- Header -->
            <header class="bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 px-8 py-4 flex items-center justify-between sticky top-0 z-20 shadow-xs">
                <div>
                    <h1 class="text-xl font-bold text-slate-900 dark:text-white">
                        {{ $heading ?? 'Organization Portal' }}
                    </h1>
                    @if(isset($subheading))
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ $subheading }}</p>
                    @endif
                </div>

                <div class="flex items-center gap-3">
                    <span class="text-xs font-medium px-2.5 py-1 rounded-full bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800">
                        iC.edu Assessment Platform
                    </span>
                </div>
            </header>

            <!-- Alerts / Flashes -->
            <div class="px-8 pt-6">
                @if(session('status'))
                    <div class="mb-4 p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200 text-sm flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span>{{ session('status') }}</span>
                        </div>
                    </div>
                @endif

                @if(session('invitation_url'))
                    <div class="mb-4 p-4 rounded-xl bg-indigo-50 dark:bg-indigo-950/40 border border-indigo-200 dark:border-indigo-800 text-indigo-900 dark:text-indigo-200 text-xs flex flex-col gap-2" x-data="{ copied: false }">
                        <div class="font-semibold text-sm flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                                <span>Invitation Link (Send to Candidate):</span>
                            </div>
                            <button type="button"
                                    @click="navigator.clipboard.writeText('{{ session('invitation_url') }}'); copied = true; setTimeout(() => copied = false, 2500)"
                                    class="px-3 py-1 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold shadow-xs transition flex items-center gap-1.5 cursor-pointer">
                                <span x-show="!copied">Copy Invitation Link</span>
                                <span x-show="copied" style="display: none;">✓ Copied!</span>
                            </button>
                        </div>
                        <input type="text" readonly value="{{ session('invitation_url') }}" class="w-full text-xs font-mono bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded px-3 py-2 text-slate-900 dark:text-slate-100" onclick="this.select()">
                    </div>
                @endif

                @if($errors->any())
                    <div class="mb-4 p-4 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 text-rose-800 dark:text-rose-200 text-sm">
                        <ul class="list-disc list-inside space-y-1">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>

            <!-- Page Body -->
            <main class="flex-1 px-8 pb-12">
                @yield('content')
            </main>
        </div>
    </div>

    <!-- Global IAP Modal System -->
    <x-iap-modal />

    <!-- Theme Switcher Engine -->
    <script>
        function updateSwitcherButtonsUI(mode) {
            ['light', 'dark', 'system'].forEach(function(m) {
                var btn = document.getElementById('theme-btn-' + m);
                if (btn) {
                    if (m === mode) {
                        btn.classList.add('active');
                        btn.setAttribute('aria-pressed', 'true');
                    } else {
                        btn.classList.remove('active');
                        btn.setAttribute('aria-pressed', 'false');
                    }
                }
            });
        }

        window.setIapTheme = function(mode) {
            var root = document.documentElement;
            var activeTheme = mode;
            if (mode === 'system') {
                activeTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            }
            root.setAttribute('data-theme', activeTheme);
            root.setAttribute('data-preference', mode);
            if (activeTheme === 'dark') {
                root.classList.add('dark');
                root.classList.remove('light');
            } else {
                root.classList.add('light');
                root.classList.remove('dark');
            }

            updateSwitcherButtonsUI(mode);

            // Persist preference to database & session
            fetch('{{ route('settings.appearance.update') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ theme: mode })
            }).catch(function(err) {
                console.error('Failed to persist theme preference:', err);
            });
        };

        document.addEventListener('DOMContentLoaded', function() {
            var currentPref = document.documentElement.getAttribute('data-preference') || 'dark';
            updateSwitcherButtonsUI(currentPref);

            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function(e) {
                var root = document.documentElement;
                if (root.getAttribute('data-preference') === 'system') {
                    var activeTheme = e.matches ? 'dark' : 'light';
                    root.setAttribute('data-theme', activeTheme);
                    if (activeTheme === 'dark') {
                        root.classList.add('dark');
                        root.classList.remove('light');
                    } else {
                        root.classList.add('light');
                        root.classList.remove('dark');
                    }
                }
            });
        });
    </script>
</body>
</html>
