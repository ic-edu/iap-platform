@props(['active'])

@php
$classes = $active ?? false
    ? 'group flex gap-x-3 rounded-md bg-indigo-50 p-2 text-sm font-semibold leading-6 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300'
    : 'group flex gap-x-3 rounded-md p-2 text-sm font-semibold leading-6 text-slate-700 transition hover:bg-slate-50 hover:text-indigo-700 dark:text-slate-300 dark:hover:bg-slate-900 dark:hover:text-indigo-300';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
