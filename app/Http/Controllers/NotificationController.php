<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    /**
     * Get user notifications payload for notification bell header.
     */
    public function index(): JsonResponse
    {
        $notifications = [
            [
                'id' => 1,
                'title' => 'Assessment Approved',
                'message' => 'TOEIC Full Simulation Test 01 has been approved and published by Super Admin.',
                'time' => '10 mins ago',
                'unread' => true,
            ],
            [
                'id' => 2,
                'title' => 'Digital Certificate Issued',
                'message' => 'Certificate #CERT-20260727-8F12A was successfully generated for candidate.',
                'time' => '1 hour ago',
                'unread' => false,
            ],
            [
                'id' => 3,
                'title' => 'New Student Enrollment',
                'message' => 'Student student@icedu.org enrolled in TOEFL Academic Writing.',
                'time' => '2 hours ago',
                'unread' => false,
            ],
        ];

        return response()->json([
            'success' => true,
            'unread_count' => 1,
            'data' => $notifications,
        ]);
    }
}
