<?php

namespace App\Modules\Commerce\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Assessment\Models\Test;
use App\Modules\Commerce\Domain\Enums\AssessmentFamily;
use App\Modules\Commerce\Domain\Enums\PaymentStatus;
use App\Modules\Commerce\Domain\Models\Coupon;
use App\Modules\Commerce\Domain\Models\Invoice;
use App\Modules\Commerce\Domain\Models\Payment;
use App\Modules\Commerce\Domain\Models\PriceChangeRequest;
use App\Modules\Commerce\Domain\Models\Product;
use App\Modules\Commerce\Domain\Models\ProductCategory;
use App\Notifications\EnterpriseSystemNotification;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CommerceController extends Controller
{
    /**
     * Display Commerce packages, vouchers, products, and transactions for Admin & Super Admin.
     */
    public function index(): View
    {
        $products = Product::with(['test', 'category', 'pendingPriceChangeRequest'])
            ->latest()
            ->paginate(10, ['*'], 'products_page');

        $categories = ProductCategory::orderBy('name')->get();
        $tests = Test::published()->orderBy('title')->get();
        $assessmentFamilies = AssessmentFamily::cases();

        $totalProductsCount = Product::count();
        $activePackagesCount = Product::where('is_active', true)->count();
        $pendingPriceChangeCount = PriceChangeRequest::where('status', 'pending')->count();
        $couponsCount = Coupon::count();
        $coupons = Coupon::latest()->take(10)->get();

        /** @var view-string $viewName */
        $viewName = 'commerce::index';

        return view($viewName, compact(
            'products',
            'categories',
            'tests',
            'assessmentFamilies',
            'totalProductsCount',
            'activePackagesCount',
            'pendingPriceChangeCount',
            'couponsCount',
            'coupons'
        ));
    }

    /**
     * Store a new product or assessment package with initial price.
     */
    public function storeProduct(Request $request): RedirectResponse
    {
        $validated = $request->validate(Product::validationRules());

        $slug = !empty($validated['slug'])
            ? Str::slug($validated['slug'])
            : Str::slug($validated['title']);

        // Ensure unique slug
        $baseSlug = $slug;
        $counter = 1;
        while (Product::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        $product = Product::create([
            'title'             => $validated['title'],
            'slug'              => $slug,
            'product_type'      => $validated['product_type'],
            'assessment_family' => $validated['assessment_family'] ?? null,
            'description'       => $validated['description'] ?? null,
            'price'             => (float) $validated['price'],
            'is_active'         => $request->boolean('is_active', true),
            'is_featured'       => $request->boolean('is_featured', false),
            'test_id'           => !empty($validated['test_id']) ? $validated['test_id'] : null,
            'category_id'       => !empty($validated['category_id']) ? $validated['category_id'] : null,
            'course_id'         => !empty($validated['course_id']) ? $validated['course_id'] : null,
        ]);

        ActivityLogger::log(
            'PRODUCT_CREATED',
            "Created assessment package product '{$product->title}' (Initial Price: IDR " . number_format($product->price) . ")",
            $product,
            ['product_type' => $product->product_type, 'assessment_family' => $product->assessment_family, 'price' => $product->price]
        );

        return redirect()->route('admin.commerce.index')
            ->with('status', "Assessment Package '{$product->title}' created successfully.");
    }

    /**
     * Update an existing product or assessment package (Non-price metadata only).
     * Price changes must go through the Super Admin proposal workflow.
     */
    public function updateProduct(Request $request, Product $product): RedirectResponse
    {
        $allowedFamilies = implode(',', AssessmentFamily::values());

        $validated = $request->validate([
            'title'             => ['required', 'string', 'max:255'],
            'assessment_family' => ['nullable', 'string', "in:{$allowedFamilies}"],
            'description'       => ['nullable', 'string'],
            'is_active'         => ['boolean'],
            'is_featured'       => ['boolean'],
            'test_id'           => ['nullable', 'exists:tests,id'],
            'category_id'       => ['nullable', 'exists:product_categories,id'],
        ]);

        // Update metadata without mutating existing price
        $product->update([
            'title'             => $validated['title'],
            'assessment_family' => $validated['assessment_family'] ?? null,
            'description'       => $validated['description'] ?? null,
            'is_active'         => $request->boolean('is_active', $product->is_active),
            'is_featured'       => $request->boolean('is_featured', $product->is_featured),
            'test_id'           => !empty($validated['test_id']) ? $validated['test_id'] : null,
            'category_id'       => !empty($validated['category_id']) ? $validated['category_id'] : null,
        ]);

        ActivityLogger::log(
            'PRODUCT_UPDATED',
            "Updated assessment package product metadata for '{$product->title}'",
            $product,
            ['is_active' => $product->is_active]
        );

        return redirect()->route('admin.commerce.index')
            ->with('status', "Product '{$product->title}' metadata updated successfully.");
    }

    /**
     * Propose a price change for an existing product (Requires Super Admin approval).
     */
    public function proposePriceChange(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'proposed_price' => ['required', 'numeric', 'min:0'],
            'reason'         => ['required', 'string', 'max:1000'],
        ]);

        // Check if pending proposal already exists
        if ($product->pendingPriceChangeRequest()->exists()) {
            return back()->withErrors(['error' => "A price change proposal for '{$product->title}' is already pending Super Admin approval."]);
        }

        $proposedPrice = (float) $validated['proposed_price'];
        if ($proposedPrice === (float) $product->price) {
            return back()->withErrors(['error' => "Proposed price (IDR " . number_format($proposedPrice) . ") is identical to the current price."]);
        }

        $changeRequest = PriceChangeRequest::create([
            'product_id'             => $product->id,
            'requested_by'           => $request->user()->id,
            'current_price_snapshot' => $product->price,
            'proposed_price'         => $proposedPrice,
            'reason'                 => $validated['reason'],
            'status'                 => 'pending',
        ]);

        ActivityLogger::log(
            'PRICE_CHANGE_PROPOSED',
            "Proposed price change for '{$product->title}' from IDR " . number_format($product->price) . " to IDR " . number_format($changeRequest->proposed_price) . ". Reason: {$changeRequest->reason}",
            $product,
            [
                'request_id'             => $changeRequest->id,
                'product_id'             => $product->id,
                'current_price_snapshot' => $changeRequest->current_price_snapshot,
                'proposed_price'         => $changeRequest->proposed_price,
                'reason'                 => $changeRequest->reason,
            ]
        );

        // Notify Super Admins
        $superAdmins = User::role('super-admin')->get();
        foreach ($superAdmins as $sa) {
            try {
                $sa->notify(new EnterpriseSystemNotification(
                    title: 'Price Change Proposal Requires Approval',
                    message: "Operational Admin {$request->user()->name} proposed price change for '{$product->title}' (IDR " . number_format($product->price) . " → IDR " . number_format($changeRequest->proposed_price) . ").",
                    type: 'PRICE_CHANGE_APPROVAL_REQUIRED',
                    priority: 'HIGH',
                    entityType: 'price_change_request',
                    entityId: (string) $changeRequest->id,
                    targetUrl: route('admin.approvals.index')
                ));
            } catch (\Throwable $e) {
                // Silently continue
            }
        }

        return redirect()->route('admin.commerce.index')
            ->with('status', "Price change proposal for '{$product->title}' (IDR " . number_format($proposedPrice) . ") submitted for Super Admin approval.");
    }

    /**
     * Toggle product activation status.
     */
    public function toggleProductStatus(Product $product): RedirectResponse
    {
        $newStatus = !$product->is_active;
        $product->update(['is_active' => $newStatus]);

        $statusLabel = $newStatus ? 'activated' : 'deactivated';

        ActivityLogger::log(
            'PRODUCT_STATUS_TOGGLED',
            "Toggled product '{$product->title}' status to " . ($newStatus ? 'ACTIVE' : 'INACTIVE'),
            $product,
            ['is_active' => $newStatus]
        );

        return redirect()->route('admin.commerce.index')
            ->with('status', "Product '{$product->title}' has been {$statusLabel}.");
    }

    /**
     * Store coupon/voucher code (RA and SA only).
     */
    public function storeVoucher(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code'     => ['required', 'string', 'max:50', 'unique:coupons,code'],
            'discount' => ['required', 'numeric', 'min:1', 'max:100'],
        ]);

        $coupon = Coupon::create([
            'code'        => strtoupper($validated['code']),
            'type'        => 'percentage',
            'value'       => (float) $validated['discount'],
            'usage_limit' => 100,
            'used_count'  => 0,
            'is_active'   => true,
        ]);

        ActivityLogger::log(
            'COUPON_CREATED',
            "Created discount coupon '{$coupon->code}' ({$coupon->value}%)",
            $coupon
        );

        return redirect()->route('admin.commerce.index')->with('status', "Voucher {$validated['code']} created successfully.");
    }
}
