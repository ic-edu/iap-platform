<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FinanceDashboardController extends Controller
{
    /**
     * Display Finance Management & Revenue Analytics Landing Dashboard.
     */
    public function index(Request $request): View
    {
        $vouchers = [
            ['code' => 'UAT-FREE-2026', 'discount' => '100% OFF', 'uses' => 42, 'status' => 'ACTIVE'],
            ['code' => 'ICEDU-PROMO-50', 'discount' => '50% OFF', 'uses' => 18, 'status' => 'ACTIVE'],
            ['code' => 'TOEIC-SCHOLAR', 'discount' => '75% OFF', 'uses' => 5, 'status' => 'ACTIVE'],
        ];

        $transactions = [
            ['id' => 'TXN-882194', 'user' => 'student@icedu.org', 'package' => 'TOEIC Full Simulation Test 01', 'amount' => '$25.00', 'status' => 'PAID', 'date' => now()->subHours(3)->format('d M Y, H:i')],
            ['id' => 'TXN-882193', 'user' => 'candidate@icedu.org', 'package' => 'TOEFL ITP Standard Exam', 'amount' => '$35.00', 'status' => 'PAID', 'date' => now()->subHours(8)->format('d M Y, H:i')],
            ['id' => 'TXN-882192', 'user' => 'finance.test@icedu.org', 'package' => 'IELTS Academic Prep Package', 'amount' => '$45.00', 'status' => 'PAID', 'date' => now()->subHours(14)->format('d M Y, H:i')],
        ];

        $grossRevenue = '$14,850.00';
        $completedTransactionsCount = 342;
        $invoicesIssuedCount = 289;

        return view('finance.dashboard', compact(
            'vouchers',
            'transactions',
            'grossRevenue',
            'completedTransactionsCount',
            'invoicesIssuedCount'
        ));
    }
}
