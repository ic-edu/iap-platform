<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    /**
     * JSON feed for top-nav notification bell (ADMIN-OPS-001 Section 7 & NOTIFICATION-001).
     */
    public function feed(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['success' => false, 'unread_count' => 0, 'data' => []]);
        }

        $dbNotifications = $user->notifications()->take(15)->get();

        $formatted = $dbNotifications->map(function ($n) {
            $data = $n->data;

            return [
                'id' => $n->id,
                'title' => $data['title'] ?? 'System Alert',
                'message' => $data['message'] ?? '',
                'notification_type' => $data['notification_type'] ?? 'SYSTEM_ALERT',
                'priority' => $data['priority'] ?? 'NORMAL',
                'entity_type' => $data['entity_type'] ?? null,
                'entity_id' => $data['entity_id'] ?? null,
                'target_url' => $data['target_url'] ?? $data['url'] ?? route('notifications.index'),
                'timestamp' => $n->created_at?->toIso8601String(),
                'time_ago' => $n->created_at?->diffForHumans(),
                'unread' => is_null($n->read_at),
            ];
        });

        return response()->json([
            'success' => true,
            'unread_count' => $user->unreadNotifications()->count(),
            'data' => $formatted->values(),
        ]);
    }

    /**
     * GET /notifications – Notification history page or JSON feed.
     * When Accept: application/json, returns the notification feed (for bell dropdown API).
     */
    public function index(Request $request): View|JsonResponse
    {
        if ($request->expectsJson()) {
            return $this->feed($request);
        }

        $user = $request->user();
        $notifications = $user
            ? $user->notifications()->paginate(20)
            : collect();

        return view('notifications.index', compact('notifications'));
    }

    /**
     * POST /notifications/{id}/read – Mark single notification as read and redirect to target_url.
     * NOTIFICATION-001: Graceful error handling, RESTful POST compliance, Deep Linking.
     */
    public function markRead(Request $request, string $id): RedirectResponse|JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
            }

            return redirect()->route('login');
        }

        // Locate notification belonging to authenticated user
        $notification = $user->notifications()->where('id', $id)->first();

        if (! $notification) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Notification not found or access denied.'], 404);
            }

            return redirect()->route('notifications.index')->with('error', 'Notification not found.');
        }

        // Mark as read if not already read
        if (is_null($notification->read_at)) {
            $notification->markAsRead();
        }

        ActivityLogger::log(
            action: 'NOTIFICATION_OPENED',
            description: "User opened notification: " . ($notification->data['title'] ?? 'Alert'),
            subject: $user,
            properties: ['notification_id' => $id, 'title' => $notification->data['title'] ?? '']
        );

        // Resolve deep link target URL (fallback to notification history)
        $targetUrl = $notification->data['target_url']
            ?? $notification->data['url']
            ?? route('notifications.index');

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'target_url' => $targetUrl,
                'unread_count' => $user->unreadNotifications()->count(),
            ]);
        }

        return redirect()->to($targetUrl);
    }

    /**
     * POST /notifications/read-all – Mark all notifications as read.
     */
    public function markAllRead(Request $request): RedirectResponse|JsonResponse
    {
        $user = $request->user();

        if ($user) {
            $user->unreadNotifications->markAsRead();

            ActivityLogger::log(
                action: 'NOTIFICATIONS_MARKED_ALL_READ',
                description: 'User marked all notifications as read.',
                subject: $user
            );
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'unread_count' => 0,
            ]);
        }

        return redirect()->route('notifications.index')->with('status', 'All notifications marked as read.');
    }
}
