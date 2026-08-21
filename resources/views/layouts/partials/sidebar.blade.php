<div class="flex grow flex-col gap-y-5 overflow-y-auto border-r border-slate-200 bg-white px-6 pb-4 dark:border-slate-800 dark:bg-slate-950">
    <div class="flex h-16 shrink-0 items-center gap-x-3">
        <div class="flex items-center gap-x-3 select-none">
            <x-application-logo class="h-9 w-auto fill-current text-indigo-600 dark:text-indigo-400" />
            <span class="text-base font-semibold text-slate-950 dark:text-white">{{ config('app.name', 'IAP Platform') }}</span>
        </div>
    </div>

    <nav class="flex flex-1 flex-col">
        <ul role="list" class="flex flex-1 flex-col gap-y-7">
            <li>
                <ul role="list" class="-mx-2 space-y-1">
                    @foreach ($navigation as $item)
                        <li>
                            <x-sidebar-link :href="route($item['route'])" :active="request()->routeIs($item['route'])">
                                <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}" />
                                </svg>
                                <span>{{ __($item['label']) }}</span>
                            </x-sidebar-link>
                        </li>
                    @endforeach
                </ul>
            </li>

            <li class="mt-auto">
                <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-900">
                    <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('Secure workspace') }}</p>
                    <p class="mt-1 text-sm leading-6 text-slate-500 dark:text-slate-400">{{ __('Authentication is active and your session is protected.') }}</p>
                </div>
            </li>
        </ul>
    </nav>
</div>
