<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AttemptResource;
use App\Http\Resources\CertificateResource;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\OrderResource;
use App\Http\Resources\QuestionBankResource;
use App\Http\Resources\TestResource;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\Test;
use App\Modules\Certificate\Models\Certificate;
use App\Modules\Commerce\Domain\Models\Invoice;
use App\Modules\Commerce\Domain\Models\Order;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Modules\Reporting\Services\SystemHealthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InternalApiController extends Controller
{
    public function questionBanks(): JsonResponse
    {
        $banks = QuestionBank::paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'Question banks retrieved successfully.',
            'data' => QuestionBankResource::collection($banks),
            'meta' => ['total' => $banks->total()],
        ]);
    }

    public function tests(): JsonResponse
    {
        $tests = Test::paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'Tests retrieved successfully.',
            'data' => TestResource::collection($tests),
            'meta' => ['total' => $tests->total()],
        ]);
    }

    public function userAttempts(Request $request): JsonResponse
    {
        $user = $request->user();
        $attempts = Attempt::where('user_id', $user->id)->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'User attempts retrieved successfully.',
            'data' => AttemptResource::collection($attempts),
            'meta' => ['total' => $attempts->total()],
        ]);
    }

    public function userCertificates(Request $request): JsonResponse
    {
        $user = $request->user();
        $certs = Certificate::where('user_id', $user->id)->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'User certificates retrieved successfully.',
            'data' => CertificateResource::collection($certs),
            'meta' => ['total' => $certs->total()],
        ]);
    }

    public function userOrders(Request $request): JsonResponse
    {
        $user = $request->user();
        $orders = Order::where('user_id', $user->id)->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'User orders retrieved successfully.',
            'data' => OrderResource::collection($orders),
            'meta' => ['total' => $orders->total()],
        ]);
    }

    public function userInvoices(Request $request): JsonResponse
    {
        $user = $request->user();
        $invoices = Invoice::where('user_id', $user->id)->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'User invoices retrieved successfully.',
            'data' => InvoiceResource::collection($invoices),
            'meta' => ['total' => $invoices->total()],
        ]);
    }

    public function systemHealth(SystemHealthService $healthService): JsonResponse
    {
        $report = $healthService->checkHealth();

        return response()->json([
            'success' => true,
            'message' => 'System health report generated.',
            'data' => $report,
            'meta' => ['timestamp' => now()->toIso8601String()],
        ]);
    }
}
