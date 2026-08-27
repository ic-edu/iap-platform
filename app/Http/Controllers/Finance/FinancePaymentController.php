<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Modules\Commerce\Application\BillingEngine;
use App\Modules\Commerce\Domain\Enums\PaymentStatus;
use App\Modules\Commerce\Domain\Models\Payment;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FinancePaymentController extends Controller
{
    public function __construct(
        protected BillingEngine $billingEngine
    ) {}

    /**
     * Display Finance Pending Payments Queue and Payment Reports.
     */
    public function index(Request $request): View
    {
        $isPendingRoute = $request->routeIs('finance.payments.pending');

        if ($request->has('status')) {
            $statusFilter = $request->query('status');
        } else {
            $statusFilter = $isPendingRoute ? 'pending' : 'all';
        }

        $baseQuery = Payment::validCommerce()
            ->with(['user', 'invoice.order.items.product.test'])
            ->latest();

        if ($statusFilter === 'all') {
            $query = clone $baseQuery;
        } elseif (in_array($statusFilter, ['pending', 'success', 'failed', 'refunded'], true)) {
            $query = (clone $baseQuery)->where('status', $statusFilter);
        } else {
            $statusFilter = $isPendingRoute ? 'pending' : 'all';
            $query = $isPendingRoute
                ? (clone $baseQuery)->where('status', PaymentStatus::Pending)
                : clone $baseQuery;
        }

        $payments = $query->paginate(15)->withQueryString();

        $pendingCount = Payment::validCommerce()->where('status', PaymentStatus::Pending)->count();
        $successCount = Payment::validCommerce()->where('status', PaymentStatus::Success)->count();
        $failedCount = Payment::validCommerce()->where('status', PaymentStatus::Failed)->count();

        $pageTitle = $isPendingRoute
            ? 'Payment Review & Approval Queue'
            : 'Payment & Invoice Reports';

        $pageSubtitle = $isPendingRoute
            ? 'Review candidate payment proofs and confirm or reject pending transactions.'
            : 'Review transaction history, invoice records, and payment activity.';

        $tabRoute = $isPendingRoute
            ? 'finance.payments.pending'
            : 'finance.payments.index';

        return view('finance.payments.index', compact(
            'payments',
            'statusFilter',
            'pendingCount',
            'successCount',
            'failedCount',
            'isPendingRoute',
            'pageTitle',
            'pageSubtitle',
            'tabRoute'
        ));
    }

    /**
     * Display Single Payment Transaction Detail for Finance Review.
     */
    public function show(Request $request, Payment $payment): View
    {
        $payment->loadMissing(['user', 'invoice.order.items.product.test']);

        return view('finance.payments.show', compact('payment'));
    }

    /**
     * Securely Stream Payment Evidence File for Finance Verification.
     */
    public function viewProof(Request $request, Payment $payment): BinaryFileResponse|RedirectResponse
    {
        if (!$payment->proof_path || !Storage::disk('local')->exists($payment->proof_path)) {
            abort(404, 'Payment proof document not found.');
        }

        $filePath = Storage::disk('local')->path($payment->proof_path);

        return response()->file($filePath, [
            'Content-Disposition' => 'inline; filename="' . ($payment->proof_original_name ?? 'proof.pdf') . '"',
        ]);
    }

    /**
     * Approve/Confirm Pending Payment.
     */
    public function approve(Request $request, Payment $payment): RedirectResponse
    {
        if ($payment->status !== PaymentStatus::Pending) {
            $statusVal = is_object($payment->status) ? $payment->status->value : $payment->status;
            return back()->withErrors(['error' => "Cannot approve payment. Current status is already {$statusVal}."]);
        }

        $transactionId = $request->input('transaction_id') ?: ('TXN-FI-' . strtoupper(bin2hex(random_bytes(4))));

        // 1. Execute canonical billing engine confirmation
        $this->billingEngine->confirmPayment($payment, $transactionId);

        // 2. Audit log
        ActivityLogger::log(
            action: 'PAYMENT_APPROVED',
            description: "Finance confirmed payment #{$payment->reference_number} (Amount: IDR " . number_format($payment->amount) . ")",
            subject: $payment,
            properties: [
                'payment_id'       => $payment->id,
                'reference_number' => $payment->reference_number,
                'amount'           => $payment->amount,
                'transaction_id'   => $transactionId,
                'confirmed_by'     => Auth::id(),
                'candidate_id'     => $payment->user_id,
            ]
        );

        return redirect()->route('finance.payments.show', $payment->id)
            ->with('status', "Payment {$payment->reference_number} confirmed successfully. Candidate {$payment->user?->name} is now Paid & Eligible.");
    }

    /**
     * Reject/Cancel Pending Payment.
     */
    public function reject(Request $request, Payment $payment): RedirectResponse
    {
        if ($payment->status !== PaymentStatus::Pending) {
            $statusVal = is_object($payment->status) ? $payment->status->value : $payment->status;
            return back()->withErrors(['error' => "Cannot reject payment. Current status is already {$statusVal}."]);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $reason = $validated['reason'];

        // 1. Execute canonical billing engine cancellation
        $this->billingEngine->cancelPayment($payment, $reason);

        // 2. Audit log
        ActivityLogger::log(
            action: 'PAYMENT_REJECTED',
            description: "Finance rejected payment #{$payment->reference_number}. Reason: {$reason}",
            subject: $payment,
            properties: [
                'payment_id'       => $payment->id,
                'reference_number' => $payment->reference_number,
                'amount'           => $payment->amount,
                'reason'           => $reason,
                'rejected_by'      => Auth::id(),
                'candidate_id'     => $payment->user_id,
            ]
        );

        return redirect()->route('finance.payments.show', $payment->id)
            ->with('status', "Payment {$payment->reference_number} has been rejected and cancelled.");
    }
}
