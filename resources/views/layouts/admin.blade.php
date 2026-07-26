<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-900 text-slate-100">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'iC.edu Assessment Platform') }} - Admin Platform</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

    <!-- Scripts and Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full font-sans antialiased bg-slate-900 text-slate-100">
    <div class="min-h-screen flex flex-col md:flex-row">
        <!-- Sidebar Navigation -->
        <aside class="w-full md:w-64 bg-slate-950 border-b md:border-b-0 md:border-r border-slate-800 flex-shrink-0">
            <div class="p-6 flex items-center justify-between">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-600 font-bold text-white shadow-lg shadow-indigo-500/30">IAP</span>
                    <div>
                        <span class="block font-bold text-white text-base leading-tight">iC.edu</span>
                        <span class="block text-xs font-medium text-slate-400">Assessment Platform</span>
                    </div>
                </a>
            </div>

            <!-- Navigation Links (Modular & Permission-based) -->
            <nav class="px-4 py-2 space-y-1">
                @foreach (\App\Services\NavigationService::getMenuItems() as $item)
                    @php
                        $isActive = request()->is($item['active_pattern']);
                    @endphp
                    <a href="{{ route($item['route']) }}"
                       class="flex items-center justify-between px-3 py-2.5 rounded-lg text-sm font-medium transition-colors {{ $isActive ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20' : 'text-slate-400 hover:text-white hover:bg-slate-900' }}">
                        <span class="flex items-center gap-3">
                            <svg class="w-5 h-5 text-current" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                            </svg>
                            {{ $item['label'] }}
                        </span>
                        @if ($item['badge'])
                            <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-indigo-500/20 text-indigo-300">{{ $item['badge'] }}</span>
                        @endif
                    </a>
                @endforeach
            </nav>
        </aside>

        <!-- Main Content Area -->
        <div class="flex-1 flex flex-col min-w-0">
            <!-- Topbar Header -->
            <header class="bg-slate-950 border-b border-slate-800 px-6 py-4 flex items-center justify-between gap-4">
                <!-- Breadcrumbs & Search -->
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
                    <!-- Quick Action Button -->
                    <button class="hidden sm:inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold px-3 py-2 rounded-lg transition-colors shadow-sm">
                        <span>+ Quick Action</span>
                    </button>

                    <!-- Notifications Dropdown Placeholder -->
                    <button class="p-2 rounded-lg text-slate-400 hover:text-white hover:bg-slate-900 transition-colors relative" title="Notifications">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                        </svg>
                        <span class="absolute top-1.5 right-1.5 w-2 h-2 rounded-full bg-indigo-500 ring-2 ring-slate-950"></span>
                    </button>

                    <!-- User Profile & Dropdown -->
                    @auth
                        <div class="flex items-center gap-3 border-l border-slate-800 pl-4">
                            <div class="text-right hidden sm:block">
                                <span class="block text-xs font-semibold text-slate-200 leading-tight">{{ Auth::user()->name }}</span>
                                <span class="block text-[10px] text-indigo-400 font-medium">{{ Auth::user()->roles->first()?->name ?? 'User' }}</span>
                            </div>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="text-xs text-rose-400 hover:text-rose-300 font-medium px-2 py-1 rounded bg-rose-500/10 transition-colors">
                                    Logout
                                </button>
                            </form>
                        </div>
                    @endauth
                </div>
            </header>

            <!-- Page Header Slot -->
            @if (isset($header))
                <div class="bg-slate-950 border-b border-slate-800 px-6 py-4">
                    {{ $header }}
                </div>
            @endif

            <!-- Main Content Container -->
            <main class="flex-1 p-6 overflow-y-auto">
                {{ $slot }}
            </main>
        </div>
    </div>
</body>
</html>
