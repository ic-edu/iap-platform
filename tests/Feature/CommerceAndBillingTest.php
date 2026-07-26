<?php

use App\Models\User;
use App\Modules\Academic\Models\Course;
use App\Modules\Academic\Models\CourseCategory;
use App\Modules\Assessment\Models\Test;
use App\Modules\Commerce\Application\BillingEngine;
use App\Modules\Commerce\Application\CartEngine;
use App\Modules\Commerce\Application\CheckoutEngine;
use App\Modules\Commerce\Application\CouponEngine;
use App\Modules\Commerce\Application\InvoiceEngine;
use App\Modules\Commerce\Application\PricingEngine;
use App\Modules\Commerce\Application\SubscriptionEngine;
use App\Modules\Commerce\Domain\Enums\InvoiceStatus;
use App\Modules\Commerce\Domain\Enums\OrderStatus;
use App\Modules\Commerce\Domain\Enums\PaymentStatus;
use App\Modules\Commerce\Domain\Enums\SubscriptionStatus;
use App\Modules\Commerce\Domain\Models\Coupon;
use App\Modules\Commerce\Domain\Models\Product;
use App\Modules\Commerce\Domain\Models\ProductCategory;
use App\Modules\Commerce\Events\CheckoutCompleted;
use App\Modules\Commerce\Events\InvoiceGenerated;
use App\Modules\Commerce\Events\PaymentCancelled;
use App\Modules\Commerce\Events\PaymentConfirmed;
use App\Modules\Commerce\Events\PaymentCreated;
use App\Modules\Commerce\Events\PaymentRefunded;
use App\Modules\Commerce\Infrastructure\ManualTransferGateway;
use App\Modules\Commerce\Infrastructure\MidtransGateway;
use App\Modules\Commerce\Infrastructure\StripeGateway;
use App\Modules\Commerce\Infrastructure\XenditGateway;
use App\Modules\QuestionBank\Enums\TestType;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('pricing engine calculates base price, discount and tax correctly', function () {
    $engine = new PricingEngine;
    $cat = ProductCategory::create(['name' => 'Prep', 'slug' => 'prep']);
    $product = Product::create(['title' => 'TOEFL Prep', 'slug' => 'toefl-prep', 'product_type' => 'toefl_prep', 'category_id' => $cat->id, 'price' => 100000]);
    $coupon = Coupon::create(['code' => 'DISC10', 'type' => 'percentage', 'value' => 10, 'usage_limit' => 10, 'is_active' => true]);

    $calc = $engine->calculate($product, 1, $coupon, 11.0);

    expect($calc['base_price'])->toBe(100000.0);
    expect($calc['discount'])->toBe(10000.0);
    expect($calc['tax'])->toBe(9900.0);
    expect($calc['grand_total'])->toBe(99900.0);
});

test('cart engine adds, updates, and calculates totals', function () {
    $cart = new CartEngine;
    $cat = ProductCategory::create(['name' => 'Cart Cat', 'slug' => 'cart-cat']);
    $p1 = Product::create(['title' => 'Test 1', 'slug' => 't1', 'product_type' => 'placement_test', 'category_id' => $cat->id, 'price' => 50000]);

    $cart->addItem($p1, 2);
    $summary = $cart->getSummary(11.0);

    expect($summary['subtotal'])->toBe(100000.0);
    expect($summary['items_count'])->toBe(1);

    $cart->clear();
    expect($cart->getSummary()['items_count'])->toBe(0);
});

test('cart engine updates existing product quantity when added again', function () {
    $cart = new CartEngine;
    $cat = ProductCategory::create(['name' => 'Add Cat', 'slug' => 'add-cat']);
    $p1 = Product::create(['title' => 'Test Add', 'slug' => 't-add', 'product_type' => 'placement_test', 'category_id' => $cat->id, 'price' => 50000]);

    $cart->addItem($p1, 1);
    $cart->addItem($p1, 2);

    $summary = $cart->getSummary(11.0);
    expect($summary['subtotal'])->toBe(150000.0);
});

test('checkout engine processes order and generates invoice', function () {
    Event::fake([CheckoutCompleted::class, InvoiceGenerated::class]);
    $pricing = new PricingEngine;
    $invoiceEngine = new InvoiceEngine;
    $checkout = new CheckoutEngine($pricing, $invoiceEngine);

    $user = User::factory()->create();
    $cat = ProductCategory::create(['name' => 'Checkout Cat', 'slug' => 'checkout-cat']);
    $product = Product::create(['title' => 'IELTS Prep', 'slug' => 'ielts-prep', 'product_type' => 'ielts_prep', 'category_id' => $cat->id, 'price' => 200000]);

    $res = $checkout->checkout($user, $product);

    expect($res['order']->status)->toBe(OrderStatus::Pending);
    expect($res['invoice']->invoice_number)->toContain('INV-');
    Event::assertDispatched(CheckoutCompleted::class);
    Event::assertDispatched(InvoiceGenerated::class);
});

test('checkout engine calculates correct subtotal and grand total with coupon and tax', function () {
    $pricing = new PricingEngine;
    $invoiceEngine = new InvoiceEngine;
    $checkout = new CheckoutEngine($pricing, $invoiceEngine);

    $user = User::factory()->create();
    $cat = ProductCategory::create(['name' => 'Cout Cat', 'slug' => 'cout-cat']);
    $product = Product::create(['title' => 'Course P', 'slug' => 'course-p', 'product_type' => 'toefl_prep', 'category_id' => $cat->id, 'price' => 100000]);
    $coupon = Coupon::create(['code' => 'HALF10', 'type' => 'percentage', 'value' => 50, 'usage_limit' => 10, 'is_active' => true]);

    $res = $checkout->checkout($user, $product, 1, $coupon);
    expect($res['order']->discount)->toBe(50000.0);
    expect($res['order']->grand_total)->toBe(55500.0);
});

test('invoice engine generates formatted invoice number and due date', function () {
    $engine = new InvoiceEngine;
    $user = User::factory()->create();
    $cat = ProductCategory::create(['name' => 'Inv Cat', 'slug' => 'inv-cat']);
    $product = Product::create(['title' => 'P1', 'slug' => 'p1', 'product_type' => 'membership', 'category_id' => $cat->id, 'price' => 150000]);
    $pricing = new PricingEngine;
    $checkout = new CheckoutEngine($pricing, $engine);

    $res = $checkout->checkout($user, $product);
    $invoice = $res['invoice'];

    expect($invoice->invoice_number)->toMatch('/^INV-\d{8}-[A-Z0-9]{4}$/');
    expect($invoice->status)->toBe(InvoiceStatus::Unpaid);
});

test('billing engine creates, confirms payment and fires event', function () {
    Event::fake([PaymentCreated::class, PaymentConfirmed::class]);
    $billing = new BillingEngine;
    $invoiceEngine = new InvoiceEngine;
    $pricing = new PricingEngine;
    $checkout = new CheckoutEngine($pricing, $invoiceEngine);

    $user = User::factory()->create();
    $cat = ProductCategory::create(['name' => 'Bill Cat', 'slug' => 'bill-cat']);
    $product = Product::create(['title' => 'P2', 'slug' => 'p2', 'product_type' => 'membership', 'category_id' => $cat->id, 'price' => 100000]);
    $checkoutRes = $checkout->checkout($user, $product);
    $invoice = $checkoutRes['invoice'];

    $payment = $billing->createPayment($invoice, 'manual_transfer');
    expect($payment->status)->toBe(PaymentStatus::Pending);
    Event::assertDispatched(PaymentCreated::class);

    $confirmed = $billing->confirmPayment($payment, 'TXN-999');
    expect($confirmed->status)->toBe(PaymentStatus::Success);
    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid);
    expect($checkoutRes['order']->fresh()->status)->toBe(OrderStatus::Completed);
    Event::assertDispatched(PaymentConfirmed::class);
});

test('billing engine handles payment cancellation and refund', function () {
    Event::fake([PaymentCancelled::class, PaymentRefunded::class]);
    $billing = new BillingEngine;
    $invoiceEngine = new InvoiceEngine;
    $pricing = new PricingEngine;
    $checkout = new CheckoutEngine($pricing, $invoiceEngine);

    $user = User::factory()->create();
    $cat = ProductCategory::create(['name' => 'Cancel Cat', 'slug' => 'cancel-cat']);
    $product = Product::create(['title' => 'P3', 'slug' => 'p3', 'product_type' => 'membership', 'category_id' => $cat->id, 'price' => 100000]);
    $checkoutRes = $checkout->checkout($user, $product);

    $p1 = $billing->createPayment($checkoutRes['invoice'], 'manual_transfer');
    $billing->cancelPayment($p1);
    Event::assertDispatched(PaymentCancelled::class);

    $p2 = $billing->createPayment($checkoutRes['invoice'], 'manual_transfer');
    $billing->refundPayment($p2);
    Event::assertDispatched(PaymentRefunded::class);
});

test('payment confirmation automatically activates course enrollment and test assignment', function () {
    $billing = new BillingEngine;
    $invoiceEngine = new InvoiceEngine;
    $pricing = new PricingEngine;
    $checkout = new CheckoutEngine($pricing, $invoiceEngine);

    $user = User::factory()->create();
    $teacher = User::factory()->create();

    $courseCat = CourseCategory::create(['name' => 'Auto Cat', 'slug' => 'auto-cat']);
    $course = Course::create(['title' => 'Auto Course', 'slug' => 'auto-course', 'code' => 'AC1', 'category_id' => $courseCat->id, 'is_published' => true]);
    $test = Test::create(['title' => 'Auto Test', 'slug' => 'auto-test', 'test_type' => TestType::General, 'duration_minutes' => 60, 'pass_score' => 70, 'is_published' => true, 'created_by' => $teacher->id]);

    $prodCat = ProductCategory::create(['name' => 'Prod Cat', 'slug' => 'prod-cat']);
    $product = Product::create([
        'title' => 'Combo Package',
        'slug' => 'combo-pkg',
        'product_type' => 'toefl_prep',
        'category_id' => $prodCat->id,
        'price' => 250000,
        'course_id' => $course->id,
        'test_id' => $test->id,
    ]);

    $checkoutRes = $checkout->checkout($user, $product);
    $payment = $billing->createPayment($checkoutRes['invoice']);

    $billing->confirmPayment($payment, 'TXN-AUTO-123');

    expect($user->enrollments()->where('course_id', $course->id)->exists())->toBeTrue();
});

test('coupon engine validates coupon active, expired, and usage limits', function () {
    $engine = new CouponEngine;
    $coupon = Coupon::create(['code' => 'SAVE20', 'type' => 'percentage', 'value' => 20, 'usage_limit' => 5, 'is_active' => true]);

    $val1 = $engine->validateCoupon('SAVE20');
    expect($val1['valid'])->toBeTrue();

    $val2 = $engine->validateCoupon('INVALIDCODE');
    expect($val2['valid'])->toBeFalse();
});

test('coupon engine blocks validation when usage limit is reached', function () {
    $engine = new CouponEngine;
    $coupon = Coupon::create(['code' => 'LIMIT1', 'type' => 'percentage', 'value' => 20, 'usage_limit' => 1, 'used_count' => 1, 'is_active' => true]);

    $res = $engine->validateCoupon('LIMIT1');
    expect($res['valid'])->toBeFalse();
    expect($res['reason'])->toContain('usage limit reached');
});

test('subscription engine activates and expires subscription', function () {
    $engine = new SubscriptionEngine;
    $user = User::factory()->create();

    $sub = $engine->activateSubscription($user, null, 'monthly');
    expect($sub->status)->toBe(SubscriptionStatus::Active);

    $expired = $engine->expireSubscription($sub);
    expect($expired->status)->toBe(SubscriptionStatus::Expired);
});

test('subscription engine defaults to monthly plan when unknown plan type is passed', function () {
    $engine = new SubscriptionEngine;
    $user = User::factory()->create();

    $sub = $engine->activateSubscription($user, null, 'unknown_plan');
    expect($sub->plan_type)->toBe('unknown_plan');
    expect($sub->ends_at)->not->toBeNull();
});

test('manual transfer gateway returns charge and verification structure', function () {
    $gateway = new ManualTransferGateway;
    $user = User::factory()->create();
    $cat = ProductCategory::create(['name' => 'Man Cat', 'slug' => 'man-cat']);
    $product = Product::create(['title' => 'Man P', 'slug' => 'man-p', 'product_type' => 'toefl_prediction', 'category_id' => $cat->id, 'price' => 50000]);
    $checkout = new CheckoutEngine(new PricingEngine, new InvoiceEngine);
    $res = $checkout->checkout($user, $product);

    $charge = $gateway->charge($res['invoice']);
    expect($charge['success'])->toBeTrue();
    expect($charge['message'])->toContain('BCA');

    $verify = $gateway->verify($charge['transaction_id']);
    expect($verify['status'])->toBe('success');
});

test('midtrans gateway placeholder returns snap token response', function () {
    $gateway = new MidtransGateway;
    $user = User::factory()->create();
    $cat = ProductCategory::create(['name' => 'Mid Cat', 'slug' => 'mid-cat']);
    $product = Product::create(['title' => 'Mid P', 'slug' => 'mid-p', 'product_type' => 'toefl_prediction', 'category_id' => $cat->id, 'price' => 50000]);
    $checkout = new CheckoutEngine(new PricingEngine, new InvoiceEngine);
    $res = $checkout->checkout($user, $product);

    $charge = $gateway->charge($res['invoice']);
    expect($charge['success'])->toBeTrue();
    expect($charge['redirect_url'])->toContain('midtrans.com');
});

test('xendit gateway placeholder returns invoice url response', function () {
    $gateway = new XenditGateway;
    $user = User::factory()->create();
    $cat = ProductCategory::create(['name' => 'Xnd Cat', 'slug' => 'xnd-cat']);
    $product = Product::create(['title' => 'Xnd P', 'slug' => 'xnd-p', 'product_type' => 'toefl_prediction', 'category_id' => $cat->id, 'price' => 50000]);
    $checkout = new CheckoutEngine(new PricingEngine, new InvoiceEngine);
    $res = $checkout->checkout($user, $product);

    $charge = $gateway->charge($res['invoice']);
    expect($charge['success'])->toBeTrue();
    expect($charge['redirect_url'])->toContain('xendit.co');
});

test('stripe gateway placeholder returns checkout session response', function () {
    $gateway = new StripeGateway;
    $user = User::factory()->create();
    $cat = ProductCategory::create(['name' => 'Stp Cat', 'slug' => 'stp-cat']);
    $product = Product::create(['title' => 'Stp P', 'slug' => 'stp-p', 'product_type' => 'toefl_prediction', 'category_id' => $cat->id, 'price' => 50000]);
    $checkout = new CheckoutEngine(new PricingEngine, new InvoiceEngine);
    $res = $checkout->checkout($user, $product);

    $charge = $gateway->charge($res['invoice']);
    expect($charge['success'])->toBeTrue();
    expect($charge['redirect_url'])->toContain('stripe.com');
});

test('cart engine handles product with fixed discount coupon correctly', function () {
    $cart = new CartEngine;
    $cat = ProductCategory::create(['name' => 'Fix Cat', 'slug' => 'fix-cat']);
    $product = Product::create(['title' => 'Fix P', 'slug' => 'fix-p', 'product_type' => 'toefl_prep', 'category_id' => $cat->id, 'price' => 100000]);
    $coupon = Coupon::create(['code' => 'FIX20', 'type' => 'fixed', 'value' => 20000, 'usage_limit' => 10, 'is_active' => true]);

    $cart->addItem($product, 1);
    $cart->applyCoupon($coupon);
    $summary = $cart->getSummary(11.0);

    expect($summary['discount'])->toBe(20000.0);
    expect($summary['tax'])->toBe(8800.0);
});

test('coupon engine blocks expired coupon validation', function () {
    $engine = new CouponEngine;
    $coupon = Coupon::create(['code' => 'OLD10', 'type' => 'percentage', 'value' => 10, 'usage_limit' => 5, 'expires_at' => now()->subDay(), 'is_active' => true]);

    $res = $engine->validateCoupon('OLD10');
    expect($res['valid'])->toBeFalse();
    expect($res['reason'])->toContain('expired');
});

test('subscription engine handles quarterly and yearly plan types', function () {
    $engine = new SubscriptionEngine;
    $user = User::factory()->create();

    $subQuarter = $engine->activateSubscription($user, null, 'quarterly');
    expect($subQuarter->ends_at)->not->toBeNull();

    $subYear = $engine->activateSubscription($user, null, 'yearly');
    expect($subYear->ends_at)->not->toBeNull();
});

test('manual transfer gateway refunds and cancels transaction', function () {
    $gateway = new ManualTransferGateway;
    $refund = $gateway->refund('MAN-123', 50000);
    expect($refund['success'])->toBeTrue();

    $cancel = $gateway->cancel('MAN-123');
    expect($cancel['success'])->toBeTrue();
});
