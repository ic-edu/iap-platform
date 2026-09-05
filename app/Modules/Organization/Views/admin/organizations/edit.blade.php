@extends('layouts.admin')

@section('content')
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Edit Organization: {{ $organization->name }}</h1>
            <p class="text-xs text-slate-500 dark:text-slate-400">Update institutional details, contact information, and review governance feedback</p>
        </div>
        <a href="{{ route('admin.organizations.index') }}" class="px-3 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-medium rounded-lg text-center transition-colors">
            &larr; Back to Directory
        </a>
    </div>

    <!-- Needs Revision Banner -->
    @if($organization->needsRevision() && $organization->revision_note)
        <div class="mb-6 p-4 rounded-xl bg-amber-500/10 border border-amber-500/30 text-amber-300 text-xs flex items-start gap-3">
            <span class="text-lg">⚠️</span>
            <div>
                <div class="font-bold text-amber-200 text-sm mb-1">Super Admin Revision Requested</div>
                <p class="text-slate-300 mb-2">{{ $organization->revision_note }}</p>
                <p class="text-[11px] text-amber-400 font-medium">Please address the feedback below and save to resubmit this organization for Super Admin approval.</p>
            </div>
        </div>
    @elseif($organization->isRejected() && $organization->rejection_reason)
        <div class="mb-6 p-4 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-300 text-xs flex items-start gap-3">
            <span class="text-lg">❌</span>
            <div>
                <div class="font-bold text-rose-200 text-sm mb-1">Super Admin Rejection Notice</div>
                <p class="text-slate-300 mb-2">{{ $organization->rejection_reason }}</p>
                <p class="text-[11px] text-rose-400 font-medium">Updating this form will submit a renewed application for review.</p>
            </div>
        </div>
    @endif

    @if($errors->any())
        <div class="mb-6 p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-600 dark:text-rose-400 text-xs font-medium">
            <ul class="list-disc list-inside space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="max-w-3xl bg-slate-950/80 border border-slate-800 rounded-xl p-6 shadow-sm">
        <form method="POST" action="{{ route('admin.organizations.update', $organization->id) }}" class="space-y-5">
            @csrf
            @method('PUT')
            <div>
                <label class="block text-xs font-medium text-slate-300 mb-1">Organization Name *</label>
                <input type="text" name="name" value="{{ old('name', $organization->name) }}" required
                       class="w-full p-2.5 bg-slate-900 border border-slate-800 rounded-lg text-xs text-slate-900 dark:text-white focus:border-indigo-500 focus:outline-none">
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-300 mb-1">Organization Type *</label>
                <select name="organization_type" required class="w-full p-2.5 bg-slate-900 border border-slate-800 rounded-lg text-xs text-slate-900 dark:text-white focus:border-indigo-500 focus:outline-none">
                    @foreach($types as $t)
                        <option value="{{ $t->value }}" {{ old('organization_type', $organization->organization_type->value) === $t->value ? 'selected' : '' }}>{{ $t->label() }}</option>
                    @endforeach
                </select>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">Official Email</label>
                    <input type="email" name="email" value="{{ old('email', $organization->email) }}"
                           class="w-full p-2.5 bg-slate-900 border border-slate-800 rounded-lg text-xs text-slate-900 dark:text-white focus:border-indigo-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">Phone Number</label>
                    <input type="text" name="phone" value="{{ old('phone', $organization->phone) }}"
                           class="w-full p-2.5 bg-slate-900 border border-slate-800 rounded-lg text-xs text-slate-900 dark:text-white focus:border-indigo-500 focus:outline-none">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">City</label>
                    <input type="text" name="city" value="{{ old('city', $organization->city) }}"
                           class="w-full p-2.5 bg-slate-900 border border-slate-800 rounded-lg text-xs text-slate-900 dark:text-white focus:border-indigo-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">Website</label>
                    <input type="url" name="website" value="{{ old('website', $organization->website) }}" placeholder="https://example.edu"
                           class="w-full p-2.5 bg-slate-900 border border-slate-800 rounded-lg text-xs text-slate-900 dark:text-white focus:border-indigo-500 focus:outline-none">
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-300 mb-1">Address</label>
                <textarea name="address" rows="2"
                          class="w-full p-2.5 bg-slate-900 border border-slate-800 rounded-lg text-xs text-slate-900 dark:text-white focus:border-indigo-500 focus:outline-none">{{ old('address', $organization->address) }}</textarea>
            </div>

            @if($organization->needsRevision() || $organization->isRejected())
                <div class="p-3 bg-indigo-500/10 border border-indigo-500/20 rounded-lg flex items-center gap-2">
                    <input type="checkbox" name="resubmit" id="resubmit" value="1" checked class="rounded border-slate-800 bg-slate-900 text-indigo-600 focus:ring-indigo-500">
                    <label for="resubmit" class="text-xs text-indigo-300 font-medium">Resubmit to Super Admin approval queue upon saving</label>
                </div>
            @endif

            <div class="pt-4 flex items-center justify-end gap-3 border-t border-slate-800">
                <a href="{{ route('admin.organizations.index') }}" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-medium rounded-lg transition-colors">Cancel</a>
                <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs rounded-lg shadow transition-colors">
                    {{ ($organization->needsRevision() || $organization->isRejected()) ? 'Save & Resubmit' : 'Save Changes' }}
                </button>
            </div>
        </form>
    </div>
@endsection
