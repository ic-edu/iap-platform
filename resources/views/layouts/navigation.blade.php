@php
    $navigation = [
        ['label' => 'Dashboard', 'route' => 'dashboard', 'icon' => 'M3.75 6A2.25 2.25 0 0 1 6 3.75h3A2.25 2.25 0 0 1 11.25 6v3A2.25 2.25 0 0 1 9 11.25H6A2.25 2.25 0 0 1 3.75 9V6Zm9 0A2.25 2.25 0 0 1 15 3.75h3A2.25 2.25 0 0 1 20.25 6v3A2.25 2.25 0 0 1 18 11.25h-3A2.25 2.25 0 0 1 12.75 9V6Zm-9 9A2.25 2.25 0 0 1 6 12.75h3A2.25 2.25 0 0 1 11.25 15v3A2.25 2.25 0 0 1 9 20.25H6A2.25 2.25 0 0 1 3.75 18v-3Zm9 0A2.25 2.25 0 0 1 15 12.75h3A2.25 2.25 0 0 1 20.25 15v3A2.25 2.25 0 0 1 18 20.25h-3A2.25 2.25 0 0 1 12.75 18v-3Z'],
    ];
@endphp

<div x-show="sidebarOpen" class="relative z-50 lg:hidden" role="dialog" aria-modal="true" x-cloak>
    <div x-show="sidebarOpen" x-transition.opacity class="fixed inset-0 bg-slate-950/60"></div>

    <div class="fixed inset-0 flex">
        <div
            x-show="sidebarOpen"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="-translate-x-full"
            x-transition:enter-end="translate-x-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="translate-x-0"
            x-transition:leave-end="-translate-x-full"
            class="relative flex w-full max-w-72 flex-1"
        >
            <div class="absolute left-full top-0 flex w-16 justify-center pt-5">
                <button type="button" class="-m-2.5 p-2.5 text-white" @click="sidebarOpen = false">
                    <span class="sr-only">{{ __('Close sidebar') }}</span>
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            @include('layouts.partials.sidebar', ['navigation' => $navigation])
        </div>
    </div>
</div>

<aside class="hidden lg:fixed lg:inset-y-0 lg:z-50 lg:flex lg:w-72 lg:flex-col">
    @include('layouts.partials.sidebar', ['navigation' => $navigation])
</aside>

<div class="sticky top-0 z-40 flex h-16 items-center gap-x-4 border-b border-slate-200 bg-white/85 px-4 backdrop-blur dark:border-slate-800 dark:bg-slate-950/85 sm:gap-x-6 sm:px-6 lg:pl-72 lg:pr-8">
    <button type="button" class="-m-2.5 p-2.5 text-slate-700 dark:text-slate-200 lg:hidden" @click="sidebarOpen = true">
        <span class="sr-only">{{ __('Open sidebar') }}</span>
        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
        </svg>
    </button>

    <div class="h-6 w-px bg-slate-200 dark:bg-slate-800 lg:hidden"></div>

    <div class="flex flex-1 items-center justify-between gap-x-4">
        <div>
            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Workspace') }}</p>
            <p class="text-base font-semibold text-slate-900 dark:text-white">{{ config('app.name', 'IAP Platform') }}</p>
        </div>

        <div class="flex items-center gap-x-3">
            <button
                type="button"
                class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white dark:focus:ring-offset-slate-950"
                @click="toggleTheme()"
                :aria-label="darkMode ? '{{ __('Use light mode') }}' : '{{ __('Use dark mode') }}'"
            >
                <svg x-show="!darkMode" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" />
                </svg>
                <svg x-show="darkMode" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" x-cloak>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.718 9.718 0 0 1 18 15.75 9.75 9.75 0 0 1 8.25 6c0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25 9.75 9.75 0 0 0 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z" />
                </svg>
            </button>

            <x-dropdown align="right" width="56">
                <x-slot name="trigger">
                    <button class="flex items-center gap-x-3 rounded-full p-1.5 text-sm transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:hover:bg-slate-800 dark:focus:ring-offset-slate-950">
                        <span class="flex h-9 w-9 items-center justify-center rounded-full bg-indigo-600 text-sm font-semibold text-white">
                            {{ Str::of(Auth::user()->name)->trim()->substr(0, 1)->upper() }}
                        </span>
                        <span class="hidden text-left sm:block">
                            <span class="block text-sm font-semibold text-slate-900 dark:text-white">{{ Auth::user()->name }}</span>
                            <span class="block text-xs text-slate-500 dark:text-slate-400">{{ Auth::user()->email }}</span>
                        </span>
                        <svg class="hidden h-4 w-4 text-slate-500 sm:block" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </x-slot>

                <x-slot name="content">
                    <div class="px-4 py-3">
                        <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ Auth::user()->name }}</p>
                        <p class="truncate text-sm text-slate-500 dark:text-slate-400">{{ Auth::user()->email }}</p>
                    </div>

                    <div class="border-t border-slate-100 dark:border-slate-700"></div>

                    <x-dropdown-link :href="route('profile.edit')">
                        {{ __('Profile settings') }}
                    </x-dropdown-link>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf

                        <x-dropdown-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();">
                            {{ __('Log out') }}
                        </x-dropdown-link>
                    </form>
                </x-slot>
            </x-dropdown>
        </div>
    </div>
</div>
