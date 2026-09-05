@extends('layouts.admin')

@section('content')
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Institutional Organizations</h1>
            <p class="text-xs text-slate-500 dark:text-slate-400">Manage partner schools, universities, companies, and training institutions</p>
        </div>
        <a href="{{ route('admin.organizations.create') }}" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-lg shadow transition-colors flex items-center gap-1.5">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            <span>Create Organization</span>
        </a>
    </div>

    <!-- System Feedback Alerts -->
    @if(session('status'))
        <div class="mb-6 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-400 text-xs font-medium flex items-center gap-2">
            <span>✅</span> {{ session('status') }}
        </div>
    @endif
    @if(session('error'))
        <div class="mb-6 p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-600 dark:text-rose-400 text-xs font-medium flex items-center gap-2">
            <span>⚠️</span> {{ session('error') }}
        </div>
    @endif

    <!-- Search & Filter Controls -->
    <div class="mb-6 p-4 bg-slate-950/80 border border-slate-800 rounded-xl">
        <form action="{{ route('admin.organizations.index') }}" method="GET" class="flex flex-col sm:flex-row gap-3">
            <div class="flex-1 relative">
                <input type="text" name="search" value="{{ $search }}" placeholder="Search by name, slug, city..."
                       class="w-full pl-9 pr-4 py-2 bg-slate-900 border border-slate-800 rounded-lg text-xs text-slate-900 dark:text-white placeholder-slate-500 focus:border-indigo-500 focus:outline-none transition-colors">
                <svg class="w-4 h-4 text-slate-500 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
            </div>

            <select name="status" class="px-4 py-2 bg-slate-900 border border-slate-800 rounded-lg text-xs text-slate-900 dark:text-white focus:border-indigo-500 focus:outline-none">
                <option value="all">All Statuses</option>
                @foreach($statuses as $s)
                    <option value="{{ $s->value }}" {{ $statusFilter === $s->value ? 'selected' : '' }}>{{ $s->label() }}</option>
                @endforeach
            </select>

            <select name="type" class="px-4 py-2 bg-slate-900 border border-slate-800 rounded-lg text-xs text-slate-900 dark:text-white focus:border-indigo-500 focus:outline-none">
                <option value="all">All Types</option>
                @foreach($types as $t)
                    <option value="{{ $t->value }}" {{ $typeFilter === $t->value ? 'selected' : '' }}>{{ $t->label() }}</option>
                @endforeach
            </select>

            <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-lg shadow transition-colors">
                Apply Filters
            </button>
            @if($search || $statusFilter !== 'all' || $typeFilter !== 'all')
                <a href="{{ route('admin.organizations.index') }}" class="px-3 py-2 bg-slate-800 hover:bg-slate-700 text-slate-400 text-xs font-medium rounded-lg text-center transition-colors">
                    Reset
                </a>
            @endif
        </form>
    </div>

    <!-- Organizations Table -->
    <div class="bg-slate-950/80 border border-slate-800 rounded-xl overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="border-b border-slate-800 bg-slate-900/50 text-[11px] font-semibold uppercase text-slate-400">
                        <th class="py-3 px-4">Organization</th>
                        <th class="py-3 px-4">Type</th>
                        <th class="py-3 px-4">Active Members</th>
                        <th class="py-3 px-4">Groups</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                    @forelse($organizations as $org)
                        <tr class="hover:bg-slate-900/40 transition-colors">
                            <td class="py-3 px-4">
                                <div class="font-bold text-slate-900 dark:text-white">{{ $org->name }}</div>
                                <div class="text-[11px] text-slate-500 font-mono">/organization/{{ $org->slug }}</div>
                            </td>
                            <td class="py-3 px-4 text-slate-600 dark:text-slate-400 capitalize">{{ $org->organization_type->label() }}</td>
                            <td class="py-3 px-4 font-semibold text-slate-900 dark:text-white">{{ $org->active_memberships_count }}</td>
                            <td class="py-3 px-4 text-slate-600 dark:text-slate-400">{{ $org->groups_count }}</td>
                            <td class="py-3 px-4">
                                <span class="px-2.5 py-0.5 rounded text-[10px] font-bold uppercase {{ $org->status->value === 'active' ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' : 'bg-amber-500/20 text-amber-400 border border-amber-500/30' }}">
                                    {{ $org->status->label() }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-right space-x-2">
                                <a href="{{ route('organization.dashboard', $org->slug) }}" class="text-xs font-semibold text-indigo-400 hover:text-indigo-300" target="_blank">Portal ↗</a>
                                <a href="{{ route('admin.organizations.edit', $org->id) }}" class="text-xs font-semibold text-slate-400 hover:text-slate-300">Edit</a>
                                <form method="POST" action="{{ route('admin.organizations.toggle-status', $org->id) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="text-xs font-medium text-amber-400 hover:text-amber-300">
                                        {{ $org->isActive() ? 'Suspend' : 'Activate' }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-slate-500 text-xs">No organizations found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($organizations->hasPages())
            <div class="p-4 border-t border-slate-800">
                {{ $organizations->links() }}
            </div>
        @endif
    </div>
@endsection
