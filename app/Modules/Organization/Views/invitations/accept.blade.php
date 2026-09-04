<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Accept Organization Invitation — iC.edu Assessment Platform</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100 min-h-screen flex items-center justify-center p-6">
    <div class="max-w-md w-full bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 p-8 shadow-xl space-y-6">
        @if($error)
            <div class="text-center space-y-4">
                <div class="w-12 h-12 rounded-full bg-rose-50 text-rose-600 flex items-center justify-center mx-auto">✕</div>
                <h1 class="text-xl font-bold text-slate-900 dark:text-white">Invitation Unavailable</h1>
                <p class="text-xs text-slate-500">{{ $error }}</p>
                <a href="{{ route('login') }}" class="inline-block px-5 py-2 text-xs font-semibold bg-slate-100 rounded-xl">Back to Login</a>
            </div>
        @else
            <div class="text-center space-y-2">
                <div class="w-12 h-12 rounded-2xl bg-indigo-600 text-white font-bold text-lg flex items-center justify-center mx-auto shadow-md">
                    {{ strtoupper(substr($organization->name, 0, 2)) }}
                </div>
                <h1 class="text-xl font-bold text-slate-900 dark:text-white">Join {{ $organization->name }}</h1>
                <p class="text-xs text-slate-500">
                    You have been invited to join as <span class="font-bold text-indigo-600">{{ $invitation->intended_role->label() }}</span>.
                </p>
            </div>

            @if($errors->any())
                <div class="p-3 bg-rose-50 border border-rose-200 text-rose-700 text-xs rounded-xl">
                    @foreach($errors->all() as $err)
                        <div>{{ $err }}</div>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('invitations.process', $token) }}" class="space-y-4">
                @csrf

                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">Email Address</label>
                    <input type="email" value="{{ $invitation->email }}" readonly class="w-full px-3.5 py-2 text-sm rounded-xl border border-slate-200 bg-slate-100 text-slate-600 font-mono">
                </div>

                @if(Auth::check())
                    <div class="p-3 bg-indigo-50 border border-indigo-200 rounded-xl text-xs text-indigo-900">
                        You are logged in as <strong>{{ Auth::user()->name }}</strong> ({{ Auth::user()->email }}). Clicking accept will link this organization to your existing account.
                    </div>
                @elseif($existingUser)
                    <div class="p-3 bg-amber-50 border border-amber-200 rounded-xl text-xs text-amber-900">
                        An account with this email already exists on IAP. Please enter your password to confirm and accept:
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">Password *</label>
                        <input type="password" name="password" required class="w-full px-3.5 py-2 text-sm rounded-xl border border-slate-300">
                    </div>
                @else
                    <div>
                        <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">Your Full Name *</label>
                        <input type="text" name="name" required placeholder="Full Name" class="w-full px-3.5 py-2 text-sm rounded-xl border border-slate-300">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">Create Password *</label>
                        <input type="password" name="password" required class="w-full px-3.5 py-2 text-sm rounded-xl border border-slate-300">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">Confirm Password *</label>
                        <input type="password" name="password_confirmation" required class="w-full px-3.5 py-2 text-sm rounded-xl border border-slate-300">
                    </div>
                @endif

                <button type="submit" class="w-full py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-sm rounded-xl shadow-xs transition">
                    Accept Invitation & Continue →
                </button>
            </form>
        @endif
    </div>
</body>
</html>
