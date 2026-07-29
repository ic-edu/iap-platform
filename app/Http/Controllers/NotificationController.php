<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Get user notifications payload for notification bell header (QB-002 Notification Center).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['success' => false, 'unread_count' => 0, 'data' => []]);
        }

        $dbNotifications = $user->notifications()->take(10)->get();

        $formatted = $dbNotifications->map(function ($n) {
            $data = $n->data;

            return [
                'id' => $n->id,
                'title' => $data['title'] ?? 'System Alert',
                'message' => $data['message'] ?? 'Notification details',
                'time' => $n->created_at?->diffForHumans() ?? 'Just now',
                'unread' => $n->unread(),
            ];
        });

        // Fallback default notifications if database notifications empty
        if ($formatted->isEmpty()) {
            $formatted = collect([
                [
                    'id' => 'system-1',
                    'title' => 'System Ready',
                    'message' => 'Notification center initialized and ready for automated governance alerts.',
                    'time' => 'Just now',
                    'unread' => false,
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'unread_count' => $user->unreadNotifications->count(),
            'data' => $formatted,
        ]);
    }
}
