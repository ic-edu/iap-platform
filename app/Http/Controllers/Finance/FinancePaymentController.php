<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Modules\Commerce\Application\BillingEngine;
use App\Modules\Commerce\Domain\Enums\PaymentStatus;
use App\Modules\Commerce\Domain\Models\Payment;
use App\Services\ActivityLogger;
use App\Services\FinanceReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinancePaymentController extends Controller
{
    public function __construct(
        protected BillingEngine $billingEngine,
        protected FinanceReportService $reportService
    ) {}

    /**
     * Display Canonical Payment & Invoice Reports Workspace with Multi-Factor Filtering.
     */
    public function index(Request $request): View
    {
        $filters = [
            'status'     => $request->query('status', 'all'),
            'search'     => $request->query('search'),
            'start_date' => $request->query('start_date'),
            'end_date'   => $request->query('end_date'),
            'product_id' => $request->query('product_id'),
        ];

        $reportQuery = $this->reportService->buildReportQuery($filters);
        $summary = $this->reportService->calculateFinancialSummary($reportQuery);
        $products = $this->reportService->getFilterProducts();

        $payments = (clone $reportQuery)->paginate(15)->withQueryString();

        // Overall status metrics for top navigation tabs
        $pendingCount = Payment::validCommerce()->where('status', PaymentStatus::Pending)->count();
        $successCount = Payment::validCommerce()->where('status', PaymentStatus::Success)->count();
        $failedCount = Payment::validCommerce()->where('status', PaymentStatus::Failed)->count();

        $pageTitle = 'Payment & Invoice Reports';
        $pageSubtitle = 'Review transaction history, invoice records, and payment proofs.';

        return view('finance.payments.index', [
            'payments'      => $payments,
            'statusFilter'  => $filters['status'],
            'search'        => $filters['search'],
            'startDate'     => $filters['start_date'],
            'endDate'       => $filters['end_date'],
            'productId'     => $filters['product_id'],
            'summary'       => $summary,
            'products'      => $products,
            'pendingCount'  => $pendingCount,
            'successCount'  => $successCount,
            'failedCount'   => $failedCount,
            'pageTitle'     => $pageTitle,
            'pageSubtitle'  => $pageSubtitle,
        ]);
    }

    /**
     * Backward-Compatible Redirect Endpoint for Legacy Pending Queue Route.
     */
    public function pending(Request $request): RedirectResponse
    {
        $status = $request->query('status', 'pending');

        return redirect()->route('finance.payments.index', ['status' => $status]);
    }

    /**
     * Stream CSV export of filtered payments.
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $filters = [
            'status'     => $request->query('status', 'all'),
            'search'     => $request->query('search'),
            'start_date' => $request->query('start_date'),
            'end_date'   => $request->query('end_date'),
            'product_id' => $request->query('product_id'),
        ];

        $query = $this->reportService->buildReportQuery($filters);

        return $this->reportService->exportCsv($query);
    }

    /**
     * Stream XLSX export of filtered payments.
     */
    public function exportXlsx(Request $request): StreamedResponse
    {
        $filters = [
            'status'     => $request->query('status', 'all'),
            'search'     => $request->query('search'),
            'start_date' => $request->query('start_date'),
            'end_date'   => $request->query('end_date'),
            'product_id' => $request->query('product_id'),
        ];

        $query = $this->reportService->buildReportQuery($filters);

        return $this->reportService->exportXlsx($query);
    }

    /**
     * Display Single Payment Transaction Detail for Finance Review.
     */
    public function show(Request $request, Payment $payment): View
    {
        $payment->loadMissing(['user', 'invoice.order.items.product.test', 'invoice.order.coupon.campaign', 'invoice.order.organization']);

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

        $order = $payment->invoice?->order;
        if ($order?->organization) {
            $seats = (int) ($order->items->sum('quantity') ?: 1);
            $seatWord = $seats === 1 ? '1 seat' : "{$seats} seats";
            $statusMsg = "Payment {$payment->reference_number} confirmed successfully. An entitlement pool with {$seatWord} has been provisioned for {$order->organization->name}.";
        } else {
            $statusMsg = "Payment {$payment->reference_number} confirmed successfully. Candidate {$payment->user?->name} is now Paid & Eligible.";
        }

        return redirect()->route('finance.payments.show', $payment->id)
            ->with('status', $statusMsg);
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
