<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    /**
     * Display System Activity & Audit Logs workspace.
     */
    public function index(Request $request): View
    {
        $logs = [
            ['id' => 'LOG-9921', 'user' => 'admin@icedu.org', 'action' => 'ROLE_ASSIGNMENT', 'description' => 'Assigned role finance to user finance@icedu.org', 'ip' => '127.0.0.1', 'time' => now()->subMinutes(12)->format('Y-m-d H:i:s')],
            ['id' => 'LOG-9920', 'user' => 'teacher@icedu.org', 'action' => 'QUESTION_BANK_CREATE', 'description' => 'Created Question Bank TOEIC Speaking & Writing', 'ip' => '127.0.0.1', 'time' => now()->subMinutes(35)->format('Y-m-d H:i:s')],
            ['id' => 'LOG-9919', 'user' => 'student@icedu.org', 'action' => 'ASSESSMENT_SUBMIT', 'description' => 'Submitted Attempt 01kyh7w0752bv5x02c2m3f4739 for TOEIC Full Simulation Test 01', 'ip' => '127.0.0.1', 'time' => now()->subHour()->format('Y-m-d H:i:s')],
            ['id' => 'LOG-9918', 'user' => 'admin@icedu.org', 'action' => 'CERTIFICATE_ISSUE', 'description' => 'Issued Certificate #CERT-20260727-8F12A for student@icedu.org', 'ip' => '127.0.0.1', 'time' => now()->subHours(2)->format('Y-m-d H:i:s')],
        ];

        return view('admin.audit_logs.index', compact('logs'));
    }
}
