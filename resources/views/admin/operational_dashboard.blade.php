@extends('layouts.admin')

@section('title', 'Admin — Operational Dashboard')

@section('content')
<div class="operational-dashboard" x-data="operationalDashboard()">

    {{-- Page Header --}}
    <div class="page-header">
        <div class="page-header__inner">
            <div class="page-header__meta">
                <span class="page-badge page-badge--ops">OPS CENTER</span>
                <h1 class="page-title">Operational Dashboard</h1>
                <p class="page-subtitle">Publication Queue &amp; Task Center — {{ now()->format('l, d F Y') }}</p>
            </div>
            <div class="page-header__actions">
                @if($unreadNotificationsCount > 0)
                <a href="{{ route('notifications.index') }}" class="notif-alert">
                    <span class="notif-alert__icon">🔔</span>
                    <span class="notif-alert__badge">{{ $unreadNotificationsCount }}</span>
                    <span class="notif-alert__label">{{ $unreadNotificationsCount }} Unread</span>
                </a>
                @endif
                <a href="{{ route('admin.publications.question-banks') }}" class="btn btn--primary">
                    <svg xmlns="http://www.w3.org/2000/svg" class="btn-icon" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zM14 9a1 1 0 00-1 1v6a1 1 0 001 1h2a1 1 0 001-1v-6a1 1 0 00-1-1h-2z"/>
                    </svg>
                    Publication Queues
                </a>
            </div>
        </div>
    </div>

    @if(session('status'))
    <div class="alert alert--success" x-data="{ show: true }" x-show="show" x-transition>
        <span>✅ {{ session('status') }}</span>
        <button @click="show = false" class="alert__close">×</button>
    </div>
    @endif

    {{-- Section 5: Operational Counters --}}
    <section class="ops-stats">
        <h2 class="section-label">Live Workflow Counters</h2>
        <div class="stats-grid stats-grid--6">

            {{-- Card 1: Ready to Publish — Question Banks --}}
            <a href="{{ route('admin.publications.question-banks', ['status' => 'approved']) }}" class="stat-card stat-card--action stat-card--amber">
                <div class="stat-card__icon">📂</div>
                <div class="stat-card__body">
                    <p class="stat-card__value">{{ $readyToPublishQuestionBanks }}</p>
                    <p class="stat-card__label">Question Banks Ready to Publish</p>
                    @if($readyToPublishQuestionBanks > 0)
                    <span class="stat-card__badge stat-card__badge--warn">ACTION REQUIRED</span>
                    @else
                    <span class="stat-card__badge stat-card__badge--ok">QUEUE CLEAR</span>
                    @endif
                </div>
            </a>

            {{-- Card 2: Ready to Publish — Assessments --}}
            <a href="{{ route('admin.publications.assessments', ['status' => 'approved']) }}" class="stat-card stat-card--action stat-card--blue">
                <div class="stat-card__icon">📋</div>
                <div class="stat-card__body">
                    <p class="stat-card__value">{{ $readyToPublishAssessments }}</p>
                    <p class="stat-card__label">Assessments Ready to Publish</p>
                    @if($readyToPublishAssessments > 0)
                    <span class="stat-card__badge stat-card__badge--warn">ACTION REQUIRED</span>
                    @else
                    <span class="stat-card__badge stat-card__badge--ok">QUEUE CLEAR</span>
                    @endif
                </div>
            </a>

            {{-- Card 3: Pending Archive Requests --}}
            <a href="{{ route('admin.publications.archive-requests') }}" class="stat-card stat-card--action stat-card--orange">
                <div class="stat-card__icon">📦</div>
                <div class="stat-card__body">
                    <p class="stat-card__value">{{ $pendingArchiveRequests }}</p>
                    <p class="stat-card__label">Pending Archive Requests</p>
                    @if($pendingArchiveRequests > 0)
                    <span class="stat-card__badge stat-card__badge--warn">AWAITING SA</span>
                    @else
                    <span class="stat-card__badge stat-card__badge--ok">NONE PENDING</span>
                    @endif
                </div>
            </a>

            {{-- Card 4: Published Today --}}
            <a href="{{ route('admin.publications.published') }}" class="stat-card stat-card--green">
                <div class="stat-card__icon">✅</div>
                <div class="stat-card__body">
                    <p class="stat-card__value">{{ $publishedToday }}</p>
                    <p class="stat-card__label">Published Today</p>
                    <span class="stat-card__badge stat-card__badge--info">TODAY</span>
                </div>
            </a>

            {{-- Card 5: Published This Week --}}
            <a href="{{ route('admin.publications.published') }}" class="stat-card stat-card--teal">
                <div class="stat-card__icon">📈</div>
                <div class="stat-card__body">
                    <p class="stat-card__value">{{ $publishedThisWeek }}</p>
                    <p class="stat-card__label">Published This Week</p>
                    <span class="stat-card__badge stat-card__badge--info">WEEK</span>
                </div>
            </a>

            {{-- Card 6: Certificates Generated Today --}}
            <div class="stat-card stat-card--indigo">
                <div class="stat-card__icon">🏅</div>
                <div class="stat-card__body">
                    <p class="stat-card__value">{{ $certificatesGeneratedToday }}</p>
                    <p class="stat-card__label">Certificates Generated Today</p>
                    <span class="stat-card__badge stat-card__badge--info">TODAY</span>
                </div>
            </div>

        </div>
    </section>

    {{-- Section 6: Task Center --}}
    <section class="task-center">
        <div class="task-center__header">
            <h2 class="section-label">Task Center</h2>
            <span class="task-count">{{ count($tasks) }} Active Task{{ count($tasks) !== 1 ? 's' : '' }}</span>
        </div>

        @if(count($tasks) === 0)
        <div class="empty-state empty-state--tasks">
            <div class="empty-state__icon">🎉</div>
            <h3>All Clear!</h3>
            <p>No pending publication tasks. Your operational queue is clear.</p>
        </div>
        @else
        <div class="task-list">
            @foreach($tasks as $task)
            <a href="{{ $task['url'] }}" class="task-item task-item--{{ strtolower($task['priority']) }}">
                <span class="task-item__icon">{{ $task['icon'] }}</span>
                <div class="task-item__body">
                    <p class="task-item__title">{{ $task['title'] }}</p>
                    <p class="task-item__entity">{{ $task['entity'] }}</p>
                </div>
                <div class="task-item__meta">
                    <span class="task-priority task-priority--{{ strtolower($task['priority']) }}">{{ $task['priority'] }}</span>
                    <span class="task-item__arrow">→</span>
                </div>
            </a>
            @endforeach
        </div>
        @endif
    </section>

    {{-- Recent Publications Feed --}}
    <section class="recent-feed">
        <h2 class="section-label">Recent Publications</h2>
        @if($recentPublications->isEmpty())
        <div class="empty-state">
            <div class="empty-state__icon">📭</div>
            <p>No content has been published yet.</p>
        </div>
        @else
        <div class="feed-list">
            @foreach($recentPublications as $item)
            <a href="{{ $item['url'] }}" class="feed-item">
                <span class="feed-item__icon">{{ $item['icon'] }}</span>
                <div class="feed-item__body">
                    <p class="feed-item__title">{{ $item['title'] }}</p>
                    <p class="feed-item__meta">{{ $item['type'] }} · {{ $item['date'] }}</p>
                </div>
                <span class="feed-item__badge">PUBLISHED</span>
            </a>
            @endforeach
        </div>
        @endif
    </section>

</div>

<style>
:root {
    --col-amber: #f59e0b;
    --col-blue: #3b82f6;
    --col-orange: #f97316;
    --col-green: #22c55e;
    --col-teal: #14b8a6;
    --col-indigo: #6366f1;
}

.operational-dashboard {
    padding: 2rem;
    display: flex;
    flex-direction: column;
    gap: 2.5rem;
}

/* Page Header */
.page-header { margin-bottom: 0; }
.page-header__inner {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 1rem;
}
.page-badge {
    display: inline-block;
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 0.1em;
    padding: 2px 8px;
    border-radius: 4px;
    margin-bottom: 0.4rem;
}
.page-badge--ops { background: #1e293b; color: #94a3b8; }
.page-title { font-size: 2rem; font-weight: 800; color: #0f172a; margin: 0 0 0.2rem; }
.page-subtitle { color: #64748b; font-size: 0.95rem; margin: 0; }

.page-header__actions { display: flex; align-items: center; gap: 0.75rem; }
.notif-alert {
    display: flex; align-items: center; gap: 0.4rem;
    background: #fef3c7; border: 1px solid #fcd34d; color: #92400e;
    padding: 0.45rem 0.9rem; border-radius: 8px; font-size: 0.85rem;
    font-weight: 600; text-decoration: none;
    transition: background 0.2s;
}
.notif-alert:hover { background: #fde68a; }
.notif-alert__badge {
    background: #dc2626; color: white; font-size: 0.7rem; font-weight: 700;
    padding: 1px 6px; border-radius: 999px;
}

/* Alert */
.alert {
    display: flex; align-items: center; justify-content: space-between;
    padding: 0.875rem 1.25rem; border-radius: 10px; font-size: 0.9rem;
}
.alert--success { background: #dcfce7; border: 1px solid #86efac; color: #15803d; }
.alert__close { background: none; border: none; font-size: 1.2rem; cursor: pointer; color: inherit; margin-left: 1rem; }

/* Section Label */
.section-label { font-size: 0.8rem; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: #64748b; margin: 0 0 1rem; }

/* Stats Grid */
.stats-grid { display: grid; gap: 1rem; }
.stats-grid--6 { grid-template-columns: repeat(3, 1fr); }
@media (max-width: 1024px) { .stats-grid--6 { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 640px) { .stats-grid--6 { grid-template-columns: 1fr; } }

.stat-card {
    position: relative;
    background: white;
    border-radius: 14px;
    padding: 1.25rem;
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    box-shadow: 0 1px 4px rgba(0,0,0,0.06), 0 4px 12px rgba(0,0,0,0.04);
    text-decoration: none;
    color: inherit;
    border: 1.5px solid transparent;
    transition: box-shadow 0.2s, border-color 0.2s, transform 0.15s;
    overflow: hidden;
}
.stat-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
}
.stat-card--action { cursor: pointer; }
.stat-card--action:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,0.1); }
.stat-card--amber::before { background: var(--col-amber); }
.stat-card--blue::before { background: var(--col-blue); }
.stat-card--orange::before { background: var(--col-orange); }
.stat-card--green::before { background: var(--col-green); }
.stat-card--teal::before { background: var(--col-teal); }
.stat-card--indigo::before { background: var(--col-indigo); }

.stat-card__icon { font-size: 1.8rem; line-height: 1; }
.stat-card__value { font-size: 2rem; font-weight: 800; color: #0f172a; line-height: 1.1; margin: 0 0 0.2rem; }
.stat-card__label { font-size: 0.82rem; color: #64748b; margin: 0 0 0.5rem; }
.stat-card__badge { font-size: 0.65rem; font-weight: 700; letter-spacing: 0.08em; padding: 2px 7px; border-radius: 4px; }
.stat-card__badge--warn { background: #fef3c7; color: #92400e; }
.stat-card__badge--ok { background: #dcfce7; color: #15803d; }
.stat-card__badge--info { background: #e0f2fe; color: #0369a1; }

/* Task Center */
.task-center__header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; }
.task-count { font-size: 0.8rem; font-weight: 600; background: #f1f5f9; color: #475569; padding: 3px 10px; border-radius: 999px; }

.task-list { display: flex; flex-direction: column; gap: 0.5rem; }
.task-item {
    display: flex; align-items: center; gap: 1rem;
    padding: 1rem 1.25rem; background: white; border-radius: 10px;
    border: 1px solid #f1f5f9; text-decoration: none; color: inherit;
    transition: border-color 0.2s, box-shadow 0.2s, transform 0.15s;
}
.task-item:hover { border-color: #3b82f6; box-shadow: 0 4px 12px rgba(59,130,246,0.12); transform: translateX(2px); }
.task-item--high { border-left: 3px solid #ef4444; }
.task-item--normal { border-left: 3px solid #94a3b8; }

.task-item__icon { font-size: 1.3rem; }
.task-item__body { flex: 1; min-width: 0; }
.task-item__title { font-size: 0.88rem; color: #475569; margin: 0 0 0.15rem; }
.task-item__entity { font-size: 0.95rem; font-weight: 600; color: #0f172a; margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.task-item__meta { display: flex; align-items: center; gap: 0.75rem; flex-shrink: 0; }
.task-priority { font-size: 0.65rem; font-weight: 700; letter-spacing: 0.06em; padding: 2px 7px; border-radius: 4px; }
.task-priority--high { background: #fee2e2; color: #dc2626; }
.task-priority--normal { background: #f1f5f9; color: #64748b; }
.task-item__arrow { color: #94a3b8; font-size: 1rem; }

/* Recent Feed */
.feed-list { display: flex; flex-direction: column; gap: 0.5rem; }
.feed-item {
    display: flex; align-items: center; gap: 1rem;
    padding: 0.875rem 1.25rem; background: white; border-radius: 10px;
    border: 1px solid #f1f5f9; text-decoration: none; color: inherit;
    transition: border-color 0.2s, box-shadow 0.2s;
}
.feed-item:hover { border-color: #22c55e; box-shadow: 0 4px 12px rgba(34,197,94,0.1); }
.feed-item__icon { font-size: 1.2rem; }
.feed-item__body { flex: 1; min-width: 0; }
.feed-item__title { font-size: 0.95rem; font-weight: 600; color: #0f172a; margin: 0 0 0.15rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.feed-item__meta { font-size: 0.78rem; color: #94a3b8; margin: 0; }
.feed-item__badge { font-size: 0.65rem; font-weight: 700; letter-spacing: 0.07em; background: #dcfce7; color: #15803d; padding: 2px 8px; border-radius: 4px; flex-shrink: 0; }

/* Empty States */
.empty-state { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 3rem; background: white; border-radius: 14px; border: 1.5px dashed #e2e8f0; text-align: center; }
.empty-state__icon { font-size: 2.5rem; margin-bottom: 0.75rem; }
.empty-state h3 { font-size: 1.1rem; font-weight: 700; color: #0f172a; margin: 0 0 0.4rem; }
.empty-state p { color: #94a3b8; font-size: 0.88rem; margin: 0; }
</style>
@endsection

@push('scripts')
<script>
function operationalDashboard() {
    return {
        init() {
            // Periodically refresh unread notification count via AJAX
            setInterval(() => this.refreshNotifCount(), 60000);
        },
        async refreshNotifCount() {
            try {
                const res = await fetch('{{ route('notifications.feed') }}', {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await res.json();
                if (data.unread_count !== undefined) {
                    const badge = document.querySelector('.notif-alert__badge');
                    if (badge) badge.textContent = data.unread_count;
                }
            } catch (e) { /* silent */ }
        }
    };
}
</script>
@endpush
