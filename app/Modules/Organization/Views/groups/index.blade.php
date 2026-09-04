@extends('organization::layouts.organization', [
    'title' => 'Groups & Cohorts',
    'heading' => 'Organization Groups',
    'subheading' => 'Organize candidate members into academic classes, cohorts, or departments'
])

@section('content')
<div class="space-y-6" x-data="{ showCreateModal: false }">
    <!-- Action Bar -->
    <div class="flex items-center justify-between bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-xs">
        <h2 class="font-bold text-base text-slate-900 dark:text-white">All Groups ({{ $groups->total() }})</h2>

        <button @click="showCreateModal = true" class="px-4 py-2 text-sm font-semibold bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl shadow-xs transition flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Create Group
        </button>
    </div>

    <!-- Groups Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        @forelse($groups as $group)
            <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 p-5 shadow-xs flex flex-col justify-between space-y-4">
                <div>
                    <div class="flex items-center justify-between">
                        <span class="px-2 py-0.5 rounded text-[11px] font-semibold uppercase {{ $group->is_active ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300' : 'bg-slate-100 text-slate-600' }}">
                            {{ $group->is_active ? 'Active' : 'Inactive' }}
                        </span>
                        <span class="text-xs text-slate-400 capitalize">{{ $group->group_type ?? 'Cohort' }}</span>
                    </div>

                    <h3 class="text-lg font-bold text-slate-900 dark:text-white mt-2">{{ $group->name }}</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 line-clamp-2 mt-1">{{ $group->description ?? 'No description provided.' }}</p>
                </div>

                <div class="pt-4 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between">
                    <span class="text-xs font-semibold text-slate-600 dark:text-slate-400">
                        {{ $group->group_members_count }} Members
                    </span>
                    <a href="{{ route('organization.groups.show', [$organization->slug, $group->id]) }}" class="px-3 py-1.5 text-xs font-semibold bg-indigo-50 text-indigo-700 dark:bg-indigo-950/50 dark:text-indigo-300 hover:bg-indigo-100 rounded-lg transition">
                        Manage Members →
                    </a>
                </div>
            </div>
        @empty
            <div class="col-span-full py-12 text-center bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800">
                <p class="text-slate-400 text-sm">No groups created yet. Click "Create Group" to organize candidates.</p>
            </div>
        @endforelse
    </div>

    @if($groups->hasPages())
        <div class="p-4 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800">
            {{ $groups->links() }}
        </div>
    @endif

    <!-- Create Group Modal -->
    <div x-show="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs" style="display: none;">
        <div @click.away="showCreateModal = false" class="bg-white dark:bg-slate-900 rounded-2xl max-w-md w-full p-6 border border-slate-200 dark:border-slate-800 shadow-xl space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                <h3 class="font-bold text-lg text-slate-900 dark:text-white">Create New Group</h3>
                <button @click="showCreateModal = false" class="text-slate-400 hover:text-slate-600">✕</button>
            </div>

            <form method="POST" action="{{ route('organization.groups.store', $organization->slug) }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">Group Name *</label>
                    <input type="text" name="name" required placeholder="e.g. Class 12-A / Batch 2026" class="w-full px-3.5 py-2 text-sm rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">Group Type</label>
                    <select name="group_type" class="w-full px-3 py-2 text-sm rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white">
                        @foreach($groupTypes as $type)
                            <option value="{{ $type->value }}">{{ $type->label() }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">Description</label>
                    <textarea name="description" rows="3" placeholder="Optional notes about this group..." class="w-full px-3.5 py-2 text-sm rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white"></textarea>
                </div>

                <div class="pt-4 flex items-center justify-end gap-3 border-t border-slate-100 dark:border-slate-800">
                    <button type="button" @click="showCreateModal = false" class="px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-xl">Cancel</button>
                    <button type="submit" class="px-5 py-2 text-sm font-semibold bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl shadow-xs">Create Group</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
