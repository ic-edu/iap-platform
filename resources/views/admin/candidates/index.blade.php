@extends('layouts.admin')

@section('content')
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Candidate Management Workspace</h1>
            <p class="text-xs text-slate-500 dark:text-slate-400">Manage registered candidate and student accounts, verify payment eligibility, and monitor testing access.</p>
        </div>
        <button onclick="document.getElementById('create-candidate-modal').classList.remove('hidden')" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-lg shadow transition-colors flex items-center gap-1.5">
            <span>+ Register Candidate</span>
        </button>
    </div>

    <!-- System Feedback Alerts -->
    @if (session('status'))
        <div class="mb-6 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-400 text-xs font-medium flex items-center gap-2">
            <span>✅</span> {{ session('status') }}
        </div>
    @endif
    @if (session('error'))
        <div class="mb-6 p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-600 dark:text-rose-400 text-xs font-medium flex items-center gap-2">
            <span>⚠️</span> {{ session('error') }}
        </div>
    @endif

    <!-- Search & Filter Controls -->
    <div class="mb-6 p-4 bg-slate-950/80 border border-slate-800 rounded-xl">
        <form action="{{ route('admin.candidates.index') }}" method="GET" class="flex flex-col sm:flex-row gap-3">
            <div class="flex-1 relative">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by candidate name, email address..." 
                       class="w-full pl-9 pr-4 py-2 bg-slate-900 border border-slate-800 rounded-lg text-xs text-slate-900 dark:text-white placeholder-slate-500 focus:border-indigo-500 focus:outline-none transition-colors">
                <svg class="w-4 h-4 text-slate-500 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
            </div>

            <select name="status" class="px-4 py-2 bg-slate-900 border border-slate-800 rounded-lg text-xs text-slate-900 dark:text-white focus:border-indigo-500 focus:outline-none">
                <option value="">All Account Statuses</option>
                <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
            </select>

            <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-lg shadow transition-colors">
                Apply Filters
            </button>
            @if (request()->hasAny(['search', 'status', 'filter']))
                <a href="{{ route('admin.candidates.index') }}" class="px-3 py-2 bg-slate-800 hover:bg-slate-700 text-slate-400 text-xs font-medium rounded-lg text-center transition-colors">
                    Reset
                </a>
            @endif
        </form>
    </div>

    @if(request('filter') === 'paid-eligible' || request('filter') === 'eligible')
    <div class="mb-6 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-400 text-xs font-medium flex items-center justify-between shadow-sm">
        <span class="flex items-center gap-2">
            <span>💳</span>
            <span>Filtered: <strong>Paid &amp; Eligible Candidates</strong> (Candidates with confirmed paid transactions)</span>
        </span>
        <a href="{{ route('admin.candidates.index') }}" class="px-2.5 py-1 rounded bg-slate-800 hover:bg-slate-700 text-slate-300 text-[11px] font-semibold border border-slate-700 transition-colors">
            Clear Filter ✕
        </a>
    </div>
    @endif

    <!-- Candidates Table -->
    <div class="bg-slate-950/80 border border-slate-800 rounded-xl overflow-hidden shadow-sm mb-6">
        <table class="w-full text-left text-sm text-slate-300">
            <thead class="bg-slate-900 text-xs uppercase text-slate-400 border-b border-slate-800">
                <tr>
                    <th class="p-4">Candidate Details</th>
                    <th class="p-4">Email</th>
                    <th class="p-4">Role</th>
                    <th class="p-4">Status</th>
                    <th class="p-4">Registered Date</th>
                    <th class="p-4 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60">
                @forelse ($candidates as $candidate)
                    <tr class="hover:bg-slate-900/40 transition-colors">
                        <td class="p-4">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-lg bg-indigo-600/20 border border-indigo-500/30 text-indigo-400 font-bold text-xs flex items-center justify-center flex-shrink-0">
                                    {{ strtoupper(substr($candidate->name, 0, 2)) }}
                                </div>
                                <div>
                                    <span class="block font-semibold text-slate-900 dark:text-white text-xs">{{ $candidate->name }}</span>
                                    @if ($candidate->phone_number)
                                        <span class="block text-[10px] text-slate-500">{{ $candidate->phone_number }}</span>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="p-4 text-slate-600 dark:text-slate-300 text-xs font-mono">{{ $candidate->email }}</td>
                        <td class="p-4">
                            <x-role-badge role="student" />
                        </td>
                        <td class="p-4">
                            @if ($candidate->status === 'inactive')
                                <span class="px-2.5 py-0.5 text-[10px] font-bold rounded bg-rose-500/10 text-rose-500 dark:text-rose-400 border border-rose-500/20 uppercase">
                                    INACTIVE
                                </span>
                            @else
                                <span class="px-2.5 py-0.5 text-[10px] font-bold rounded bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 uppercase">
                                    ACTIVE
                                </span>
                            @endif
                        </td>
                        <td class="p-4 text-slate-500 dark:text-slate-400 text-xs">{{ $candidate->created_at?->format('Y-m-d') }}</td>
                        <td class="p-4 text-right">
                            <div class="flex items-center justify-end gap-2 text-xs">
                                <!-- View Details -->
                                <button type="button" 
                                        onclick='openViewCandidateModal({{ json_encode($candidate) }})'
                                        class="px-2 py-1 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded transition-colors" title="View Details">
                                    👁 View
                                </button>

                                <!-- Edit Candidate -->
                                <button type="button" 
                                        onclick='openEditCandidateModal({{ json_encode($candidate) }})'
                                        class="px-2 py-1 bg-indigo-600/20 hover:bg-indigo-600/30 text-indigo-400 border border-indigo-500/30 rounded transition-colors" title="Edit Candidate">
                                    ✏ Edit
                                </button>

                                <!-- Reset Password -->
                                <button type="button" 
                                        onclick='openResetCandidatePasswordModal({{ json_encode($candidate) }})'
                                        class="px-2 py-1 bg-amber-500/20 hover:bg-amber-500/30 text-amber-400 border border-amber-500/30 rounded transition-colors" title="Reset Password">
                                    🔑 Reset
                                </button>

                                <!-- Toggle Status -->
                                <form action="{{ route('admin.users.toggle-status', $candidate->id) }}" method="POST" class="inline" 
                                      onsubmit="event.preventDefault(); iapConfirm({ title: '{{ $candidate->status === 'inactive' ? 'Activate Candidate Account?' : 'Deactivate Candidate Account?' }}', message: 'Are you sure you want to {{ $candidate->status === 'inactive' ? 'ACTIVATE' : 'DEACTIVATE' }} {{ addslashes($candidate->name) }}?', confirmText: '{{ $candidate->status === 'inactive' ? 'Activate Candidate' : 'Deactivate Candidate' }}', variant: '{{ $candidate->status === 'inactive' ? 'success' : 'warning' }}', form: this });">
                                    @csrf
                                    <button type="submit" class="px-2 py-1 {{ $candidate->status === 'inactive' ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 hover:bg-emerald-500/30' : 'bg-rose-500/20 text-rose-400 border border-rose-500/30 hover:bg-rose-500/30' }} rounded transition-colors">
                                        {{ $candidate->status === 'inactive' ? '⚡ Activate' : '⏸ Deactivate' }}
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="p-8 text-center text-slate-500 text-xs">No registered candidates match your criteria.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $candidates->links() }}</div>

    <!-- Create Candidate Modal -->
    <div id="create-candidate-modal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm hidden flex items-center justify-center p-4 z-50">
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-6 max-w-md w-full shadow-2xl">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-base font-bold text-white">Register New Candidate</h2>
                <button type="button" onclick="document.getElementById('create-candidate-modal').classList.add('hidden')" class="text-slate-400 hover:text-white">&times;</button>
            </div>
            <form action="{{ route('admin.candidates.store') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">Candidate Full Name *</label>
                    <input type="text" name="name" required class="w-full p-2.5 bg-slate-950 border border-slate-800 rounded-lg text-white text-xs focus:border-indigo-500 focus:outline-none" placeholder="Jane Doe">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">Email Address *</label>
                    <input type="email" name="email" required class="w-full p-2.5 bg-slate-950 border border-slate-800 rounded-lg text-white text-xs focus:border-indigo-500 focus:outline-none" placeholder="jane@example.com">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">Initial Password *</label>
                    <input type="password" name="password" required minlength="8" class="w-full p-2.5 bg-slate-950 border border-slate-800 rounded-lg text-white text-xs focus:border-indigo-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">Phone Number (Optional)</label>
                    <input type="text" name="phone_number" class="w-full p-2.5 bg-slate-950 border border-slate-800 rounded-lg text-white text-xs focus:border-indigo-500 focus:outline-none" placeholder="+628123456789">
                </div>
                <div class="p-3 bg-indigo-500/10 border border-indigo-500/20 rounded-lg text-[11px] text-indigo-300">
                    💡 <strong>Candidate Registration:</strong> Registered candidate accounts are activated immediately upon creation and assigned the student role.
                </div>
                <div class="flex justify-end gap-3 pt-4 border-t border-slate-800">
                    <button type="button" onclick="document.getElementById('create-candidate-modal').classList.add('hidden')" class="px-4 py-2 bg-slate-800 text-slate-300 text-xs rounded-lg">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs rounded-lg shadow">Register Candidate</button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Candidate Modal -->
    <div id="view-candidate-modal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm hidden flex items-center justify-center p-4 z-50">
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-6 max-w-md w-full shadow-2xl">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-base font-bold text-white flex items-center gap-2">
                    <span>👁 Candidate Context &amp; Details</span>
                </h2>
                <button type="button" onclick="document.getElementById('view-candidate-modal').classList.add('hidden')" class="text-slate-400 hover:text-white">&times;</button>
            </div>
            
            <div class="space-y-3 text-xs bg-slate-950 p-4 rounded-xl border border-slate-800 mb-4">
                <div class="flex justify-between py-1 border-b border-slate-800">
                    <span class="text-slate-400 font-medium">Candidate Name</span>
                    <span id="view-candidate-name" class="font-bold text-white"></span>
                </div>
                <div class="flex justify-between py-1 border-b border-slate-800">
                    <span class="text-slate-400 font-medium">Email Address</span>
                    <span id="view-candidate-email" class="font-mono text-indigo-400"></span>
                </div>
                <div class="flex justify-between py-1 border-b border-slate-800">
                    <span class="text-slate-400 font-medium">Role</span>
                    <span class="font-bold uppercase text-indigo-400">STUDENT / CANDIDATE</span>
                </div>
                <div class="flex justify-between py-1 border-b border-slate-800">
                    <span class="text-slate-400 font-medium">Account Status</span>
                    <span id="view-candidate-status" class="font-bold uppercase"></span>
                </div>
                <div class="flex justify-between py-1 border-b border-slate-800">
                    <span class="text-slate-400 font-medium">Phone Number</span>
                    <span id="view-candidate-phone" class="text-slate-300"></span>
                </div>
                <div class="flex justify-between py-1">
                    <span class="text-slate-400 font-medium">Registration Date</span>
                    <span id="view-candidate-created" class="text-slate-400"></span>
                </div>
            </div>
            
            <div class="flex justify-end">
                <button type="button" onclick="document.getElementById('view-candidate-modal').classList.add('hidden')" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs rounded-lg">Close</button>
            </div>
        </div>
    </div>

    <!-- Edit Candidate Modal -->
    <div id="edit-candidate-modal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm hidden flex items-center justify-center p-4 z-50">
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-6 max-w-md w-full shadow-2xl">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-base font-bold text-white">Edit Candidate Profile</h2>
                <button type="button" onclick="document.getElementById('edit-candidate-modal').classList.add('hidden')" class="text-slate-400 hover:text-white">&times;</button>
            </div>
            <form id="edit-candidate-form" method="POST" class="space-y-4">
                @csrf
                @method('PUT')
                <input type="hidden" name="role" value="student">
                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">Full Name *</label>
                    <input type="text" id="edit-candidate-name" name="name" required class="w-full p-2.5 bg-slate-950 border border-slate-800 rounded-lg text-white text-xs focus:border-indigo-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">Email Address *</label>
                    <input type="email" id="edit-candidate-email" name="email" required class="w-full p-2.5 bg-slate-950 border border-slate-800 rounded-lg text-white text-xs focus:border-indigo-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">Phone Number</label>
                    <input type="text" id="edit-candidate-phone" name="phone_number" class="w-full p-2.5 bg-slate-950 border border-slate-800 rounded-lg text-white text-xs focus:border-indigo-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">Account Status *</label>
                    <select id="edit-candidate-status" name="status" required class="w-full p-2.5 bg-slate-950 border border-slate-800 rounded-lg text-white text-xs focus:border-indigo-500 focus:outline-none">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <div class="flex justify-end gap-3 pt-4 border-t border-slate-800">
                    <button type="button" onclick="document.getElementById('edit-candidate-modal').classList.add('hidden')" class="px-4 py-2 bg-slate-800 text-slate-300 text-xs rounded-lg">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs rounded-lg shadow">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Reset Password Modal -->
    <div id="reset-candidate-password-modal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm hidden flex items-center justify-center p-4 z-50">
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-6 max-w-md w-full shadow-2xl">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-base font-bold text-white flex items-center gap-2">
                    <span>🔑 Reset Candidate Password</span>
                </h2>
                <button type="button" onclick="document.getElementById('reset-candidate-password-modal').classList.add('hidden')" class="text-slate-400 hover:text-white">&times;</button>
            </div>
            <p class="text-xs text-slate-400 mb-4">
                Set a new password for candidate <strong id="reset-candidate-name" class="text-white"></strong> (<span id="reset-candidate-email" class="text-indigo-400 font-mono"></span>).
            </p>
            <form id="reset-candidate-password-form" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">New Password *</label>
                    <input type="password" name="password" required minlength="8" class="w-full p-2.5 bg-slate-950 border border-slate-800 rounded-lg text-white text-xs focus:border-indigo-500 focus:outline-none" placeholder="Minimum 8 characters">
                </div>
                <div class="flex justify-end gap-3 pt-4 border-t border-slate-800">
                    <button type="button" onclick="document.getElementById('reset-candidate-password-modal').classList.add('hidden')" class="px-4 py-2 bg-slate-800 text-slate-300 text-xs rounded-lg">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-amber-600 hover:bg-amber-500 text-white font-semibold text-xs rounded-lg shadow">Confirm Reset</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openViewCandidateModal(candidate) {
            document.getElementById('view-candidate-name').textContent = candidate.name || 'N/A';
            document.getElementById('view-candidate-email').textContent = candidate.email || 'N/A';
            document.getElementById('view-candidate-status').textContent = candidate.status || 'N/A';
            document.getElementById('view-candidate-status').className = 'font-bold uppercase ' + (candidate.status === 'active' ? 'text-emerald-400' : 'text-rose-400');
            document.getElementById('view-candidate-phone').textContent = candidate.phone_number || 'None provided';
            document.getElementById('view-candidate-created').textContent = candidate.created_at ? new Date(candidate.created_at).toLocaleDateString() : 'N/A';
            document.getElementById('view-candidate-modal').classList.remove('hidden');
        }

        function openEditCandidateModal(candidate) {
            document.getElementById('edit-candidate-form').action = '/admin/users/' + candidate.id;
            document.getElementById('edit-candidate-name').value = candidate.name || '';
            document.getElementById('edit-candidate-email').value = candidate.email || '';
            document.getElementById('edit-candidate-phone').value = candidate.phone_number || '';
            document.getElementById('edit-candidate-status').value = candidate.status || 'active';
            document.getElementById('edit-candidate-modal').classList.remove('hidden');
        }

        function openResetCandidatePasswordModal(candidate) {
            document.getElementById('reset-candidate-password-form').action = '/admin/users/' + candidate.id + '/reset-password';
            document.getElementById('reset-candidate-name').textContent = candidate.name;
            document.getElementById('reset-candidate-email').textContent = candidate.email;
            document.getElementById('reset-candidate-password-modal').classList.remove('hidden');
        }
    </script>
@endsection
