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
use Illuminate\Support\Carbon;
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
     * Store coupon/voucher code with finite validity (RA and SA only).
     */
    public function storeVoucher(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code'        => ['required', 'string', 'max:50', 'unique:coupons,code'],
            'discount'    => ['required', 'numeric', 'min:1', 'max:100'],
            'valid_from'  => ['nullable', 'date'],
            'valid_until' => ['required', 'date'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'is_active'   => ['nullable', 'boolean'],
        ]);

        $validFrom = !empty($validated['valid_from'])
            ? Carbon::parse($validated['valid_from'])
            : now();
        $validUntil = Carbon::parse($validated['valid_until']);

        if ($validUntil->lte($validFrom)) {
            return back()->withErrors(['valid_until' => 'The valid until date must be after the valid from date.'])->withInput();
        }

        $isActive = $request->has('is_active') ? $request->boolean('is_active') : true;

        $coupon = Coupon::create([
            'code'        => strtoupper(trim($validated['code'])),
            'type'        => 'percentage',
            'value'       => (float) $validated['discount'],
            'usage_limit' => !empty($validated['usage_limit']) ? (int) $validated['usage_limit'] : 100,
            'used_count'  => 0,
            'valid_from'  => $validFrom,
            'valid_until' => $validUntil,
            'expires_at'  => $validUntil,
            'is_active'   => $isActive,
        ]);

        ActivityLogger::log(
            'VOUCHER_CREATED',
            "Created promotional voucher '{$coupon->code}' ({$coupon->value}% OFF, valid {$coupon->valid_from->format('d M Y')} to {$coupon->valid_until->format('d M Y')})",
            $coupon,
            [
                'code'        => $coupon->code,
                'value'       => $coupon->value,
                'valid_from'  => $coupon->valid_from->toDateTimeString(),
                'valid_until' => $coupon->valid_until->toDateTimeString(),
                'is_active'   => $coupon->is_active,
            ]
        );

        return redirect()->route('admin.commerce.index')->with('status', "Voucher {$coupon->code} created successfully.");
    }

    /**
     * Update an existing voucher's validity and metadata (RA and SA only).
     */
    public function updateVoucher(Request $request, Coupon $coupon): RedirectResponse
    {
        $validated = $request->validate([
            'discount'    => ['required', 'numeric', 'min:1', 'max:100'],
            'valid_from'  => ['nullable', 'date'],
            'valid_until' => ['required', 'date'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'is_active'   => ['nullable', 'boolean'],
        ]);

        $validFrom = !empty($validated['valid_from'])
            ? Carbon::parse($validated['valid_from'])
            : ($coupon->valid_from ?? now());
        $validUntil = Carbon::parse($validated['valid_until']);

        if ($validUntil->lte($validFrom)) {
            return back()->withErrors(['valid_until' => 'The valid until date must be after the valid from date.'])->withInput();
        }

        $validityChanged = ($coupon->valid_from?->toDateTimeString() !== $validFrom->toDateTimeString())
            || ($coupon->valid_until?->toDateTimeString() !== $validUntil->toDateTimeString());

        $coupon->update([
            'value'       => (float) $validated['discount'],
            'usage_limit' => !empty($validated['usage_limit']) ? (int) $validated['usage_limit'] : $coupon->usage_limit,
            'valid_from'  => $validFrom,
            'valid_until' => $validUntil,
            'expires_at'  => $validUntil,
            'is_active'   => $request->has('is_active') ? $request->boolean('is_active') : $coupon->is_active,
        ]);

        if ($validityChanged) {
            ActivityLogger::log(
                'VOUCHER_VALIDITY_UPDATED',
                "Updated validity period for voucher '{$coupon->code}' ({$coupon->valid_from->format('d M Y')} to {$coupon->valid_until->format('d M Y')})",
                $coupon,
                [
                    'valid_from'  => $coupon->valid_from->toDateTimeString(),
                    'valid_until' => $coupon->valid_until->toDateTimeString(),
                ]
            );
        } else {
            ActivityLogger::log(
                'VOUCHER_UPDATED',
                "Updated voucher '{$coupon->code}'",
                $coupon,
                ['value' => $coupon->value, 'is_active' => $coupon->is_active]
            );
        }

        return redirect()->route('admin.commerce.index')
            ->with('status', "Voucher '{$coupon->code}' updated successfully.");
    }

    /**
     * Toggle voucher active/inactive state.
     */
    public function toggleVoucherStatus(Coupon $coupon): RedirectResponse
    {
        if (!$coupon->is_active && ($coupon->valid_until === null || $coupon->valid_until->isPast())) {
            return back()->withErrors(['error' => "Cannot activate voucher '{$coupon->code}' without a future expiration date. Please update its validity period first."]);
        }

        $newStatus = !$coupon->is_active;
        $coupon->update(['is_active' => $newStatus]);

        $action = $newStatus ? 'VOUCHER_ACTIVATED' : 'VOUCHER_DEACTIVATED';
        ActivityLogger::log(
            $action,
            "Toggled voucher '{$coupon->code}' status to " . ($newStatus ? 'ACTIVE' : 'INACTIVE'),
            $coupon,
            ['is_active' => $newStatus]
        );

        return redirect()->route('admin.commerce.index')
            ->with('status', "Voucher '{$coupon->code}' status updated.");
    }

    /**
     * Safely delete an unused voucher.
     */
    public function destroyVoucher(Coupon $coupon): RedirectResponse
    {
        if (!$coupon->isDeletable()) {
            return back()->withErrors(['error' => 'This voucher has redemption history and cannot be deleted. Deactivate it instead to preserve transaction history.']);
        }

        $code = $coupon->code;
        $usedCount = $coupon->used_count;

        ActivityLogger::log(
            'VOUCHER_DELETED',
            "Deleted promotional voucher '{$code}'",
            $coupon,
            ['code' => $code, 'used_count' => $usedCount]
        );

        $coupon->delete();

        return redirect()->route('admin.commerce.index')
            ->with('status', "Voucher '{$code}' deleted successfully.");
    }
}
