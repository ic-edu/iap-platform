<x-admin-layout>
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-white">Academic Curriculum &amp; Courses</h1>
            <p class="text-xs text-slate-400">Manage academic courses, categories, cohorts, and student enrollments.</p>
        </div>
        <button onclick="document.getElementById('create-course-modal').classList.remove('hidden')" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-lg shadow transition-colors">
            + Create New Course
        </button>
    </div>

    <!-- Metrics Bar -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="p-4 bg-slate-900 border border-slate-800 rounded-xl">
            <span class="text-xs text-slate-400 font-medium uppercase">Active Courses</span>
            <span class="text-2xl font-bold text-white mt-1 block">{{ $courses->total() }}</span>
        </div>
        <div class="p-4 bg-slate-900 border border-slate-800 rounded-xl">
            <span class="text-xs text-slate-400 font-medium uppercase">Course Categories</span>
            <span class="text-2xl font-bold text-indigo-400 mt-1 block">{{ $categories->count() }}</span>
        </div>
        <div class="p-4 bg-slate-900 border border-slate-800 rounded-xl">
            <span class="text-xs text-slate-400 font-medium uppercase">Total Enrollments</span>
            <span class="text-2xl font-bold text-emerald-400 mt-1 block">{{ $totalEnrollments }}</span>
        </div>
    </div>

    <!-- Status Alert -->
    @if (session('status'))
        <div class="mb-6 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-medium">
            ✅ {{ session('status') }}
        </div>
    @endif

    <!-- Courses Table -->
    <div class="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden shadow-sm mb-6">
        <table class="w-full text-left text-sm text-slate-300">
            <thead class="bg-slate-950 text-xs uppercase text-slate-400 border-b border-slate-800">
                <tr>
                    <th class="p-4">Course Code</th>
                    <th class="p-4">Title</th>
                    <th class="p-4">Category</th>
                    <th class="p-4">Enrollments</th>
                    <th class="p-4">Status</th>
                    <th class="p-4 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60">
                @forelse ($courses as $course)
                    <tr>
                        <td class="p-4 font-mono font-bold text-indigo-400 text-xs">{{ $course->code }}</td>
                        <td class="p-4 font-semibold text-white">{{ $course->title }}</td>
                        <td class="p-4 text-xs text-slate-400">{{ $course->category?->name ?? 'General Academic' }}</td>
                        <td class="p-4 text-xs font-bold text-white">{{ $course->enrollments->count() }} enrolled</td>
                        <td class="p-4">
                            <span class="px-2.5 py-0.5 text-xs font-bold rounded bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                ACTIVE
                            </span>
                        </td>
                        <td class="p-4 text-right">
                            @if (!Auth::user()?->hasRole('teacher'))
                                <form action="{{ route('admin.academic.courses.destroy', $course->id) }}" method="POST" class="inline" onsubmit="return confirm('Delete course {{ $course->title }}?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs text-rose-400 hover:underline">Delete</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="p-8 text-center text-slate-500">No academic courses registered yet. Click "+ Create New Course" to create one.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $courses->links() }}</div>

    <!-- Create Course Modal -->
    <div id="create-course-modal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm hidden flex items-center justify-center p-4 z-50">
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-6 max-w-md w-full shadow-2xl">
            <h2 class="text-lg font-bold text-white mb-4">Create Academic Course</h2>
            <form action="{{ route('admin.academic.courses.store') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">Course Code *</label>
                    <input type="text" name="code" required placeholder="e.g. ENG-101" class="w-full p-2.5 bg-slate-950 border border-slate-800 rounded-lg text-white text-xs uppercase font-mono">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">Course Title *</label>
                    <input type="text" name="title" required placeholder="e.g. TOEFL Academic Writing & Speaking" class="w-full p-2.5 bg-slate-950 border border-slate-800 rounded-lg text-white text-xs">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">Category</label>
                    <select name="category_id" class="w-full p-2.5 bg-slate-950 border border-slate-800 rounded-lg text-white text-xs">
                        <option value="">General Category</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">Description</label>
                    <textarea name="description" rows="3" class="w-full p-2.5 bg-slate-950 border border-slate-800 rounded-lg text-white text-xs" placeholder="Course syllabus overview..."></textarea>
                </div>
                <div class="flex justify-end gap-3 pt-4 border-t border-slate-800">
                    <button type="button" onclick="document.getElementById('create-course-modal').classList.add('hidden')" class="px-4 py-2 bg-slate-800 text-slate-300 text-xs rounded-lg">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white font-semibold text-xs rounded-lg shadow">Save Course</button>
                </div>
            </form>
        </div>
    </div>
</x-admin-layout>
