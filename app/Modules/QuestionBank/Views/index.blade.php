<x-admin-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs font-semibold text-indigo-400 uppercase tracking-wider">{{ __('Teacher Authoring & Assessment Workspace') }}</p>
                <h1 class="text-2xl font-bold tracking-tight text-white mt-1">{{ __('Teacher Dashboard & Question Banks') }}</h1>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.media.index') }}" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold rounded-lg border border-slate-700 transition-colors">
                    📁 Media Library
                </a>
                <a href="{{ route('admin.tests.index') }}" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-lg shadow transition-colors">
                    📋 Test Builder
                </a>
            </div>
        </div>
    </x-slot>

    @if (session('status'))
        <div class="mb-6 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-medium">
            ✅ {{ session('status') }}
        </div>
    @endif

    <!-- Section 1: Dashboard KPI Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <div class="p-4 bg-slate-950 border border-slate-800 rounded-xl">
            <span class="text-[10px] font-bold uppercase text-slate-400">Total Courses</span>
            <span class="text-2xl font-extrabold text-white block mt-1">12</span>
        </div>
        <div class="p-4 bg-slate-950 border border-slate-800 rounded-xl">
            <span class="text-[10px] font-bold uppercase text-slate-400">Question Banks</span>
            <span class="text-2xl font-extrabold text-indigo-400 block mt-1">{{ $banks->total() }}</span>
        </div>
        <div class="p-4 bg-slate-950 border border-slate-800 rounded-xl">
            <span class="text-[10px] font-bold uppercase text-slate-400">Draft Tests</span>
            <span class="text-2xl font-extrabold text-amber-400 block mt-1">4</span>
        </div>
        <div class="p-4 bg-slate-950 border border-slate-800 rounded-xl">
            <span class="text-[10px] font-bold uppercase text-slate-400">Published / Pending</span>
            <span class="text-2xl font-extrabold text-emerald-400 block mt-1">8 / 2</span>
        </div>
    </div>

    <!-- Section 2: Quick Action Hub & Authoring Form -->
    <div class="grid gap-6 lg:grid-cols-3 mb-8">
        <!-- New Question Bank Form (Teacher Only - QB-002) -->
        @if (Auth::user()?->hasRole('teacher'))
            <div class="bg-slate-950 border border-slate-800 rounded-xl p-6 shadow-sm">
                <h3 class="text-base font-semibold text-white mb-4 flex items-center gap-2">
                    <span>📂</span> {{ __('Create Question Bank') }}
                </h3>
                <form method="POST" action="{{ route('admin.question-banks.store') }}" class="space-y-4">
                    @csrf
                    <div>
                        <label for="title" class="block text-xs font-medium text-slate-300">{{ __('Bank Title') }}</label>
                        <input type="text" id="title" name="title" required
                               class="mt-1 block w-full rounded-lg bg-slate-900 border border-slate-800 text-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none" placeholder="e.g. TOEIC Part 1 Listening Pool" />
                    </div>
                    <div>
                        <label for="test_type" class="block text-xs font-medium text-slate-300">{{ __('Test Type') }}</label>
                        <select id="test_type" name="test_type"
                                class="mt-1 block w-full rounded-lg bg-slate-900 border border-slate-800 text-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
                            <option value="toeic">TOEIC</option>
                            <option value="toefl">TOEFL iBT</option>
                            <option value="ielts">IELTS</option>
                            <option value="general">General</option>
                        </select>
                    </div>
                    <div>
                        <label for="description" class="block text-xs font-medium text-slate-300">{{ __('Description') }}</label>
                        <textarea id="description" name="description" rows="3"
                                  class="mt-1 block w-full rounded-lg bg-slate-900 border border-slate-800 text-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none" placeholder="Bank details..."></textarea>
                    </div>
                    <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-500 text-white font-semibold py-2 rounded-lg text-sm transition-colors shadow-sm">
                        {{ __('Create Bank') }}
                    </button>
                </form>
            </div>
        @endif

        <!-- Question Banks Table -->
        <div class="{{ Auth::user()?->hasRole('teacher') ? 'lg:col-span-2' : 'lg:col-span-3' }} bg-slate-950 border border-slate-800 rounded-xl p-6 shadow-sm">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-base font-semibold text-white">{{ __('Question Banks Listing') }}</h3>
                <form method="GET" action="{{ route('admin.question-banks.index') }}" class="flex gap-2">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search banks..." class="bg-slate-900 border border-slate-800 text-xs text-white px-2.5 py-1.5 rounded-lg focus:outline-none">
                    <button type="submit" class="px-2.5 py-1.5 bg-slate-800 text-xs text-slate-200 rounded-lg">Filter</button>
                </form>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-300">
                    <thead class="bg-slate-900 text-xs uppercase text-slate-400 border-b border-slate-800">
                        <tr>
                            <th class="p-3">Title</th>
                            <th class="p-3">Type</th>
                            <th class="p-3">Status</th>
                            <th class="p-3">Questions</th>
                            <th class="p-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60">
                        @forelse ($banks as $bank)
                            @php
                                $statusBadge = match($bank->status) {
                                    'published' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
                                    'approved' => 'bg-indigo-500/10 text-indigo-400 border-indigo-500/20',
                                    'pending_approval' => 'bg-amber-500/10 text-amber-400 border-amber-500/20',
                                    'pending_archive_approval' => 'bg-amber-500/10 text-amber-400 border-amber-500/20',
                                    'archived' => 'bg-slate-800 text-slate-400 border-slate-700',
                                    'rejected' => 'bg-rose-500/10 text-rose-400 border-rose-500/20',
                                    default => 'bg-slate-800 text-slate-400 border-slate-700',
                                };
                            @endphp
                            <tr>
                                <td class="p-3">
                                    <a href="{{ route('admin.question-banks.show', $bank->id) }}" class="font-semibold text-indigo-400 hover:underline block">
                                        {{ $bank->title }}
                                    </a>
                                    <span class="text-xs text-slate-500">{{ $bank->description ?? 'No description' }}</span>
                                </td>
                                <td class="p-3">
                                    <span class="px-2 py-0.5 text-xs font-medium rounded bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                                        {{ strtoupper($bank->test_type?->value ?? 'General') }}
                                    </span>
                                </td>
                                <td class="p-3">
                                    <span class="px-2 py-0.5 text-xs font-bold rounded border uppercase {{ $statusBadge }}">
                                        {{ $bank->status ?? 'draft' }}
                                    </span>
                                </td>
                                <td class="p-3 font-semibold text-white">{{ $bank->questions->count() }} items</td>
                                <td class="p-3 text-right space-x-2">
                                    <a href="{{ route('admin.question-banks.show', $bank->id) }}" class="text-xs text-indigo-400 font-semibold hover:underline">
                                        {{ Auth::user()?->hasRole('teacher') ? 'Author Items' : 'View Items' }}
                                    </a>
                                    @if (Auth::user()?->hasRole('teacher') && in_array($bank->status, ['draft', 'rejected', null]))
                                        <form method="POST" action="{{ route('admin.question-banks.submit', $bank->id) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="text-xs text-amber-400 hover:underline font-semibold">Submit</button>
                                        </form>
                                    @endif
                                    @if (Auth::user()?->hasRole('admin') && !Auth::user()?->hasRole('super-admin'))
                                        @if ($bank->status === 'approved')
                                            <form method="POST" action="{{ route('admin.question-banks.publish', $bank->id) }}" class="inline">
                                                @csrf
                                                <button type="submit" class="text-xs text-emerald-400 hover:underline font-semibold">Publish</button>
                                            </form>
                                        @elseif ($bank->status === 'published')
                                            <form method="POST" action="{{ route('admin.question-banks.unpublish', $bank->id) }}" class="inline">
                                                @csrf
                                                <button type="submit" class="text-xs text-rose-400 hover:underline font-semibold">Unpublish</button>
                                            </form>
                                        @endif
                                    @endif
                                    @if (!Auth::user()?->hasRole('teacher'))
                                        <form method="POST" action="{{ route('admin.question-banks.destroy', $bank->id) }}" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-xs text-rose-400 hover:underline">Delete</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="p-4 text-center text-slate-500">No question banks found. Create a bank to begin authoring questions.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">
                {{ $banks->links() }}
            </div>
        </div>
    </div>
</x-admin-layout>
