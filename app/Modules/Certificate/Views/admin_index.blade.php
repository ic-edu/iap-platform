<x-admin-layout>
    <x-slot name="header">
        <h1 class="font-bold text-2xl text-white">
            {{ __('Official Certificate Registry') }}
        </h1>
        <p class="text-xs text-slate-400 mt-1">Manage, verify, download, reissue, and revoke issued digital certificates.</p>
    </x-slot>

    <div class="bg-slate-900 border border-slate-800 shadow-sm rounded-xl p-6">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-sm font-bold text-white">Certificates Database</h3>
            <form action="{{ route('admin.certificates.index') }}" method="GET" class="flex gap-2">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search certificate #..." class="rounded-lg border-slate-800 bg-slate-950 text-white text-xs px-3 py-1.5 focus:border-indigo-500 focus:outline-none">
                <button type="submit" class="px-4 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-xs font-semibold shadow">Filter</button>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-300">
                <thead class="bg-slate-950 text-slate-400 uppercase text-xs border-b border-slate-800">
                    <tr>
                        <th class="px-4 py-3">Cert Number</th>
                        <th class="px-4 py-3">Verification Code</th>
                        <th class="px-4 py-3">Candidate</th>
                        <th class="px-4 py-3">Test</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Issued Date</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @forelse($certificates as $cert)
                        <tr class="hover:bg-slate-950/50">
                            <td class="px-4 py-3 font-mono font-bold text-white">{{ $cert->certificate_number }}</td>
                            <td class="px-4 py-3 font-mono text-xs text-indigo-400">{{ $cert->verification_code }}</td>
                            <td class="px-4 py-3 font-semibold text-white">{{ $cert->user?->name }}</td>
                            <td class="px-4 py-3 text-xs">{{ $cert->attempt?->test?->title }}</td>
                            <td class="px-4 py-3">
                                <span class="px-2.5 py-0.5 rounded text-xs font-bold uppercase
                                    {{ $cert->status->value === 'valid' ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' : 'bg-rose-500/20 text-rose-400 border border-rose-500/30' }}">
                                    {{ $cert->status->label() }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-400">{{ $cert->issued_at?->format('Y-m-d') }}</td>
                            <td class="px-4 py-3 text-right space-x-2 text-xs">
                                <a href="{{ route('admin.certificates.download', $cert) }}" target="_blank" class="text-indigo-400 hover:underline font-semibold">Download</a>
                                @if($cert->status->value === 'valid')
                                    <form action="{{ route('admin.certificates.revoke', $cert) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit" class="text-rose-400 hover:underline font-semibold">Revoke</button>
                                    </form>
                                @else
                                    <form action="{{ route('admin.certificates.reissue', $cert) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit" class="text-emerald-400 hover:underline font-semibold">Reissue</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-slate-500 text-xs">No certificates found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $certificates->links() }}</div>
    </div>
</x-admin-layout>
