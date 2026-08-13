<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-900 text-slate-100">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name', 'iC.edu Assessment Platform') . ' - Admin Platform')</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

    <!-- Scripts and Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="h-full font-sans antialiased bg-slate-900 text-slate-100">
    <div class="min-h-screen flex flex-col md:flex-row">
        <!-- Sidebar Navigation (Removed for Repository Manager role - Full Width Command Center) -->
        @unless(Auth::user()?->hasRole('repository-manager'))
        <aside class="w-full md:w-64 bg-slate-950 border-b md:border-b-0 md:border-r border-slate-800 flex-shrink-0">
            <div class="p-6 flex items-center justify-between border-b border-slate-900">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-600 font-bold text-white shadow-lg shadow-indigo-500/30">IAP</span>
                    <div>
                        <span class="block font-bold text-white text-base leading-tight">iC.edu</span>
                        <span class="block text-xs font-medium text-slate-400">Assessment Platform</span>
                    </div>
                </a>
            </div>

            <!-- Navigation Links Grouped by Section -->
            <nav class="px-3 py-4 space-y-4 overflow-y-auto max-h-[calc(100vh-80px)]">
                @php
                    $menuItems = \App\Services\NavigationService::getMenuItems();
                    $groupedMenu = [];
                    foreach ($menuItems as $item) {
                        $groupedMenu[$item['section']][] = $item;
                    }
                @endphp

                @foreach ($groupedMenu as $sectionName => $items)
                    <div>
                        <span class="px-3 text-[10px] font-bold uppercase tracking-wider text-slate-500 block mb-1.5">{{ $sectionName }}</span>
                        <div class="space-y-1">
                            @foreach ($items as $item)
                                @php
                                    $isActive = request()->is($item['active_pattern']);
                                @endphp
                                <a href="{{ route($item['route']) }}"
                                   class="flex items-center justify-between px-3 py-2 rounded-lg text-xs font-semibold transition-colors {{ $isActive ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20' : 'text-slate-400 hover:text-white hover:bg-slate-900' }}">
                                    <span class="flex items-center gap-2.5">
                                        <svg class="w-4 h-4 text-current flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                                        </svg>
                                        {{ $item['label'] }}
                                    </span>
                                    @if ($item['badge'])
                                        <span class="px-1.5 py-0.5 text-[9px] font-bold rounded bg-indigo-500/20 text-indigo-300">{{ $item['badge'] }}</span>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </nav>
        </aside>
        @endunless

        <!-- Main Content Area -->
        <div class="flex-1 flex flex-col min-w-0">
            <!-- Topbar Header -->
            <header class="bg-slate-950 border-b border-slate-800 px-6 py-4 flex items-center justify-between gap-4">
                <!-- Global Search -->
                <div class="flex items-center gap-4 flex-1">
                    <div class="relative w-full max-w-xs">
                        <input type="text" placeholder="Global Search (Ctrl+K)..." 
                               class="w-full bg-slate-900 text-slate-200 text-sm rounded-lg border border-slate-800 px-3 py-2 pl-9 focus:outline-none focus:border-indigo-500 transition-colors" />
                        <svg class="w-4 h-4 text-slate-500 absolute left-3 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </div>
                </div>

                <!-- Topbar Actions -->
                <div class="flex items-center gap-3">
                    <!-- Topbar Action Button (Role-Aware: Dashboard for Repository Manager and Teacher, Quick Action for others) -->
                    @if(Auth::user()?->hasRole('repository-manager'))
                    <a href="{{ route('admin.repository-manager.dashboard') }}" class="hidden sm:inline-flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold px-3 py-2 rounded-lg transition-colors shadow-sm" title="Repository Manager Dashboard">
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                        </svg>
                        <span>🏠 Dashboard</span>
                    </a>
                    @elseif(Auth::user()?->hasRole('teacher'))
                    <a href="{{ route('teacher.dashboard') }}" class="hidden sm:inline-flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold px-3 py-2 rounded-lg transition-colors shadow-sm" title="Teacher Authoring Dashboard">
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                        </svg>
                        <span>🏠 Dashboard</span>
                    </a>
                    @else
                    <button onclick="document.getElementById('quick-action-modal').classList.remove('hidden')" class="hidden sm:inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold px-3 py-2 rounded-lg transition-colors shadow-sm">
                        <span>+ Quick Action</span>
                    </button>
                    @endif

                    <!-- Notifications Dropdown (NOTIFICATION-001) -->
                    <div class="relative" id="notifications-bell-container">
                        <button onclick="toggleNotificationsDropdown()" class="p-2 rounded-lg text-slate-400 hover:text-white hover:bg-slate-900 transition-colors relative" title="Notifications">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                            </svg>
                            <span id="notif-badge-dot" class="hidden absolute top-1.5 right-1.5 w-2 h-2 rounded-full bg-indigo-500 ring-2 ring-slate-950"></span>
                        </button>

                        <div id="notifications-dropdown" class="hidden absolute right-0 mt-2 w-80 bg-slate-900 border border-slate-800 rounded-xl shadow-2xl z-50 overflow-hidden">
                            <div class="p-3 border-b border-slate-800 flex justify-between items-center bg-slate-950">
                                <span class="text-xs font-bold text-white">Notifications</span>
                                <span id="notif-dropdown-count" class="text-[10px] font-semibold bg-indigo-500/20 text-indigo-400 px-2 py-0.5 rounded-full">0 New</span>
                            </div>
                            <div id="notif-dropdown-list" class="divide-y divide-slate-800 text-xs max-h-72 overflow-y-auto">
                                <div class="p-4 text-center text-slate-500 text-xs">Loading alerts…</div>
                            </div>
                            <div class="p-2 border-t border-slate-800 bg-slate-950 text-center">
                                <a href="{{ route('notifications.index') }}" class="text-[11px] font-bold text-indigo-400 hover:underline">View All History →</a>
                            </div>
                        </div>
                    </div>

                    <!-- User Profile Drawer Trigger -->
                    @auth
                        <div class="flex items-center gap-3 border-l border-slate-800 pl-4">
                            <button type="button" onclick="openMyProfileDrawer()" class="flex items-center gap-2.5 text-left hover:opacity-80 transition-opacity">
                                <div class="w-8 h-8 rounded-lg bg-indigo-600 text-white font-bold text-xs flex items-center justify-center shadow-md">
                                    {{ strtoupper(substr(Auth::user()->name, 0, 2)) }}
                                </div>
                                <div class="hidden sm:block">
                                    <span class="block text-xs font-semibold text-slate-200 leading-tight">{{ Auth::user()->name }}</span>
                                    <span class="block text-[10px] text-indigo-400 font-medium">{{ Auth::user()->roles->first()?->name ?? 'User' }}</span>
                                </div>
                            </button>
                        </div>
                    @endauth
                </div>
            </header>

            <!-- Page Header Section -->
            @hasSection('header')
                <div class="bg-slate-950 border-b border-slate-800 px-6 py-4">
                    @yield('header')
                </div>
            @endif

            <!-- Main Content Container -->
            <main class="flex-1 p-6 overflow-y-auto">
                @yield('content')
            </main>
        </div>
    </div>

    <!-- Slide-Over "My Profile" Right Drawer -->
    <div id="my-profile-drawer" class="hidden fixed inset-0 z-50 overflow-hidden">
        <div class="absolute inset-0 bg-slate-950/80 backdrop-blur-sm transition-opacity" onclick="closeMyProfileDrawer()"></div>
        <div class="fixed inset-y-0 right-0 max-w-full flex pl-10">
            <div class="w-screen max-w-md bg-slate-900 border-l border-slate-800 text-white shadow-2xl flex flex-col justify-between p-6 overflow-y-auto">
                <div>
                    <div class="flex items-center justify-between border-b border-slate-800 pb-4 mb-6">
                        <h2 class="text-base font-bold text-white flex items-center gap-2">
                            <span>👤 My Profile &amp; Account Context</span>
                        </h2>
                        <button type="button" onclick="closeMyProfileDrawer()" class="text-slate-400 hover:text-white text-lg">&times;</button>
                    </div>

                    @auth
                        <!-- Authenticated User Overview Card -->
                        <div class="bg-slate-950 border border-slate-800 rounded-xl p-4 mb-6 space-y-3">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 rounded-xl bg-indigo-600 font-extrabold text-white text-base flex items-center justify-center shadow-lg">
                                    {{ strtoupper(substr(Auth::user()->name, 0, 2)) }}
                                </div>
                                <div>
                                    <h3 class="font-bold text-white text-sm">{{ Auth::user()->name }}</h3>
                                    <p class="text-slate-400 text-xs font-mono">{{ Auth::user()->email }}</p>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-2 pt-2 border-t border-slate-800/80 text-xs">
                                <div>
                                    <span class="text-[10px] text-slate-500 uppercase font-bold block">Active Role</span>
                                    <span class="font-bold text-indigo-400 uppercase">{{ Auth::user()->roles->first()?->name ?? 'student' }}</span>
                                </div>
                                <div>
                                    <span class="text-[10px] text-slate-500 uppercase font-bold block">Account Status</span>
                                    <span class="font-bold text-emerald-400">ACTIVE</span>
                                </div>
                            </div>
                        </div>

                        <!-- Password Update Form -->
                        <form method="POST" action="{{ route('password.update') }}" class="space-y-4 bg-slate-950 border border-slate-800 rounded-xl p-4">
                            @csrf
                            @method('PUT')
                            <h4 class="text-xs font-bold text-white uppercase tracking-wider mb-2">🔒 Update Security Credentials</h4>

                            <div>
                                <label class="block text-xs font-medium text-slate-400 mb-1">Current Password</label>
                                <input type="password" name="current_password" class="w-full p-2.5 bg-slate-900 border border-slate-800 rounded-lg text-xs text-white focus:border-indigo-500 focus:outline-none" required placeholder="••••••••">
                            </div>

                            <div>
                                <label class="block text-xs font-medium text-slate-400 mb-1">New Password</label>
                                <input type="password" name="password" class="w-full p-2.5 bg-slate-900 border border-slate-800 rounded-lg text-xs text-white focus:border-indigo-500 focus:outline-none" required placeholder="••••••••">
                            </div>

                            <div>
                                <label class="block text-xs font-medium text-slate-400 mb-1">Confirm New Password</label>
                                <input type="password" name="password_confirmation" class="w-full p-2.5 bg-slate-900 border border-slate-800 rounded-lg text-xs text-white focus:border-indigo-500 focus:outline-none" required placeholder="••••••••">
                            </div>

                            <button type="submit" class="w-full py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs rounded-lg shadow transition-colors">
                                Update Password
                            </button>
                        </form>
                    @endauth
                </div>

                <div class="pt-6 border-t border-slate-800 mt-6">
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="w-full py-2.5 bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/30 text-xs font-bold rounded-lg transition-colors">
                            Logout of Session
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Role-Aware Quick Action Modal -->
    <div id="quick-action-modal" class="hidden fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-slate-900 border border-slate-800 rounded-xl max-w-md w-full p-6 shadow-2xl">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold text-white">⚡ Permitted Quick Actions</h3>
                <button onclick="document.getElementById('quick-action-modal').classList.add('hidden')" class="text-slate-400 hover:text-white">✕</button>
            </div>
            <div class="grid grid-cols-2 gap-3 text-xs">
                @auth
                    @if (Auth::user()->hasRole('super-admin') || Auth::user()->hasRole('admin'))
                        <a href="{{ route('admin.question-banks.index') }}" class="p-3 bg-slate-800 hover:bg-indigo-600/30 rounded-lg border border-slate-700 text-slate-200 font-semibold block transition-colors">
                            📂 Manage Question Banks
                        </a>
                        <a href="{{ route('admin.tests.index') }}" class="p-3 bg-slate-800 hover:bg-indigo-600/30 rounded-lg border border-slate-700 text-slate-200 font-semibold block transition-colors">
                            📋 Build Assessment Test
                        </a>
                        <a href="{{ route('admin.users.index') }}" class="p-3 bg-slate-800 hover:bg-indigo-600/30 rounded-lg border border-slate-700 text-slate-200 font-semibold block transition-colors">
                            👤 Add Platform User
                        </a>
                        <a href="{{ route('admin.approvals.index') }}" class="p-3 bg-slate-800 hover:bg-indigo-600/30 rounded-lg border border-slate-700 text-slate-200 font-semibold block transition-colors">
                            🛡️ Review Content Approvals
                        </a>
                        <a href="{{ route('admin.reporting.index') }}" class="p-3 bg-slate-800 hover:bg-indigo-600/30 rounded-lg border border-slate-700 text-slate-200 font-semibold block transition-colors">
                            📊 Export Analytics Report
                        </a>
                        <a href="{{ route('admin.settings.index') }}" class="p-3 bg-slate-800 hover:bg-indigo-600/30 rounded-lg border border-slate-700 text-slate-200 font-semibold block transition-colors">
                            ⚙️ System Settings
                        </a>
                    @elseif (Auth::user()->hasRole('teacher'))
                        <a href="{{ route('admin.academic-library.index') }}" class="p-3 bg-slate-800 hover:bg-indigo-600/30 rounded-lg border border-slate-700 text-slate-200 font-semibold block transition-colors">
                            📚 Academic Library
                        </a>
                        <a href="{{ route('admin.question-banks.index') }}" class="p-3 bg-slate-800 hover:bg-indigo-600/30 rounded-lg border border-slate-700 text-slate-200 font-semibold block transition-colors">
                            📂 Author Question Bank
                        </a>
                        <a href="{{ route('admin.tests.index') }}" class="p-3 bg-slate-800 hover:bg-indigo-600/30 rounded-lg border border-slate-700 text-slate-200 font-semibold block transition-colors">
                            📋 Create Test Draft
                        </a>
                        <a href="{{ route('teacher.dashboard') }}" class="p-3 bg-slate-800 hover:bg-indigo-600/30 rounded-lg border border-slate-700 text-slate-200 font-semibold block transition-colors">
                            🏡 Teacher Workspace
                        </a>
                        <a href="{{ route('admin.reporting.index') }}" class="p-3 bg-slate-800 hover:bg-indigo-600/30 rounded-lg border border-slate-700 text-slate-200 font-semibold block transition-colors">
                            📊 Student Reports
                        </a>
                    @elseif (Auth::user()->hasRole('repository-manager'))
                        <a href="{{ route('admin.repository-manager.dashboard') }}" class="p-3 bg-slate-800 hover:bg-indigo-600/30 rounded-lg border border-indigo-500/30 text-indigo-300 font-bold block transition-colors">
                            ⚡ Repository Manager Command Center
                        </a>
                        <a href="{{ route('admin.repository-manager.media-approval') }}" class="p-3 bg-slate-800 hover:bg-indigo-600/30 rounded-lg border border-slate-700 text-slate-200 font-semibold block transition-colors">
                            🖼 Media Approval Center
                        </a>
                        <a href="{{ route('admin.repository-manager.questions-approval') }}" class="p-3 bg-slate-800 hover:bg-indigo-600/30 rounded-lg border border-slate-700 text-slate-200 font-semibold block transition-colors">
                            📂 Question Banks Approval
                        </a>
                        <a href="{{ route('admin.academic-library.index') }}" class="p-3 bg-slate-800 hover:bg-indigo-600/30 rounded-lg border border-slate-700 text-slate-200 font-semibold block transition-colors">
                            📚 Academic Library Explorer
                        </a>
                    @elseif (Auth::user()->hasRole('finance'))
                        <a href="{{ route('admin.commerce.index') }}" class="p-3 bg-slate-800 hover:bg-indigo-600/30 rounded-lg border border-slate-700 text-slate-200 font-semibold block transition-colors">
                            🛍️ Billing &amp; Packages
                        </a>
                        <a href="{{ route('admin.commerce.index') }}" class="p-3 bg-slate-800 hover:bg-indigo-600/30 rounded-lg border border-slate-700 text-slate-200 font-semibold block transition-colors">
                            📊 Financial Reports
                        </a>
                    @endif
                @endauth
            </div>
        </div>
    </div>

    <script>
        function openMyProfileDrawer() {
            document.getElementById('my-profile-drawer').classList.remove('hidden');
        }

        function closeMyProfileDrawer() {
            document.getElementById('my-profile-drawer').classList.add('hidden');
        }

        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('open_profile') === '1') {
                openMyProfileDrawer();
            }
            loadNotificationFeed();
        });

        async function loadNotificationFeed() {
            try {
                const res = await fetch('{{ route('notifications.feed') }}', {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                const json = await res.json();
                if (!json.success) return;

                const countEl = document.getElementById('notif-dropdown-count');
                const badgeDot = document.getElementById('notif-badge-dot');
                const listEl = document.getElementById('notif-dropdown-list');

                if (countEl) countEl.textContent = json.unread_count + ' New';
                if (badgeDot) {
                    if (json.unread_count > 0) badgeDot.classList.remove('hidden');
                    else badgeDot.classList.add('hidden');
                }

                if (listEl) {
                    if (!json.data || json.data.length === 0) {
                        listEl.innerHTML = '<div class="p-4 text-center text-slate-500 text-xs">No notifications yet</div>';
                        return;
                    }

                    const csrfToken = document.querySelector('meta[name=csrf-token]')?.content || '';
                    listEl.innerHTML = json.data.map(item => {
                        let cta = 'Click to view →';
                        if (item.title && item.title.toLowerCase().includes('resubmit')) cta = 'Review Repository →';
                        else if (item.title && item.title.toLowerCase().includes('revision')) cta = 'View Revision Task →';
                        else if (item.title && item.title.toLowerCase().includes('irqa')) cta = 'Inspect Governance →';

                        return `
                        <form action="/notifications/${item.id}/read" method="POST" class="m-0 p-0 block">
                            <input type="hidden" name="_token" value="${csrfToken}">
                            <input type="hidden" name="return_url" value="${encodeURIComponent(window.location.pathname + window.location.search)}">
                            <button type="submit" class="w-full text-left p-3 ${item.unread ? 'bg-indigo-950/40 border-l-2 border-indigo-500' : ''} hover:bg-slate-800/70 transition-all block border-none cursor-pointer group">
                                <div class="font-semibold ${item.unread ? 'text-white' : 'text-slate-300'} flex items-center justify-between text-xs">
                                    <span class="truncate pr-2">${escapeHtml(item.title)}</span>
                                    ${item.unread ? '<span class="px-1.5 py-0.5 text-[9px] font-bold rounded bg-indigo-500 text-white flex-shrink-0">NEW</span>' : ''}
                                </div>
                                <div class="text-slate-400 text-[11px] mt-1 line-clamp-2 leading-relaxed">${escapeHtml(item.message)}</div>
                                <div class="text-[10px] text-indigo-400 font-semibold mt-1.5 flex items-center justify-between">
                                    <span class="text-slate-500 font-normal">${escapeHtml(item.time_ago || '')}</span>
                                    <span class="group-hover:translate-x-0.5 transition-transform">${cta}</span>
                                </div>
                            </button>
                        </form>
                    `;
                    }).join('');
                }
            } catch(e) { /* silent */ }
        }

        function escapeHtml(str) {
            if (!str) return '';
            return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        function toggleNotificationsDropdown() {
            const dropdown = document.getElementById('notifications-dropdown');
            if (!dropdown) return;
            const isHidden = dropdown.classList.contains('hidden');
            dropdown.classList.toggle('hidden');
            if (isHidden) {
                loadNotificationFeed();
            }
        }

        // Back to Top Scroll Control Logic
        (function() {
            document.addEventListener('DOMContentLoaded', function() {
                const backToTopBtn = document.getElementById('back-to-top-btn');
                if (!backToTopBtn) return;

                let isTicking = false;

                function checkScrollPosition() {
                    const scrollTop = window.pageYOffset || document.documentElement.scrollTop || document.body.scrollTop || 0;
                    if (scrollTop > 500) {
                        backToTopBtn.classList.remove('opacity-0', 'pointer-events-none', 'translate-y-4');
                        backToTopBtn.classList.add('opacity-100', 'pointer-events-auto', 'translate-y-0');
                    } else {
                        backToTopBtn.classList.remove('opacity-100', 'pointer-events-auto', 'translate-y-0');
                        backToTopBtn.classList.add('opacity-0', 'pointer-events-none', 'translate-y-4');
                    }
                    isTicking = false;
                }

                window.addEventListener('scroll', function() {
                    if (!isTicking) {
                        window.requestAnimationFrame(checkScrollPosition);
                        isTicking = true;
                    }
                }, { passive: true });

                window.scrollToTop = function() {
                    window.scrollTo({
                        top: 0,
                        behavior: 'smooth'
                    });
                };
            });
        })();
    </script>

    <!-- Floating Back to Top Control -->
    <button id="back-to-top-btn" 
            type="button" 
            aria-label="Back to top" 
            title="Back to top"
            onclick="scrollToTop()"
            class="fixed bottom-4 right-4 sm:bottom-6 sm:right-6 z-40 p-3 bg-indigo-600 hover:bg-indigo-500 active:bg-indigo-700 text-white rounded-full shadow-xl shadow-indigo-950/50 border border-indigo-400/30 transition-all duration-300 ease-in-out opacity-0 pointer-events-none translate-y-4 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:ring-offset-2 focus:ring-offset-slate-900 flex items-center justify-center group"
            style="width: 44px; height: 44px;">
        <svg class="w-5 h-5 transition-transform group-hover:-translate-y-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 10l7-7m0 0l7 7m-7-7v18"></path>
        </svg>
    </button>

    <!-- Global IAP Modal System -->
    <x-iap-modal />

    @stack('scripts')
</body>
</html>
