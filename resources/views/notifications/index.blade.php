@extends('layouts.admin')

@section('title', 'Notification Center — iC.edu Platform')

@section('content')
<div class="notif-page-container">

    {{-- Page Header --}}
    <div class="notif-header-card">
        <div class="notif-header-left">
            <div class="notif-header-title-row">
                <h1 class="notif-page-title">Notification Center</h1>
                @php
                    $unreadCount = auth()->user()?->unreadNotifications()->count() ?? 0;
                @endphp
                @if($unreadCount > 0)
                    <span class="notif-count-badge">{{ $unreadCount }} Unread</span>
                @endif
            </div>
            <p class="notif-page-subtitle">All alerts, approvals, and workflow updates across the platform.</p>
        </div>
        <div class="notif-header-actions">
            @if(isset($notifications) && $notifications->isNotEmpty() && $unreadCount > 0)
            <form action="{{ route('notifications.read-all') }}" method="POST" class="inline-block m-0">
                @csrf
                <button type="submit" class="notif-btn-ghost">
                    ✓ Mark All Read
                </button>
            </form>
            @endif
        </div>
    </div>

    @if(session('status'))
    <div class="notif-alert-banner notif-alert-success">
        ✅ {{ session('status') }}
    </div>
    @endif

    @if(session('error'))
    <div class="notif-alert-banner notif-alert-error">
        ⚠️ {{ session('error') }}
    </div>
    @endif

    @if(!isset($notifications) || $notifications->isEmpty())
    <div class="notif-empty-state">
        <div class="notif-empty-icon">🔔</div>
        <h3 class="notif-empty-title">No Notifications Yet</h3>
        <p class="notif-empty-desc">You have no active alerts or workflow updates. They will appear here when events occur.</p>
    </div>
    @else
    <div class="notif-card-list">
        @foreach($notifications as $notification)
        @php
            $data = is_array($notification->data) ? $notification->data : (json_decode($notification->data ?? '[]', true) ?? []);
            $isUnread = is_null($notification->read_at);
            $priority = strtoupper($data['priority'] ?? 'NORMAL');
            $title = $data['title'] ?? 'System Alert';
            $message = $data['message'] ?? '';
            $type = $notification->type ?? $data['notification_type'] ?? 'default';

            // Determine Icon & Badge Style
            $icon = '🔔';
            $badgeTheme = 'indigo';
            $actionLabel = 'View Details →';

            if (str_contains(strtolower($type), 'payment_pending') || str_contains(strtolower($type), 'payment_created')) {
                $icon = '💳';
                $badgeTheme = 'amber';
                $actionLabel = 'Review Payment →';
            } elseif (str_contains(strtolower($type), 'payment_confirmed')) {
                $icon = '🎉';
                $badgeTheme = 'emerald';
                $actionLabel = 'View Payment →';
            } elseif (str_contains(strtolower($type), 'payment_cancelled') || str_contains(strtolower($type), 'payment_rejected')) {
                $icon = '⚠️';
                $badgeTheme = 'rose';
                $actionLabel = 'Inspect Payment →';
            } elseif (str_contains(strtolower($type), 'rejected') || str_contains(strtolower($title), 'rejected')) {
                $icon = '🚫';
                $badgeTheme = 'rose';
                $actionLabel = 'Inspect Repository →';
            } elseif (str_contains(strtolower($type), 'resubmitted') || str_contains(strtolower($title), 'resubmit')) {
                $icon = '📥';
                $badgeTheme = 'indigo';
                $actionLabel = 'Review Repository →';
            } elseif (str_contains(strtolower($type), 'revision') || str_contains(strtolower($title), 'revision')) {
                $icon = '📝';
                $badgeTheme = 'rose';
                $actionLabel = 'View Revision Request →';
            } elseif (str_contains(strtolower($type), 'passed') || str_contains(strtolower($title), 'approved')) {
                $icon = '✅';
                $badgeTheme = 'emerald';
                $actionLabel = 'Inspect Status →';
            } elseif (str_contains(strtolower($type), 'failed') || str_contains(strtolower($priority), 'high')) {
                $icon = '⚠️';
                $badgeTheme = 'amber';
                $actionLabel = 'View Governance Details →';
            }
        @endphp
        <form action="{{ route('notifications.read', $notification->id) }}" method="POST" class="notif-card-form">
            @csrf
            <input type="hidden" name="from" value="notifications">
            <button type="submit" class="notif-card-btn {{ $isUnread ? 'notif-card-unread' : 'notif-card-read' }}">
                <div class="notif-card-icon-col">
                    <span class="notif-card-icon">{{ $icon }}</span>
                </div>
                <div class="notif-card-content-col">
                    <div class="notif-card-top-row">
                        <span class="notif-card-title">{{ $title }}</span>
                        <div class="notif-card-meta-tags">
                            @if($isUnread)
                            <span class="notif-tag notif-tag-new">NEW</span>
                            @endif
                            <span class="notif-tag notif-tag-{{ strtolower($priority) }}">{{ $priority }}</span>
                        </div>
                    </div>
                    @if($message)
                    <p class="notif-card-message">{{ $message }}</p>
                    @endif
                    @if(!empty($data['rejection_reason']))
                    <div style="margin-top:.35rem;padding:.4rem .65rem;background:rgba(225,29,72,0.1);border:1px solid rgba(244,63,94,0.25);border-radius:.4rem;font-size:.78rem;color:#fecdd3;line-height:1.4;text-align:left;">
                        <strong style="color:#fb7185;">Rejection Reason:</strong> {{ $data['rejection_reason'] }}
                    </div>
                    @endif
                    <div class="notif-card-bottom-row">
                        <span class="notif-card-time">⏱ {{ $notification->created_at?->diffForHumans() }}</span>
                        <span class="notif-card-action-link">{{ $actionLabel }}</span>
                    </div>
                </div>
            </button>
        </form>
        @endforeach
    </div>

    <div class="notif-pagination-wrapper">
        {{ $notifications->links() }}
    </div>
    @endif

</div>

<style>
.notif-page-container {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
    width: 100%;
}
.notif-header-card {
    background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%);
    border: 1px solid #334155;
    border-radius: 1rem;
    padding: 1.5rem 1.75rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3);
}
.notif-header-title-row {
    display: flex;
    align-items: center;
    gap: .75rem;
}
.notif-page-title {
    font-size: 1.5rem;
    font-weight: 800;
    color: #f8fafc;
    margin: 0;
    letter-spacing: -.02em;
}
.notif-count-badge {
    background: rgba(99, 102, 241, 0.2);
    border: 1px solid rgba(99, 102, 241, 0.4);
    color: #818cf8;
    font-size: .72rem;
    font-weight: 800;
    padding: .2rem .65rem;
    border-radius: 99px;
}
.notif-page-subtitle {
    color: #94a3b8;
    font-size: .88rem;
    margin: .3rem 0 0;
}
.notif-btn-ghost {
    background: #1e293b;
    border: 1px solid #334155;
    color: #cbd5e1;
    font-size: .82rem;
    font-weight: 700;
    padding: .5rem 1rem;
    border-radius: .65rem;
    cursor: pointer;
    transition: all .2s ease;
}
.notif-btn-ghost:hover {
    background: #334155;
    color: #fff;
    border-color: #6366f1;
}
.notif-alert-banner {
    padding: .85rem 1.15rem;
    border-radius: .75rem;
    font-size: .88rem;
    font-weight: 600;
}
.notif-alert-success {
    background: rgba(16, 185, 129, 0.15);
    border: 1px solid rgba(16, 185, 129, 0.3);
    color: #34d399;
}
.notif-alert-error {
    background: rgba(244, 63, 94, 0.15);
    border: 1px solid rgba(244, 63, 94, 0.3);
    color: #fb7185;
}
.notif-card-list {
    display: flex;
    flex-direction: column;
    gap: .75rem;
}
.notif-card-form {
    margin: 0;
    padding: 0;
    display: block;
    width: 100%;
}
.notif-card-btn {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    width: 100%;
    text-align: left;
    background: #0f172a;
    border: 1px solid #1e293b;
    border-radius: .85rem;
    padding: 1.15rem 1.35rem;
    cursor: pointer;
    transition: all .2s ease;
    position: relative;
    overflow: hidden;
    color: inherit;
    font-family: inherit;
}
.notif-card-btn:hover {
    border-color: #6366f1;
    background: #1e293b;
    transform: translateY(-1px);
    box-shadow: 0 8px 20px -6px rgba(99, 102, 241, 0.25);
}
.notif-card-unread {
    background: linear-gradient(135deg, rgba(30, 27, 75, 0.45) 0%, #0f172a 100%);
    border-color: #4338ca;
    border-left: 4px solid #6366f1;
}
.notif-card-icon-col {
    width: 2.5rem;
    height: 2.5rem;
    border-radius: .65rem;
    background: #1e293b;
    border: 1px solid #334155;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.notif-card-icon {
    font-size: 1.1rem;
}
.notif-card-content-col {
    flex: 1;
    min-width: 0;
}
.notif-card-top-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1rem;
    margin-bottom: .35rem;
}
.notif-card-title {
    font-size: .95rem;
    font-weight: 700;
    color: #f1f5f9;
    line-height: 1.3;
}
.notif-card-meta-tags {
    display: flex;
    align-items: center;
    gap: .4rem;
    flex-shrink: 0;
}
.notif-tag {
    font-size: .65rem;
    font-weight: 800;
    padding: .15rem .5rem;
    border-radius: .4rem;
    letter-spacing: .03em;
}
.notif-tag-new {
    background: #6366f1;
    color: #fff;
}
.notif-tag-high {
    background: rgba(244, 63, 94, 0.2);
    color: #fb7185;
    border: 1px solid rgba(244, 63, 94, 0.3);
}
.notif-tag-normal {
    background: #1e293b;
    color: #94a3b8;
    border: 1px solid #334155;
}
.notif-card-message {
    font-size: .86rem;
    color: #94a3b8;
    margin: 0 0 .65rem;
    line-height: 1.45;
}
.notif-card-bottom-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    padding-top: .4rem;
    border-top: 1px solid rgba(255,255,255,0.05);
}
.notif-card-time {
    font-size: .75rem;
    color: #64748b;
}
.notif-card-action-link {
    font-size: .78rem;
    font-weight: 700;
    color: #818cf8;
    transition: color .2s ease;
}
.notif-card-btn:hover .notif-card-action-link {
    color: #a5b4fc;
    text-decoration: underline;
}
.notif-empty-state {
    background: #0f172a;
    border: 2px dashed #334155;
    border-radius: 1rem;
    padding: 3.5rem 2rem;
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
}
.notif-empty-icon {
    font-size: 2.5rem;
    margin-bottom: .75rem;
}
.notif-empty-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: #f1f5f9;
    margin: 0 0 .4rem;
}
.notif-empty-desc {
    font-size: .88rem;
    color: #64748b;
    margin: 0;
    max-width: 400px;
}
.notif-pagination-wrapper {
    margin-top: hover;
}
</style>
@endsection
