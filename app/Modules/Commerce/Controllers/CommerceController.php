<?php

namespace App\Modules\Commerce\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Assessment\Models\Test;
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

        $vouchers = [
            ['code' => 'UAT-FREE-2026', 'discount' => '100% OFF', 'uses' => 42, 'status' => 'ACTIVE'],
            ['code' => 'ICEDU-PROMO-50', 'discount' => '50% OFF', 'uses' => 18, 'status' => 'ACTIVE'],
            ['code' => 'TOEIC-SCHOLAR', 'discount' => '75% OFF', 'uses' => 5, 'status' => 'ACTIVE'],
        ];

        $transactions = [
            ['id' => 'TXN-882194', 'user' => 'student@icedu.org', 'package' => 'TOEIC Full Simulation Test 01', 'amount' => '$25.00', 'status' => 'PAID', 'date' => now()->subHours(3)->format('d M Y, H:i')],
            ['id' => 'TXN-882193', 'user' => 'candidate@icedu.org', 'package' => 'TOEFL ITP Standard Exam', 'amount' => '$35.00', 'status' => 'PAID', 'date' => now()->subHours(8)->format('d M Y, H:i')],
        ];

        /** @var view-string $viewName */
        $viewName = 'commerce::index';

        return view($viewName, compact('tests', 'vouchers', 'transactions'));
    }

    /**
     * Store voucher code.
     */
    public function storeVoucher(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50'],
            'discount' => ['required', 'string', 'max:50'],
        ]);

        return redirect()->route('admin.commerce.index')->with('status', "Voucher {$validated['code']} created successfully.");
    }
}
