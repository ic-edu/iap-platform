<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
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
</head>
<body class="font-sans antialiased bg-slate-950 text-slate-100 min-h-screen flex flex-col">
    <!-- Candidate Navigation Bar -->
    <nav class="bg-slate-900 border-b border-slate-800 sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center gap-3">
                    <a href="{{ route('candidate.portal') }}" class="flex items-center gap-2">
                        <span class="w-8 h-8 rounded-lg bg-indigo-600 flex items-center justify-center font-bold text-white shadow-md shadow-indigo-500/30">iC</span>
                        <span class="font-bold text-white tracking-wide text-lg">iC.edu <span class="text-indigo-400 font-medium">Student</span></span>
                    </a>
                </div>

                <div class="flex items-center gap-6 text-sm font-medium">
                    <a href="{{ route('candidate.portal') }}"
                       aria-label="Dashboard"
                       title="Dashboard"
                       class="{{ request()->routeIs('candidate.portal') ? 'text-indigo-400 bg-slate-800/80' : 'text-slate-300 hover:text-white hover:bg-slate-800/50' }} p-2 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:ring-offset-slate-900 transition-colors inline-flex items-center justify-center cursor-pointer">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                    </a>
                    <a href="{{ route('candidate.available-tests') }}" class="{{ request()->routeIs('candidate.available-tests') ? 'text-indigo-400 font-bold' : 'text-slate-300 hover:text-white' }} hover:underline focus:outline-none focus:ring-2 focus:ring-indigo-500 rounded-lg px-2 py-1 transition-colors inline-block cursor-pointer">Available Tests</a>
                    <a href="{{ route('candidate.my-attempts') }}" class="{{ request()->routeIs('candidate.my-attempts') ? 'text-indigo-400 font-bold' : 'text-slate-300 hover:text-white' }} hover:underline focus:outline-none focus:ring-2 focus:ring-indigo-500 rounded-lg px-2 py-1 transition-colors inline-block cursor-pointer">My Attempts</a>
                </div>

                <div class="flex items-center gap-3">
                    <span class="text-xs text-slate-400 font-medium">{{ Auth::user()?->name ?? 'Candidate' }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="text-xs text-slate-400 hover:text-rose-400 transition-colors">Logout</button>
                    </form>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Candidate Content Area -->
    <main class="flex-1 max-w-7xl w-full mx-auto p-4 sm:p-6 lg:p-8">
        {{ $slot }}
    </main>

    <!-- Footer -->
    <footer class="bg-slate-900 border-t border-slate-800/80 py-4 text-center text-xs text-slate-500">
        &copy; {{ date('Y') }} iC.edu Assessment Platform (IAP). Computer-Based Testing Delivery Engine.
    </footer>
</body>
</html>
