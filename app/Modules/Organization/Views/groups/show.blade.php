@extends('organization::layouts.organization', [
    'title' => $group->name,
    'heading' => $group->name,
    'subheading' => 'Group roster and member management'
])

@section('content')
<div class="space-y-6" x-data="{ showAddMemberModal: false, showEditGroupModal: false }">
    <!-- Group Details Header Card -->
    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 p-6 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <span class="px-2.5 py-0.5 rounded text-xs font-semibold uppercase {{ $group->is_active ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300' : 'bg-slate-100 text-slate-600' }}">
                    {{ $group->is_active ? 'Active Group' : 'Inactive Group' }}
                </span>
                <span class="text-xs text-slate-500 capitalize">• {{ $group->group_type ?? 'Cohort' }}</span>
                <span class="text-xs text-slate-500">• {{ $group->group_members_count }} Members Assigned</span>
            </div>
            <p class="text-sm text-slate-600 dark:text-slate-400 mt-2">{{ $group->description ?? 'No group description.' }}</p>
        </div>

        <div class="flex items-center gap-2">
            <button @click="showEditGroupModal = true" class="px-3.5 py-2 text-xs font-semibold bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 rounded-xl transition">
                Edit Group Details
            </button>
            <button @click="showAddMemberModal = true" class="px-4 py-2 text-xs font-semibold bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl shadow-xs transition flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Add Member to Group
            </button>
        </div>
    </div>

    <!-- Members Table -->
    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 overflow-hidden shadow-xs">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-800">
            <h2 class="font-bold text-base text-slate-900 dark:text-white">Assigned Members ({{ $members->total() }})</h2>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="border-b border-slate-200 dark:border-slate-800 bg-slate-50/75 dark:bg-slate-950/50 text-xs font-semibold uppercase text-slate-500">
                        <th class="py-3.5 px-6">Member Name & Email</th>
                        <th class="py-3.5 px-4">Member ID</th>
                        <th class="py-3.5 px-4">Role</th>
                        <th class="py-3.5 px-4">Department</th>
                        <th class="py-3.5 px-6 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($members as $gm)
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition">
                            <td class="py-3.5 px-6">
                                <div class="font-semibold text-slate-900 dark:text-white">{{ $gm->membership->user->name ?? '-' }}</div>
                                <div class="text-xs text-slate-500">{{ $gm->membership->user->email ?? '-' }}</div>
                            </td>
                            <td class="py-3.5 px-4 font-mono text-xs text-slate-600 dark:text-slate-400">
                                {{ $gm->membership->member_identifier ?? '—' }}
                            </td>
                            <td class="py-3.5 px-4 capitalize text-xs">
                                {{ $gm->membership->role->label() }}
                            </td>
                            <td class="py-3.5 px-4 text-xs text-slate-600 dark:text-slate-400">
                                {{ $gm->membership->department ?? '—' }}
                            </td>
                            <td class="py-3.5 px-6 text-right">
                                <form method="POST" action="{{ route('organization.groups.members.remove', [$organization->slug, $group->id, $gm->membership_id]) }}" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs font-medium text-rose-600 hover:text-rose-800 dark:text-rose-400">
                                        Remove
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-8 text-center text-slate-400 text-sm">
                                No members assigned to this group yet. Click "Add Member to Group" above.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($members->hasPages())
            <div class="p-4 border-t border-slate-200 dark:border-slate-800">
                {{ $members->links() }}
            </div>
        @endif
    </div>

    <!-- Add Member Modal -->
    <div x-show="showAddMemberModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs" style="display: none;">
        <div @click.away="showAddMemberModal = false" class="bg-white dark:bg-slate-900 rounded-2xl max-w-md w-full p-6 border border-slate-200 dark:border-slate-800 shadow-xl space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                <h3 class="font-bold text-lg text-slate-900 dark:text-white">Add Member to {{ $group->name }}</h3>
                <button @click="showAddMemberModal = false" class="text-slate-400 hover:text-slate-600">✕</button>
            </div>

            @if($availableMemberships->isEmpty())
                <p class="text-xs text-slate-500 py-4 text-center">All active organization members are already in this group.</p>
            @else
                <form method="POST" action="{{ route('organization.groups.members.add', [$organization->slug, $group->id]) }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">Select Active Member *</label>
                        <select name="membership_id" required class="w-full px-3 py-2 text-sm rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white">
                            @foreach($availableMemberships as $avail)
                                <option value="{{ $avail->id }}">
                                    {{ $avail->user->name }} ({{ $avail->user->email }}) — {{ $avail->department ?? 'General' }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="pt-4 flex items-center justify-end gap-3 border-t border-slate-100 dark:border-slate-800">
                        <button type="button" @click="showAddMemberModal = false" class="px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-xl">Cancel</button>
                        <button type="submit" class="px-5 py-2 text-sm font-semibold bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl shadow-xs">Add to Group</button>
                    </div>
                </form>
            @endif
        </div>
    </div>

    <!-- Edit Group Modal -->
    <div x-show="showEditGroupModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs" style="display: none;">
        <div @click.away="showEditGroupModal = false" class="bg-white dark:bg-slate-900 rounded-2xl max-w-md w-full p-6 border border-slate-200 dark:border-slate-800 shadow-xl space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                <h3 class="font-bold text-lg text-slate-900 dark:text-white">Edit Group</h3>
                <button @click="showEditGroupModal = false" class="text-slate-400 hover:text-slate-600">✕</button>
            </div>

            <form method="POST" action="{{ route('organization.groups.update', [$organization->slug, $group->id]) }}" class="space-y-4">
                @csrf
                @method('PUT')
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">Group Name *</label>
                    <input type="text" name="name" value="{{ $group->name }}" required class="w-full px-3.5 py-2 text-sm rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white">
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">Group Type</label>
                    <select name="group_type" class="w-full px-3 py-2 text-sm rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white">
                        @foreach($groupTypes as $type)
                            <option value="{{ $type->value }}" {{ $group->group_type === $type->value ? 'selected' : '' }}>{{ $type->label() }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">Description</label>
                    <textarea name="description" rows="3" class="w-full px-3.5 py-2 text-sm rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white">{{ $group->description }}</textarea>
                </div>

                <div class="pt-4 flex items-center justify-end gap-3 border-t border-slate-100 dark:border-slate-800">
                    <button type="button" @click="showEditGroupModal = false" class="px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-xl">Cancel</button>
                    <button type="submit" class="px-5 py-2 text-sm font-semibold bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl shadow-xs">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
