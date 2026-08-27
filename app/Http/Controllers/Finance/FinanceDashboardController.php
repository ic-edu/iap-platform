<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Modules\Commerce\Domain\Enums\PaymentStatus;
use App\Modules\Commerce\Domain\Models\Coupon;
use App\Modules\Commerce\Domain\Models\Invoice;
use App\Modules\Commerce\Domain\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FinanceDashboardController extends Controller
{
    /**
     * Display Finance Management & Revenue Analytics Landing Dashboard.
     */
    public function index(Request $request): View
    {
        $grossRevenue = (float) Payment::validCommerce()->whereIn('status', [PaymentStatus::Success, PaymentStatus::Paid])->sum('amount');
        $pendingPaymentsCount = Payment::validCommerce()->where('status', PaymentStatus::Pending)->count();
        $invoicesIssuedCount = Invoice::count();
        $couponsCount = Coupon::count();

        $recentPendingPayments = Payment::validCommerce()
            ->with(['user', 'invoice.order.items.product.test'])
            ->where('status', PaymentStatus::Pending)
            ->latest()
            ->take(5)
            ->get();

        // Human UAT presentation: Show the latest legitimate persistent transaction only
        $latestTransactions = Payment::validCommerce()
            ->with(['user', 'invoice.order.items.product'])
            ->latest()
            ->take(1)
            ->get();

        return view('finance.dashboard', compact(
            'grossRevenue',
            'pendingPaymentsCount',
            'invoicesIssuedCount',
            'couponsCount',
            'recentPendingPayments',
            'latestTransactions'
        ));
    }
}
