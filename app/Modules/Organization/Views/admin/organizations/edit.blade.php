<x-app-layout>
    <x-slot name="header">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Edit Organization: {{ $organization->name }}</h1>
    </x-slot>

    <div class="py-8 px-4 sm:px-6 lg:px-8 max-w-3xl mx-auto">
        <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 p-6 shadow-xs space-y-6">
            <form method="POST" action="{{ route('admin.organizations.update', $organization->id) }}" class="space-y-5">
                @csrf
                @method('PUT')
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">Organization Name *</label>
                    <input type="text" name="name" value="{{ old('name', $organization->name) }}" required class="w-full px-3.5 py-2 text-sm rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">Organization Type *</label>
                        <select name="organization_type" required class="w-full px-3 py-2 text-sm rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white">
                            @foreach($types as $t)
                                <option value="{{ $t->value }}" {{ $organization->organization_type->value === $t->value ? 'selected' : '' }}>{{ $t->label() }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">Account Status *</label>
                        <select name="status" required class="w-full px-3 py-2 text-sm rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white">
                            @foreach($statuses as $s)
                                <option value="{{ $s->value }}" {{ $organization->status->value === $s->value ? 'selected' : '' }}>{{ $s->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">Official Email</label>
                        <input type="email" name="email" value="{{ old('email', $organization->email) }}" class="w-full px-3.5 py-2 text-sm rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">Phone Number</label>
                        <input type="text" name="phone" value="{{ old('phone', $organization->phone) }}" class="w-full px-3.5 py-2 text-sm rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white">
                    </div>
                </div>

                <div class="pt-4 flex items-center justify-end gap-3 border-t border-slate-100 dark:border-slate-800">
                    <a href="{{ route('admin.organizations.index') }}" class="px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-400">Cancel</a>
                    <button type="submit" class="px-6 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-sm rounded-xl shadow-xs">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
