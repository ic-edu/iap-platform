<x-admin-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs font-semibold text-indigo-400 uppercase tracking-wider">{{ __('Authoring Module') }}</p>
                <h1 class="text-2xl font-bold tracking-tight text-white mt-1">{{ __('Question Banks & Item Pools') }}</h1>
            </div>
        </div>
    </x-slot>

    @if (session('status'))
        <div class="mb-6 p-4 rounded-lg bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-sm font-medium">
            {{ session('status') }}
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3 mb-8">
        <!-- New Question Bank Form -->
        <div class="bg-slate-950 border border-slate-800 rounded-xl p-6 shadow-sm">
            <h3 class="text-base font-semibold text-white mb-4">{{ __('Create Question Bank') }}</h3>
            <form method="POST" action="{{ route('admin.question-banks.store') }}" class="space-y-4">
                @csrf
                <div>
                    <label for="title" class="block text-xs font-medium text-slate-300">{{ __('Bank Title') }}</label>
                    <input type="text" id="title" name="title" required
                           class="mt-1 block w-full rounded-lg bg-slate-900 border border-slate-800 text-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none" placeholder="e.g. TOEIC Part 1 Photo Listening Pool" />
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

        <!-- Question Banks Table -->
        <div class="lg:col-span-2 bg-slate-950 border border-slate-800 rounded-xl p-6 shadow-sm">
            <h3 class="text-base font-semibold text-white mb-4">{{ __('Question Banks Listing') }}</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-300">
                    <thead class="bg-slate-900 text-xs uppercase text-slate-400 border-b border-slate-800">
                        <tr>
                            <th class="p-3">Title</th>
                            <th class="p-3">Type</th>
                            <th class="p-3">Questions</th>
                            <th class="p-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60">
                        @forelse ($banks as $bank)
                            <tr>
                                <td class="p-3">
                                    <span class="font-semibold text-white block">{{ $bank->title }}</span>
                                    <span class="text-xs text-slate-500">{{ $bank->description }}</span>
                                </td>
                                <td class="p-3">
                                    <span class="px-2 py-0.5 text-xs font-medium rounded bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                                        {{ strtoupper($bank->test_type->value) }}
                                    </span>
                                </td>
                                <td class="p-3 font-semibold text-white">{{ $bank->questions->count() }}</td>
                                <td class="p-3 text-right space-x-2">
                                    <form method="POST" action="{{ route('admin.question-banks.duplicate', $bank) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="text-xs text-indigo-400 hover:underline">Duplicate</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.question-banks.destroy', $bank) }}" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs text-rose-400 hover:underline">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="p-4 text-center text-slate-500">No question banks found.</td>
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
