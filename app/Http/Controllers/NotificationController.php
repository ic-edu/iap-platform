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
     * Resolve the canonical target URL for a notification, respecting user authorization & fallbacks.
     */
    public function resolveTargetUrl(object $notification, mixed $user = null): string
    {
        $data = is_array($notification->data) ? $notification->data : (json_decode($notification->data ?? '[]', true) ?? []);

        // 1. Check direct target_url / url / link from notification payload
        $rawUrl = $data['target_url'] ?? $data['url'] ?? $data['link'] ?? null;
        $resolved = null;

        if ($rawUrl) {
            // Normalize full URLs (e.g. http://localhost:8000/admin/...) to relative paths so port mismatches don't break routing
            $parsed = parse_url($rawUrl);
            $path = $parsed['path'] ?? $rawUrl;
            if (!empty($parsed['query'])) {
                $path .= '?' . $parsed['query'];
            }

            // Role-Aware Destination Safeguard
            if ($user) {
                // If notification points to RM governance route but user is Teacher:
                if (str_starts_with($path, '/admin/repository-manager/') && $user->hasRole('teacher') && !$user->hasRole(['repository-manager', 'super-admin'])) {
                    return route('teacher.repository-revisions.index');
                }
                // If notification points to Teacher revision route but user is RM/Admin:
                if (str_starts_with($path, '/teacher/repository-revisions') && $user->hasRole(['repository-manager', 'super-admin']) && !$user->hasRole('teacher')) {
                    if (!empty($data['question_bank_id'])) {
                        $resolved = route('admin.repository-manager.question-bank-validate', $data['question_bank_id']);
                    } else {
                        $resolved = route('admin.repository-manager.dashboard');
                    }
                }
            }

            if (!$resolved) {
                $resolved = $path;
            }
        } else {
            // 2. Entity-Based Fallback Resolution
            $entityType = $data['entity_type'] ?? null;
            $entityId   = $data['entity_id'] ?? $data['question_bank_id'] ?? null;

            if ($user && ($entityType || $entityId || !empty($data['revision_request_id']))) {
                if ($entityType === 'Payment' && $entityId) {
                    if ($user->hasRole('finance')) {
                        $resolved = route('finance.payments.show', $entityId);
                    } elseif ($user->hasRole('admin')) {
                        $resolved = route('admin.dashboard');
                    } elseif ($user->hasRole('student')) {
                        $resolved = route('candidate.payments.show', $entityId);
                    }
                } elseif ($user->hasRole(['repository-manager', 'super-admin'])) {
                    if ($entityType === 'RepositoryRevisionRequest' && $entityId) {
                        $resolved = route('admin.repository-manager.revisions.review', $entityId);
                    } elseif ($entityId && (str_contains(strtolower((string)$entityType), 'repository') || $entityType === 'QuestionBank')) {
                        $resolved = route('admin.repository-manager.question-bank-validate', $entityId);
                    } elseif ($entityId && in_array(strtolower((string)$entityType), ['test', 'assessment'], true)) {
                        $resolved = route('admin.repository-manager.assessment-review', $entityId);
                    } else {
                        $resolved = route('admin.repository-manager.dashboard');
                    }
                } elseif ($user->hasRole('teacher')) {
                    if (!empty($data['revision_request_id'])) {
                        $resolved = route('teacher.repository-revisions.show', $data['revision_request_id']);
                    } elseif (!empty($data['question_bank_id'])) {
                        $resolved = route('teacher.question-banks.show', $data['question_bank_id']);
                    } else {
                        $resolved = route('teacher.repository-revisions.index');
                    }
                }
            }
        }

        if (!$resolved) {
            return route('notifications.index');
        }

        return $resolved;
    }

    /**
     * Attach safe return context to target URL for contextual Back navigation.
     */
    public function attachReturnContext(string $targetUrl, ?string $from = null, ?string $returnUrl = null): string
    {
        // 1. If target URL is already notification index, return as is
        if ($targetUrl === route('notifications.index') || str_ends_with(parse_url($targetUrl, PHP_URL_PATH) ?? '', '/notifications')) {
            return $targetUrl;
        }

        // Clean any existing return parameters first to avoid duplication
        $cleaned = preg_replace('/([?&])(from|from_url)=[^&]*&?/', '$1', $targetUrl);
        $cleaned = rtrim($cleaned, '?&');
        $separator = str_contains($cleaned, '?') ? '&' : '?';

        // 2. Explicit 'from=notifications' origin (e.g. from Notification Center page)
        if ($from === 'notifications') {
            return $cleaned . $separator . 'from=notifications';
        }

        // 3. Contextual return_url provided (e.g. from navbar notification dropdown overlay)
        if ($returnUrl) {
            $decodedUrl = urldecode($returnUrl);
            // Open Redirect Safeguard: Must be internal relative path starting with '/' and NOT starting with '//' or containing '://'
            if (str_starts_with($decodedUrl, '/') && !str_starts_with($decodedUrl, '//') && !str_contains($decodedUrl, '://')) {
                $pathOnly = parse_url($decodedUrl, PHP_URL_PATH) ?? '';

                if ($pathOnly === '/notifications' || str_ends_with($pathOnly, '/notifications')) {
                    return $cleaned . $separator . 'from=notifications';
                }

                return $cleaned . $separator . 'from_url=' . urlencode($decodedUrl);
            }
        }

        // 4. Default fallback when no return_url/from parameter was provided (e.g. direct notification resolve)
        if (!str_contains($targetUrl, 'from=') && !str_contains($targetUrl, 'from_url=')) {
            return $cleaned . $separator . 'from=notifications';
        }

        return $targetUrl;
    }

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

        $formatted = $dbNotifications->map(function ($n) use ($user) {
            $data = is_array($n->data) ? $n->data : (json_decode($n->data ?? '[]', true) ?? []);

            return [
                'id' => $n->id,
                'title' => $data['title'] ?? 'System Alert',
                'message' => $data['message'] ?? '',
                'notification_type' => $data['notification_type'] ?? $n->type ?? 'SYSTEM_ALERT',
                'priority' => $data['priority'] ?? 'NORMAL',
                'entity_type' => $data['entity_type'] ?? null,
                'entity_id' => $data['entity_id'] ?? null,
                'target_url' => $this->resolveTargetUrl($n, $user),
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

        $rawTargetUrl = $this->resolveTargetUrl($notification, $user);
        $from = $request->input('from');
        $returnUrl = $request->input('return_url') ?? $request->input('from_url');

        $finalTargetUrl = $this->attachReturnContext($rawTargetUrl, $from, $returnUrl);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'target_url' => $finalTargetUrl,
                'unread_count' => $user->unreadNotifications()->count(),
            ]);
        }

        return redirect()->to($finalTargetUrl);
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
