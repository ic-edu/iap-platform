<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Certificates Management') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">Official Certificate Registry</h3>
                    <form action="{{ route('admin.certificates.index') }}" method="GET" class="flex gap-2">
                        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search certificate #..." class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 text-sm">
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm font-semibold">Filter</button>
                    </form>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-500 dark:text-gray-400">
                        <thead class="bg-gray-50 dark:bg-gray-700 text-gray-700 dark:text-gray-300 uppercase text-xs">
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
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse($certificates as $cert)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-750">
                                    <td class="px-4 py-3 font-mono font-bold text-gray-900 dark:text-white">{{ $cert->certificate_number }}</td>
                                    <td class="px-4 py-3 font-mono text-xs text-indigo-400">{{ $cert->verification_code }}</td>
                                    <td class="px-4 py-3 font-semibold">{{ $cert->user?->name }}</td>
                                    <td class="px-4 py-3">{{ $cert->attempt?->test?->title }}</td>
                                    <td class="px-4 py-3">
                                        <span class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase
                                            {{ $cert->status->value === 'valid' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                            {{ $cert->status->label() }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">{{ $cert->issued_at?->format('Y-m-d') }}</td>
                                    <td class="px-4 py-3 text-right space-x-2">
                                        <a href="{{ route('admin.certificates.download', $cert) }}" target="_blank" class="text-indigo-600 hover:underline">Download</a>
                                        @if($cert->status->value === 'valid')
                                            <form action="{{ route('admin.certificates.revoke', $cert) }}" method="POST" class="inline">
                                                @csrf
                                                <button type="submit" class="text-red-600 hover:underline">Revoke</button>
                                            </form>
                                        @else
                                            <form action="{{ route('admin.certificates.reissue', $cert) }}" method="POST" class="inline">
                                                @csrf
                                                <button type="submit" class="text-emerald-600 hover:underline">Reissue</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-8 text-center text-gray-500">No certificates found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">{{ $certificates->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
