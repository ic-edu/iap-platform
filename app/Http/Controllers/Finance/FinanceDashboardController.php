<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Modules\Commerce\Domain\Enums\InvoiceStatus;
use App\Modules\Commerce\Domain\Enums\PaymentStatus;
use App\Modules\Commerce\Domain\Models\Invoice;
use App\Modules\Commerce\Domain\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class FinanceDashboardController extends Controller
{
    /**
     * Display Finance Management & Cash Collections Landing Dashboard.
     */
    public function index(Request $request): View
    {
        $period = $request->query('period', 'all_time');

        $paymentQuery = Payment::validCommerce();
        $invoiceQuery = Invoice::query();

        // Apply period filter if selected
        if ($period === 'today') {
            $start = Carbon::today()->startOfDay();
            $paymentQuery->where('created_at', '>=', $start);
            $invoiceQuery->where('created_at', '>=', $start);
        } elseif ($period === 'this_month') {
            $start = Carbon::now()->startOfMonth();
            $paymentQuery->where('created_at', '>=', $start);
            $invoiceQuery->where('created_at', '>=', $start);
        } elseif ($period === 'this_quarter') {
            $start = Carbon::now()->startOfQuarter();
            $paymentQuery->where('created_at', '>=', $start);
            $invoiceQuery->where('created_at', '>=', $start);
        } elseif ($period === 'this_year') {
            $start = Carbon::now()->startOfYear();
            $paymentQuery->where('created_at', '>=', $start);
            $invoiceQuery->where('created_at', '>=', $start);
        } else {
            $period = 'all_time';
        }

        // 1. Gross Cash Collections (Including Tax)
        $grossCashCollections = (float) (clone $paymentQuery)
            ->whereIn('status', [PaymentStatus::Success, PaymentStatus::Paid])
            ->sum('amount');

        // 2. Pending Approvals
        $pendingPaymentsCount = (clone $paymentQuery)
            ->where('status', PaymentStatus::Pending)
            ->count();

        // 3. Invoices Issued
        $invoicesIssuedCount = (clone $invoiceQuery)->count();

        // 4. Unpaid Invoices
        $unpaidInvoicesCount = (clone $invoiceQuery)
            ->where('status', InvoiceStatus::Unpaid)
            ->count();

        // 5. Recent Pending Payments Queue
        $recentPendingPayments = Payment::validCommerce()
            ->with(['user', 'invoice.order.items.product.test'])
            ->where('status', PaymentStatus::Pending)
            ->latest('created_at')
            ->take(5)
            ->get();

        // 6. Latest Legitimate Persistent Transaction
        $latestTransactions = Payment::validCommerce()
            ->with(['user', 'invoice.order.items.product'])
            ->latest('created_at')
            ->take(1)
            ->get();

        return view('finance.dashboard', compact(
            'grossCashCollections',
            'pendingPaymentsCount',
            'invoicesIssuedCount',
            'unpaidInvoicesCount',
            'recentPendingPayments',
            'latestTransactions',
            'period'
        ));
    }
}
