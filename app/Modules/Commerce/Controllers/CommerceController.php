<?php

namespace App\Modules\Commerce\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Assessment\Models\Test;
use App\Modules\Commerce\Application\CouponGenerator;
use App\Modules\Commerce\Domain\Enums\AssessmentFamily;
use App\Modules\Commerce\Domain\Enums\PaymentStatus;
use App\Modules\Commerce\Domain\Models\Coupon;
use App\Modules\Commerce\Domain\Models\CouponCampaign;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CommerceController extends Controller
{
    public function __construct(
        protected CouponGenerator $couponGenerator
    ) {}

    /**
     * Display Commerce packages, vouchers, campaigns, and products for Admin & Super Admin.
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
        $coupons = Coupon::with(['campaign'])->latest()->take(10)->get();

        $campaignsCount = CouponCampaign::count();
        $campaigns = CouponCampaign::withCount('coupons')
            ->with(['creator'])
            ->latest()
            ->take(10)
            ->get();

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
            'coupons',
            'campaignsCount',
            'campaigns'
        ));
    }

    /**
     * Show form to create/generate a new Voucher Campaign.
     */
    public function createCampaign(): View
    {
        $assessmentFamilies = AssessmentFamily::cases();
        $products = Product::where('is_active', true)
            ->orderBy('title')
            ->get(['id', 'title', 'price', 'product_type', 'assessment_family', 'slug']);

        /** @var view-string $viewName */
        $viewName = 'commerce::campaigns.create';

        return view($viewName, compact('assessmentFamilies', 'products'));
    }

    /**
     * Store and generate a new Voucher Campaign.
     */
    public function storeCampaign(Request $request): RedirectResponse
    {
        $allowedFamilies = implode(',', AssessmentFamily::values());

        $validated = $request->validate([
            'name'              => ['required', 'string', 'max:255'],
            'assessment_family' => ['required', 'string', "in:{$allowedFamilies}"],
            'scope_mode'        => ['required', 'string', 'in:all_products_in_family,selected_products'],
            'products'          => ['nullable', 'array'],
            'products.*'        => ['exists:products,id'],
            'discount_type'     => ['required', 'string', 'in:percentage,fixed'],
            'discount_value'    => ['required', 'numeric', 'min:1'],
            'valid_from'        => ['nullable', 'date'],
            'valid_until'       => ['required', 'date'],
            'generation_mode'   => ['required', 'string', 'in:shared,batch'],
            'code_prefix'       => ['nullable', 'string', 'max:20'],
            'code_length'       => ['nullable', 'integer', 'min:4', 'max:12'],
            'total_codes'       => ['nullable', 'integer', 'min:1', 'max:500'],
            'uses_per_code'     => ['nullable', 'integer', 'min:1'],
            'is_active'         => ['nullable', 'boolean'],
        ]);

        if ($validated['discount_type'] === 'percentage' && (float) $validated['discount_value'] > 100) {
            return back()->withErrors(['discount_value' => 'Percentage discount cannot exceed 100%.'])->withInput();
        }

        $validFrom = !empty($validated['valid_from'])
            ? Carbon::parse($validated['valid_from'])
            : now();
        $validUntil = Carbon::parse($validated['valid_until']);

        if ($validUntil->lte($validFrom)) {
            return back()->withErrors(['valid_until' => 'The valid until date must be after the valid from date.'])->withInput();
        }

        $family = $validated['assessment_family'];

        if ($validated['scope_mode'] === 'selected_products') {
            if (empty($validated['products'])) {
                return back()->withErrors(['products' => 'Please select at least one eligible product for the selected products scope.'])->withInput();
            }

            $selectedProducts = Product::whereIn('id', $validated['products'])->get();
            foreach ($selectedProducts as $prod) {
                $prodFamily = $prod->getEffectiveFamily();
                if (empty($prodFamily) || strtolower($prodFamily) !== strtolower($family)) {
                    return back()->withErrors([
                        'products' => "The selected product '{$prod->title}' does not belong to the " . strtoupper($family) . " assessment family.",
                    ])->withInput();
                }
            }
        }

        $prefix = !empty($validated['code_prefix'])
            ? strtoupper(trim($validated['code_prefix']))
            : CouponGenerator::getDefaultPrefix($family);

        $totalCodes = $validated['generation_mode'] === 'shared'
            ? 1
            : (!empty($validated['total_codes']) ? (int) $validated['total_codes'] : 1);

        $usesPerCode = !empty($validated['uses_per_code']) ? (int) $validated['uses_per_code'] : 1;

        $campaign = DB::transaction(function () use ($validated, $family, $prefix, $usesPerCode, $totalCodes, $validFrom, $validUntil, $request, &$generatedCoupons) {
            $campaign = CouponCampaign::create([
                'name'              => $validated['name'],
                'assessment_family' => $family,
                'scope_mode'        => $validated['scope_mode'],
                'discount_type'     => $validated['discount_type'],
                'discount_value'    => (float) $validated['discount_value'],
                'generation_mode'   => $validated['generation_mode'],
                'code_prefix'       => $prefix,
                'code_length'       => !empty($validated['code_length']) ? (int) $validated['code_length'] : 6,
                'uses_per_code'     => $usesPerCode,
                'total_codes'       => $totalCodes,
                'valid_from'        => $validFrom,
                'valid_until'       => $validUntil,
                'is_active'         => $request->has('is_active') ? $request->boolean('is_active') : true,
                'created_by'        => $request->user()?->id,
            ]);

            if ($validated['scope_mode'] === 'selected_products' && !empty($validated['products'])) {
                $campaign->products()->sync(array_unique($validated['products']));
            }

            $generatedCoupons = $this->couponGenerator->generateForCampaign($campaign);

            return $campaign;
        });

        ActivityLogger::log(
            'CAMPAIGN_CREATED',
            "Created promotional campaign '{$campaign->name}' and generated {$generatedCoupons->count()} voucher code(s)",
            $campaign,
            [
                'campaign_id'       => $campaign->id,
                'family'            => $campaign->assessment_family,
                'scope_mode'        => $campaign->scope_mode,
                'discount'          => "{$campaign->discount_value} " . ($campaign->discount_type === 'percentage' ? '%' : 'IDR'),
                'generated_count'   => $generatedCoupons->count(),
            ]
        );

        return redirect()->route('admin.commerce.campaigns.show', $campaign->id)
            ->with('status', "Voucher Campaign '{$campaign->name}' created successfully with {$generatedCoupons->count()} generated code(s).");
    }

    /**
     * Display Single Voucher Campaign Detail and Codes.
     */
    public function showCampaign(CouponCampaign $campaign): View
    {
        $campaign->loadMissing(['products', 'creator']);
        $coupons = $campaign->coupons()
            ->withCount([
                'redemptions as reserved_count' => fn($q) => $q->where('status', 'reserved'),
                'redemptions as consumed_count' => fn($q) => $q->where('status', 'consumed'),
            ])
            ->latest()
            ->paginate(25);

        $totalCodes = $campaign->coupons()->count();
        $activeCodes = $campaign->coupons()->where('is_active', true)->count();
        $totalConsumed = (int) $campaign->coupons()->sum('used_count');
        $totalReserved = (int) $campaign->coupons()->withCount(['redemptions as r_count' => fn($q) => $q->where('status', 'reserved')])->get()->sum('r_count');

        /** @var view-string $viewName */
        $viewName = 'commerce::campaigns.show';

        return view($viewName, compact(
            'campaign',
            'coupons',
            'totalCodes',
            'activeCodes',
            'totalConsumed',
            'totalReserved'
        ));
    }

    /**
     * Toggle campaign active/inactive status.
     */
    public function toggleCampaignStatus(CouponCampaign $campaign): RedirectResponse
    {
        $newStatus = !$campaign->is_active;
        $campaign->update(['is_active' => $newStatus]);
        $campaign->coupons()->update(['is_active' => $newStatus]);

        $action = $newStatus ? 'CAMPAIGN_ACTIVATED' : 'CAMPAIGN_DEACTIVATED';
        ActivityLogger::log(
            $action,
            "Toggled campaign '{$campaign->name}' status to " . ($newStatus ? 'ACTIVE' : 'INACTIVE'),
            $campaign,
            ['is_active' => $newStatus]
        );

        return back()->with('status', "Campaign '{$campaign->name}' status updated to " . ($newStatus ? 'ACTIVE' : 'INACTIVE') . '.');
    }

    /**
     * Soft-delete a campaign.
     */
    public function destroyCampaign(CouponCampaign $campaign): RedirectResponse
    {
        $campaignName = $campaign->name;
        $campaign->coupons()->delete();
        $campaign->delete();

        ActivityLogger::log(
            'CAMPAIGN_DELETED',
            "Archived campaign '{$campaignName}'",
            $campaign
        );

        return redirect()->route('admin.commerce.index')
            ->with('status', "Campaign '{$campaignName}' archived successfully.");
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
