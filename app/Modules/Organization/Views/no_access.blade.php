<!DOCTYPE html>
<html lang="en" data-theme="{{ Auth::user()?->getThemePreference() ?? 'light' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>No Organization Access — iC.edu Assessment Platform</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100 min-h-screen flex items-center justify-center p-6">
    <div class="max-w-md w-full bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 p-8 shadow-xl text-center space-y-6">
        <div class="w-16 h-16 rounded-full bg-amber-50 dark:bg-amber-950/60 text-amber-600 flex items-center justify-center mx-auto">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        </div>

        <div class="space-y-2">
            <h1 class="text-xl font-bold text-slate-900 dark:text-white">No Active Organization Assigned</h1>
            <p class="text-xs text-slate-500 leading-relaxed">
                Your account carries Organization Coordinator capabilities, but no active organization membership is currently linked to your profile. Please contact your Super Administrator.
            </p>
        </div>

        <div class="pt-4 flex justify-center gap-3">
            <a href="{{ route('candidate.portal') }}" class="px-5 py-2.5 text-xs font-semibold bg-indigo-600 text-white rounded-xl hover:bg-indigo-700 transition">
                Return to Candidate Portal
            </a>
        </div>
    </div>
</body>
</html>
