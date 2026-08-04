@extends('layouts.admin')

@section('title', 'Notification History')

@section('content')
<div class="notif-page">

    <div class="page-header">
        <div class="page-header__inner">
            <div>
                <h1 class="page-title">Notification History</h1>
                <p class="page-subtitle">All alerts, approvals, and workflow updates.</p>
            </div>
            <div class="page-header__actions">
                @if(isset($notifications) && $notifications->isNotEmpty())
                <form action="{{ route('notifications.read-all') }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn--ghost btn--sm">Mark All Read</button>
                </form>
                @endif
            </div>
        </div>
    </div>

    @if(session('status'))
    <div class="notif-alert notif-alert--success">
        ✅ {{ session('status') }}
    </div>
    @endif

    @if(session('error'))
    <div class="notif-alert notif-alert--error">
        ⚠️ {{ session('error') }}
    </div>
    @endif

    @if(!isset($notifications) || $notifications->isEmpty())
    <div class="empty-state">
        <div class="empty-state__icon">🔔</div>
        <h3>No Notifications</h3>
        <p>You have no notifications yet. They will appear here when workflow events occur.</p>
    </div>
    @else
    <div class="notif-list">
        @foreach($notifications as $notification)
        @php
            $data = $notification->data;
            $isUnread = is_null($notification->read_at);
            $priority = $data['priority'] ?? 'NORMAL';
        @endphp
        <form action="{{ route('notifications.read', $notification->id) }}" method="POST" class="notif-form">
            @csrf
            <button type="submit" class="notif-item {{ $isUnread ? 'notif-item--unread' : '' }} notif-item--{{ strtolower($priority) }}">
                <div class="notif-item__indicator"></div>
                <div class="notif-item__body">
                    <div class="notif-item__header">
                        <p class="notif-item__title">{{ $data['title'] ?? 'System Alert' }}</p>
                        <div class="notif-item__meta">
                            @if($isUnread)
                            <span class="notif-badge notif-badge--unread">NEW</span>
                            @endif
                            <span class="notif-priority notif-priority--{{ strtolower($priority) }}">{{ $priority }}</span>
                        </div>
                    </div>
                    <p class="notif-item__message">{{ $data['message'] ?? '' }}</p>
                    <p class="notif-item__time">{{ $notification->created_at?->diffForHumans() }}</p>
                </div>
            </button>
        </form>
        @endforeach
    </div>

    <div class="notif-pagination">
        {{ $notifications->links() }}
    </div>
    @endif

</div>

<style>
.notif-page { padding: 2rem; display: flex; flex-direction: column; gap: 1.5rem; }
.page-title { font-size: 1.6rem; font-weight: 800; color: #0f172a; margin: 0 0 .2rem; }
.page-subtitle { color: #64748b; font-size: .9rem; margin: 0; }
.page-header__inner { display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem; }
.page-header__actions { display: flex; gap: .5rem; }
.notif-alert { padding: .85rem 1.1rem; border-radius: 10px; font-size: .88rem; font-weight: 600; }
.notif-alert--success { background: #dcfce7; border: 1px solid #86efac; color: #15803d; }
.notif-alert--error { background: #fee2e2; border: 1px solid #fca5a5; color: #b91c1c; }
.notif-list { display: flex; flex-direction: column; gap: .65rem; }
.notif-form { margin: 0; padding: 0; width: 100%; display: block; }
.notif-item {
    display: flex; align-items: flex-start; gap: 1rem; width: 100%; text-align: left;
    background: white; border-radius: 12px; border: 1px solid #e2e8f0;
    text-decoration: none; color: inherit; cursor: pointer;
    padding: 1rem 1.25rem; transition: border-color .2s, box-shadow .2s;
    position: relative; overflow: hidden; font-family: inherit;
}
.notif-item:hover { border-color: #3b82f6; box-shadow: 0 4px 12px rgba(59,130,246,.1); }
.notif-item--unread { background: #f0f9ff; border-color: #bae6fd; }
.notif-item__indicator {
    width: 4px; min-height: 100%; border-radius: 2px; flex-shrink: 0;
    position: absolute; left: 0; top: 0; bottom: 0;
}
.notif-item--high .notif-item__indicator { background: #ef4444; }
.notif-item--normal .notif-item__indicator { background: #94a3b8; }
.notif-item__body { flex: 1; min-width: 0; padding-left: .5rem; }
.notif-item__header { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; margin-bottom: .25rem; }
.notif-item__title { font-weight: 700; font-size: .95rem; color: #0f172a; margin: 0; }
.notif-item__message { font-size: .875rem; color: #475569; margin: 0 0 .35rem; line-height: 1.5; }
.notif-item__time { font-size: .75rem; color: #94a3b8; margin: 0; }
.notif-item__meta { display: flex; align-items: center; gap: .4rem; flex-shrink: 0; }
.notif-badge { font-size: .65rem; font-weight: 700; padding: 2px 7px; border-radius: 4px; }
.notif-badge--unread { background: #dbeafe; color: #1d4ed8; }
.notif-priority { font-size: .65rem; font-weight: 700; letter-spacing: .06em; padding: 2px 7px; border-radius: 4px; }
.notif-priority--high { background: #fee2e2; color: #dc2626; }
.notif-priority--normal { background: #f1f5f9; color: #64748b; }
.notif-pagination { }
.empty-state { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 3rem; background: white; border-radius: 14px; border: 1.5px dashed #e2e8f0; text-align: center; }
.empty-state__icon { font-size: 2.5rem; margin-bottom: .75rem; }
.empty-state h3 { font-size: 1.1rem; font-weight: 700; color: #0f172a; margin: 0 0 .4rem; }
.empty-state p { color: #94a3b8; font-size: .88rem; margin: 0; }
.btn { display: inline-flex; align-items: center; gap: .4rem; padding: .6rem 1.25rem; border-radius: 8px; font-size: .875rem; font-weight: 600; border: none; cursor: pointer; text-decoration: none; transition: background .2s; }
.btn--ghost { background: #f1f5f9; color: #475569; }
.btn--sm { padding: .35rem .75rem; font-size: .78rem; }
</style>
@endsection
