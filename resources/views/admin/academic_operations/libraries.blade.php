@extends('layouts.admin')

@section('title', 'Academic Libraries Monitoring — Academic Operations')

@section('content')
<div style="display:flex;flex-direction:column;gap:1.5rem;">

    {{-- Header --}}
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;">
        <div>
            <h1 style="font-size:1.5rem;font-weight:800;color:#fff;margin:0 0 .25rem;">📚 Academic Libraries Monitoring</h1>
            <p style="font-size:.85rem;color:#94a3b8;margin:0;">Institutional read-only repository monitoring for TOEFL, TOEIC, IELTS, and Foundational libraries. (Admin monitors repository volume and health; content editing is performed in Teacher Workspace).</p>
        </div>
    </div>

    {{-- Monitoring Cards Grid --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(280px, 1fr));gap:1.25rem;">
        @foreach($monitoringData as $key => $lib)
        <div style="background:#0f172a;border:1px solid #1e293b;border-radius:1.25rem;padding:1.5rem;display:flex;flex-direction:column;justify-space:between;gap:1.1rem;">
            <div>
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <span style="font-size:1.1rem;font-weight:800;color:#f1f5f9;">{{ $lib['name'] }}</span>
                    <span style="padding:.2rem .65rem;border-radius:99px;font-size:.65rem;font-weight:900;background:rgba(52,211,153,.15);color:#34d399;border:1px solid rgba(52,211,153,.3);">
                        HEALTH {{ $lib['health_score'] }}%
                    </span>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-top:1.25rem;padding-top:1rem;border-top:1px solid #1e293b;">
                    <div>
                        <div style="font-size:.68rem;color:#64748b;font-weight:700;text-transform:uppercase;">Question Count</div>
                        <div style="font-size:1.5rem;font-weight:900;color:#818cf8;margin-top:2px;">{{ $lib['questions_count'] }}</div>
                    </div>
                    <div>
                        <div style="font-size:.68rem;color:#64748b;font-weight:700;text-transform:uppercase;">Media Assets</div>
                        <div style="font-size:1.5rem;font-weight:900;color:#34d399;margin-top:2px;">{{ $lib['media_count'] }}</div>
                    </div>
                </div>

                <div style="display:grid;grid-template-columns:repeat(3, 1fr);gap:.5rem;margin-top:1rem;background:#080f1d;padding:.75rem;border-radius:.75rem;border:1px solid #1e293b;">
                    <div style="text-align:center;">
                        <div style="font-size:.65rem;color:#64748b;">Draft</div>
                        <div style="font-size:.9rem;font-weight:800;color:#fbbf24;">{{ $lib['draft_count'] }}</div>
                    </div>
                    <div style="text-align:center;">
                        <div style="font-size:.65rem;color:#64748b;">Published</div>
                        <div style="font-size:.9rem;font-weight:800;color:#34d399;">{{ $lib['published_count'] }}</div>
                    </div>
                    <div style="text-align:center;">
                        <div style="font-size:.65rem;color:#64748b;">Archived</div>
                        <div style="font-size:.9rem;font-weight:800;color:#64748b;">{{ $lib['archived_count'] }}</div>
                    </div>
                </div>
            </div>

            <a href="{{ route('admin.publications.question-banks') }}" style="display:block;text-align:center;padding:.6rem;background:#1e293b;color:#cbd5e1;border-radius:.6rem;font-size:.78rem;font-weight:700;text-decoration:none;transition:background .15s;">
                View Publication Queues →
            </a>
        </div>
        @endforeach
    </div>

</div>
@endsection
