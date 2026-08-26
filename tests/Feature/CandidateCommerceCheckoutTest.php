<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Assessment\Engines\AssignmentEngine;
use App\Modules\Assessment\Models\CandidateTestAssignment;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\Assessment\Services\TestBuilderService;
use App\Modules\Commerce\Application\BillingEngine;
use App\Modules\Commerce\Domain\Enums\OrderStatus;
use App\Modules\Commerce\Domain\Enums\PaymentStatus;
use App\Modules\Commerce\Domain\Models\Invoice;
use App\Modules\Commerce\Domain\Models\Order;
use App\Modules\Commerce\Domain\Models\Payment;
use App\Modules\Commerce\Domain\Models\Product;
use App\Services\NavigationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Phase P1 — Candidate Commerce & Checkout UI Feature Tests
 *
 * Validates candidate-facing store browsing, product details, checkout,
 * order/invoice generation, pending payment creation, evidence upload,
 * strict multi-tenant authorization, and non-automatic assignment safety.
 */
class CandidateCommerceCheckoutTest extends \Tests\TestCase
{
    use RefreshDatabase;

    protected User $candidate;
    protected User $otherCandidate;
    protected User $rm;
    protected User $teacher;
    protected Test $publishedTest;
    protected Test $draftTest;
    protected Product $publishedAssessmentProduct;
    protected Product $draftAssessmentProduct;
    protected Product $courseProduct;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        // Candidate 1
        $this->candidate = User::create([
            'name'     => 'Budi Candidate',
            'email'    => 'budi@icedu.org',
            'password' => bcrypt('password'),
            'status'   => 'active',
        ]);
        $this->candidate->assignRole('student');

        // Candidate 2 (Other user for isolation tests)
        $this->otherCandidate = User::create([
            'name'     => 'Siti Candidate',
            'email'    => 'siti@icedu.org',
            'password' => bcrypt('password'),
            'status'   => 'active',
        ]);
        $this->otherCandidate->assignRole('student');

        // Staff
        $this->rm = User::create([
            'name'     => 'Repository Manager',
            'email'    => 'repomanager@icedu.org',
            'password' => bcrypt('password'),
            'status'   => 'active',
        ]);
        $this->rm->assignRole('repository-manager');

        $this->teacher = User::create([
            'name'     => 'Teacher Instructor',
            'email'    => 'teacher@icedu.org',
            'password' => bcrypt('password'),
            'status'   => 'active',
        ]);
        $this->teacher->assignRole('teacher');

        // Build Published Mock Test
        $this->publishedTest = $this->createMockTest('TOEIC Mock Test — SMK Perhotelan (UAT)', 'approved', true);

        // Build Draft Mock Test (unpublished)
        $this->draftTest = $this->createMockTest('Draft Unfinished Mock Test', 'draft', false);

        // Products
        $this->publishedAssessmentProduct = Product::create([
            'title'        => 'TOEIC Mock Test SMK Package',
            'slug'         => 'toeic-mock-test-smk-package',
            'product_type' => 'assessment',
            'price'        => 750000.0,
            'is_active'    => true,
            'test_id'      => $this->publishedTest->id,
        ]);

        $this->draftAssessmentProduct = Product::create([
            'title'        => 'Draft Unapproved Mock Package',
            'slug'         => 'draft-unapproved-mock-package',
            'product_type' => 'assessment',
            'price'        => 500000.0,
            'is_active'    => true,
            'test_id'      => $this->draftTest->id,
        ]);

        $this->courseProduct = Product::create([
            'title'        => 'TOEIC Intensive Grammar Course',
            'slug'         => 'toeic-intensive-grammar-course',
            'product_type' => 'course',
            'price'        => 1200000.0,
            'is_active'    => true,
        ]);
    }

    private function createMockTest(string $title, string $status, bool $isPublished): Test
    {
        $test = Test::create([
            'title'            => $title,
            'slug'             => Str::slug($title) . '-' . Str::random(4),
            'test_type'        => 'toeic',
            'assessment_mode'  => 'real_test',
            'scoring_method'   => 'automatic',
            'duration_minutes' => 30,
            'pass_score'       => 0,
            'created_by'       => $this->rm->id,
            'assigned_to'      => $this->teacher->id,
            'status'           => $status,
            'is_published'     => $isPublished,
        ]);

        $section = TestSection::create([
            'test_id' => $test->id,
            'title'   => 'Section 1: Reading',
            'order'   => 1,
        ]);

        $service = app(TestBuilderService::class);
        $service->createAssessmentQuestion($section, [
            'prompt'        => 'The supervisor _____ the schedule.',
            'section'       => 'reading',
            'part_number'   => 5,
            'question_type' => 'multiple_choice',
            'difficulty'    => 'medium',
            'points'        => 5,
            'choices'       => [
                ['label' => 'A', 'content' => 'updates', 'is_correct' => true],
                ['label' => 'B', 'content' => 'update',  'is_correct' => false],
            ],
        ]);

        return $test;
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 1: Candidate can view Store
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_01_candidate_can_view_store(): void
    {
        $response = $this->actingAs($this->candidate)->get(route('candidate.store'));

        $response->assertStatus(200);
        $response->assertSee('Assessment & Learning Store', false);
        $response->assertSee('TOEIC Mock Test SMK Package');
        $response->assertSee('TOEIC Intensive Grammar Course');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 2: Inactive/unavailable product is hidden from store
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_02_inactive_product_is_hidden_from_store(): void
    {
        $inactiveProduct = Product::create([
            'title'        => 'Archived Inactive Package',
            'slug'         => 'archived-inactive-package',
            'product_type' => 'package',
            'price'        => 100000.0,
            'is_active'    => false,
        ]);

        $response = $this->actingAs($this->candidate)->get(route('candidate.store'));

        $response->assertStatus(200);
        $response->assertDontSee('Archived Inactive Package');

        // Direct detail access must abort 404
        $detailResponse = $this->actingAs($this->candidate)->get(route('candidate.store.show', $inactiveProduct->id));
        $detailResponse->assertStatus(404);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 3: Published assessment product can be viewed
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_03_published_assessment_product_can_be_viewed(): void
    {
        $response = $this->actingAs($this->candidate)->get(route('candidate.store.show', $this->publishedAssessmentProduct->id));

        $response->assertStatus(200);
        $response->assertSee($this->publishedAssessmentProduct->title);
        $response->assertSee('IDR 832,500'); // 750,000 + 11% tax
        $response->assertSee('Proceed to Checkout');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 4: Unpublished Mock Test product cannot be purchased (blocked server-side)
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_04_unpublished_mock_test_product_cannot_be_purchased(): void
    {
        // Store catalog must not display draft test product
        $storeResponse = $this->actingAs($this->candidate)->get(route('candidate.store'));
        $storeResponse->assertDontSee('Draft Unapproved Mock Package');

        // Direct product detail must 404
        $detailResponse = $this->actingAs($this->candidate)->get(route('candidate.store.show', $this->draftAssessmentProduct->id));
        $detailResponse->assertStatus(404);

        // Checkout form must 404
        $checkoutFormResponse = $this->actingAs($this->candidate)->get(route('candidate.checkout.show', $this->draftAssessmentProduct->id));
        $checkoutFormResponse->assertStatus(404);

        // Checkout POST process must 404
        $checkoutPostResponse = $this->actingAs($this->candidate)->post(route('candidate.checkout.process', $this->draftAssessmentProduct->id));
        $checkoutPostResponse->assertStatus(404);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 5: Candidate can open product detail
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_05_candidate_can_open_product_detail(): void
    {
        $response = $this->actingAs($this->candidate)->get(route('candidate.store.show', $this->courseProduct->id));

        $response->assertStatus(200);
        $response->assertSee('TOEIC Intensive Grammar Course');
        $response->assertSee('Proceed to Checkout');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 6: Candidate can checkout (Order, OrderItem, Invoice created)
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_06_candidate_can_checkout_and_generate_order_and_invoice(): void
    {
        $response = $this->actingAs($this->candidate)->post(route('candidate.checkout.process', $this->publishedAssessmentProduct->id));

        $order = Order::where('user_id', $this->candidate->id)->first();
        $this->assertNotNull($order);
        $this->assertEquals(OrderStatus::Pending, $order->status);

        $invoice = Invoice::where('order_id', $order->id)->first();
        $this->assertNotNull($invoice);
        $this->assertEquals('unpaid', is_object($invoice->status) ? $invoice->status->value : $invoice->status);
        $this->assertEquals(832500.0, $invoice->amount);

        // Redirects to invoice view
        $response->assertRedirect(route('candidate.invoices.show', $invoice->id));
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 7: Duplicate checkout does not create duplicate open order
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_07_duplicate_checkout_does_not_create_duplicate_open_order(): void
    {
        // First checkout
        $this->actingAs($this->candidate)->post(route('candidate.checkout.process', $this->publishedAssessmentProduct->id));
        $this->assertEquals(1, Order::where('user_id', $this->candidate->id)->count());

        // Second checkout for same product while previous is pending
        $this->actingAs($this->candidate)->post(route('candidate.checkout.process', $this->publishedAssessmentProduct->id));
        $this->assertEquals(1, Order::where('user_id', $this->candidate->id)->count(), 'Must reuse existing pending order.');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 8: Candidate can initiate Payment (Payment = pending)
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_08_candidate_can_initiate_payment(): void
    {
        $this->actingAs($this->candidate)->post(route('candidate.checkout.process', $this->publishedAssessmentProduct->id));

        $invoice = Invoice::where('user_id', $this->candidate->id)->first();
        $payment = Payment::where('invoice_id', $invoice->id)->first();

        $this->assertNotNull($payment);
        $this->assertEquals(PaymentStatus::Pending, $payment->status);
        $this->assertEquals($this->candidate->id, $payment->user_id);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 9: Candidate can view own invoice
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_09_candidate_can_view_own_invoice(): void
    {
        $this->actingAs($this->candidate)->post(route('candidate.checkout.process', $this->publishedAssessmentProduct->id));

        $invoice = Invoice::where('user_id', $this->candidate->id)->first();

        $response = $this->actingAs($this->candidate)->get(route('candidate.invoices.show', $invoice->id));

        $response->assertStatus(200);
        $response->assertSee($invoice->invoice_number);
        $response->assertSee('IDR 832,500');
        $response->assertSee('Bank Central Asia');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 10: Candidate cannot view another candidate's invoice (403)
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_10_candidate_cannot_view_another_candidates_invoice(): void
    {
        // Candidate 1 checks out
        $this->actingAs($this->candidate)->post(route('candidate.checkout.process', $this->publishedAssessmentProduct->id));
        $invoice = Invoice::where('user_id', $this->candidate->id)->first();

        // Candidate 2 attempts to access Candidate 1's invoice
        $response = $this->actingAs($this->otherCandidate)->get(route('candidate.invoices.show', $invoice->id));

        $response->assertStatus(403);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 11: Candidate cannot access another candidate's payment (403)
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_11_candidate_cannot_access_another_candidates_payment(): void
    {
        $this->actingAs($this->candidate)->post(route('candidate.checkout.process', $this->publishedAssessmentProduct->id));
        $invoice = Invoice::where('user_id', $this->candidate->id)->first();
        $payment = Payment::where('invoice_id', $invoice->id)->first();

        $response = $this->actingAs($this->otherCandidate)->get(route('candidate.payments.show', $payment->id));

        $response->assertStatus(403);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 12: Candidate cannot confirm payment (no candidate route/method to confirm)
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_12_candidate_cannot_confirm_own_payment(): void
    {
        $this->actingAs($this->candidate)->post(route('candidate.checkout.process', $this->publishedAssessmentProduct->id));
        $invoice = Invoice::where('user_id', $this->candidate->id)->first();
        $payment = Payment::where('invoice_id', $invoice->id)->first();

        // Payment status must remain pending
        $payment->refresh();
        $this->assertEquals(PaymentStatus::Pending, $payment->status);

        // Attempting to post to a confirm endpoint or alter status is not available to candidate
        $this->assertFalse(in_array('candidate.payments.confirm', array_keys(\Illuminate\Support\Facades\Route::getRoutes()->getRoutesByName())));
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 13: Pending payment does NOT make Candidate Paid/Eligible
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_13_pending_payment_does_not_make_candidate_paid_eligible(): void
    {
        $this->actingAs($this->candidate)->post(route('candidate.checkout.process', $this->publishedAssessmentProduct->id));

        $engine = app(AssignmentEngine::class);

        // Pending payment must return false for eligibility
        $isEligible = $engine->isPaymentEligible($this->publishedTest, $this->candidate);
        $this->assertFalse($isEligible, 'Candidate must NOT be eligible while payment is pending.');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 14: Pending payment does NOT allow Mock Test assignment
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_14_pending_payment_does_not_allow_mock_test_assignment(): void
    {
        $this->actingAs($this->candidate)->post(route('candidate.checkout.process', $this->publishedAssessmentProduct->id));

        $engine = app(AssignmentEngine::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/requires a confirmed PAID transaction/i');

        $engine->assignToUser($this->publishedTest, $this->candidate, $this->rm);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 15: Existing Finance confirmation path remains available for P2
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_15_finance_confirmation_path_remains_available_for_p2(): void
    {
        $this->actingAs($this->candidate)->post(route('candidate.checkout.process', $this->publishedAssessmentProduct->id));
        $invoice = Invoice::where('user_id', $this->candidate->id)->first();
        $payment = Payment::where('invoice_id', $invoice->id)->first();

        // Simulate Phase P2 Finance confirmation via BillingEngine
        $billingEngine = app(BillingEngine::class);
        $billingEngine->confirmPayment($payment, 'TXN-FINANCE-TEST');

        $payment->refresh();
        $this->assertEquals(PaymentStatus::Success, $payment->status);

        $engine = app(AssignmentEngine::class);
        $this->assertTrue($engine->isPaymentEligible($this->publishedTest, $this->candidate));
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 16: Payment evidence upload is ownership-protected
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_16_payment_evidence_upload_is_ownership_protected(): void
    {
        Storage::fake('local');

        $this->actingAs($this->candidate)->post(route('candidate.checkout.process', $this->publishedAssessmentProduct->id));
        $invoice = Invoice::where('user_id', $this->candidate->id)->first();
        $payment = Payment::where('invoice_id', $invoice->id)->first();

        // Other candidate tries to upload proof
        $file = UploadedFile::fake()->create('proof.pdf', 500, 'application/pdf');
        $unauthorizedResponse = $this->actingAs($this->otherCandidate)->post(route('candidate.payments.proof', $payment->id), [
            'proof' => $file,
            'notes' => 'Hacked transfer notes',
        ]);
        $unauthorizedResponse->assertStatus(403);

        // Legitimate candidate uploads proof
        $validResponse = $this->actingAs($this->candidate)->post(route('candidate.payments.proof', $payment->id), [
            'proof' => $file,
            'notes' => 'Transfer from BCA Budi',
        ]);
        $validResponse->assertRedirect();

        $payment->refresh();
        $this->assertNotNull($payment->proof_path);
        $this->assertEquals('proof.pdf', $payment->proof_original_name);
        $this->assertEquals('Transfer from BCA Budi', $payment->proof_notes);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 17: Candidate navigation includes commerce links
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_17_candidate_navigation_includes_commerce_links(): void
    {
        \Illuminate\Support\Facades\Auth::login($this->candidate);
        $menuItems = NavigationService::getMenuItems();

        $routes = array_column($menuItems, 'route');

        $this->assertContains('candidate.store', $routes);
        $this->assertContains('candidate.orders.index', $routes);
        $this->assertContains('candidate.invoices.index', $routes);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 18: Light Theme semantic contract passes
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_18_light_theme_semantic_contract_passes(): void
    {
        $this->candidate->setThemePreference('light');

        $response = $this->actingAs($this->candidate)->get(route('candidate.store'));

        $response->assertStatus(200);
        $response->assertSee('data-theme="light"', false);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 19: Dark Theme semantic contract passes
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_19_dark_theme_semantic_contract_passes(): void
    {
        $this->candidate->setThemePreference('dark');

        $response = $this->actingAs($this->candidate)->get(route('candidate.store'));

        $response->assertStatus(200);
        $response->assertSee('data-theme="dark"', false);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 20: No global app.css/theme contract regression
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_20_no_global_theme_contract_regression(): void
    {
        $response = $this->actingAs($this->candidate)->get(route('candidate.portal'));

        $response->assertStatus(200);
        $response->assertSee('Student Portal Dashboard');
        $response->assertSee('Available Tests');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 21: Candidate portal existing simulator behavior remains unchanged
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_21_candidate_portal_existing_simulator_behavior_remains_unchanged(): void
    {
        $simulatorTest = Test::create([
            'title'            => 'Free TOEIC Simulator 01',
            'slug'             => 'free-toeic-simulator-01',
            'test_type'        => 'toeic',
            'assessment_mode'  => 'simulator',
            'scoring_method'   => 'automatic',
            'duration_minutes' => 15,
            'pass_score'       => 0,
            'created_by'       => $this->rm->id,
            'status'           => 'published',
            'is_published'     => true,
        ]);

        $response = $this->actingAs($this->candidate)->get(route('candidate.available-tests'));

        $response->assertStatus(200);
        $response->assertSee('Free TOEIC Simulator 01');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 22: Candidate existing Mock Test assignment behavior remains unchanged
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_22_candidate_existing_mock_test_assignment_behavior_remains_unchanged(): void
    {
        // When explicitly assigned by RA (after confirmed payment)
        CandidateTestAssignment::create([
            'user_id'      => $this->candidate->id,
            'test_id'      => $this->publishedTest->id,
            'assigned_by'  => $this->rm->id,
            'assigned_at'  => now(),
            'status'       => 'active',
            'max_attempts' => 2,
        ]);

        $response = $this->actingAs($this->candidate)->get(route('candidate.available-tests'));

        $response->assertStatus(200);
        $response->assertSee($this->publishedTest->title);
    }
}
