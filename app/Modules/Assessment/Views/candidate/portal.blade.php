<x-candidate-layout>
    <div class="mb-8">
        <h1 class="text-3xl font-bold tracking-tight text-white">Student Portal Dashboard</h1>
        <p class="text-sm text-slate-400 mt-1">Welcome back, {{ Auth::user()?->name }}. Manage your test attempts and certifications.</p>
    </div>

    <!-- Metrics Summary -->
    <div class="grid gap-5 sm:grid-cols-3 mb-8">
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-5 shadow-sm">
            <p class="text-xs font-medium text-slate-400 uppercase tracking-wider">Available Tests</p>
            <p class="text-3xl font-bold text-indigo-400 mt-2">{{ $availableTestsCount }}</p>
        </div>
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-5 shadow-sm">
            <p class="text-xs font-medium text-slate-400 uppercase tracking-wider">My Total Attempts</p>
            <p class="text-3xl font-bold text-white mt-2">{{ $myAttemptsCount }}</p>
        </div>
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-5 shadow-sm">
            <p class="text-xs font-medium text-slate-400 uppercase tracking-wider">Completed Tests</p>
            <p class="text-3xl font-bold text-emerald-400 mt-2">{{ $completedAttemptsCount }}</p>
        </div>
    </div>

    <!-- Active / Ongoing Attempt Alert -->
    @if ($ongoingAttempt)
        <div class="mb-8 p-6 rounded-xl bg-indigo-600/10 border border-indigo-500/30 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-500/20 text-indigo-300">
                    <span class="w-1.5 h-1.5 rounded-full bg-indigo-400 animate-pulse"></span>
                    Ongoing Session
                </span>
                <h3 class="text-lg font-bold text-white mt-2">{{ $ongoingAttempt->test?->title }}</h3>
                <p class="text-xs text-slate-400 mt-0.5">Started at {{ $ongoingAttempt->started_at?->format('H:i, d M Y') }}</p>
            </div>
            <a href="{{ route('candidate.exam', $ongoingAttempt) }}" class="inline-flex items-center justify-center px-5 py-2.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-sm transition-colors shadow-md shadow-indigo-600/30">
                Resume Test Exam &rarr;
            </a>
        </div>
    @endif

    <!-- Quick Action Banner -->
    <div class="bg-slate-900 border border-slate-800 rounded-xl p-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h3 class="text-lg font-semibold text-white">Ready to take a new simulation test?</h3>
            <p class="text-sm text-slate-400">Browse through the available Computer-Based Testing catalog.</p>
        </div>
        <a href="{{ route('candidate.available-tests') }}" class="px-5 py-2.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-white font-semibold text-sm transition-colors">
            Browse Test Catalog
        </a>
    </div>
</x-candidate-layout>
