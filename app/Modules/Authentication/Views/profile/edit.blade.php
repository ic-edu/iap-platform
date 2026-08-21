@extends(Auth::user()?->hasRole('student') ? 'layouts.candidate' : 'layouts.admin')

@section('title', 'Profile & Account Settings — iC.edu Assessment Platform')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">

    {{-- Page Header --}}
    <div class="pb-4 border-b border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="px-2.5 py-0.5 rounded text-[11px] font-bold tracking-wider uppercase bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                    USER PROFILE &amp; ACCOUNT
                </span>
            </div>
            <h1 class="text-2xl font-black text-white tracking-tight">Profile &amp; Account Settings</h1>
            <p class="text-sm text-slate-400">Manage your personal information, security credentials, and account settings.</p>
        </div>
    </div>

    {{-- Status Alerts --}}
    @if (session('status') === 'profile-updated')
        <div class="p-4 rounded-xl bg-emerald-950/60 border border-emerald-500/30 text-emerald-300 text-xs font-semibold flex items-center gap-2 shadow-lg">
            ✅ Profile information updated successfully.
        </div>
    @endif

    @if (session('status') === 'password-updated')
        <div class="p-4 rounded-xl bg-emerald-950/60 border border-emerald-500/30 text-emerald-300 text-xs font-semibold flex items-center gap-2 shadow-lg">
            ✅ Security password updated successfully.
        </div>
    @endif

    @if (session('status') === 'verification-link-sent')
        <div class="p-4 rounded-xl bg-sky-950/60 border border-sky-500/30 text-sky-300 text-xs font-semibold flex items-center gap-2 shadow-lg">
            ✉️ A new verification link has been sent to your email address.
        </div>
    @endif

    {{-- 1. Account Context & Identity Card --}}
    <div class="p-5 bg-slate-900 border border-slate-800 rounded-2xl shadow-sm space-y-4">
        <div class="flex items-center gap-4">
            <div class="w-14 h-14 rounded-2xl bg-indigo-600 font-black text-white text-lg flex items-center justify-center shadow-lg shadow-indigo-600/30 flex-shrink-0">
                {{ strtoupper(substr($user->name, 0, 2)) }}
            </div>
            <div class="min-w-0 flex-1">
                <h2 class="text-lg font-bold text-white truncate">{{ $user->name }}</h2>
                <p class="text-xs text-slate-400 font-mono truncate">{{ $user->email }}</p>
                <div class="flex flex-wrap items-center gap-2 mt-2">
                    <span class="px-2.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-indigo-500/20 text-indigo-300 border border-indigo-500/30">
                        {{ $user->roles->first()?->name ?? 'User' }}
                    </span>
                    <span class="px-2.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                        ACTIVE ACCOUNT
                    </span>
                    <span class="px-2.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-slate-800 text-slate-300 border border-slate-700">
                        Theme: {{ ucfirst($user->getThemePreference()) }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- 2. Profile Information Form --}}
    <div class="p-6 bg-slate-900 border border-slate-800 rounded-2xl shadow-sm space-y-4">
        <div class="pb-3 border-b border-slate-800">
            <h2 class="text-sm font-bold text-white uppercase tracking-wider">👤 Personal Information</h2>
            <p class="text-xs text-slate-400 mt-0.5">Update your display name, registered email address, and phone number.</p>
        </div>

        <form method="POST" action="{{ route('profile.update') }}" class="space-y-4">
            @csrf
            @method('PATCH')

            <div>
                <label for="name" class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1.5">Full Name</label>
                <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}" required
                       class="w-full p-2.5 rounded-xl bg-slate-950 border border-slate-800 text-sm text-white placeholder-slate-500 focus:border-indigo-500 focus:outline-none transition-colors">
                @error('name')
                    <p class="text-xs text-rose-400 mt-1 font-semibold">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="email" class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1.5">Email Address</label>
                <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}" required
                       class="w-full p-2.5 rounded-xl bg-slate-950 border border-slate-800 text-sm text-white placeholder-slate-500 focus:border-indigo-500 focus:outline-none transition-colors">
                @error('email')
                    <p class="text-xs text-rose-400 mt-1 font-semibold">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex justify-end pt-2">
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold shadow-lg shadow-indigo-600/30 transition-all cursor-pointer">
                    Save Profile Changes
                </button>
            </div>
        </form>
    </div>

    {{-- 3. Update Password / Security Credentials --}}
    <div class="p-6 bg-slate-900 border border-slate-800 rounded-2xl shadow-sm space-y-4">
        <div class="pb-3 border-b border-slate-800">
            <h2 class="text-sm font-bold text-white uppercase tracking-wider">🔒 Security &amp; Credentials</h2>
            <p class="text-xs text-slate-400 mt-0.5">Ensure your account uses a secure password to maintain platform safety.</p>
        </div>

        <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
            @csrf
            @method('PUT')

            <div>
                <label for="update_password_current_password" class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1.5">Current Password</label>
                <input type="password" id="update_password_current_password" name="current_password" required placeholder="••••••••"
                       class="w-full p-2.5 rounded-xl bg-slate-950 border border-slate-800 text-sm text-white placeholder-slate-500 focus:border-indigo-500 focus:outline-none transition-colors">
                @error('current_password', 'updatePassword')
                    <p class="text-xs text-rose-400 mt-1 font-semibold">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="update_password_password" class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1.5">New Password</label>
                <input type="password" id="update_password_password" name="password" required placeholder="••••••••"
                       class="w-full p-2.5 rounded-xl bg-slate-950 border border-slate-800 text-sm text-white placeholder-slate-500 focus:border-indigo-500 focus:outline-none transition-colors">
                @error('password', 'updatePassword')
                    <p class="text-xs text-rose-400 mt-1 font-semibold">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="update_password_password_confirmation" class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1.5">Confirm New Password</label>
                <input type="password" id="update_password_password_confirmation" name="password_confirmation" required placeholder="••••••••"
                       class="w-full p-2.5 rounded-xl bg-slate-950 border border-slate-800 text-sm text-white placeholder-slate-500 focus:border-indigo-500 focus:outline-none transition-colors">
                @error('password_confirmation', 'updatePassword')
                    <p class="text-xs text-rose-400 mt-1 font-semibold">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex justify-end pt-2">
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold shadow-lg shadow-indigo-600/30 transition-all cursor-pointer">
                    Update Security Password
                </button>
            </div>
        </form>
    </div>

</div>
@endsection
