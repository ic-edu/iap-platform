<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\AssessmentRequest;
use App\Models\User;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\CandidateTestAssignment;
use App\Modules\Assessment\Models\Test;
use App\Modules\Certificate\Models\Certificate;
use App\Modules\Commerce\Application\BillingEngine;
use App\Modules\Commerce\Application\CheckoutEngine;
use App\Modules\Commerce\Domain\Enums\AssessmentFamily;
use App\Modules\Commerce\Domain\Enums\PaymentStatus;
use App\Modules\Commerce\Domain\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminCommerceProductManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $financeUser;
    protected User $superAdminUser;
    protected User $candidateUser;
    protected User $teacherUser;
    protected User $rmUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->adminUser = User::create([
            'name'     => 'Operational Admin',
            'email'    => 'admin@icedu.org',
            'password' => bcrypt('password'),
            'status'   => 'active',
        ]);
        $this->adminUser->assignRole('admin');

        $this->financeUser = User::create([
            'name'     => 'Finance Officer',
            'email'    => 'finance@icedu.org',
            'password' => bcrypt('password'),
            'status'   => 'active',
        ]);
        $this->financeUser->assignRole('finance');

        $this->superAdminUser = User::create([
            'name'     => 'Super Administrator',
            'email'    => 'superadmin@icedu.org',
            'password' => bcrypt('password'),
            'status'   => 'active',
        ]);
        $this->superAdminUser->assignRole('super-admin');

        $this->candidateUser = User::create([
            'name'     => 'Candidate Student',
            'email'    => 'candidate@icedu.org',
            'password' => bcrypt('password'),
            'status'   => 'active',
        ]);
        $this->candidateUser->assignRole('student');

        $this->teacherUser = User::create([
            'name'     => 'Teacher User',
            'email'    => 'teacher@icedu.org',
            'password' => bcrypt('password'),
            'status'   => 'active',
        ]);
        $this->teacherUser->assignRole('teacher');

        $this->rmUser = User::create([
            'name'     => 'Repository Manager',
            'email'    => 'rm@icedu.org',
            'password' => bcrypt('password'),
            'status'   => 'active',
        ]);
        $this->rmUser->assignRole('repository-manager');
    }

    protected function createMockTest(string $title, string $type = 'toeic'): Test
    {
        return Test::create([
            'title'            => $title,
            'slug'             => Str::slug($title) . '-' . Str::random(5),
            'test_type'        => $type,
            'assessment_mode'  => 'real_test',
            'duration_minutes' => 120,
            'passing_score'    => 70,
            'max_attempts'     => 2,
            'is_published'     => true,
            'status'           => 'published',
            'author_id'        => $this->adminUser->id,
            'created_by'       => $this->adminUser->id,
        ]);
    }

    public function test_01_authorized_admin_can_view_product_catalog(): void
    {
        $product = Product::create([
            'title'             => 'TOEIC Mock Test Package',
            'slug'              => 'toeic-mock-test-pkg',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'price'             => 750000,
            'is_active'         => true,
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('admin.commerce.index'));

        $response->assertStatus(200);
        $response->assertSee('Assessment Package &amp; Product Catalog', false);
        $response->assertSee('TOEIC Mock Test Package');
        $response->assertSee('750,000');
    }

    public function test_02_finance_cannot_access_product_catalog_and_receives_403(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('admin.commerce.index'));
        $response->assertStatus(403);
    }

    public function test_03_super_admin_access_remains_according_to_existing_permission(): void
    {
        $response = $this->actingAs($this->superAdminUser)->get(route('admin.commerce.index'));
        $response->assertStatus(200);
    }

    public function test_04_candidate_cannot_access_product_admin_ui(): void
    {
        $response = $this->actingAs($this->candidateUser)->get(route('admin.commerce.index'));
        $this->assertTrue(in_array($response->status(), [403, 302]));
    }

    public function test_05_teacher_cannot_access_product_admin_ui(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('admin.commerce.index'));
        $this->assertTrue(in_array($response->status(), [403, 302]));
    }

    public function test_06_repository_manager_cannot_access_product_admin_ui(): void
    {
        $response = $this->actingAs($this->rmUser)->get(route('admin.commerce.index'));
        $this->assertTrue(in_array($response->status(), [403, 302]));
    }

    public function test_07_assessment_package_can_be_created_with_toeic_family_and_null_test_id(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('admin.commerce.products.store'), [
            'title'             => 'TOEIC Official Package',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'price'             => 750000,
            'description'       => 'Full simulation package',
            'is_active'         => 1,
        ]);

        $response->assertRedirect(route('admin.commerce.index'));
        $this->assertDatabaseHas('products', [
            'title'             => 'TOEIC Official Package',
            'assessment_family' => 'toeic',
            'test_id'           => null,
            'is_active'         => 1,
        ]);
    }

    public function test_08_assessment_package_can_be_created_with_toefl_family_and_null_test_id(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('admin.commerce.products.store'), [
            'title'             => 'TOEFL iBT Package',
            'product_type'      => 'assessment',
            'assessment_family' => 'toefl',
            'price'             => 850000,
            'is_active'         => 1,
        ]);

        $response->assertRedirect(route('admin.commerce.index'));
        $this->assertDatabaseHas('products', [
            'title'             => 'TOEFL iBT Package',
            'assessment_family' => 'toefl',
            'test_id'           => null,
        ]);
    }

    public function test_09_assessment_package_can_be_created_with_ielts_family_and_null_test_id(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('admin.commerce.products.store'), [
            'title'             => 'IELTS Academic Package',
            'product_type'      => 'assessment',
            'assessment_family' => 'ielts',
            'price'             => 900000,
            'is_active'         => 1,
        ]);

        $response->assertRedirect(route('admin.commerce.index'));
        $this->assertDatabaseHas('products', [
            'title'             => 'IELTS Academic Package',
            'assessment_family' => 'ielts',
            'test_id'           => null,
        ]);
    }

    public function test_10_assessment_package_can_be_created_with_general_family_and_null_test_id(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('admin.commerce.products.store'), [
            'title'             => 'General Assessment Package',
            'product_type'      => 'assessment',
            'assessment_family' => 'general',
            'price'             => 350000,
            'is_active'         => 1,
        ]);

        $response->assertRedirect(route('admin.commerce.index'));
        $this->assertDatabaseHas('products', [
            'title'             => 'General Assessment Package',
            'assessment_family' => 'general',
            'test_id'           => null,
        ]);
    }

    public function test_11_assessment_package_can_be_created_with_vocational_family_and_null_test_id(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('admin.commerce.products.store'), [
            'title'             => 'Vocational Assessment Package',
            'product_type'      => 'assessment',
            'assessment_family' => 'vocational',
            'price'             => 500000,
            'is_active'         => 1,
        ]);

        $response->assertRedirect(route('admin.commerce.index'));
        $this->assertDatabaseHas('products', [
            'title'             => 'Vocational Assessment Package',
            'assessment_family' => 'vocational',
            'test_id'           => null,
        ]);
    }

    public function test_12_invalid_assessment_family_is_rejected(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('admin.commerce.products.store'), [
            'title'             => 'Invalid Family Package',
            'product_type'      => 'assessment',
            'assessment_family' => 'unknown_fake_family',
            'price'             => 500000,
            'is_active'         => 1,
        ]);

        $response->assertSessionHasErrors(['assessment_family']);
    }

    public function test_13_assessment_product_without_family_is_rejected(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('admin.commerce.products.store'), [
            'title'             => 'Missing Family Package',
            'product_type'      => 'assessment',
            'assessment_family' => '',
            'price'             => 500000,
            'is_active'         => 1,
        ]);

        $response->assertSessionHasErrors(['assessment_family']);
    }

    public function test_14_specific_test_product_can_still_be_created_with_test_id(): void
    {
        $test = $this->createMockTest('Specific TOEIC Test');

        $response = $this->actingAs($this->adminUser)->post(route('admin.commerce.products.store'), [
            'title'             => 'Specific TOEIC Test Product',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'test_id'           => $test->id,
            'price'             => 750000,
            'is_active'         => 1,
        ]);

        $response->assertRedirect(route('admin.commerce.index'));
        $this->assertDatabaseHas('products', [
            'title'   => 'Specific TOEIC Test Product',
            'test_id' => $test->id,
        ]);
    }

    public function test_15_invalid_test_id_is_rejected(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('admin.commerce.products.store'), [
            'title'             => 'Invalid Test Product',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'test_id'           => 'non-existent-test-ulid',
            'price'             => 750000,
            'is_active'         => 1,
        ]);

        $response->assertSessionHasErrors(['test_id']);
    }

    public function test_16_product_can_be_activated_and_deactivated(): void
    {
        $product = Product::create([
            'title'             => 'Toggle Test Package',
            'slug'              => 'toggle-test-package',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'price'             => 750000,
            'is_active'         => true,
        ]);

        $response = $this->actingAs($this->adminUser)->post(route('admin.commerce.products.toggle', $product->id));
        $response->assertRedirect(route('admin.commerce.index'));
        $this->assertFalse($product->fresh()->is_active);

        $response2 = $this->actingAs($this->adminUser)->post(route('admin.commerce.products.toggle', $product->id));
        $response2->assertRedirect(route('admin.commerce.index'));
        $this->assertTrue($product->fresh()->is_active);
    }

    public function test_17_inactive_package_is_not_visible_in_candidate_store(): void
    {
        Product::create([
            'title'             => 'Hidden Inactive Package',
            'slug'              => 'hidden-inactive-pkg',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'price'             => 750000,
            'is_active'         => false,
        ]);

        $response = $this->actingAs($this->candidateUser)->get(route('candidate.store'));
        $response->assertStatus(200);
        $response->assertDontSee('Hidden Inactive Package');
    }

    public function test_18_active_abstract_package_is_visible_in_candidate_store_even_when_test_id_null(): void
    {
        Product::create([
            'title'             => 'Visible Abstract TOEIC Package',
            'slug'              => 'visible-abstract-toeic-pkg',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'test_id'           => null,
            'price'             => 750000,
            'is_active'         => true,
        ]);

        $response = $this->actingAs($this->candidateUser)->get(route('candidate.store'));
        $response->assertStatus(200);
        $response->assertSee('Visible Abstract TOEIC Package');
        $response->assertSee('TOEIC');
    }

    public function test_19_candidate_store_does_not_require_published_test_for_abstract_package(): void
    {
        $this->assertEquals(0, Test::count());

        Product::create([
            'title'             => 'Standalone TOEIC Package',
            'slug'              => 'standalone-toeic-pkg',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'test_id'           => null,
            'price'             => 750000,
            'is_active'         => true,
        ]);

        $response = $this->actingAs($this->candidateUser)->get(route('candidate.store'));
        $response->assertStatus(200);
        $response->assertSee('Standalone TOEIC Package');
    }

    public function test_20_product_detail_displays_package_metadata_without_fake_test_details(): void
    {
        $product = Product::create([
            'title'             => 'TOEIC Package Detail Test',
            'slug'              => 'toeic-pkg-detail-test',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'test_id'           => null,
            'price'             => 750000,
            'is_active'         => true,
        ]);

        $response = $this->actingAs($this->candidateUser)->get(route('candidate.store.show', $product->id));

        $response->assertStatus(200);
        $response->assertSee('TOEIC Package Detail Test');
        $response->assertSee('Assessment Package Details');
        $response->assertSee('2 Exam Attempts');
        $response->assertDontSee('Minutes'); // No fake test duration
    }

    public function test_21_checkout_engine_accepts_abstract_assessment_package(): void
    {
        $product = Product::create([
            'title'             => 'TOEIC Abstract Package',
            'slug'              => 'toeic-abstract-pkg-test',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'test_id'           => null,
            'price'             => 750000,
            'is_active'         => true,
        ]);

        $checkoutEngine = app(CheckoutEngine::class);
        $result = $checkoutEngine->checkout($this->candidateUser, $product);

        $this->assertNotNull($result['order']);
        $this->assertNotNull($result['invoice']);
    }

    public function test_22_checkout_creates_order_order_item_invoice_payment_pending(): void
    {
        $product = Product::create([
            'title'             => 'TOEIC Flow Package',
            'slug'              => 'toeic-flow-package',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'test_id'           => null,
            'price'             => 750000,
            'is_active'         => true,
        ]);

        $checkoutEngine = app(CheckoutEngine::class);
        $billingEngine = app(BillingEngine::class);

        $result = $checkoutEngine->checkout($this->candidateUser, $product);
        $order = $result['order'];
        $invoice = $result['invoice'];

        $this->assertEquals(\App\Modules\Commerce\Domain\Enums\OrderStatus::Pending, $order->status);
        $this->assertEquals(\App\Modules\Commerce\Domain\Enums\InvoiceStatus::Unpaid, $invoice->status);
        $this->assertCount(1, $order->items);
        $this->assertEquals($product->id, $order->items->first()->product_id);

        $payment = $billingEngine->createPayment($invoice, 'manual_transfer');
        $this->assertEquals(PaymentStatus::Pending, $payment->status);
    }

    public function test_23_creating_product_does_not_create_candidate_test_assignment(): void
    {
        $this->actingAs($this->adminUser)->post(route('admin.commerce.products.store'), [
            'title'             => 'TOEIC Isolation Test Package',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'price'             => 750000,
            'is_active'         => 1,
        ]);

        $this->assertEquals(0, CandidateTestAssignment::count());
    }

    public function test_24_creating_product_does_not_create_attempt(): void
    {
        $this->actingAs($this->adminUser)->post(route('admin.commerce.products.store'), [
            'title'             => 'TOEIC Attempt Isolation Package',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'price'             => 750000,
            'is_active'         => 1,
        ]);

        $this->assertEquals(0, Attempt::count());
    }

    public function test_25_creating_product_does_not_create_certificate(): void
    {
        $this->actingAs($this->adminUser)->post(route('admin.commerce.products.store'), [
            'title'             => 'TOEIC Certificate Isolation Package',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'price'             => 750000,
            'is_active'         => 1,
        ]);

        $this->assertEquals(0, Certificate::count());
    }

    public function test_26_creating_product_does_not_create_assessment_request(): void
    {
        $this->actingAs($this->adminUser)->post(route('admin.commerce.products.store'), [
            'title'             => 'TOEIC Request Isolation Package',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'price'             => 750000,
            'is_active'         => 1,
        ]);

        $this->assertEquals(0, AssessmentRequest::count());
    }

    public function test_27_product_update_does_not_alter_historical_order_item_pricing(): void
    {
        $product = Product::create([
            'title'             => 'Original Price Package',
            'slug'              => 'orig-price-package',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'price'             => 500000,
            'is_active'         => true,
        ]);

        $checkoutEngine = app(CheckoutEngine::class);
        $result = $checkoutEngine->checkout($this->candidateUser, $product);
        $orderItem = $result['order']->items->first();
        $this->assertEquals(500000, $orderItem->price);

        // Update product price via Super Admin price change approval workflow
        $priceChange = \App\Modules\Commerce\Domain\Models\PriceChangeRequest::create([
            'product_id'             => $product->id,
            'requested_by'           => $this->adminUser->id,
            'current_price_snapshot' => 500000,
            'proposed_price'         => 990000,
            'reason'                 => 'Price increase',
            'status'                 => 'pending',
        ]);
        $this->actingAs($this->superAdminUser)->post(route('admin.approvals.price-changes.approve', $priceChange->id));

        $this->assertEquals(990000, $product->fresh()->price);
        $this->assertEquals(500000, $orderItem->fresh()->price);
    }

    public function test_28_product_deactivation_preserves_historical_transactions(): void
    {
        $product = Product::create([
            'title'             => 'Deactivate Historical Package',
            'slug'              => 'deact-hist-package',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'price'             => 500000,
            'is_active'         => true,
        ]);

        $checkoutEngine = app(CheckoutEngine::class);
        $result = $checkoutEngine->checkout($this->candidateUser, $product);
        $orderId = $result['order']->id;

        $this->actingAs($this->adminUser)->post(route('admin.commerce.products.toggle', $product->id));

        $this->assertFalse($product->fresh()->is_active);
        $this->assertDatabaseHas('orders', ['id' => $orderId]);
    }

    public function test_29_duplicate_provisioning_is_idempotent(): void
    {
        Artisan::call('iap:commerce:provision-assessment-packages');
        $count1 = Product::count();

        Artisan::call('iap:commerce:provision-assessment-packages');
        $count2 = Product::count();

        $this->assertEquals(5, $count1);
        $this->assertEquals(5, $count2);
    }

    public function test_30_provisioning_command_does_not_create_duplicate_packages(): void
    {
        Artisan::call('iap:commerce:provision-assessment-packages');
        Artisan::call('iap:commerce:provision-assessment-packages');

        $this->assertEquals(1, Product::where('slug', 'toeic-mock-test-package')->count());
        $this->assertEquals(1, Product::where('slug', 'toefl-ibt-mock-test-package')->count());
        $this->assertEquals(1, Product::where('slug', 'ielts-assessment-package')->count());
        $this->assertEquals(1, Product::where('slug', 'general-assessment-package')->count());
        $this->assertEquals(1, Product::where('slug', 'vocational-assessment-package')->count());
    }

    public function test_31_provisioning_command_keeps_test_id_null(): void
    {
        Artisan::call('iap:commerce:provision-assessment-packages');

        $packages = Product::all();
        foreach ($packages as $pkg) {
            $this->assertNull($pkg->test_id);
            $this->assertTrue($pkg->is_active);
            $this->assertEquals('assessment', $pkg->product_type);
        }
    }

    public function test_32_product_management_actions_are_audited_using_existing_activity_log(): void
    {
        $this->actingAs($this->adminUser)->post(route('admin.commerce.products.store'), [
            'title'             => 'Audited Package',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'price'             => 750000,
            'is_active'         => 1,
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'action'  => 'PRODUCT_CREATED',
            'user_id' => $this->adminUser->id,
        ]);
    }

    public function test_33_light_theme_admin_commerce_contract_passes(): void
    {
        $this->adminUser->setThemePreference('light');

        $response = $this->actingAs($this->adminUser)->get(route('admin.commerce.index'));
        $response->assertStatus(200);
        $response->assertSee('data-theme="light"', false);
    }

    public function test_34_dark_theme_admin_commerce_contract_passes(): void
    {
        $this->adminUser->setThemePreference('dark');

        $response = $this->actingAs($this->adminUser)->get(route('admin.commerce.index'));
        $response->assertStatus(200);
        $response->assertSee('data-theme="dark"', false);
    }

    public function test_35_global_theme_contract_remains_intact(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('admin.commerce.index'));
        $response->assertStatus(200);
        $response->assertSee('Assessment Package &amp; Product Catalog', false);
        $response->assertSee('setIapTheme');
    }
}
