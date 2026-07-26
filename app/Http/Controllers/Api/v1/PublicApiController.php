<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CourseResource;
use App\Http\Resources\ProductResource;
use App\Modules\Academic\Models\Course;
use App\Modules\Academic\Models\CourseCategory;
use App\Modules\CMS\Models\Announcement;
use App\Modules\Commerce\Domain\Models\Product;
use App\Services\VerificationService;
use Illuminate\Http\JsonResponse;

class PublicApiController extends Controller
{
    public function __construct(protected VerificationService $verificationService) {}

    /**
     * Public Certificate Verification Endpoint.
     */
    public function verifyCertificate(string $code): JsonResponse
    {
        $result = $this->verificationService->verify($code);

        return response()->json([
            'success' => $result['status'] === 'valid',
            'message' => $result['message'],
            'data' => $result['certificate'] ? [
                'certificate_number' => $result['certificate']->certificate_number,
                'verification_code' => $result['certificate']->verification_code,
                'recipient_name' => $result['certificate']->user ? $result['certificate']->user->name : 'Candidate',
                'status' => $result['certificate']->status->value ?? $result['certificate']->status,
                'issued_at' => $result['certificate']->issued_at?->toIso8601String(),
            ] : null,
            'meta' => ['verification_status' => $result['status']],
        ]);
    }

    /**
     * Public Courses Catalogue.
     */
    public function courses(): JsonResponse
    {
        $courses = Course::where('is_published', true)->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'Public courses retrieved successfully.',
            'data' => CourseResource::collection($courses),
            'meta' => [
                'current_page' => $courses->currentPage(),
                'last_page' => $courses->lastPage(),
                'total' => $courses->total(),
            ],
        ]);
    }

    /**
     * Public Products Catalogue.
     */
    public function products(): JsonResponse
    {
        $products = Product::where('is_active', true)->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'Public products retrieved successfully.',
            'data' => ProductResource::collection($products),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'total' => $products->total(),
            ],
        ]);
    }

    /**
     * Public Announcements.
     */
    public function announcements(): JsonResponse
    {
        $announcements = Announcement::latest()->take(10)->get();

        return response()->json([
            'success' => true,
            'message' => 'Public announcements retrieved.',
            'data' => $announcements,
            'meta' => ['count' => $announcements->count()],
        ]);
    }

    /**
     * Public Course Categories.
     */
    public function categories(): JsonResponse
    {
        $categories = CourseCategory::all();

        return response()->json([
            'success' => true,
            'message' => 'Public course categories retrieved.',
            'data' => $categories,
            'meta' => ['count' => $categories->count()],
        ]);
    }
}
