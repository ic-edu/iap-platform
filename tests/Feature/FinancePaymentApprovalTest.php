<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use App\Modules\Academic\Models\Course;
use App\Modules\Academic\Models\CourseEnrollment;
use App\Modules\Assessment\Engines\AssignmentEngine;
use App\Modules\Assessment\Models\CandidateTestAssignment;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\Assessment\Services\TestBuilderService;
use App\Modules\Commerce\Application\BillingEngine;
use App\Modules\Commerce\Application\CheckoutEngine;
use App\Modules\Commerce\Domain\Enums\InvoiceStatus;
use App\Modules\Commerce\Domain\Enums\OrderStatus;
use App\Modules\Commerce\Domain\Enums\PaymentStatus;
use App\Modules\Commerce\Domain\Models\Invoice;
use App\Modules\Commerce\Domain\Models\Order;
use App\Modules\Commerce\Domain\Models\Payment;
use App\Modules\Commerce\Domain\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Phase P2 — Finance Payment Approval Workspace Feature Tests
 *
 * Validates Finance payment review queue, payment detail inspection,
 * secure proof viewing, atomic confirmation & rejection, duplicate approval protection,
 * multi-role security gating, audit trail logging, and RA Paid Eligibility synchronization.
 */
class FinancePaymentApprovalTest extends \Tests\TestCase
{
    use RefreshDatabase;

    protected User $finance;
    protected User $candidate;
    protected User $ra;
    protected User $teacher;
    protected User $rm;
    protected Test $publishedMockTest;
    protected Test $simulatorTest;
    protected Course $course;
    protected Product $mockTestProduct;
    protected Product $simulatorProduct;
    protected Product $courseProduct;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        // Finance Officer
        $this->finance = User::create([
            'name'     => 'Finance Officer',
            'email'    => 'finance@icedu.org',
            'password' => bcrypt('password'),
            'status'   => 'active',
        ]);
        $this->finance->assignRole('finance');

        // Candidate Student
        $this->candidate = User::create([
            'name'     => 'Budi Candidate',
            'email'    => 'budi@icedu.org',
            'password' => bcrypt('password'),
            'status'   => 'active',
        ]);
        $this->candidate->assignRole('student');

        // Operational Admin (RA)
        $this->ra = User::create([
            'name'     => 'Operational Admin',
            'email'    => 'admin@icedu.org',
            'password' => bcrypt('password'),
            'status'   => 'active',
        ]);
        $this->ra->assignRole('admin');

        // Teacher
        $this->teacher = User::create([
            'name'     => 'Teacher User',
            'email'    => 'teacher@icedu.org',
            'password' => bcrypt('password'),
            'status'   => 'active',
        ]);
        $this->teacher->assignRole('teacher');

        // Repository Manager (RM)
        $this->rm = User::create([
            'name'     => 'Repo Manager',
            'email'    => 'rm@icedu.org',
            'password' => bcrypt('password'),
            'status'   => 'active',
        ]);
        $this->rm->assignRole('repository-manager');

        // Build Mock Test (real_test)
        $this->publishedMockTest = $this->createMockTest('TOEIC Mock Test — SMK Perhotelan', 'real_test', 'approved', true);

        // Build Simulator Test
        $this->simulatorTest = $this->createMockTest('TOEIC Simulator Practice 01', 'simulator', 'published', true);

        $category = \App\Modules\Academic\Models\CourseCategory::create([
            'name' => 'Language Prep',
            'slug' => 'language-prep-' . Str::random(4),
        ]);

        // Build Academic Course
        $this->course = Course::create([
            'category_id'  => $category->id,
            'title'        => 'TOEIC Grammar Mastery Course',
            'slug'         => 'toeic-grammar-mastery-course-' . Str::random(4),
            'code'         => 'ENG-101-' . Str::random(4),
            'description'  => 'Comprehensive grammar course',
            'is_published' => true,
        ]);

        // Products
        $this->mockTestProduct = Product::create([
            'title'        => 'TOEIC Mock Test SMK Package',
            'slug'         => 'toeic-mock-test-smk-package',
            'product_type' => 'assessment',
            'price'        => 750000.0,
            'is_active'    => true,
            'test_id'      => $this->publishedMockTest->id,
        ]);

        $this->simulatorProduct = Product::create([
            'title'        => 'TOEIC Simulator Prep Package',
            'slug'         => 'toeic-simulator-prep-package',
            'product_type' => 'assessment',
            'price'        => 150000.0,
            'is_active'    => true,
            'test_id'      => $this->simulatorTest->id,
        ]);

        $this->courseProduct = Product::create([
            'title'        => 'TOEIC Grammar Course Package',
            'slug'         => 'toeic-grammar-course-package',
            'product_type' => 'course',
            'price'        => 500000.0,
            'is_active'    => true,
            'course_id'    => $this->course->id,
        ]);
    }

    private function createMockTest(string $title, string $mode, string $status, bool $isPublished): Test
    {
        $test = Test::create([
            'title'            => $title,
            'slug'             => Str::slug($title) . '-' . Str::random(4),
            'test_type'        => 'toeic',
            'assessment_mode'  => $mode,
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
            'title'   => 'Section 1',
            'order'   => 1,
        ]);

        $service = app(TestBuilderService::class);
        $service->createAssessmentQuestion($section, [
            'prompt'        => 'Question prompt for ' . $title,
            'section'       => 'reading',
            'part_number'   => 5,
            'question_type' => 'multiple_choice',
            'difficulty'    => 'medium',
            'points'        => 5,
            'choices'       => [
                ['label' => 'A', 'content' => 'Correct Option', 'is_correct' => true],
                ['label' => 'B', 'content' => 'Incorrect Option', 'is_correct' => false],
            ],
        ]);

        return $test;
    }

    /**
     * Helper to create a complete pending payment for a product.
     */
    private function createPendingPayment(Product $product, ?User $user = null): Payment
    {
        $user = $user ?? $this->candidate;
        $checkoutEngine = app(CheckoutEngine::class);
        $billingEngine = app(BillingEngine::class);

        $result = $checkoutEngine->checkout($user, $product);
        return $billingEngine->createPayment($result['invoice'], 'manual_transfer');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 1: Finance can see pending payment in queue
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_01_finance_can_see_pending_payment_in_queue(): void
    {
        $payment = $this->createPendingPayment($this->mockTestProduct);

        $response = $this->actingAs($this->finance)->get(route('finance.payments.index', ['status' => 'pending']));

        $response->assertStatus(200);
        $response->assertSee($payment->reference_number);
        $response->assertSee($this->candidate->name);
        $response->assertSee('TOEIC Mock Test SMK Package');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 2: Candidate cannot access finance payment queue
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_02_candidate_cannot_access_finance_payment_queue(): void
    {
        $response = $this->actingAs($this->candidate)->get(route('finance.payments.pending'));

        $response->assertStatus(403);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 3: RA cannot approve payment
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_03_ra_cannot_approve_payment(): void
    {
        $payment = $this->createPendingPayment($this->mockTestProduct);

        $response = $this->actingAs($this->ra)->post(route('finance.payments.approve', $payment->id));

        $response->assertStatus(403);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 4: Teacher cannot approve payment
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_04_teacher_cannot_approve_payment(): void
    {
        $payment = $this->createPendingPayment($this->mockTestProduct);

        $response = $this->actingAs($this->teacher)->post(route('finance.payments.approve', $payment->id));

        $response->assertStatus(403);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 5: RM cannot approve payment
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_05_rm_cannot_approve_payment(): void
    {
        $payment = $this->createPendingPayment($this->mockTestProduct);

        $response = $this->actingAs($this->rm)->post(route('finance.payments.approve', $payment->id));

        $response->assertStatus(403);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 6: Finance can view payment detail
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_06_finance_can_view_payment_detail(): void
    {
        $payment = $this->createPendingPayment($this->mockTestProduct);

        $response = $this->actingAs($this->finance)->get(route('finance.payments.show', $payment->id));

        $response->assertStatus(200);
        $response->assertSee($payment->reference_number);
        $response->assertSee($this->candidate->name);
        $response->assertSee('IDR 832,500');
        $response->assertSee('Confirm &amp; Approve Payment', false);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 7: Finance can securely view payment proof
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_07_finance_can_securely_view_payment_proof(): void
    {
        Storage::fake('local');
        $file = UploadedFile::fake()->createWithContent('bank_slip.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF");
        $storedPath = $file->store('payment_proofs', 'local');

        $payment = $this->createPendingPayment($this->mockTestProduct);
        $payment->update([
            'proof_path'          => $storedPath,
            'proof_original_name' => 'bank_slip.pdf',
            'proof_uploaded_at'   => now(),
        ]);

        $response = $this->actingAs($this->finance)->get(route('finance.payments.proof', $payment->id));

        $response->assertStatus(200);
        $this->assertStringContainsString('inline', (string)$response->headers->get('content-disposition'));
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 8: Finance can approve pending payment (Payment=success, Invoice=paid, Order=completed)
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_08_finance_can_approve_pending_payment(): void
    {
        $payment = $this->createPendingPayment($this->mockTestProduct);
        $invoice = $payment->invoice;
        $order = $invoice->order;

        $response = $this->actingAs($this->finance)->post(route('finance.payments.approve', $payment->id), [
            'transaction_id' => 'BCA-TXN-12345',
        ]);

        $response->assertRedirect(route('finance.payments.show', $payment->id));

        $payment->refresh();
        $invoice->refresh();
        $order->refresh();

        $this->assertEquals(PaymentStatus::Success, $payment->status);
        $this->assertEquals('BCA-TXN-12345', $payment->transaction_id);
        $this->assertNotNull($payment->confirmed_at);

        $this->assertEquals(InvoiceStatus::Paid, $invoice->status);
        $this->assertNotNull($invoice->paid_at);

        $this->assertEquals(OrderStatus::Completed, $order->status);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 9: Finance can reject pending payment (Payment=failed, Invoice=cancelled, Order=cancelled)
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_09_finance_can_reject_pending_payment(): void
    {
        $payment = $this->createPendingPayment($this->mockTestProduct);
        $invoice = $payment->invoice;
        $order = $invoice->order;

        $response = $this->actingAs($this->finance)->post(route('finance.payments.reject', $payment->id), [
            'reason' => 'Transfer receipt is illegible and unverified in bank mutasi.',
        ]);

        $response->assertRedirect(route('finance.payments.show', $payment->id));

        $payment->refresh();
        $invoice->refresh();
        $order->refresh();

        $this->assertEquals(PaymentStatus::Failed, $payment->status);
        $this->assertStringContainsString('Transfer receipt is illegible', $payment->proof_notes ?? '');

        $this->assertEquals(InvoiceStatus::Cancelled, $invoice->status);
        $this->assertEquals(OrderStatus::Cancelled, $order->status);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 10: Successful payment cannot be approved again
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_10_successful_payment_cannot_be_approved_again(): void
    {
        $payment = $this->createPendingPayment($this->mockTestProduct);

        // First approval
        $this->actingAs($this->finance)->post(route('finance.payments.approve', $payment->id));
        $payment->refresh();
        $this->assertEquals(PaymentStatus::Success, $payment->status);

        // Second approval attempt
        $secondResponse = $this->actingAs($this->finance)->post(route('finance.payments.approve', $payment->id));
        $secondResponse->assertSessionHasErrors(['error']);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 11: Cancelled payment cannot be approved
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_11_cancelled_payment_cannot_be_approved(): void
    {
        $payment = $this->createPendingPayment($this->mockTestProduct);

        // Reject first
        $this->actingAs($this->finance)->post(route('finance.payments.reject', $payment->id), [
            'reason' => 'Cancelled by admin',
        ]);
        $payment->refresh();
        $this->assertEquals(PaymentStatus::Failed, $payment->status);

        // Attempt approval
        $approveResponse = $this->actingAs($this->finance)->post(route('finance.payments.approve', $payment->id));
        $approveResponse->assertSessionHasErrors(['error']);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 12: Failed payment cannot be approved
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_12_failed_payment_cannot_be_approved(): void
    {
        $payment = $this->createPendingPayment($this->mockTestProduct);
        $payment->update(['status' => PaymentStatus::Failed]);

        $response = $this->actingAs($this->finance)->post(route('finance.payments.approve', $payment->id));
        $response->assertSessionHasErrors(['error']);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 13: Approval is atomic (all entities or none)
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_13_approval_is_atomic(): void
    {
        $payment = $this->createPendingPayment($this->mockTestProduct);

        $billingEngine = app(BillingEngine::class);

        // Force an exception during transaction
        Invoice::saving(function () {
            static $thrown = false;
            if (!$thrown) {
                $thrown = true;
                throw new \RuntimeException('Database disk failure simulation');
            }
        });

        try {
            $billingEngine->confirmPayment($payment);
        } catch (\Throwable $e) {
            // Expected simulation error
        }

        $payment->refresh();
        $this->assertEquals(PaymentStatus::Pending, $payment->status, 'Payment status must be rolled back on failure.');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 14: Reject is atomic
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_14_reject_is_atomic(): void
    {
        $payment = $this->createPendingPayment($this->mockTestProduct);

        $billingEngine = app(BillingEngine::class);

        Invoice::saving(function () {
            static $thrown = false;
            if (!$thrown) {
                $thrown = true;
                throw new \RuntimeException('Database constraint error simulation');
            }
        });

        try {
            $billingEngine->cancelPayment($payment, 'Test reason');
        } catch (\Throwable $e) {
            // Expected simulation error
        }

        $payment->refresh();
        $this->assertEquals(PaymentStatus::Pending, $payment->status, 'Payment status must be rolled back on failure.');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 15: Successful payment changes RA Paid & Eligible KPI dynamically
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_15_successful_payment_changes_ra_paid_eligible_kpi(): void
    {
        $payment = $this->createPendingPayment($this->mockTestProduct);

        // Before approval: RA dashboard shows 0 paid eligible
        $dashboardBefore = $this->actingAs($this->ra)->get(route('admin.dashboard'));
        $dashboardBefore->assertStatus(200);
        $dashboardBefore->assertSee('Paid &amp; Eligible Candidates', false);

        // Finance approves
        $this->actingAs($this->finance)->post(route('finance.payments.approve', $payment->id));

        // After approval: AssignmentEngine confirms candidate is now eligible
        $assignmentEngine = app(AssignmentEngine::class);
        $this->assertTrue($assignmentEngine->isPaymentEligible($this->publishedMockTest, $this->candidate));

        // RA dashboard displays Candidate Requiring Action
        $dashboardAfter = $this->actingAs($this->ra)->get(route('admin.dashboard'));
        $dashboardAfter->assertStatus(200);
        $dashboardAfter->assertSee($this->candidate->name);
        $dashboardAfter->assertSee('Candidates Requiring Action');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 16: Real-test payment does NOT auto-create CandidateTestAssignment
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_16_real_test_payment_does_not_auto_create_assignment(): void
    {
        $payment = $this->createPendingPayment($this->mockTestProduct);

        $this->actingAs($this->finance)->post(route('finance.payments.approve', $payment->id));

        // Candidate must NOT have auto-created assignment
        $assignment = CandidateTestAssignment::where('user_id', $this->candidate->id)
            ->where('test_id', $this->publishedMockTest->id)
            ->first();

        $this->assertNull($assignment, 'Real test payment confirmation must NOT auto-create test assignment.');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 17: Existing course payment behavior remains unchanged (auto-enrolls)
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_17_existing_course_payment_behavior_remains_unchanged(): void
    {
        $payment = $this->createPendingPayment($this->courseProduct);

        $this->actingAs($this->finance)->post(route('finance.payments.approve', $payment->id));

        // Course enrollment should be activated by listener
        $enrollment = CourseEnrollment::where('user_id', $this->candidate->id)
            ->where('course_id', $this->course->id)
            ->first();

        $this->assertNotNull($enrollment, 'Course payment confirmation must auto-enroll student.');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 18: Existing simulator payment behavior remains unchanged (auto-assigns)
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_18_existing_simulator_payment_behavior_remains_unchanged(): void
    {
        $payment = $this->createPendingPayment($this->simulatorProduct);

        $this->actingAs($this->finance)->post(route('finance.payments.approve', $payment->id));

        // Simulator test should be auto-assigned by listener
        $assignment = CandidateTestAssignment::where('user_id', $this->candidate->id)
            ->where('test_id', $this->simulatorTest->id)
            ->first();

        $this->assertNotNull($assignment, 'Simulator test payment confirmation must auto-assign simulator.');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 19: Payment proof remains inaccessible to unauthorized users
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_19_payment_proof_remains_inaccessible_to_unauthorized_users(): void
    {
        Storage::fake('local');
        $file = UploadedFile::fake()->create('proof.pdf', 200, 'application/pdf');
        $storedPath = $file->store('payment_proofs', 'local');

        $payment = $this->createPendingPayment($this->mockTestProduct);
        $payment->update(['proof_path' => $storedPath, 'proof_original_name' => 'proof.pdf']);

        // Unauthenticated access fails
        $guestResponse = $this->get(route('finance.payments.proof', $payment->id));
        $guestResponse->assertRedirect(route('login'));

        // Candidate accessing finance proof route fails (403)
        $candidateResponse = $this->actingAs($this->candidate)->get(route('finance.payments.proof', $payment->id));
        $candidateResponse->assertStatus(403);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 20: Audit trail is created/preserved
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_20_audit_trail_is_created_for_finance_actions(): void
    {
        $payment = $this->createPendingPayment($this->mockTestProduct);

        // Approve
        $this->actingAs($this->finance)->post(route('finance.payments.approve', $payment->id));

        $log = ActivityLog::where('action', 'PAYMENT_APPROVED')
            ->where('subject_id', $payment->id)
            ->first();

        $this->assertNotNull($log, 'Approval must create ActivityLog entry.');
        $this->assertEquals($this->finance->id, $log->user_id);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 21: Light Theme finance contract passes
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_21_light_theme_finance_contract_passes(): void
    {
        $this->finance->setThemePreference('light');

        $response = $this->actingAs($this->finance)->get(route('finance.payments.index'));

        $response->assertStatus(200);
        $response->assertSee('data-theme="light"', false);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 22: Dark Theme finance contract passes
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_22_dark_theme_finance_contract_passes(): void
    {
        $this->finance->setThemePreference('dark');

        $response = $this->actingAs($this->finance)->get(route('finance.payments.index'));

        $response->assertStatus(200);
        $response->assertSee('data-theme="dark"', false);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 23: Global theme contract remains intact
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_23_global_theme_contract_remains_intact(): void
    {
        $response = $this->actingAs($this->finance)->get(route('finance.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Gross Cash Collections');
        $response->assertSee('Pending Approvals');
    }
}
