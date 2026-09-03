<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Academic\Models\Course;
use App\Modules\Assessment\Domain\Enums\AssessmentMode;
use App\Modules\Assessment\Engines\AssignmentEngine;
use App\Modules\Assessment\Models\CandidateTestAssignment;
use App\Modules\Assessment\Models\Test;
use App\Modules\Commerce\Application\BillingEngine;
use App\Modules\Commerce\Application\CheckoutEngine;
use App\Modules\Commerce\Domain\Enums\OrderStatus;
use App\Modules\Commerce\Domain\Enums\PaymentStatus;
use App\Modules\Commerce\Domain\Models\Order;
use App\Modules\Commerce\Domain\Models\Product;
use App\Modules\QuestionBank\Enums\TestType;
use App\Notifications\EnterpriseSystemNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class CandidatePortalInformationArchitectureTest extends TestCase
{
    use RefreshDatabase;

    protected User $candidate;
    protected User $financeUser;
    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->candidate = User::create([
            'name' => 'Candidate Student',
            'email' => 'student@icedu.org',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
        $this->candidate->assignRole('student');

        $this->financeUser = User::create([
            'name' => 'Finance Manager',
            'email' => 'finance@icedu.org',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
        $this->financeUser->assignRole('finance');

        $this->adminUser = User::create([
            'name' => 'Operational Admin',
            'email' => 'admin@icedu.org',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
        $this->adminUser->assignRole('admin');
    }

    protected function createTest(string $title, string $type = 'toeic', string $mode = 'real_test', bool $isPublished = true, string $status = 'published'): Test
    {
        return Test::create([
            'title' => $title,
            'slug' => Str::slug($title) . '-' . Str::random(5),
            'test_type' => $type,
            'assessment_mode' => $mode,
            'duration_minutes' => 120,
            'passing_score' => 70,
            'max_attempts' => 2,
            'is_published' => $isPublished,
            'status' => $status,
            'author_id' => $this->adminUser->id,
            'created_by' => $this->adminUser->id,
        ]);
    }

    public function test_01_candidate_top_navigation_contains_only_portal_dashboard(): void
    {
        $response = $this->actingAs($this->candidate)->get(route('candidate.portal'));

        $response->assertStatus(200);
        $response->assertSee('aria-label="Dashboard"', false);
        
        $html = $response->getContent();
        
        $this->assertStringContainsString('aria-label="Dashboard"', $html);
        $this->assertStringNotContainsString('🛍️ Store', $html);
        $this->assertStringNotContainsString('🎓 Certificates</a>', $html);
    }

    public function test_02_legacy_backend_routes_remain_reachable_by_authorized_candidate(): void
    {
        $routes = [
            'candidate.available-tests',
            'candidate.store',
            'candidate.orders.index',
            'candidate.invoices.index',
            'candidate.payments.index',
            'candidate.my-attempts',
            'candidate.my-certificates',
        ];

        foreach ($routes as $routeName) {
            $this->assertTrue(Route::has($routeName), "Route {$routeName} must exist.");
            $response = $this->actingAs($this->candidate)->get(route($routeName));
            $response->assertStatus(200);
        }
    }

    public function test_03_available_tests_kpi_is_clickable_when_count_greater_than_zero(): void
    {
        $test = $this->createTest('Sample Assessment', 'general', 'simulator', true, 'published');

        $response = $this->actingAs($this->candidate)->get(route('candidate.portal'));

        $response->assertStatus(200);
        $response->assertSee(route('candidate.available-tests'));
        $response->assertSee('Start or resume test');
    }

    public function test_04_available_tests_kpi_remains_clickable_when_count_equals_zero(): void
    {
        $response = $this->actingAs($this->candidate)->get(route('candidate.portal'));

        $response->assertStatus(200);
        $response->assertSee(route('candidate.available-tests'));
        $response->assertDontSee('aria-disabled="true"', false);
    }

    public function test_05_available_simulations_dashboard_cta_is_removed(): void
    {
        $response = $this->actingAs($this->candidate)->get(route('candidate.portal'));

        $response->assertStatus(200);
        $response->assertDontSee('Available Simulations');
        $response->assertDontSee('Browse through the available Computer-Based Testing practice catalog');
    }

    public function test_06_payment_cta_exists(): void
    {
        $response = $this->actingAs($this->candidate)->get(route('candidate.portal'));

        $response->assertStatus(200);
        $response->assertSee('PAYMENT');
        $response->assertSee('Purchase assessment packages and manage your payment.');
        $response->assertSee('Browse Assessment Packages');
    }

    public function test_07_payment_cta_links_to_canonical_candidate_store_route(): void
    {
        $response = $this->actingAs($this->candidate)->get(route('candidate.portal'));

        $response->assertStatus(200);
        $response->assertSee(route('candidate.store'));
    }

    public function test_08_payment_cta_does_not_create_a_second_store_route(): void
    {
        $storeRoutes = collect(Route::getRoutes()->getRoutesByName())
            ->filter(fn($route, $name) => str_starts_with($name, 'candidate.store'))
            ->keys()
            ->all();

        $this->assertEquals(['candidate.store', 'candidate.store.show'], $storeRoutes);
    }

    public function test_09_assessment_product_catalog_supports_toeic(): void
    {
        $test = $this->createTest('TOEIC Practice Assessment', 'toeic', 'real_test', true, 'published');

        $product = Product::create([
            'title' => 'TOEIC Assessment Package',
            'slug' => 'toeic-assessment-package',
            'product_type' => 'assessment',
            'price' => 750000,
            'test_id' => $test->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->candidate)->get(route('candidate.store'));

        $response->assertStatus(200);
        $response->assertSee('TOEIC Assessment Package');
        $response->assertSee('TOEIC');
    }

    public function test_10_assessment_product_catalog_supports_toefl(): void
    {
        $test = $this->createTest('TOEFL iBT Prep Assessment', 'toefl', 'real_test', true, 'published');

        $product = Product::create([
            'title' => 'TOEFL Preparation Assessment Package',
            'slug' => 'toefl-prep-assessment-package',
            'product_type' => 'assessment',
            'price' => 750000,
            'test_id' => $test->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->candidate)->get(route('candidate.store'));

        $response->assertStatus(200);
        $response->assertSee('TOEFL Preparation Assessment Package');
        $response->assertSee('TOEFL');
    }

    public function test_11_assessment_product_catalog_supports_ielts(): void
    {
        $test = $this->createTest('IELTS Academic Prep Assessment', 'ielts', 'real_test', true, 'published');

        $product = Product::create([
            'title' => 'IELTS Academic Master Package',
            'slug' => 'ielts-academic-master-package',
            'product_type' => 'assessment',
            'price' => 850000,
            'test_id' => $test->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->candidate)->get(route('candidate.store'));

        $response->assertStatus(200);
        $response->assertSee('IELTS Academic Master Package');
        $response->assertSee('IELTS');
    }

    public function test_12_assessment_product_catalog_supports_general_test(): void
    {
        $test = $this->createTest('General English Diagnostic Test', 'general', 'real_test', true, 'published');

        $product = Product::create([
            'title' => 'General English Diagnostic Package',
            'slug' => 'general-english-diagnostic-package',
            'product_type' => 'assessment',
            'price' => 350000,
            'test_id' => $test->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->candidate)->get(route('candidate.store'));

        $response->assertStatus(200);
        $response->assertSee('General English Diagnostic Package');
        $response->assertSee('GENERAL');
    }

    public function test_13_unpublished_assessment_products_are_not_purchasable(): void
    {
        $test = $this->createTest('Draft Unapproved Test', 'toeic', 'real_test', false, 'draft');

        $product = Product::create([
            'title' => 'Draft Assessment Package',
            'slug' => 'draft-assessment-package',
            'product_type' => 'assessment',
            'price' => 500000,
            'test_id' => $test->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->candidate)->get(route('candidate.store'));

        $response->assertStatus(200);
        $response->assertDontSee('Draft Assessment Package');
    }

    public function test_14_payment_pending_does_not_make_candidate_paid_eligible(): void
    {
        $product = Product::create([
            'title' => 'UAT Mock Package',
            'slug' => 'uat-mock-package-1',
            'product_type' => 'course',
            'price' => 500000,
            'is_active' => true,
        ]);

        $checkoutEngine = app(CheckoutEngine::class);
        $billingEngine = app(BillingEngine::class);

        $checkoutResult = $checkoutEngine->checkout($this->candidate, $product);
        $payment = $billingEngine->createPayment($checkoutResult['invoice'], 'manual_transfer');

        $this->assertEquals(PaymentStatus::Pending, $payment->status);

        // Verify Candidate is not paid
        $isPaidEligible = User::where('id', $this->candidate->id)
            ->whereHas('orders.invoice.payments', fn($p) => $p->whereIn('status', [PaymentStatus::Success, PaymentStatus::Paid]))
            ->exists();

        $this->assertFalse($isPaidEligible);
    }

    public function test_15_rejected_payment_does_not_make_candidate_paid_eligible(): void
    {
        $product = Product::create([
            'title' => 'UAT Mock Package',
            'slug' => 'uat-mock-package-2',
            'product_type' => 'course',
            'price' => 500000,
            'is_active' => true,
        ]);

        $checkoutEngine = app(CheckoutEngine::class);
        $billingEngine = app(BillingEngine::class);

        $checkoutResult = $checkoutEngine->checkout($this->candidate, $product);
        $payment = $billingEngine->createPayment($checkoutResult['invoice'], 'manual_transfer');
        $cancelledPayment = $billingEngine->cancelPayment($payment, 'Unreadable proof');

        $this->assertEquals(PaymentStatus::Failed, $cancelledPayment->status);

        $isPaidEligible = User::where('id', $this->candidate->id)
            ->whereHas('orders.invoice.payments', fn($p) => $p->whereIn('status', [PaymentStatus::Success, PaymentStatus::Paid]))
            ->exists();

        $this->assertFalse($isPaidEligible);
    }

    public function test_16_rejected_payment_does_not_create_mock_test_assignment(): void
    {
        $test = $this->createTest('UAT Mock Test Real', 'toeic', 'real_test', true, 'published');

        $product = Product::create([
            'title' => 'UAT Mock Package',
            'slug' => 'uat-mock-package-3',
            'product_type' => 'assessment',
            'price' => 500000,
            'test_id' => $test->id,
            'is_active' => true,
        ]);

        $checkoutEngine = app(CheckoutEngine::class);
        $billingEngine = app(BillingEngine::class);

        $checkoutResult = $checkoutEngine->checkout($this->candidate, $product);
        $payment = $billingEngine->createPayment($checkoutResult['invoice'], 'manual_transfer');
        $billingEngine->cancelPayment($payment, 'Proof rejected');

        $assignmentsCount = CandidateTestAssignment::where('user_id', $this->candidate->id)->count();
        $this->assertEquals(0, $assignmentsCount);
    }

    public function test_17_mock_test_purchase_does_not_auto_create_candidate_test_assignment(): void
    {
        $test = $this->createTest('TOEIC Official Mock Test Real', 'toeic', 'real_test', true, 'published');

        $product = Product::create([
            'title' => 'TOEIC Mock Package',
            'slug' => 'toeic-mock-package-4',
            'product_type' => 'assessment',
            'price' => 750000,
            'test_id' => $test->id,
            'is_active' => true,
        ]);

        $checkoutEngine = app(CheckoutEngine::class);
        $billingEngine = app(BillingEngine::class);

        $checkoutResult = $checkoutEngine->checkout($this->candidate, $product);
        $payment = $billingEngine->createPayment($checkoutResult['invoice'], 'manual_transfer');
        $confirmedPayment = $billingEngine->confirmPayment($payment, 'TXN-UAT-12345');

        $this->assertEquals(PaymentStatus::Success, $confirmedPayment->status);

        // Verify CandidateTestAssignment remains 0 (RA must explicitly assign)
        $assignmentsCount = CandidateTestAssignment::where('user_id', $this->candidate->id)->count();
        $this->assertEquals(0, $assignmentsCount);
    }

    public function test_18_dashboard_does_not_contain_official_toeic_wording(): void
    {
        $response = $this->actingAs($this->candidate)->get(route('candidate.portal'));

        $response->assertStatus(200);
        $response->assertDontSee('official TOEIC', false);
        $response->assertDontSee('Official TOEIC', false);
    }

    public function test_19_dashboard_does_not_contain_certification_store(): void
    {
        $response = $this->actingAs($this->candidate)->get(route('candidate.portal'));

        $response->assertStatus(200);
        $response->assertDontSee('Certification Store');
        $response->assertDontSee('🛍️');
    }

    public function test_20_professional_icon_contract_exists_no_primary_emoji_icon_usage(): void
    {
        $response = $this->actingAs($this->candidate)->get(route('candidate.portal'));

        $response->assertStatus(200);
        $content = $response->getContent();

        // Check for SVG presence
        $this->assertStringContainsString('<svg', $content);
        
        // Assert no primary raw emojis in dashboard cards
        $this->assertStringNotContainsString('🛍️', $content);
        $this->assertStringNotContainsString('🎓', $content);
        $this->assertStringNotContainsString('📊', $content);
    }

    public function test_21_light_theme_candidate_dashboard_contract_passes(): void
    {
        $response = $this->actingAs($this->candidate)->get(route('candidate.portal'));

        $response->assertStatus(200);
        $response->assertSee('bg-white');
        $response->assertSee('text-slate-900');
        $response->assertSee('border-slate-200');
    }

    public function test_22_dark_theme_candidate_dashboard_contract_passes(): void
    {
        $response = $this->actingAs($this->candidate)->get(route('candidate.portal'));

        $response->assertStatus(200);
        $response->assertSee('dark:bg-slate-900');
        $response->assertSee('dark:text-white');
        $response->assertSee('dark:border-slate-800');
    }

    public function test_23_global_theme_contract_remains_intact(): void
    {
        $response = $this->actingAs($this->candidate)->get(route('candidate.portal'));

        $response->assertStatus(200);
        $response->assertSee('setIapTheme');
    }

    public function test_24_p1_commerce_routes_remain_intact(): void
    {
        $response = $this->actingAs($this->candidate)->get(route('candidate.store'));
        $response->assertStatus(200);

        $response = $this->actingAs($this->candidate)->get(route('candidate.orders.index'));
        $response->assertStatus(200);

        $response = $this->actingAs($this->candidate)->get(route('candidate.invoices.index'));
        $response->assertStatus(200);

        $response = $this->actingAs($this->candidate)->get(route('candidate.payments.index'));
        $response->assertStatus(200);
    }

    public function test_25_p2_finance_approval_routes_remain_intact(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index'));
        $response->assertStatus(200);

        $response = $this->actingAs($this->financeUser)->get(route('finance.dashboard'));
        $response->assertStatus(200);
    }

    public function test_26_p3_notification_routes_and_links_remain_intact(): void
    {
        $response = $this->actingAs($this->candidate)->get(route('notifications.index'));
        $response->assertStatus(200);

        $response = $this->actingAs($this->candidate)->get(route('notifications.feed'));
        $response->assertStatus(200);
    }
}
