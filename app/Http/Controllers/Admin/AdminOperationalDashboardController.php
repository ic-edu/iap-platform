<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssessmentRequest;
use App\Models\User;
use App\Modules\Assessment\Engines\AssignmentEngine;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\CandidateTestAssignment;
use App\Modules\Assessment\Models\Test;
use App\Modules\Certificate\Models\Certificate;
use App\Modules\Commerce\Domain\Enums\PaymentStatus;
use App\Modules\Commerce\Domain\Models\Coupon;
use App\Modules\Commerce\Domain\Models\Order;
use App\Modules\Commerce\Domain\Services\VoucherOperationalSummary;
use App\Modules\Organization\Enums\OrganizationStatus;
use App\Modules\Organization\Models\Organization;
use App\Modules\Organization\Models\OrganizationSeatAllocation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminOperationalDashboardController extends Controller
{
    /**
     * Display Admin Operational Dashboard (/admin/dashboard).
     * Operational Admin Workspace: Candidate Management, Assessment Assignments, Payment Eligibility.
     */
    public function index(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if ($user && $user->hasRole('repository-manager') && ! $user->hasRole(['admin', 'super-admin'])) {
            return redirect()->route('admin.repository-manager.dashboard');
        }

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

        // 7. Pending Organization Approvals (Awaiting Super Admin review)
        $pendingOrganizationsCount = Organization::where('status', OrganizationStatus::Pending)->count();

        // 8. Active Organizations (Approved & operational institutions)
        $activeOrganizationsCount = Organization::where('status', OrganizationStatus::Active)->count();

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

        // INSTITUTIONAL ACTION PANEL: Active Seat Allocations Awaiting RA Assessment Assignment (O3)
        $institutionalSeatsAwaitingAssignment = OrganizationSeatAllocation::with([
            'membership.user',
            'membership.groups',
            'entitlement.product',
            'entitlement.organization',
            'entitlement.orderItem.order',
        ])
        ->where('status', 'active')
        ->whereDoesntHave('testAssignments', fn($q) => $q->where('status', 'active'))
        ->whereHas('entitlement', fn($q) => $q->where('status', 'active'))
        ->whereHas('membership', fn($q) => $q->where('status', 'active'))
        ->latest('allocated_at')
        ->get();

        $assignmentEngine = app(AssignmentEngine::class);
        $eligibleTestsByProduct = [];
        foreach ($institutionalSeatsAwaitingAssignment as $allocation) {
            $product = $allocation->entitlement?->product;
            if ($product && !isset($eligibleTestsByProduct[$product->id])) {
                $eligibleTestsByProduct[$product->id] = $assignmentEngine->getEligibleTestsForPackage($product);
            }
        }

        // Resolve Active Assessment Requests for Institutional Candidates Awaiting Assignment
        $candidateIds = $institutionalSeatsAwaitingAssignment
            ->pluck('membership.user_id')
            ->filter()
            ->unique()
            ->values();

        $activeRequests = AssessmentRequest::with(['test.assignedTeacher', 'candidate', 'requester'])
            ->whereIn('status', ['pending', 'draft_created'])
            ->whereIn('candidate_id', $candidateIds)
            ->get()
            ->filter(fn($req) => $req->isActive());

        $activeRequestsByAllocationId = [];
        foreach ($institutionalSeatsAwaitingAssignment as $allocation) {
            $candidateUser = $allocation->membership?->user;
            if (!$candidateUser) {
                continue;
            }

            $product = $allocation->entitlement?->product;
            $familyCode = $product?->getEffectiveFamily() ?? (is_object($product?->assessment_family) ? $product->assessment_family->value : ($product?->assessment_family ?? null));
            if (empty($familyCode)) {
                if ($product && stripos($product->name, 'toeic') !== false) {
                    $familyCode = 'toeic';
                } elseif ($product && stripos($product->name, 'toefl') !== false) {
                    $familyCode = 'toefl';
                } elseif ($product && stripos($product->name, 'ielts') !== false) {
                    $familyCode = 'ielts';
                } else {
                    $familyCode = 'toeic';
                }
            }
            $familyCode = strtolower($familyCode);

            $org = $allocation->entitlement?->organization;
            $groups = $allocation->membership?->groups ?? collect();
            $groupContext = $groups->isNotEmpty() ? ' — ' . $groups->pluck('name')->join(', ') : '';
            $progContext = trim(($org?->name ?? 'Organization') . $groupContext);

            // Unified shared matcher across dashboard, prefilled forms, and server duplicate check
            $matched = AssessmentRequest::findMatchingInCollection(
                $activeRequests,
                $candidateUser->id,
                $familyCode,
                $progContext
            );

            if ($matched) {
                $activeRequestsByAllocationId[$allocation->id] = $matched;
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

        // Commercial & Voucher Operations Snapshot (Canonical Shared Read Model)
        $voucherSummary = VoucherOperationalSummary::get();

        return view('admin.operational_dashboard', array_merge([
            'totalCandidates'                      => $totalCandidates,
            'paidEligibleCandidatesCount'          => $paidEligibleCandidatesCount,
            'activeAssignmentsCount'               => $activeAssignmentsCount,
            'completedAttemptsCount'               => $completedAttemptsCount,
            'inProgressAttemptsCount'              => $inProgressAttemptsCount,
            'totalCertificatesIssued'              => $totalCertificatesIssued,
            'pendingOrganizationsCount'            => $pendingOrganizationsCount,
            'activeOrganizationsCount'             => $activeOrganizationsCount,
            'actionRequiredCandidates'             => $actionRequiredCandidates,
            'institutionalSeatsAwaitingAssignment' => $institutionalSeatsAwaitingAssignment,
            'eligibleTestsByProduct'               => $eligibleTestsByProduct,
            'activeRequestsByAllocationId'         => $activeRequestsByAllocationId,
            'recentAssignments'                    => $recentAssignments,
            'availableTests'                       => $availableTests,
            'unreadNotificationsCount'             => $unreadNotificationsCount,
        ], $voucherSummary));
    }

    /**
     * Assign a published assessment to an active institutional seat allocation (O3).
     */
    public function assignInstitutionalSeat(
        Request $request,
        OrganizationSeatAllocation $allocation,
        AssignmentEngine $assignmentEngine
    ): RedirectResponse {
        $request->validate([
            'test_id' => ['required', 'string', 'exists:tests,id'],
        ]);

        $test = Test::findOrFail($request->input('test_id'));

        try {
            $assignment = $assignmentEngine->assignFromOrganizationSeat($allocation, $test, $request->user());

            $candidateName = $allocation->membership?->user?->name ?? 'Candidate';
            $memberId = $allocation->membership?->member_id ? " ({$allocation->membership->member_id})" : '';

            return redirect()->back()->with('status', "Successfully assigned {$test->title} to {$candidateName}{$memberId}.");
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
