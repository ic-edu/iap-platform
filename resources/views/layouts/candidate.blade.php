<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ Auth::user()?->getThemePreference() ?? session('theme_preference', 'dark') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'iC.edu Assessment Platform') }} — Candidate Portal</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

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
</head>
<body class="font-sans antialiased min-h-screen flex flex-col">
    <!-- Candidate Navigation Bar -->
    <nav class="bg-slate-900 border-b border-slate-800 sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center gap-3">
                    <div class="flex items-center gap-2 select-none">
                        <span class="w-8 h-8 rounded-lg bg-indigo-600 flex items-center justify-center font-bold text-white shadow-md shadow-indigo-500/30">iC</span>
                        <span class="font-bold text-white tracking-wide text-lg">iC.edu <span class="text-indigo-400 font-medium">Student</span></span>
                    </div>
                </div>

                <div class="flex items-center gap-5 text-sm font-medium">
                    <a href="{{ route('candidate.portal') }}" class="{{ request()->routeIs('candidate.portal') ? 'text-indigo-400 font-bold' : 'text-slate-300 hover:text-white' }} transition-colors">Portal Dashboard</a>
                    <a href="{{ route('candidate.available-tests') }}" class="{{ request()->routeIs('candidate.available-tests') ? 'text-indigo-400 font-bold' : 'text-slate-300 hover:text-white' }} transition-colors">Available Tests</a>
                    <a href="{{ route('candidate.store') }}" class="{{ request()->routeIs('candidate.store*') || request()->routeIs('candidate.checkout*') ? 'text-indigo-400 font-bold' : 'text-slate-300 hover:text-white' }} transition-colors">🛍️ Store</a>
                    <a href="{{ route('candidate.orders.index') }}" class="{{ request()->routeIs('candidate.orders*') ? 'text-indigo-400 font-bold' : 'text-slate-300 hover:text-white' }} transition-colors">Orders</a>
                    <a href="{{ route('candidate.invoices.index') }}" class="{{ request()->routeIs('candidate.invoices*') || request()->routeIs('candidate.payments*') ? 'text-indigo-400 font-bold' : 'text-slate-300 hover:text-white' }} transition-colors">Invoices</a>
                    <a href="{{ route('candidate.my-attempts') }}" class="{{ request()->routeIs('candidate.my-attempts') ? 'text-indigo-400 font-bold' : 'text-slate-300 hover:text-white' }} transition-colors">My Attempts</a>
                    <a href="{{ route('candidate.my-certificates') }}" class="{{ request()->routeIs('candidate.my-certificates') ? 'text-indigo-400 font-bold' : 'text-slate-300 hover:text-white' }} transition-colors">🎓 Certificates</a>
                </div>

                <!-- User Profile Dropdown Container -->
                @auth
                    <div class="relative border-l border-slate-800 pl-4" id="candidate-profile-container">
                        <button type="button" 
                                id="candidate-profile-btn"
                                onclick="toggleCandidateProfileDropdown(event)" 
                                aria-expanded="false"
                                aria-haspopup="true"
                                aria-controls="candidate-profile-dropdown"
                                class="flex items-center gap-2 text-left hover:opacity-90 transition-all cursor-pointer p-1 rounded-xl hover:bg-slate-800/50">
                            <div class="w-8 h-8 rounded-lg bg-indigo-600 text-white font-bold text-xs flex items-center justify-center shadow-md">
                                {{ strtoupper(substr(Auth::user()->name, 0, 2)) }}
                            </div>
                            <span class="hidden sm:inline text-xs font-semibold text-slate-200">{{ Auth::user()->name }}</span>
                            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>

                        <!-- Compact Profile Dropdown Menu -->
                        <div id="candidate-profile-dropdown" class="hidden absolute right-0 mt-2 w-72 bg-slate-900 border border-slate-800 rounded-2xl shadow-2xl z-50 p-4 space-y-3">
                            <!-- 1. Current user identity -->
                            <div class="flex items-center gap-3 pb-3 border-b border-slate-800">
                                <div class="w-10 h-10 rounded-xl bg-indigo-600 font-bold text-white text-sm flex items-center justify-center shadow-md flex-shrink-0">
                                    {{ strtoupper(substr(Auth::user()->name, 0, 2)) }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-xs font-bold text-white truncate">{{ Auth::user()->name }}</p>
                                    <p class="text-[11px] text-slate-400 font-mono truncate">{{ Auth::user()->email }}</p>
                                </div>
                            </div>

                            <!-- 2. Compact Appearance selector: [ ☼ Light ] [ ☾ Dark ] [ ▣ System ] -->
                            <div class="space-y-1.5">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Theme &amp; Appearance</span>
                                <div class="grid grid-cols-3 gap-1.5 p-1 bg-slate-950 border border-slate-800 rounded-xl" role="group" aria-label="Theme selector">
                                    <button type="button" 
                                            onclick="setIapTheme('light')" 
                                            id="theme-btn-light" 
                                            class="theme-switcher-btn flex items-center justify-center gap-1 py-1.5 px-2 rounded-lg text-xs font-bold text-slate-300 hover:text-white dark:text-slate-300 transition-all cursor-pointer"
                                            title="Light theme" 
                                            aria-label="Select Light theme">
                                        <span class="text-sm">☼</span>
                                        <span class="text-[11px]">Light</span>
                                    </button>
                                    <button type="button" 
                                            onclick="setIapTheme('dark')" 
                                            id="theme-btn-dark" 
                                            class="theme-switcher-btn flex items-center justify-center gap-1 py-1.5 px-2 rounded-lg text-xs font-bold text-slate-300 hover:text-white dark:text-slate-300 transition-all cursor-pointer"
                                            title="Dark theme" 
                                            aria-label="Select Dark theme">
                                        <span class="text-sm">☾</span>
                                        <span class="text-[11px]">Dark</span>
                                    </button>
                                    <button type="button" 
                                            onclick="setIapTheme('system')" 
                                            id="theme-btn-system" 
                                            class="theme-switcher-btn flex items-center justify-center gap-1 py-1.5 px-2 rounded-lg text-xs font-bold text-slate-300 hover:text-white dark:text-slate-300 transition-all cursor-pointer"
                                            title="System theme" 
                                            aria-label="Select System theme">
                                        <span class="text-sm">▣</span>
                                        <span class="text-[11px]">System</span>
                                    </button>
                                </div>
                            </div>

                            <!-- 3. Profile & Account Navigation Link -->
                            <div class="pt-2 border-t border-slate-800">
                                <a href="{{ route('profile.edit') }}" class="flex items-center justify-between p-2 rounded-xl text-xs font-semibold text-slate-300 hover:text-white hover:bg-slate-800 transition-colors">
                                    <span class="flex items-center gap-2">
                                        <span>👤</span> Profile &amp; Account
                                    </span>
                                    <span class="text-slate-500 text-xs">&rarr;</span>
                                </a>
                            </div>

                            <!-- 4. Sign out -->
                            <div class="pt-1">
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="w-full flex items-center justify-between p-2 rounded-xl text-xs font-semibold text-rose-400 hover:bg-rose-500/10 border border-transparent hover:border-rose-500/20 transition-colors cursor-pointer">
                                        <span class="flex items-center gap-2">
                                            <span>🚪</span> Sign out
                                        </span>
                                        <span class="text-rose-500 text-xs">&rarr;</span>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                @endauth
            </div>
        </div>
    </nav>

    <!-- Main Candidate Content Area -->
    <main class="flex-1 max-w-7xl w-full mx-auto p-4 sm:p-6 lg:p-8">
        {{ $slot ?? '' }}
        @yield('content')
    </main>

    <!-- Footer -->
    <footer class="bg-slate-900 border-t border-slate-800/80 py-4 text-center text-xs text-slate-500">
        &copy; {{ date('Y') }} iC.edu Assessment Platform (IAP). Computer-Based Testing Delivery Engine.
    </footer>

    <script>
        function toggleCandidateProfileDropdown(event) {
            if (event) event.stopPropagation();
            const dropdown = document.getElementById('candidate-profile-dropdown');
            const btn = document.getElementById('candidate-profile-btn');
            if (!dropdown) return;
            const isHidden = dropdown.classList.contains('hidden');
            if (isHidden) {
                dropdown.classList.remove('hidden');
                if (btn) btn.setAttribute('aria-expanded', 'true');
            } else {
                dropdown.classList.add('hidden');
                if (btn) btn.setAttribute('aria-expanded', 'false');
            }
        }

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

            document.addEventListener('click', function(e) {
                var userDropdown = document.getElementById('candidate-profile-dropdown');
                var userContainer = document.getElementById('candidate-profile-container');
                if (userDropdown && !userDropdown.classList.contains('hidden')) {
                    if (userContainer && !userContainer.contains(e.target)) {
                        userDropdown.classList.add('hidden');
                        var btn = document.getElementById('candidate-profile-btn');
                        if (btn) btn.setAttribute('aria-expanded', 'false');
                    }
                }
            });

            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    var userDropdown = document.getElementById('candidate-profile-dropdown');
                    if (userDropdown && !userDropdown.classList.contains('hidden')) {
                        userDropdown.classList.add('hidden');
                        var btn = document.getElementById('candidate-profile-btn');
                        if (btn) btn.setAttribute('aria-expanded', 'false');
                    }
                }
            });

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
