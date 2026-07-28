<x-admin-layout>
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-white flex items-center gap-2">
                <span>📋</span> System Audit &amp; Activity Logs
            </h1>
            <p class="text-xs text-slate-400 mt-1">Real-time audit trail recording authentication events, user lifecycle updates, security changes, and system activities.</p>
        </div>
    </div>

    <!-- Search & Filter Controls -->
    <div class="mb-6 p-4 bg-slate-900 border border-slate-800 rounded-xl">
        <form action="{{ route('admin.audit-logs.index') }}" method="GET" class="flex flex-col sm:flex-row gap-3">
            <div class="flex-1 relative">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by action, description, email, IP..." 
                       class="w-full pl-9 pr-4 py-2 bg-slate-950 border border-slate-800 rounded-lg text-xs text-white placeholder-slate-500 focus:border-indigo-500 focus:outline-none transition-colors">
                <svg class="w-4 h-4 text-slate-500 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
            </div>

            <select name="action" class="px-4 py-2 bg-slate-950 border border-slate-800 rounded-lg text-xs text-white focus:border-indigo-500 focus:outline-none">
                <option value="">All Event Actions</option>
                <option value="LOGIN" {{ request('action') === 'LOGIN' ? 'selected' : '' }}>LOGIN</option>
                <option value="LOGOUT" {{ request('action') === 'LOGOUT' ? 'selected' : '' }}>LOGOUT</option>
                <option value="LOGIN_FAILED" {{ request('action') === 'LOGIN_FAILED' ? 'selected' : '' }}>LOGIN_FAILED</option>
                <option value="USER_CREATED" {{ request('action') === 'USER_CREATED' ? 'selected' : '' }}>USER_CREATED</option>
                <option value="USER_UPDATED" {{ request('action') === 'USER_UPDATED' ? 'selected' : '' }}>USER_UPDATED</option>
                <option value="USER_ACTIVATED" {{ request('action') === 'USER_ACTIVATED' ? 'selected' : '' }}>USER_ACTIVATED</option>
                <option value="USER_DEACTIVATED" {{ request('action') === 'USER_DEACTIVATED' ? 'selected' : '' }}>USER_DEACTIVATED</option>
                <option value="PASSWORD_RESET" {{ request('action') === 'PASSWORD_RESET' ? 'selected' : '' }}>PASSWORD_RESET</option>
                <option value="ROLE_ASSIGNED" {{ request('action') === 'ROLE_ASSIGNED' ? 'selected' : '' }}>ROLE_ASSIGNED</option>
                <option value="ROLE_REMOVED" {{ request('action') === 'ROLE_REMOVED' ? 'selected' : '' }}>ROLE_REMOVED</option>
                <option value="USER_DELETED" {{ request('action') === 'USER_DELETED' ? 'selected' : '' }}>USER_DELETED</option>
            </select>

            <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-lg shadow transition-colors">
                Filter Logs
            </button>
            @if (request()->hasAny(['search', 'action']))
                <a href="{{ route('admin.audit-logs.index') }}" class="px-3 py-2 bg-slate-800 hover:bg-slate-700 text-slate-400 text-xs font-medium rounded-lg text-center transition-colors">
                    Reset
                </a>
            @endif
        </form>
    </div>

    <!-- Audit Logs Table -->
    <div class="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden shadow-sm mb-6">
        <table class="w-full text-left text-sm text-slate-300">
            <thead class="bg-slate-950 text-xs uppercase text-slate-400 border-b border-slate-800">
                <tr>
                    <th class="p-4">Log Ref</th>
                    <th class="p-4">User / Actor</th>
                    <th class="p-4">Action Event</th>
                    <th class="p-4">Description</th>
                    <th class="p-4">IP Address</th>
                    <th class="p-4 text-right">Timestamp</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60 font-mono text-xs">
                @forelse ($logs as $log)
                    @php
                        $actionStyle = match($log->action) {
                            'LOGIN', 'USER_ACTIVATED', 'USER_CREATED' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
                            'LOGOUT', 'USER_DEACTIVATED', 'USER_DELETED', 'LOGIN_FAILED' => 'bg-rose-500/10 text-rose-400 border-rose-500/20',
                            'PASSWORD_RESET', 'ROLE_ASSIGNED', 'ROLE_REMOVED' => 'bg-amber-500/10 text-amber-400 border-amber-500/20',
                            default => 'bg-indigo-500/10 text-indigo-400 border-indigo-500/20',
                        };
                    @endphp
                    <tr class="hover:bg-slate-950/40 transition-colors">
                        <td class="p-4 font-bold text-slate-500">LOG-{{ str_pad($log->id, 5, '0', STR_PAD_LEFT) }}</td>
                        <td class="p-4 font-semibold text-white">
                            {{ $log->user?->email ?? 'System / Guest' }}
                        </td>
                        <td class="p-4">
                            <span class="px-2 py-0.5 font-bold rounded border uppercase {{ $actionStyle }}">
                                {{ $log->action }}
                            </span>
                        </td>
                        <td class="p-4 text-slate-300 font-sans text-xs">{{ $log->description }}</td>
                        <td class="p-4 text-slate-400">{{ $log->ip_address ?? '127.0.0.1' }}</td>
                        <td class="p-4 text-right text-slate-400">{{ $log->created_at?->format('Y-m-d H:i:s') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="p-8 text-center text-slate-500 font-sans text-xs">No audit logs recorded yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $logs->links() }}</div>
</x-admin-layout>
