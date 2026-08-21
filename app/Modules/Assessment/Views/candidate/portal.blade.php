<x-candidate-layout>
    <div class="mb-8">
        <h1 class="text-3xl font-bold tracking-tight text-white">Student Portal Dashboard</h1>
        <p class="text-sm text-slate-400 mt-1">Welcome back, {{ Auth::user()?->name }}. Manage your test attempts and digital certifications.</p>
    </div>

    <!-- Metrics Summary Grid -->
    <div class="grid gap-5 sm:grid-cols-4 mb-8">
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
        <a href="{{ route('candidate.my-certificates') }}" class="bg-slate-900 border border-slate-800 hover:border-indigo-500/50 rounded-xl p-5 shadow-sm transition-all group">
            <div class="flex items-center justify-between">
                <p class="text-xs font-medium text-slate-400 uppercase tracking-wider group-hover:text-indigo-300">My Certificates</p>
                <span class="text-xs">🎓</span>
            </div>
            <p class="text-3xl font-bold text-amber-400 mt-2">{{ $issuedCertificatesCount ?? 0 }}</p>
        </a>
    </div>

    <!-- Active / Ongoing Attempts Section -->
    @if (isset($ongoingAttempts) && $ongoingAttempts->isNotEmpty())
        <div class="mb-8 space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-xs font-bold text-slate-400 uppercase tracking-wider flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-indigo-400 animate-pulse"></span>
                    Ongoing Sessions ({{ $ongoingAttempts->count() }})
                </h2>
            </div>

            <div class="grid gap-4 sm:grid-cols-{{ $ongoingAttempts->count() > 1 ? '2' : '1' }}">
                @foreach ($ongoingAttempts as $attempt)
                    <div class="p-5 sm:p-6 rounded-2xl bg-indigo-600/10 border border-indigo-500/30 flex flex-col justify-between gap-4 shadow-sm hover:border-indigo-500/50 transition-all">
                        <div class="space-y-1.5">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="px-2.5 py-0.5 rounded-md text-[11px] font-extrabold bg-indigo-500/20 text-indigo-300 border border-indigo-500/30 uppercase tracking-wide">
                                    {{ is_object($attempt->test?->test_type) ? $attempt->test->test_type->label() : strtoupper($attempt->test?->test_type ?? 'CBT') }}
                                </span>
                                <span class="text-xs text-slate-400">
                                    Started: {{ $attempt->started_at?->format('H:i, d M Y') }}
                                </span>
                            </div>
                            <h3 class="text-base sm:text-lg font-bold text-white leading-snug">
                                {{ $attempt->test?->title ?? 'Assessment Session' }}
                            </h3>
                        </div>

                        <div class="pt-3 flex items-center justify-between border-t border-indigo-500/20">
                            <span class="text-xs text-indigo-300 font-medium">
                                In-Progress Session
                            </span>
                            <a href="{{ route('candidate.exam', $attempt) }}" class="inline-flex items-center justify-center px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs transition-all shadow-md shadow-indigo-600/30 hover:scale-[1.02] active:scale-[0.98]">
                                Resume Test Exam &rarr;
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Quick Action Banners -->
    <div class="grid sm:grid-cols-2 gap-5">
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-6 flex flex-col justify-between gap-4">
            <div>
                <h3 class="text-lg font-semibold text-white">Ready to take a new simulation test?</h3>
                <p class="text-sm text-slate-400 mt-1">Browse through the available Computer-Based Testing catalog.</p>
            </div>
            <div>
                <a href="{{ route('candidate.available-tests') }}" class="inline-flex items-center px-5 py-2.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-white font-semibold text-sm transition-colors">
                    Browse Test Catalog
                </a>
            </div>
        </div>

        <div class="bg-slate-900 border border-slate-800 rounded-xl p-6 flex flex-col justify-between gap-4">
            <div>
                <h3 class="text-lg font-semibold text-white">View &amp; Verify Digital Certificates</h3>
                <p class="text-sm text-slate-400 mt-1">Access all your official achievement certificates and download PDFs.</p>
            </div>
            <div>
                <a href="{{ route('candidate.my-certificates') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-sm transition-colors shadow-md shadow-indigo-600/30">
                    <span>🎓</span> Open My Certificates
                </a>
            </div>
        </div>
    </div>
</x-candidate-layout>
