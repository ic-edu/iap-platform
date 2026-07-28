<x-admin-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs font-semibold text-indigo-400 uppercase tracking-wider">{{ __('CBT Assessment Engine') }}</p>
                <h1 class="text-2xl font-bold tracking-tight text-white mt-1">{{ __('Test Builder & Authoring') }}</h1>
            </div>
        </div>
    </x-slot>

    @if (session('status'))
        <div class="mb-6 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-medium">
            ✅ {{ session('status') }}
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3 mb-8">
        <!-- New Test Builder Form -->
        <div class="bg-slate-950 border border-slate-800 rounded-xl p-6 shadow-sm">
            <h3 class="text-base font-semibold text-white mb-4">{{ __('Create Assessment Test') }}</h3>
            <form method="POST" action="{{ route('admin.tests.store') }}" class="space-y-4">
                @csrf
                <div>
                    <label for="title" class="block text-xs font-medium text-slate-300">{{ __('Test Title') }}</label>
                    <input type="text" id="title" name="title" required
                           class="mt-1 block w-full rounded-lg bg-slate-900 border border-slate-800 text-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none" placeholder="e.g. TOEIC Listening &amp; Reading Simulation 01" />
                </div>
                <div>
                    <label for="test_type" class="block text-xs font-medium text-slate-300">{{ __('Type') }}</label>
                    <select id="test_type" name="test_type"
                            class="mt-1 block w-full rounded-lg bg-slate-900 border border-slate-800 text-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
                        <option value="toeic">TOEIC</option>
                        <option value="toefl">TOEFL iBT</option>
                        <option value="ielts">IELTS</option>
                        <option value="general">General</option>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="duration_minutes" class="block text-xs font-medium text-slate-300">{{ __('Duration (Mins)') }}</label>
                        <input type="number" id="duration_minutes" name="duration_minutes" value="120" min="1" required
                               class="mt-1 block w-full rounded-lg bg-slate-900 border border-slate-800 text-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none" />
                    </div>
                    <div>
                        <label for="pass_score" class="block text-xs font-medium text-slate-300">{{ __('Pass Score') }}</label>
                        <input type="number" id="pass_score" name="pass_score" value="700" min="0" required
                               class="mt-1 block w-full rounded-lg bg-slate-900 border border-slate-800 text-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none" />
                    </div>
                </div>

                <!-- Randomization Options -->
                <div class="space-y-2 pt-2 border-t border-slate-800">
                    <label class="flex items-center gap-2 text-xs text-slate-300">
                        <input type="checkbox" name="shuffle_questions" value="1" class="rounded bg-slate-900 border-slate-800 text-indigo-600 focus:ring-0" />
                        {{ __('Shuffle Question Order') }}
                    </label>
                    <label class="flex items-center gap-2 text-xs text-slate-300">
                        <input type="checkbox" name="shuffle_choices" value="1" class="rounded bg-slate-900 border-slate-800 text-indigo-600 focus:ring-0" />
                        {{ __('Shuffle Multiple Choice Options') }}
                    </label>
                </div>

                <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-500 text-white font-semibold py-2 rounded-lg text-sm transition-colors shadow-sm">
                    {{ __('Create Draft Test') }}
                </button>
            </form>
        </div>

        <!-- Tests Listing -->
        <div class="lg:col-span-2 bg-slate-950 border border-slate-800 rounded-xl p-6 shadow-sm">
            <h3 class="text-base font-semibold text-white mb-4">{{ __('Tests Listing &amp; Approval Status') }}</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-300">
                    <thead class="bg-slate-900 text-xs uppercase text-slate-400 border-b border-slate-800">
                        <tr>
                            <th class="p-3">Title</th>
                            <th class="p-3">Duration &amp; Threshold</th>
                            <th class="p-3">Status</th>
                            <th class="p-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60">
                        @forelse ($tests as $test)
                            <tr>
                                <td class="p-3">
                                    <span class="font-semibold text-white block">{{ $test->title }}</span>
                                    <span class="text-xs text-slate-500">{{ $test->sections->count() }} Sections</span>
                                </td>
                                <td class="p-3 text-xs">{{ $test->duration_minutes }} Mins | Pass: <span class="font-bold text-white">{{ $test->pass_score }} pts</span></td>
                                <td class="p-3">
                                    @if ($test->is_published)
                                        <span class="px-2 py-0.5 text-xs font-semibold rounded bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">Published Live</span>
                                    @else
                                        <span class="px-2 py-0.5 text-xs font-semibold rounded bg-amber-500/10 text-amber-400 border border-amber-500/20">Draft / Pending</span>
                                    @endif
                                </td>
                                <td class="p-3 text-right space-x-2">
                                    @if (!Auth::user()->hasRole('super-admin') && !Auth::user()->hasRole('admin') && !$test->is_published)
                                        <form method="POST" action="{{ route('admin.tests.submit-approval', $test->id) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="text-xs text-amber-400 font-semibold hover:underline">Submit for Approval</button>
                                        </form>
                                    @endif

                                    @if ((Auth::user()->hasRole('super-admin') || Auth::user()->hasRole('admin')) && !$test->is_published)
                                        <form method="POST" action="{{ route('admin.tests.publish', $test->id) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="text-xs text-emerald-400 font-semibold hover:underline">Publish</button>
                                        </form>
                                    @endif

                                    <form method="POST" action="{{ route('admin.tests.duplicate', $test->id) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="text-xs text-indigo-400 hover:underline">Duplicate</button>
                                    </form>

                                    @if (!Auth::user()?->hasRole('teacher') && (!$test->is_published || Auth::user()?->hasRole('super-admin')))
                                        <form method="POST" action="{{ route('admin.tests.destroy', $test->id) }}" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-xs text-rose-400 hover:underline">Delete</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="p-4 text-center text-slate-500">No assessment tests created yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">
                {{ $tests->links() }}
            </div>
        </div>
    </div>
</x-admin-layout>
