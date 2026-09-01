@extends('layouts.admin')

@section('title', 'Notification Center — iC.edu Platform')

@section('content')
<div class="space-y-6">

    {{-- Page Header --}}
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-sm flex justify-between items-center flex-wrap gap-4">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight">Notification Center</h1>
                @php
                    $unreadCount = auth()->user()?->unreadNotifications()->count() ?? 0;
                @endphp
                @if($unreadCount > 0)
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-extrabold bg-indigo-50 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800/60">{{ $unreadCount }} Unread</span>
                @endif
            </div>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">All alerts, approvals, and workflow updates across the platform.</p>
        </div>
        <div class="flex items-center gap-3">
            @if(isset($notifications) && $notifications->isNotEmpty() && $unreadCount > 0)
            <form action="{{ route('notifications.read-all') }}" method="POST" class="inline-block m-0">
                @csrf
                <button type="submit" class="px-3.5 py-2 rounded-xl text-xs font-bold text-slate-700 dark:text-slate-300 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 border border-slate-300 dark:border-slate-700 transition-colors inline-flex items-center gap-1.5 cursor-pointer">
                    ✓ Mark All Read
                </button>
            </form>
            @endif
        </div>
    </div>

    @if(session('status'))
    <div class="p-4 rounded-xl text-sm font-semibold bg-emerald-50 dark:bg-emerald-950/40 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/60">
        ✅ {{ session('status') }}
    </div>
    @endif

    @if(session('error'))
    <div class="p-4 rounded-xl text-sm font-semibold bg-rose-50 dark:bg-rose-950/40 text-rose-800 dark:text-rose-300 border border-rose-200 dark:border-rose-800/60">
        ⚠️ {{ session('error') }}
    </div>
    @endif

    @if(!isset($notifications) || $notifications->isEmpty())
    <div class="bg-slate-50 dark:bg-slate-900/50 border-2 border-dashed border-slate-200 dark:border-slate-800 rounded-2xl p-12 text-center flex flex-col items-center justify-center">
        <div class="w-14 h-14 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 flex items-center justify-center text-2xl mb-3 shadow-sm">🔔</div>
        <h3 class="text-base font-bold text-slate-900 dark:text-white mb-1">No Notifications Yet</h3>
        <p class="text-xs text-slate-500 dark:text-slate-400 max-w-sm">You have no active alerts or workflow updates. They will appear here when events occur.</p>
    </div>
    @else
    <div class="space-y-3">
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
            $actionLabel = 'View Details →';

            if (str_contains(strtolower($type), 'payment_pending') || str_contains(strtolower($type), 'payment_created')) {
                $icon = '💳';
                $actionLabel = 'Review Payment →';
            } elseif (str_contains(strtolower($type), 'payment_confirmed')) {
                $icon = '🎉';
                $actionLabel = 'View Payment →';
            } elseif (str_contains(strtolower($type), 'payment_cancelled') || str_contains(strtolower($type), 'payment_rejected')) {
                $icon = '⚠️';
                $actionLabel = 'Inspect Payment →';
            } elseif (str_contains(strtolower($type), 'rejected') || str_contains(strtolower($title), 'rejected')) {
                $icon = '🚫';
                $actionLabel = 'Inspect Repository →';
            } elseif (str_contains(strtolower($type), 'resubmitted') || str_contains(strtolower($title), 'resubmit')) {
                $icon = '📥';
                $actionLabel = 'Review Repository →';
            } elseif (str_contains(strtolower($type), 'revision') || str_contains(strtolower($title), 'revision')) {
                $icon = '📝';
                $actionLabel = 'View Revision Request →';
            } elseif (str_contains(strtolower($type), 'passed') || str_contains(strtolower($title), 'approved')) {
                $icon = '✅';
                $actionLabel = 'Inspect Status →';
            } elseif (str_contains(strtolower($type), 'failed') || str_contains(strtolower($priority), 'high')) {
                $icon = '⚠️';
                $actionLabel = 'View Governance Details →';
            }
        @endphp
        <form action="{{ route('notifications.read', $notification->id) }}" method="POST" class="block m-0 p-0">
            @csrf
            <input type="hidden" name="from" value="notifications">
            <button type="submit" class="w-full text-left p-4 sm:p-5 rounded-2xl border transition-all duration-150 flex items-start gap-4 cursor-pointer focus:outline-none focus:ring-2 focus:ring-indigo-500 {{ $isUnread ? 'bg-white dark:bg-slate-900 border-indigo-300 dark:border-indigo-800/80 shadow-sm ring-1 ring-indigo-500/20 hover:border-indigo-400 dark:hover:border-indigo-600 hover:shadow-md' : 'bg-slate-50/70 dark:bg-slate-900/40 border-slate-200 dark:border-slate-800/80 hover:bg-white dark:hover:bg-slate-900 hover:border-slate-300 dark:hover:border-slate-700' }}">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center text-lg flex-shrink-0 bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700">
                    <span>{{ $icon }}</span>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex justify-between items-start gap-3">
                        <span class="text-sm font-bold text-slate-900 dark:text-slate-100 leading-snug">{{ $title }}</span>
                        <div class="flex items-center gap-1.5 flex-shrink-0">
                            @if($isUnread)
                            <span class="px-2 py-0.5 rounded-md text-[10px] font-black tracking-wider uppercase bg-indigo-600 text-white shadow-xs">NEW</span>
                            @endif
                            @if($priority === 'HIGH')
                            <span class="px-2 py-0.5 rounded-md text-[10px] font-extrabold uppercase bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300 border border-rose-200 dark:border-rose-800/50">{{ $priority }}</span>
                            @else
                            <span class="px-2 py-0.5 rounded-md text-[10px] font-extrabold uppercase bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400 border border-slate-200 dark:border-slate-700">{{ $priority }}</span>
                            @endif
                        </div>
                    </div>
                    @if($message)
                    <p class="text-xs text-slate-600 dark:text-slate-300 mt-1.5 leading-relaxed">{{ $message }}</p>
                    @endif
                    @if(!empty($data['rejection_reason']))
                    <div class="mt-2 p-3 bg-rose-50 dark:bg-rose-950/30 border border-rose-200 dark:border-rose-800/50 rounded-xl text-xs text-rose-800 dark:text-rose-200 leading-relaxed text-left">
                        <strong class="text-rose-700 dark:text-rose-400">Rejection Reason:</strong> {{ $data['rejection_reason'] }}
                    </div>
                    @endif
                    <div class="flex justify-between items-center text-xs text-slate-400 dark:text-slate-500 mt-3 pt-2.5 border-t border-slate-100 dark:border-slate-800/60">
                        <span>⏱ {{ $notification->created_at?->diffForHumans() }}</span>
                        <span class="font-bold text-indigo-600 dark:text-indigo-400 hover:underline">{{ $actionLabel }}</span>
                    </div>
                </div>
            </button>
        </form>
        @endforeach
    </div>

    <div class="mt-6">
        {{ $notifications->links() }}
    </div>
    @endif

</div>
@endsection
