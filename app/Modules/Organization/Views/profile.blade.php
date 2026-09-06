@extends('organization::layouts.organization', [
    'title' => 'Profile',
    'heading' => 'Organization Profile',
    'subheading' => 'Institutional identity, contact info, and operational settings'
])

@section('content')
<div class="max-w-4xl space-y-6">
    <!-- Institutional Governance Identity Card (Read-Only) -->
    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 p-6 shadow-xs space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
            <div class="flex items-center gap-2.5">
                <div class="p-1.5 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white">Institutional Identity &amp; Governance</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Core institution attributes verified during onboarding governance</p>
                </div>
            </div>
            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300 border border-indigo-200/80 dark:border-indigo-800">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                <span>Read-Only</span>
            </span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5 pt-1">
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500 dark:text-slate-400 mb-1.5">Organization Name</label>
                <div class="w-full px-3.5 py-2.5 text-sm font-semibold rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/80 dark:bg-slate-950/60 text-slate-800 dark:text-slate-200 flex items-center justify-between select-none">
                    <span>{{ $organization->name }}</span>
                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500 dark:text-slate-400 mb-1.5">Organization Type</label>
                <div class="w-full px-3.5 py-2.5 text-sm font-semibold rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/80 dark:bg-slate-950/60 text-slate-800 dark:text-slate-200 flex items-center justify-between select-none">
                    <span>{{ $organization->organization_type->label() }}</span>
                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                </div>
            </div>
        </div>

        <p class="text-[11px] text-slate-500 dark:text-slate-400 flex items-center gap-1.5 pt-1">
            <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span>Organization identity fields are governed by iC.edu administration.</span>
        </p>
    </div>

    <!-- Editable Operational & Contact Settings Card -->
    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 p-6 shadow-xs space-y-6">
        <div class="border-b border-slate-100 dark:border-slate-800 pb-3">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white">Operational Contact &amp; Location Details</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400">Manage public contact coordinates and mailing address for this organization</p>
        </div>

        <form method="POST" action="{{ route('organization.profile.update', $organization->slug) }}" class="space-y-6">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">Official Email</label>
                    <input type="email" name="email" value="{{ old('email', $organization->email) }}" placeholder="contact@university.edu" class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">Phone Number</label>
                    <input type="text" name="phone" value="{{ old('phone', $organization->phone) }}" placeholder="+62 22 1234567" class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500">
                </div>

                <div class="col-span-full">
                    <label class="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">Website URL</label>
                    <input type="url" name="website" value="{{ old('website', $organization->website) }}" placeholder="https://..." class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500">
                </div>

                <div class="col-span-full">
                    <label class="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">Address</label>
                    <textarea name="address" rows="2" placeholder="Campus street address..." class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500">{{ old('address', $organization->address) }}</textarea>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">City</label>
                    <input type="text" name="city" value="{{ old('city', $organization->city) }}" placeholder="Bandung" class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">Province / State</label>
                    <input type="text" name="province" value="{{ old('province', $organization->province) }}" placeholder="West Java" class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">Country</label>
                    <input type="text" name="country" value="{{ old('country', $organization->country ?? 'Indonesia') }}" placeholder="Indonesia" class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">Postal Code</label>
                    <input type="text" name="postal_code" value="{{ old('postal_code', $organization->postal_code) }}" placeholder="40123" class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500">
                </div>
            </div>

            <div class="pt-4 border-t border-slate-100 dark:border-slate-800 flex justify-end">
                <button type="submit" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl shadow-xs transition cursor-pointer">
                    Save Profile Changes
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
