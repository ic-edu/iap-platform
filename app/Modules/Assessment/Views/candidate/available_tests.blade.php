<x-candidate-layout>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-white">Available CBT Tests</h1>
        <p class="text-sm text-slate-400">Select an assessment test to begin your session.</p>
    </div>

    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($tests as $test)
            <div class="bg-slate-900 border border-slate-800 rounded-xl p-6 flex flex-col justify-between shadow-sm hover:border-slate-700 transition-colors">
                <div>
                    <span class="px-2.5 py-1 text-xs font-semibold rounded bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 uppercase tracking-wider">
                        {{ is_object($test->test_type) ? $test->test_type->label() : strtoupper($test->test_type) }}
                    </span>
                    <h3 class="text-lg font-bold text-white mt-3">{{ $test->title }}</h3>
                    <div class="mt-4 space-y-1.5 text-xs text-slate-400">
                        <div class="flex justify-between">
                            <span>Duration:</span>
                            <span class="font-medium text-slate-200">{{ $test->duration_minutes }} Minutes</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Passing Score:</span>
                            <span class="font-medium text-slate-200">{{ $test->pass_score }} Points</span>
                        </div>
                    </div>
                </div>

                <div class="mt-6 pt-4 border-t border-slate-800/80">
                    @if(!empty($test->instructions))
                        <a href="{{ route('candidate.tests.instructions', $test) }}" class="block text-center w-full bg-indigo-600 hover:bg-indigo-500 text-white font-semibold py-2 rounded-lg text-sm transition-colors shadow-sm">
                            View Instructions &amp; Start &rarr;
                        </a>
                    @else
                        <form method="POST" action="{{ route('candidate.tests.start', $test) }}">
                            @csrf
                            <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-500 text-white font-semibold py-2 rounded-lg text-sm transition-colors shadow-sm">
                                Start Assessment Test
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        @empty
            <div class="col-span-full bg-slate-900 border border-slate-800 rounded-xl p-8 text-center text-slate-500">
                No active published tests available at the moment.
            </div>
        @endforelse
    </div>

    <div class="mt-6">
        {{ $tests->links() }}
    </div>
</x-candidate-layout>
