@extends('layouts.admin')

@section('content')
<div class="mb-6">
    <h1 class="text-xl font-bold text-white">Super Admin Platform Settings</h1>
</div>


    <div class="max-w-4xl bg-slate-950 border border-slate-800 rounded-xl p-6 shadow-sm">
        @if (session('status') === 'settings-updated')
            <div class="mb-6 p-4 rounded-lg bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-sm font-medium">
                {{ __('Settings updated successfully.') }}
            </div>
        @endif

        <form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-6">
            @csrf

            <!-- App Name -->
            <div>
                <label for="app_name" class="block text-sm font-medium text-slate-300">{{ __('Application Name') }}</label>
                <input type="text" id="app_name" name="app_name" value="{{ old('app_name', $settings['app_name']) }}"
                       class="mt-2 block w-full rounded-lg bg-slate-900 border border-slate-800 text-white px-4 py-2.5 text-sm focus:border-indigo-500 focus:outline-none" required />
            </div>

            <!-- Locale & Timezone -->
            <div class="grid gap-6 sm:grid-cols-2">
                <div>
                    <label for="app_locale" class="block text-sm font-medium text-slate-300">{{ __('Default Locale') }}</label>
                    <select id="app_locale" name="app_locale"
                            class="mt-2 block w-full rounded-lg bg-slate-900 border border-slate-800 text-white px-4 py-2.5 text-sm focus:border-indigo-500 focus:outline-none">
                        <option value="en" {{ $settings['app_locale'] === 'en' ? 'selected' : '' }}>English (en)</option>
                        <option value="id" {{ $settings['app_locale'] === 'id' ? 'selected' : '' }}>Indonesian (id)</option>
                    </select>
                </div>
                <div>
                    <label for="app_timezone" class="block text-sm font-medium text-slate-300">{{ __('Application Timezone') }}</label>
                    <input type="text" id="app_timezone" name="app_timezone" value="{{ old('app_timezone', $settings['app_timezone']) }}"
                           class="mt-2 block w-full rounded-lg bg-slate-900 border border-slate-800 text-white px-4 py-2.5 text-sm focus:border-indigo-500 focus:outline-none" required />
                </div>
            </div>

            <!-- Maintenance Mode & Branding -->
            <div class="grid gap-6 sm:grid-cols-2">
                <div>
                    <label for="maintenance_mode" class="block text-sm font-medium text-slate-300">{{ __('Maintenance Mode') }}</label>
                    <select id="maintenance_mode" name="maintenance_mode"
                            class="mt-2 block w-full rounded-lg bg-slate-900 border border-slate-800 text-white px-4 py-2.5 text-sm focus:border-indigo-500 focus:outline-none">
                        <option value="false" {{ $settings['maintenance_mode'] === 'false' ? 'selected' : '' }}>Disabled (Active Online)</option>
                        <option value="true" {{ $settings['maintenance_mode'] === 'true' ? 'selected' : '' }}>Enabled (Under Maintenance)</option>
                    </select>
                </div>
                <div>
                    <label for="branding_logo" class="block text-sm font-medium text-slate-300">{{ __('Branding Logo URL') }}</label>
                    <input type="text" id="branding_logo" name="branding_logo" value="{{ old('branding_logo', $settings['branding_logo']) }}"
                           class="mt-2 block w-full rounded-lg bg-slate-900 border border-slate-800 text-white px-4 py-2.5 text-sm focus:border-indigo-500 focus:outline-none" placeholder="https://example.com/logo.png" />
                </div>
            </div>

            <!-- Submit -->
            <div class="pt-4 flex justify-end">
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 text-white font-semibold px-6 py-2.5 rounded-lg text-sm transition-colors shadow-md shadow-indigo-600/20">
                    {{ __('Save Changes') }}
                </button>
            </div>
        </form>
    </div>
@endsection
