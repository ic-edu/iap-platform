<!DOCTYPE html>
<html lang="en" data-theme="{{ Auth::user()?->getThemePreference() ?? 'light' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Select Organization — iC.edu Assessment Platform</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100 min-h-screen flex items-center justify-center p-6">
    <div class="max-w-lg w-full bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 p-8 shadow-xl space-y-6">
        <div class="text-center space-y-2">
            <div class="w-12 h-12 rounded-2xl bg-indigo-600 text-white font-bold text-lg flex items-center justify-center mx-auto shadow-md">
                IAP
            </div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Select Organization</h1>
            <p class="text-xs text-slate-500">You belong to multiple organizations. Please choose a workspace to manage.</p>
        </div>

        <div class="divide-y divide-slate-100 dark:divide-slate-800">
            @foreach($organizations as $org)
                <a href="{{ route('organization.dashboard', $org->slug) }}" class="p-4 rounded-xl flex items-center justify-between hover:bg-indigo-50/75 dark:hover:bg-slate-800 transition group">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-indigo-100 dark:bg-indigo-950 text-indigo-700 dark:text-indigo-300 font-bold flex items-center justify-center">
                            {{ strtoupper(substr($org->name, 0, 2)) }}
                        </div>
                        <div>
                            <div class="font-bold text-sm text-slate-900 dark:text-white group-hover:text-indigo-600 transition">{{ $org->name }}</div>
                            <div class="text-xs text-slate-400 capitalize">{{ $org->organization_type->label() }}</div>
                        </div>
                    </div>
                    <span class="text-slate-400 group-hover:text-indigo-600 group-hover:translate-x-1 transition">→</span>
                </a>
            @endforeach
        </div>
    </div>
</body>
</html>
