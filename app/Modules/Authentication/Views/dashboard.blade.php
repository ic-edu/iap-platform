<x-admin-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-semibold text-indigo-400 uppercase tracking-wider">{{ __('Platform Overview') }}</p>
                <h1 class="text-2xl font-bold tracking-tight text-white mt-1">{{ __('Enterprise Admin Dashboard') }}</h1>
            </div>
            <div class="flex items-center gap-3">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                    {{ __('Cache: 5 Min Active') }}
                </span>
            </div>
        </div>
    </x-slot>

    <!-- 10 Primary Metrics Cards -->
    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-5 mb-8">
        <!-- Card 1: Users -->
        <div class="bg-slate-950 border border-slate-800 rounded-xl p-5 shadow-sm">
            <p class="text-xs font-medium text-slate-400">{{ __('Total Users') }}</p>
            <p class="text-2xl font-bold text-white mt-2">{{ number_format($metrics['total_users'] ?? 0) }}</p>
        </div>

        <!-- Card 2: Students -->
        <div class="bg-slate-950 border border-slate-800 rounded-xl p-5 shadow-sm">
            <p class="text-xs font-medium text-slate-400">{{ __('Total Students') }}</p>
            <p class="text-2xl font-bold text-white mt-2">{{ number_format($metrics['total_students'] ?? 0) }}</p>
        </div>

        <!-- Card 3: Teachers -->
        <div class="bg-slate-950 border border-slate-800 rounded-xl p-5 shadow-sm">
            <p class="text-xs font-medium text-slate-400">{{ __('Total Teachers') }}</p>
            <p class="text-2xl font-bold text-white mt-2">{{ number_format($metrics['total_teachers'] ?? 0) }}</p>
        </div>

        <!-- Card 4: Courses -->
        <div class="bg-slate-950 border border-slate-800 rounded-xl p-5 shadow-sm">
            <p class="text-xs font-medium text-slate-400">{{ __('Total Courses') }}</p>
            <p class="text-2xl font-bold text-white mt-2">{{ number_format($metrics['total_courses'] ?? 0) }}</p>
        </div>

        <!-- Card 5: Question Banks -->
        <div class="bg-slate-950 border border-slate-800 rounded-xl p-5 shadow-sm">
            <p class="text-xs font-medium text-slate-400">{{ __('Question Banks') }}</p>
            <p class="text-2xl font-bold text-white mt-2">{{ number_format($metrics['total_question_banks'] ?? 0) }}</p>
        </div>

        <!-- Card 6: Total Questions -->
        <div class="bg-slate-950 border border-slate-800 rounded-xl p-5 shadow-sm">
            <p class="text-xs font-medium text-slate-400">{{ __('Total Questions') }}</p>
            <p class="text-2xl font-bold text-white mt-2">{{ number_format($metrics['total_questions'] ?? 0) }}</p>
        </div>

        <!-- Card 7: Active Tests -->
        <div class="bg-slate-950 border border-slate-800 rounded-xl p-5 shadow-sm">
            <p class="text-xs font-medium text-slate-400">{{ __('Active Tests') }}</p>
            <p class="text-2xl font-bold text-indigo-400 mt-2">{{ number_format($metrics['total_active_tests'] ?? 0) }}</p>
        </div>

        <!-- Card 8: Attempts -->
        <div class="bg-slate-950 border border-slate-800 rounded-xl p-5 shadow-sm">
            <p class="text-xs font-medium text-slate-400">{{ __('Total Attempts') }}</p>
            <p class="text-2xl font-bold text-white mt-2">{{ number_format($metrics['total_attempts'] ?? 0) }}</p>
        </div>

        <!-- Card 9: Certificates -->
        <div class="bg-slate-950 border border-slate-800 rounded-xl p-5 shadow-sm">
            <p class="text-xs font-medium text-slate-400">{{ __('Certificates Issued') }}</p>
            <p class="text-2xl font-bold text-white mt-2">{{ number_format($metrics['total_certificates'] ?? 0) }}</p>
        </div>

        <!-- Card 10: Revenue Summary -->
        <div class="bg-slate-950 border border-slate-800 rounded-xl p-5 shadow-sm">
            <p class="text-xs font-medium text-slate-400">{{ __('Total Revenue') }}</p>
            <p class="text-xl font-bold text-emerald-400 mt-2">Rp {{ number_format($metrics['total_revenue'] ?? 0, 0, ',', '.') }}</p>
        </div>
    </div>

    <!-- Recent Activities & Audit Log Section -->
    <div class="grid gap-6 lg:grid-cols-3">
        <!-- Recent Activities -->
        <div class="lg:col-span-2 bg-slate-950 border border-slate-800 rounded-xl p-6 shadow-sm">
            <h3 class="text-base font-semibold text-white mb-4">{{ __('Recent CBT Test Activities') }}</h3>
            <div class="space-y-4">
                @forelse ($recentActivities as $activity)
                    <div class="flex items-center justify-between p-3 rounded-lg bg-slate-900 border border-slate-800/80">
                        <div>
                            <p class="text-sm font-semibold text-white">{{ $activity->user?->name ?? 'Student' }}</p>
                            <p class="text-xs text-slate-400">{{ $activity->test?->title ?? 'Simulation Test' }}</p>
                        </div>
                        <div class="text-right">
                            <span class="text-xs font-bold text-indigo-400">Score: {{ $activity->total_score ?? 'N/A' }}</span>
                            <span class="block text-[10px] text-slate-500">{{ $activity->updated_at?->diffForHumans() }}</span>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-slate-500 py-4 text-center">{{ __('No recent activities recorded yet.') }}</p>
                @endforelse
            </div>
        </div>

        <!-- Architecture Quick Info -->
        <div class="bg-slate-950 border border-slate-800 rounded-xl p-6 shadow-sm">
            <h3 class="text-base font-semibold text-white mb-4">{{ __('Platform Status') }}</h3>
            <div class="space-y-3 text-sm">
                <div class="flex justify-between border-b border-slate-800/60 pb-2">
                    <span class="text-slate-400">Architecture</span>
                    <span class="font-semibold text-indigo-400">Modular (app/Modules)</span>
                </div>
                <div class="flex justify-between border-b border-slate-800/60 pb-2">
                    <span class="text-slate-400">Primary Key</span>
                    <span class="font-semibold text-white">ULID</span>
                </div>
                <div class="flex justify-between border-b border-slate-800/60 pb-2">
                    <span class="text-slate-400">Permissions</span>
                    <span class="font-semibold text-emerald-400">Spatie Active</span>
                </div>
                <div class="flex justify-between border-b border-slate-800/60 pb-2">
                    <span class="text-slate-400">Event Logging</span>
                    <span class="font-semibold text-emerald-400">Enabled</span>
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
