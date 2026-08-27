<?php

namespace App\Modules\Commerce\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Assessment\Models\Test;
use App\Modules\Commerce\Domain\Enums\AssessmentFamily;
use App\Modules\Commerce\Domain\Enums\PaymentStatus;
use App\Modules\Commerce\Domain\Models\Coupon;
use App\Modules\Commerce\Domain\Models\Invoice;
use App\Modules\Commerce\Domain\Models\Payment;
use App\Modules\Commerce\Domain\Models\Product;
use App\Modules\Commerce\Domain\Models\ProductCategory;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CommerceController extends Controller
{
    /**
     * Display Commerce packages, vouchers, products, and transactions.
     */
    public function index(): View
    {
        $products = Product::with(['test', 'category'])
            ->latest()
            ->paginate(10, ['*'], 'products_page');

        $categories = ProductCategory::orderBy('name')->get();
        $tests = Test::where('is_published', true)->orderBy('title')->get();
        $assessmentFamilies = AssessmentFamily::cases();

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
            'products',
            'categories',
            'tests',
            'assessmentFamilies',
            'grossRevenue',
            'paymentTransactionsCount',
            'invoicesCount',
            'couponsCount',
            'coupons',
            'transactions'
        ));
    }

    /**
     * Store a new product or assessment package.
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
            "Created assessment package product '{$product->title}'",
            $product,
            ['product_type' => $product->product_type, 'assessment_family' => $product->assessment_family, 'price' => $product->price]
        );

        return redirect()->route('admin.commerce.index')
            ->with('status', "Product '{$product->title}' created successfully.");
    }

    /**
     * Update an existing product or assessment package.
     */
    public function updateProduct(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate(Product::validationRules($product->id));

        $product->update([
            'title'             => $validated['title'],
            'assessment_family' => $validated['assessment_family'] ?? null,
            'description'       => $validated['description'] ?? null,
            'price'             => (float) $validated['price'],
            'is_active'         => $request->boolean('is_active', true),
            'is_featured'       => $request->boolean('is_featured', false),
            'test_id'           => !empty($validated['test_id']) ? $validated['test_id'] : null,
            'category_id'       => !empty($validated['category_id']) ? $validated['category_id'] : null,
        ]);

        ActivityLogger::log(
            'PRODUCT_UPDATED',
            "Updated assessment package product '{$product->title}'",
            $product,
            ['price' => $product->price, 'is_active' => $product->is_active]
        );

        return redirect()->route('admin.commerce.index')
            ->with('status', "Product '{$product->title}' updated successfully.");
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
     * Store coupon/voucher code.
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
