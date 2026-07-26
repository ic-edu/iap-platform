<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm font-medium text-indigo-600 dark:text-indigo-400">{{ __('Overview') }}</p>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-950 dark:text-white">{{ __('Dashboard') }}</h1>
            </div>
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('Welcome back, :name', ['name' => Auth::user()->name]) }}</p>
        </div>
    </x-slot>

    <div class="px-4 py-8 sm:px-6 lg:px-8">
        <div class="grid gap-6 lg:grid-cols-3">
            <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900 lg:col-span-2">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Platform status') }}</p>
                        <h2 class="mt-2 text-xl font-semibold text-slate-950 dark:text-white">{{ __('Authentication is configured') }}</h2>
                    </div>
                    <span class="rounded-full bg-emerald-50 px-3 py-1 text-sm font-medium text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">{{ __('Active') }}</span>
                </div>

                <div class="mt-6 grid gap-4 sm:grid-cols-3">
                    <div class="rounded-lg bg-slate-50 p-4 dark:bg-slate-950">
                        <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('Users') }}</p>
                        <p class="mt-2 text-2xl font-semibold text-slate-950 dark:text-white">1</p>
                    </div>
                    <div class="rounded-lg bg-slate-50 p-4 dark:bg-slate-950">
                        <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('Guard') }}</p>
                        <p class="mt-2 text-2xl font-semibold text-slate-950 dark:text-white">Web</p>
                    </div>
                    <div class="rounded-lg bg-slate-50 p-4 dark:bg-slate-950">
                        <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('Mode') }}</p>
                        <p class="mt-2 text-2xl font-semibold text-slate-950 dark:text-white">{{ __('Modular') }}</p>
                    </div>
                </div>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Profile') }}</p>
                <div class="mt-4 flex items-center gap-4">
                    <span class="flex h-12 w-12 items-center justify-center rounded-full bg-indigo-600 text-lg font-semibold text-white">
                        {{ Str::of(Auth::user()->name)->trim()->substr(0, 1)->upper() }}
                    </span>
                    <div class="min-w-0">
                        <p class="truncate font-semibold text-slate-950 dark:text-white">{{ Auth::user()->name }}</p>
                        <p class="truncate text-sm text-slate-500 dark:text-slate-400">{{ Auth::user()->email }}</p>
                    </div>
                </div>

                <a href="{{ route('profile.edit') }}" class="mt-6 inline-flex w-full items-center justify-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900">
                    {{ __('Manage profile') }}
                </a>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900 lg:col-span-3">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-950 dark:text-white">{{ __('Modular Architecture Status') }}</h2>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Authentication module successfully isolated under app/Modules/Authentication.') }}</p>
                    </div>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
