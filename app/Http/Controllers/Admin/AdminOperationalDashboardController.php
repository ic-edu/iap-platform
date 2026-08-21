<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\CandidateTestAssignment;
use App\Modules\Assessment\Models\Test;
use App\Modules\Certificate\Models\Certificate;
use App\Modules\Commerce\Domain\Enums\PaymentStatus;
use App\Modules\Commerce\Domain\Models\Order;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminOperationalDashboardController extends Controller
{
    /**
     * Display Admin Operational Dashboard (/admin/dashboard).
     * Operational Admin Workspace: Candidate Management, Assessment Assignments, Payment Eligibility.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        // 1. Total Registered Candidates
        $totalCandidates = User::role('student')->count();

        // 2. Paid / Eligible Candidates (Candidates with confirmed paid transactions)
        $paidEligibleCandidatesCount = User::role('student')
            ->whereHas('orders', function ($q) {
                $q->whereHas('invoice.payments', fn($p) => $p->whereIn('status', [PaymentStatus::Success, PaymentStatus::Paid]));
            })
            ->count();

        // 3. Active Candidate Test Assignments
        $activeAssignmentsCount = CandidateTestAssignment::where('status', 'active')->count();

        // 4. Completed Tests / Attempts
        $completedAttemptsCount = Attempt::whereIn('status', ['submitted', 'completed'])->count();

        // 5. In-Progress Candidate Attempts (Live exam sessions)
        $inProgressAttemptsCount = Attempt::where('status', 'in_progress')->count();

        // 6. Total Verified Certificates Issued
        $totalCertificatesIssued = Certificate::count();

        // ACTION PANEL: Candidates Requiring Action (Paid Real Test awaiting Admin Assignment)
        $paidOrders = Order::with(['user', 'items.product.test', 'invoice.payments'])
            ->whereHas('invoice.payments', fn($p) => $p->whereIn('status', [PaymentStatus::Success, PaymentStatus::Paid]))
            ->whereHas('items.product.test', fn($t) => $t->where('assessment_mode', 'real_test'))
            ->latest()
            ->get();

        $actionRequiredCandidates = [];
        foreach ($paidOrders as $order) {
            $candidateUser = $order->user;
            if (!$candidateUser) {
                continue;
            }

            foreach ($order->items as $item) {
                $test = $item->product?->test;
                if ($test && $test->isRealTest()) {
                    $hasAssignment = CandidateTestAssignment::where('user_id', $candidateUser->id)
                        ->where('test_id', $test->id)
                        ->where('status', 'active')
                        ->exists();

                    if (!$hasAssignment) {
                        $actionRequiredCandidates[] = [
                            'user'       => $candidateUser,
                            'test'       => $test,
                            'order'      => $order,
                            'paid_at'    => $order->invoice?->paid_at ?? $order->created_at,
                            'type'       => 'PAID_REAL_TEST_UNASSIGNED',
                            'priority'   => 'HIGH',
                        ];
                    }
                }
            }
        }

        // Recent Active Assignments (Live Database Records)
        $recentAssignments = CandidateTestAssignment::with(['user', 'test', 'assignedBy'])
            ->latest('assigned_at')
            ->take(6)
            ->get();

        // Published Tests Catalog for Quick Assignment
        $availableTests = Test::where('is_published', true)
            ->withCount(['assignments as active_assignments_count' => function ($q) {
                $q->where('status', 'active');
            }])
            ->get();

        // Unread Notifications Count
        $unreadNotificationsCount = $user ? $user->unreadNotifications->count() : 0;

        return view('admin.operational_dashboard', compact(
            'totalCandidates',
            'paidEligibleCandidatesCount',
            'activeAssignmentsCount',
            'completedAttemptsCount',
            'inProgressAttemptsCount',
            'totalCertificatesIssued',
            'actionRequiredCandidates',
            'recentAssignments',
            'availableTests',
            'unreadNotificationsCount'
        ));
    }
}
