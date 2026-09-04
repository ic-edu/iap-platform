<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Institutional Organizations</h1>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Manage partner schools, universities, companies, and training institutions</p>
            </div>
            <a href="{{ route('admin.organizations.create') }}" class="px-4 py-2 text-sm font-semibold bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl shadow-xs transition flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Create Organization
            </a>
        </div>
    </x-slot>

    <div class="py-8 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto space-y-6">
        @if(session('status'))
            <div class="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200 text-sm">
                {{ session('status') }}
            </div>
        @endif

        <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 p-4 shadow-xs">
            <form method="GET" action="{{ route('admin.organizations.index') }}" class="flex flex-wrap items-center gap-3">
                <input type="text" name="search" value="{{ $search }}" placeholder="Search by name, slug, city..." class="px-3.5 py-2 text-sm rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-white w-64">

                <select name="status" class="px-3 py-2 text-sm rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-white">
                    <option value="all">All Statuses</option>
                    @foreach($statuses as $s)
                        <option value="{{ $s->value }}" {{ $statusFilter === $s->value ? 'selected' : '' }}>{{ $s->label() }}</option>
                    @endforeach
                </select>

                <select name="type" class="px-3 py-2 text-sm rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-white">
                    <option value="all">All Types</option>
                    @foreach($types as $t)
                        <option value="{{ $t->value }}" {{ $typeFilter === $t->value ? 'selected' : '' }}>{{ $t->label() }}</option>
                    @endforeach
                </select>

                <button type="submit" class="px-4 py-2 text-sm font-medium bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 rounded-xl">Filter</button>
            </form>
        </div>

        <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 overflow-hidden shadow-xs">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="border-b border-slate-200 dark:border-slate-800 bg-slate-50/75 dark:bg-slate-950/50 text-xs font-semibold uppercase text-slate-500">
                        <th class="py-3.5 px-6">Organization</th>
                        <th class="py-3.5 px-4">Type</th>
                        <th class="py-3.5 px-4">Active Members</th>
                        <th class="py-3.5 px-4">Groups</th>
                        <th class="py-3.5 px-4">Status</th>
                        <th class="py-3.5 px-6 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($organizations as $org)
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition">
                            <td class="py-3.5 px-6">
                                <div class="font-bold text-slate-900 dark:text-white">{{ $org->name }}</div>
                                <div class="text-xs text-slate-400 font-mono">/organization/{{ $org->slug }}</div>
                            </td>
                            <td class="py-3.5 px-4 text-xs capitalize text-slate-600 dark:text-slate-400">{{ $org->organization_type->label() }}</td>
                            <td class="py-3.5 px-4 text-xs font-semibold">{{ $org->active_memberships_count }}</td>
                            <td class="py-3.5 px-4 text-xs">{{ $org->groups_count }}</td>
                            <td class="py-3.5 px-4">
                                <span class="px-2.5 py-0.5 rounded text-xs font-semibold uppercase {{ $org->status->value === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                    {{ $org->status->label() }}
                                </span>
                            </td>
                            <td class="py-3.5 px-6 text-right space-x-2">
                                <a href="{{ route('organization.dashboard', $org->slug) }}" class="text-xs font-semibold text-indigo-600 hover:underline" target="_blank">Portal ↗</a>
                                <a href="{{ route('admin.organizations.edit', $org->id) }}" class="text-xs font-semibold text-slate-600 hover:underline">Edit</a>
                                <form method="POST" action="{{ route('admin.organizations.toggle-status', $org->id) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="text-xs font-medium text-amber-600 hover:underline">
                                        {{ $org->isActive() ? 'Suspend' : 'Activate' }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-slate-400 text-sm">No organizations created yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            @if($organizations->hasPages())
                <div class="p-4 border-t border-slate-200 dark:border-slate-800">
                    {{ $organizations->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
