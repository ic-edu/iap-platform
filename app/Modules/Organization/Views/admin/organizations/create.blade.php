@extends('layouts.admin')

@section('content')
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Create New Organization</h1>
            <p class="text-xs text-slate-500 dark:text-slate-400">Register a new partner institution for Super Admin approval</p>
        </div>
        <a href="{{ route('admin.organizations.index') }}" class="px-3 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-medium rounded-lg text-center transition-colors">
            &larr; Back to Directory
        </a>
    </div>

    <!-- Governance Notice Banner -->
    <div class="mb-6 p-4 rounded-xl bg-indigo-500/10 border border-indigo-500/20 text-indigo-300 text-xs flex items-start gap-3">
        <span class="text-base">ℹ️</span>
        <div>
            <div class="font-semibold text-white mb-0.5">Approval Governance Workflow</div>
            <p class="text-slate-400">
                Submitting this form creates the organization in <strong>Pending Approval</strong> status.
                Once reviewed and approved by Super Admin, you can issue primary coordinator onboarding invitations.
            </p>
        </div>
    </div>

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
        <form method="POST" action="{{ route('admin.organizations.store') }}" class="space-y-5">
            @csrf
            <div>
                <label class="block text-xs font-medium text-slate-300 mb-1">Organization Name *</label>
                <input type="text" name="name" value="{{ old('name') }}" required placeholder="e.g. Jakarta State University"
                       class="w-full p-2.5 bg-slate-900 border border-slate-800 rounded-lg text-xs text-slate-900 dark:text-white focus:border-indigo-500 focus:outline-none">
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-300 mb-1">Organization Type *</label>
                <select name="organization_type" required class="w-full p-2.5 bg-slate-900 border border-slate-800 rounded-lg text-xs text-slate-900 dark:text-white focus:border-indigo-500 focus:outline-none">
                    @foreach($types as $t)
                        <option value="{{ $t->value }}" {{ old('organization_type') === $t->value ? 'selected' : '' }}>{{ $t->label() }}</option>
                    @endforeach
                </select>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">Official Email</label>
                    <input type="email" name="email" value="{{ old('email') }}" placeholder="contact@example.edu"
                           class="w-full p-2.5 bg-slate-900 border border-slate-800 rounded-lg text-xs text-slate-900 dark:text-white focus:border-indigo-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">Phone Number</label>
                    <input type="text" name="phone" value="{{ old('phone') }}" placeholder="+62 21 1234567"
                           class="w-full p-2.5 bg-slate-900 border border-slate-800 rounded-lg text-xs text-slate-900 dark:text-white focus:border-indigo-500 focus:outline-none">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">City</label>
                    <input type="text" name="city" value="{{ old('city') }}" placeholder="Jakarta"
                           class="w-full p-2.5 bg-slate-900 border border-slate-800 rounded-lg text-xs text-slate-900 dark:text-white focus:border-indigo-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">Website</label>
                    <input type="url" name="website" value="{{ old('website') }}" placeholder="https://example.edu"
                           class="w-full p-2.5 bg-slate-900 border border-slate-800 rounded-lg text-xs text-slate-900 dark:text-white focus:border-indigo-500 focus:outline-none">
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-300 mb-1">Address</label>
                <textarea name="address" rows="2" placeholder="Campus / Office Street Address..."
                          class="w-full p-2.5 bg-slate-900 border border-slate-800 rounded-lg text-xs text-slate-900 dark:text-white focus:border-indigo-500 focus:outline-none">{{ old('address') }}</textarea>
            </div>

            <div class="pt-4 flex items-center justify-end gap-3 border-t border-slate-800">
                <a href="{{ route('admin.organizations.index') }}" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-medium rounded-lg transition-colors">Cancel</a>
                <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs rounded-lg shadow transition-colors">Submit for Approval</button>
            </div>
        </form>
    </div>
@endsection
