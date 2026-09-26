<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate Verification — iC.edu</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style> body { font-family: 'Inter', sans-serif; } </style>
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen flex flex-col justify-between">
    <header class="border-b border-slate-800 bg-slate-950/80 backdrop-blur py-4 px-6">
        <div class="max-w-5xl mx-auto flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="h-9 w-9 rounded-xl bg-indigo-600 flex items-center justify-center font-bold text-white shadow-lg shadow-indigo-500/30">iC</div>
                <span class="font-bold text-lg text-white tracking-wide">iC.edu Certificate Verification</span>
            </div>
            <a href="/" class="text-sm text-slate-400 hover:text-white transition-colors">Return to Home</a>
        </div>
    </header>

    <main class="max-w-2xl mx-auto w-full px-6 py-12">
        <div class="bg-slate-800/80 border border-slate-700/80 rounded-2xl p-8 shadow-2xl backdrop-blur">
            <div class="text-center mb-8">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-indigo-500/10 text-indigo-400 mb-4 border border-indigo-500/20">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                </div>
                <h1 class="text-2xl font-bold text-white">Verify Certificate Authenticity</h1>
                <p class="text-slate-400 text-sm mt-1">Enter Certificate Number or Verification Code below</p>
            </div>

            <form action="{{ route('public.verify') }}" method="GET" class="space-y-4">
                <div>
                    <input type="text" name="code" value="{{ $code }}" placeholder="e.g. CERT-20260726-ABCD or VRF-XXXX-YYYY" required
                           class="w-full px-4 py-3 bg-slate-900 border border-slate-700 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 text-center font-mono">
                </div>
                <button type="submit" class="w-full py-3.5 px-4 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold rounded-xl shadow-lg shadow-indigo-600/30 transition-all">
                    Verify Authenticity
                </button>
            </form>

            @if($result)
                <div class="mt-8 pt-8 border-t border-slate-700/80">
                    @if($result['status'] === 'valid')
                        <div class="p-4 bg-emerald-500/10 border border-emerald-500/20 rounded-xl text-center">
                            <span class="inline-block px-3 py-1 bg-emerald-500/20 text-emerald-400 rounded-full text-xs font-semibold uppercase mb-2">Valid & Authentic</span>
                            <h3 class="text-xl font-bold text-white">{{ $result['certificate']->user?->name }}</h3>
                            <p class="text-slate-300 text-sm mt-1">{{ $result['certificate']->attempt?->test?->title }}</p>
                            <p class="text-xs text-slate-400 mt-2">Issued: {{ $result['certificate']->issued_at?->format('F d, Y') }} | Ref: {{ $result['certificate']->certificate_number }}</p>
                        </div>
                    @elseif($result['status'] === 'revoked')
                        <div class="p-4 bg-rose-500/10 border border-rose-500/20 rounded-xl text-center">
                            <span class="inline-block px-3 py-1 bg-rose-500/20 text-rose-400 rounded-full text-xs font-semibold uppercase mb-2">Revoked Certificate</span>
                            <p class="text-rose-300 text-sm font-medium">{{ $result['message'] }}</p>
                        </div>
                    @elseif($result['status'] === 'expired')
                        <div class="p-4 bg-amber-500/10 border border-amber-500/20 rounded-xl text-center">
                            <span class="inline-block px-3 py-1 bg-amber-500/20 text-amber-400 rounded-full text-xs font-semibold uppercase mb-2">Expired</span>
                            <p class="text-amber-300 text-sm font-medium">{{ $result['message'] }}</p>
                        </div>
                    @else
                        <div class="p-4 bg-slate-900 border border-slate-700 rounded-xl text-center">
                            <span class="inline-block px-3 py-1 bg-slate-800 text-slate-400 rounded-full text-xs font-semibold uppercase mb-2">Not Found</span>
                            <p class="text-slate-400 text-sm">{{ $result['message'] }}</p>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </main>

    <footer class="border-t border-slate-800 py-6 text-center text-xs text-slate-500">
        &copy; {{ date('Y') }} iC.edu Assessment Platform (IAP). All rights reserved.
    </footer>
</body>
</html>
