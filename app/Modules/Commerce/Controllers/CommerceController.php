<?php

namespace App\Modules\Commerce\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Assessment\Models\Test;
use App\Modules\Commerce\Domain\Enums\PaymentStatus;
use App\Modules\Commerce\Domain\Models\Coupon;
use App\Modules\Commerce\Domain\Models\Invoice;
use App\Modules\Commerce\Domain\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CommerceController extends Controller
{
    /**
     * Display Commerce packages, vouchers, and transactions.
     */
    public function index(): View
    {
        $tests = Test::where('is_published', true)->get();

        $grossRevenue = Payment::whereIn('status', [PaymentStatus::Success, PaymentStatus::Paid])->sum('amount');
        $paymentTransactionsCount = Payment::count();
        $invoicesCount = Invoice::count();
        $couponsCount = Coupon::count();

        $coupons = Coupon::latest()->take(10)->get();

        $transactions = Payment::with(['invoice.order.user', 'invoice.order.items.product', 'user'])
            ->latest()
            ->take(15)
            ->get();

        /** @var view-string $viewName */
        $viewName = 'commerce::index';

        return view($viewName, compact(
            'tests',
            'grossRevenue',
            'paymentTransactionsCount',
            'invoicesCount',
            'couponsCount',
            'coupons',
            'transactions'
        ));
    }

    /**
     * Store coupon/voucher code.
     */
    public function storeVoucher(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:coupons,code'],
            'discount' => ['required', 'numeric', 'min:1'],
        ]);

        Coupon::create([
            'code' => strtoupper($validated['code']),
            'type' => 'percentage',
            'value' => (float) $validated['discount'],
            'usage_limit' => 100,
            'used_count' => 0,
            'is_active' => true,
        ]);

        return redirect()->route('admin.commerce.index')->with('status', "Voucher {$validated['code']} created successfully.");
    }
}
