<x-candidate-layout>
    <div class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-white flex items-center gap-2">
                <span>🎓</span> My Digital Certificates
            </h1>
            <p class="text-sm text-slate-400 mt-1">
                View, download, and verify all official digital certificates issued for your passed assessment attempts.
            </p>
        </div>
        <div>
            <a href="{{ route('candidate.available-tests') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 font-semibold text-xs transition-colors border border-slate-700">
                Take New Assessment &rarr;
            </a>
        </div>
    </div>

    <!-- Certificates Grid / List -->
    @if($certificates->isEmpty())
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-12 text-center my-6">
            <div class="w-16 h-16 rounded-full bg-slate-800 border border-slate-700 flex items-center justify-center mx-auto mb-4 text-2xl text-slate-400">
                🎓
            </div>
            <h3 class="text-lg font-bold text-white mb-1">No Certificates Issued Yet</h3>
            <p class="text-sm text-slate-400 max-w-md mx-auto mb-6">
                You haven't earned any digital certificates yet. Complete and pass an assessment to automatically receive your verified digital certificate.
            </p>
            <a href="{{ route('candidate.available-tests') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-sm transition-colors shadow-md shadow-indigo-600/30">
                Browse Available Tests
            </a>
        </div>
    @else
        <div class="space-y-4 mb-8">
            @foreach($certificates as $certificate)
                <div class="bg-slate-900 border border-slate-800 hover:border-slate-700 rounded-xl p-6 shadow-sm transition-all flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
                    <div class="flex items-start gap-4">
                        <div class="w-12 h-12 rounded-xl bg-indigo-950/60 border border-indigo-500/30 flex items-center justify-center text-xl shrink-0">
                            📜
                        </div>
                        <div>
                            <div class="flex items-center gap-3 flex-wrap">
                                <span class="font-mono text-xs font-bold text-indigo-400 bg-indigo-500/10 border border-indigo-500/20 px-2.5 py-0.5 rounded-md">
                                    #{{ $certificate->certificate_number }}
                                </span>
                                @if(($certificate->status->value ?? 'issued') === 'issued')
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                                        ISSUED &amp; VALID
                                    </span>
                                @elseif(($certificate->status->value ?? '') === 'revoked')
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-500/10 text-rose-400 border border-rose-500/20">
                                        REVOKED
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-500/10 text-amber-400 border border-amber-500/20">
                                        EXPIRED
                                    </span>
                                @endif
                            </div>

                            <h3 class="text-lg font-bold text-white mt-2">
                                {{ $certificate->attempt?->test?->title ?? 'Assessment Certification' }}
                            </h3>

                            <div class="flex items-center gap-6 mt-2 text-xs text-slate-400 flex-wrap">
                                <div>
                                    Issued Date: <span class="font-medium text-slate-200">{{ $certificate->issued_at?->format('d M Y') ?? 'N/A' }}</span>
                                </div>
                                <div>
                                    Expiry Date: <span class="font-medium text-slate-200">{{ $certificate->expires_at?->format('d M Y') ?? 'No Expiry' }}</span>
                                </div>
                                <div>
                                    Verification Code: <span class="font-mono text-slate-300">{{ $certificate->verification_code }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex items-center gap-3 shrink-0 pt-4 lg:pt-0 border-t lg:border-t-0 border-slate-800">
                        <a href="{{ route('public.verify.code', $certificate->verification_code) }}" target="_blank" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 font-semibold text-xs transition-colors border border-slate-700">
                            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                            Verify Online
                        </a>
                        <a href="{{ route('candidate.certificates.download', $certificate->id) }}" target="_blank" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs transition-colors shadow-md shadow-indigo-600/20">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                            Download PDF
                        </a>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Pagination -->
        <div class="mt-6">
            {{ $certificates->links() }}
        </div>
    @endif
</x-candidate-layout>
