@extends('organization::layouts.organization', [
    'title' => 'Profile',
    'heading' => 'Organization Profile',
    'subheading' => 'Institutional identity, contact info, and operational settings'
])

@section('content')
<div class="max-w-4xl space-y-6">
    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 p-6 shadow-xs">
        <form method="POST" action="{{ route('organization.profile.update', $organization->slug) }}" class="space-y-6">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">Organization Name *</label>
                    <input type="text" name="name" value="{{ old('name', $organization->name) }}" required class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white">
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">Organization Type *</label>
                    <select name="organization_type" required class="w-full px-3 py-2.5 text-sm rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white">
                        @foreach($organizationTypes as $type)
                            <option value="{{ $type->value }}" {{ old('organization_type', $organization->organization_type->value) === $type->value ? 'selected' : '' }}>
                                {{ $type->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">Official Email</label>
                    <input type="email" name="email" value="{{ old('email', $organization->email) }}" class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white">
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">Phone Number</label>
                    <input type="text" name="phone" value="{{ old('phone', $organization->phone) }}" class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white">
                </div>

                <div class="col-span-full">
                    <label class="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">Website URL</label>
                    <input type="url" name="website" value="{{ old('website', $organization->website) }}" placeholder="https://..." class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white">
                </div>

                <div class="col-span-full">
                    <label class="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">Address</label>
                    <textarea name="address" rows="2" class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white">{{ old('address', $organization->address) }}</textarea>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">City</label>
                    <input type="text" name="city" value="{{ old('city', $organization->city) }}" class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white">
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">Province / State</label>
                    <input type="text" name="province" value="{{ old('province', $organization->province) }}" class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white">
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">Country</label>
                    <input type="text" name="country" value="{{ old('country', $organization->country ?? 'Indonesia') }}" class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white">
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">Postal Code</label>
                    <input type="text" name="postal_code" value="{{ old('postal_code', $organization->postal_code) }}" class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white">
                </div>
            </div>

            <div class="pt-4 border-t border-slate-100 dark:border-slate-800 flex justify-end">
                <button type="submit" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl shadow-xs transition">
                    Save Profile Changes
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
