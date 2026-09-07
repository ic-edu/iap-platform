<x-candidate-layout>
    <div class="mb-8">
        <h1 class="text-3xl font-bold tracking-tight text-slate-900 dark:text-white">Student Portal Dashboard</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Welcome back, {{ Auth::user()?->name }}. Manage your test attempts and digital certifications.</p>
    </div>

    {{-- Institutional Membership Context Banner --}}
    @if (isset($institutionalMemberships) && $institutionalMemberships->isNotEmpty())
        <div class="mb-8 space-y-4">
            @foreach ($institutionalMemberships as $membership)
                <div class="p-5 sm:p-6 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div class="flex items-start gap-3.5">
                            <div class="p-2.5 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 border border-indigo-100 dark:border-indigo-900/50 shrink-0 mt-0.5">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                </svg>
                            </div>
                            <div class="space-y-1">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-950/50 px-2 py-0.5 rounded border border-indigo-100 dark:border-indigo-900/50">
                                        Institutional Membership
                                    </span>
                                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-[11px] font-semibold bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/20 dark:bg-emerald-950/50 dark:text-emerald-400 dark:ring-emerald-500/30">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                        Active
                                    </span>
                                </div>
                                <h2 class="text-lg font-bold text-slate-900 dark:text-white leading-snug">
                                    {{ $membership->organization->name }}
                                </h2>
                                <p class="text-xs text-slate-500 dark:text-slate-400">
                                    {{ is_object($membership->organization->organization_type) ? $membership->organization->organization_type->label() : ($membership->organization->organization_type ?? 'Institutional Organization') }}
                                </p>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-2 sm:gap-3 pt-3 sm:pt-0 border-t sm:border-t-0 border-slate-100 dark:border-slate-800/60">
                            <div class="px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-700/60">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 block">Role</span>
                                <span class="text-xs font-bold text-slate-800 dark:text-slate-200">
                                    {{ is_object($membership->role) ? $membership->role->label() : $membership->role }}
                                </span>
                            </div>

                            <div class="px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-700/60">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 block">Group / Cohort</span>
                                <span class="text-xs font-bold {{ $membership->groups->isNotEmpty() ? 'text-slate-800 dark:text-slate-200' : 'text-slate-500 dark:text-slate-400 font-normal' }}">
                                    {{ $membership->groups->isNotEmpty() ? $membership->groups->pluck('name')->join(', ') : 'Not Assigned' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <!-- Metrics Summary Grid -->
    <div class="grid gap-5 sm:grid-cols-4 mb-8">
        {{-- 1. Available Tests KPI --}}
        <a href="{{ route('candidate.available-tests') }}"
           class="p-5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 hover:border-indigo-500/60 dark:hover:border-indigo-500/60 rounded-2xl shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:ring-offset-white dark:focus:ring-offset-slate-900 transition-colors duration-150 group block cursor-pointer"
           aria-label="Available Tests: {{ $availableTestsCount }}">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-500 dark:text-slate-400 block group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">Available Tests</span>
                <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                </svg>
            </div>
            <span class="text-3xl font-black {{ $availableTestsCount > 0 ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-700 dark:text-slate-300' }} mt-1 block">{{ $availableTestsCount }}</span>
            <span class="text-xs text-slate-500 dark:text-slate-400 mt-1 block group-hover:underline">{{ $availableTestsCount > 0 ? 'Start or resume test →' : 'Browse test catalog →' }}</span>
        </a>

        {{-- 2. My Total Attempts KPI --}}
        <a href="{{ route('candidate.my-attempts') }}"
           class="p-5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 hover:border-indigo-500/60 dark:hover:border-indigo-500/60 rounded-2xl shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:ring-offset-white dark:focus:ring-offset-slate-900 transition-colors duration-150 group block cursor-pointer"
           aria-label="My Total Attempts: {{ $myAttemptsCount }}">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-500 dark:text-slate-400 block group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">My Total Attempts</span>
                <svg class="w-4 h-4 text-slate-400 group-hover:text-indigo-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <span class="text-3xl font-black text-slate-900 dark:text-white mt-1 block">{{ $myAttemptsCount }}</span>
            <span class="text-xs text-slate-500 dark:text-slate-400 mt-1 block group-hover:underline">View test history &rarr;</span>
        </a>

        {{-- 3. Completed Tests KPI --}}
        <a href="{{ route('candidate.my-results') }}"
           class="p-5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 hover:border-emerald-500/60 dark:hover:border-emerald-500/60 rounded-2xl shadow-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 focus:ring-offset-white dark:focus:ring-offset-slate-900 transition-colors duration-150 group block cursor-pointer"
           aria-label="Completed Tests: {{ $completedAttemptsCount }}">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-500 dark:text-slate-400 block group-hover:text-emerald-600 dark:group-hover:text-emerald-400 transition-colors">Completed Tests</span>
                <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <span class="text-3xl font-black text-emerald-600 dark:text-emerald-400 mt-1 block">{{ $completedAttemptsCount }}</span>
            <span class="text-xs text-slate-500 dark:text-slate-400 mt-1 block group-hover:underline">View completed results &rarr;</span>
        </a>

        {{-- 4. My Certificates KPI --}}
        <a href="{{ route('candidate.my-certificates') }}"
           class="p-5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 hover:border-amber-500/60 dark:hover:border-amber-500/60 rounded-2xl shadow-sm focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 focus:ring-offset-white dark:focus:ring-offset-slate-900 transition-colors duration-150 group block cursor-pointer"
           aria-label="My Certificates: {{ $issuedCertificatesCount ?? 0 }}">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-500 dark:text-slate-400 block group-hover:text-amber-600 dark:group-hover:text-amber-400 transition-colors">My Certificates</span>
                <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
                </svg>
            </div>
            <span class="text-3xl font-black text-amber-600 dark:text-amber-400 mt-1 block">{{ $issuedCertificatesCount ?? 0 }}</span>
            <span class="text-xs text-slate-500 dark:text-slate-400 mt-1 block group-hover:underline">View achievement awards &rarr;</span>
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
                            <h3 class="text-base sm:text-lg font-bold text-slate-900 dark:text-white leading-snug">
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

    <!-- Primary Action Hub Cards -->
    <div class="grid {{ (!isset($institutionalMemberships) || $institutionalMemberships->isEmpty()) ? 'sm:grid-cols-2' : 'grid-cols-1' }} gap-5 mb-8">
        {{-- Card 1: PAYMENT (Standalone Candidates Only) --}}
        @if(!isset($institutionalMemberships) || $institutionalMemberships->isEmpty())
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 sm:p-8 flex flex-col justify-between gap-6 shadow-sm">
            <div class="space-y-3">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-200 dark:border-indigo-800/60 flex items-center justify-center text-indigo-600 dark:text-indigo-400 flex-shrink-0 shadow-sm">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                        </svg>
                    </div>
                    <div>
                        <span class="text-[10px] uppercase font-bold tracking-wider text-slate-400 block">Assessment Entry</span>
                        <h2 class="text-lg font-bold text-slate-900 dark:text-white">PAYMENT</h2>
                    </div>
                </div>
                <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
                    Purchase assessment packages and manage your payment.
                </p>
                @if(isset($pendingPaymentsCount) && $pendingPaymentsCount > 0)
                <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl text-xs font-bold bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-900/40 text-amber-700 dark:text-amber-300">
                    <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                    <span>{{ $pendingPaymentsCount }} Payment(s) Awaiting Review</span>
                </div>
                @endif
            </div>

            <div class="pt-4 border-t border-slate-100 dark:border-slate-800/80 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="flex items-center gap-3 text-xs text-slate-500 dark:text-slate-400">
                    <a href="{{ route('candidate.orders.index') }}" class="hover:text-indigo-600 dark:hover:text-indigo-400 font-medium hover:underline">My Orders</a>
                    <span>&bull;</span>
                    <a href="{{ route('candidate.invoices.index') }}" class="hover:text-indigo-600 dark:hover:text-indigo-400 font-medium hover:underline">Invoices</a>
                    <span>&bull;</span>
                    <a href="{{ route('candidate.payments.index') }}" class="hover:text-indigo-600 dark:hover:text-indigo-400 font-medium hover:underline">Payments</a>
                </div>
                <a href="{{ route('candidate.store') }}" class="inline-flex items-center justify-center px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs transition-all shadow-md shadow-indigo-600/20">
                    <span>Browse Assessment Packages</span>
                    <span class="ml-1">&rarr;</span>
                </a>
            </div>
        </div>
        @endif

        {{-- Card 2: DIGITAL CERTIFICATES --}}
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 sm:p-8 flex flex-col justify-between gap-6 shadow-sm">
            <div class="space-y-3">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-amber-50 dark:bg-amber-950/60 border border-amber-200 dark:border-amber-800/60 flex items-center justify-center text-amber-600 dark:text-amber-400 flex-shrink-0 shadow-sm">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
                        </svg>
                    </div>
                    <div>
                        <span class="text-[10px] uppercase font-bold tracking-wider text-slate-400 block">Achievement Hub</span>
                        <h2 class="text-lg font-bold text-slate-900 dark:text-white">Digital Certificates</h2>
                    </div>
                </div>
                <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
                    Access your achievement certificates and downloads.
                </p>
            </div>

            <div class="pt-4 border-t border-slate-100 dark:border-slate-800/80 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="flex items-center gap-3 text-xs text-slate-500 dark:text-slate-400">
                    <a href="{{ route('candidate.my-attempts') }}" class="hover:text-amber-600 dark:hover:text-amber-400 font-medium hover:underline">Attempt History</a>
                </div>
                <a href="{{ route('candidate.my-certificates') }}" class="inline-flex items-center justify-center px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-white font-bold text-xs transition-colors">
                    <span>Open Certificates</span>
                    <span class="ml-1">&rarr;</span>
                </a>
            </div>
        </div>
    </div>
</x-candidate-layout>
