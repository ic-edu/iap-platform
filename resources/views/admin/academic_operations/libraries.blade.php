@extends('layouts.admin')

@section('title', 'Academic Libraries Monitoring — Academic Operations')

@section('content')
<div class="space-y-6">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-white">📚 Academic Libraries Monitoring</h1>
            <p class="text-xs text-slate-400">Institutional read-only repository monitoring for TOEFL, TOEIC, IELTS, and Foundational libraries. (Admin monitors repository volume and health; content editing is performed in Teacher Workspace).</p>
        </div>
    </div>

    {{-- Monitoring Cards Grid --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5">
        @foreach($monitoringData as $key => $lib)
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 flex flex-col justify-between gap-4 shadow-sm">
            <div class="space-y-4">
                <div class="flex justify-between items-center">
                    <span class="text-base font-bold text-white">{{ $lib['name'] }}</span>
                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                        HEALTH {{ $lib['health_score'] }}%
                    </span>
                </div>

                <div class="grid grid-cols-2 gap-3 pt-3 border-t border-slate-800">
                    <div>
                        <div class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Question Count</div>
                        <div class="text-2xl font-black text-indigo-400 mt-0.5">{{ $lib['questions_count'] }}</div>
                    </div>
                    <div>
                        <div class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Media Assets</div>
                        <div class="text-2xl font-black text-emerald-400 mt-0.5">{{ $lib['media_count'] }}</div>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-2 bg-slate-950/60 p-3 rounded-xl border border-slate-800 text-center">
                    <div>
                        <div class="text-[10px] text-slate-400">Draft</div>
                        <div class="text-sm font-bold text-amber-400">{{ $lib['draft_count'] }}</div>
                    </div>
                    <div>
                        <div class="text-[10px] text-slate-400">Published</div>
                        <div class="text-sm font-bold text-emerald-400">{{ $lib['published_count'] }}</div>
                    </div>
                    <div>
                        <div class="text-[10px] text-slate-400">Archived</div>
                        <div class="text-sm font-bold text-slate-400">{{ $lib['archived_count'] }}</div>
                    </div>
                </div>
            </div>

            @if(Auth::user()?->hasAnyRole(['repository-manager', 'super-admin']))
            <a href="{{ route('admin.publications.question-banks') }}" class="block text-center py-2 px-3 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold rounded-xl transition-colors">
                View Publication Queues →
            </a>
            @endif
        </div>
        @endforeach
    </div>

</div>
@endsection
