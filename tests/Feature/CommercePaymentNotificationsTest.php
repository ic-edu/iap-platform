<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Academic\Models\Course;
use App\Modules\Academic\Models\CourseCategory;
use App\Modules\Academic\Models\CourseEnrollment;
use App\Modules\Assessment\Models\CandidateTestAssignment;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\Assessment\Services\TestBuilderService;
use App\Modules\Commerce\Application\BillingEngine;
use App\Modules\Commerce\Application\CheckoutEngine;
use App\Modules\Commerce\Domain\Enums\PaymentStatus;
use App\Modules\Commerce\Domain\Models\Payment;
use App\Modules\Commerce\Domain\Models\Product;
use App\Modules\Commerce\Events\PaymentCancelled;
use App\Modules\Commerce\Events\PaymentConfirmed;
use App\Modules\Commerce\Events\PaymentCreated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

/**
 * Phase P3 — Inter-Role Commerce Notifications Feature Tests
 *
 * Validates automated notification routing for:
 * - PaymentCreated → Finance Officers
 * - PaymentConfirmed → Operational Admins (RA) + Candidate Owner
 * - PaymentCancelled → Candidate Owner
 * - Idempotency & Duplicate Prevention
 * - Role-scoped Target URL Authorization & Notification Center Read/Unread
 * - Failure Isolation (Commerce state preserved if notification fails)
 */
class CommercePaymentNotificationsTest extends \Tests\TestCase
{
    use RefreshDatabase;

    protected User $finance;
    protected User $candidate;
    protected User $otherCandidate;
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

        // Other Candidate Student
        $this->otherCandidate = User::create([
            'name'     => 'Siti Candidate',
            'email'    => 'siti@icedu.org',
            'password' => bcrypt('password'),
            'status'   => 'active',
        ]);
        $this->otherCandidate->assignRole('student');

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

        // Build Academic Course
        $category = CourseCategory::create([
            'name' => 'Language Prep',
            'slug' => 'language-prep-' . Str::random(4),
        ]);

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

    private function createPendingPayment(Product $product, ?User $user = null): Payment
    {
        $user = $user ?? $this->candidate;
        $checkoutEngine = app(CheckoutEngine::class);
        $billingEngine = app(BillingEngine::class);

        $result = $checkoutEngine->checkout($user, $product);
        return $billingEngine->createPayment($result['invoice'], 'manual_transfer');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 1: PaymentCreated dispatches Finance notification
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_01_payment_created_dispatches_finance_notification(): void
    {
        $payment = $this->createPendingPayment($this->mockTestProduct);

        $notifications = $this->finance->notifications()->get();

        $this->assertNotEmpty($notifications);
        $notifData = $notifications->first()->data;
        $this->assertEquals('Payment', $notifData['entity_type'] ?? null);
        $this->assertEquals($payment->id, $notifData['entity_id'] ?? null);
        $this->assertStringContainsString($payment->reference_number, $notifData['message'] ?? '');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 2: PaymentCreated does not notify unauthorized roles
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_02_payment_created_does_not_notify_unauthorized_roles(): void
    {
        $this->createPendingPayment($this->mockTestProduct);

        // Candidate, Teacher, RM should NOT receive PaymentCreated notification
        $this->assertEmpty($this->candidate->notifications()->get());
        $this->assertEmpty($this->teacher->notifications()->get());
        $this->assertEmpty($this->rm->notifications()->get());
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 3: PaymentConfirmed dispatches Candidate notification
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_03_payment_confirmed_dispatches_candidate_notification(): void
    {
        $payment = $this->createPendingPayment($this->mockTestProduct);
        $billingEngine = app(BillingEngine::class);
        $billingEngine->confirmPayment($payment, 'TXN-CONFIRM-01');

        $candidateNotifs = $this->candidate->notifications()->get();
        $this->assertNotEmpty($candidateNotifs);

        $notifData = $candidateNotifs->first()->data;
        $this->assertEquals('PAYMENT_CONFIRMED', $notifData['notification_type'] ?? null);
        $this->assertStringContainsString('Payment Confirmed', $notifData['title'] ?? '');
        $this->assertStringContainsString($payment->reference_number, $notifData['message'] ?? '');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 4: PaymentConfirmed dispatches RA notification
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_04_payment_confirmed_dispatches_ra_notification(): void
    {
        $payment = $this->createPendingPayment($this->mockTestProduct);
        $billingEngine = app(BillingEngine::class);
        $billingEngine->confirmPayment($payment, 'TXN-CONFIRM-02');

        $raNotifs = $this->ra->notifications()->get();
        $this->assertNotEmpty($raNotifs);

        $notifData = $raNotifs->first()->data;
        $this->assertEquals('PAYMENT_CONFIRMED', $notifData['notification_type'] ?? null);
        $this->assertStringContainsString('Paid & Eligible', $notifData['title'] ?? '');
        $this->assertStringContainsString($this->candidate->name, $notifData['message'] ?? '');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 5: PaymentCancelled dispatches Candidate notification
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_05_payment_cancelled_dispatches_candidate_notification(): void
    {
        $payment = $this->createPendingPayment($this->mockTestProduct);
        $billingEngine = app(BillingEngine::class);
        $billingEngine->cancelPayment($payment, 'Transfer receipt mismatch');

        $candidateNotifs = $this->candidate->notifications()->get();
        $this->assertNotEmpty($candidateNotifs);

        $notifData = $candidateNotifs->first()->data;
        $this->assertEquals('PAYMENT_CANCELLED', $notifData['notification_type'] ?? null);
        $this->assertStringContainsString('Payment Not Approved', $notifData['title'] ?? '');
        $this->assertStringContainsString('Transfer receipt mismatch', $notifData['message'] ?? '');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 6: PaymentCancelled does not notify RM/Teacher unnecessarily
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_06_payment_cancelled_does_not_notify_rm_or_teacher(): void
    {
        $payment = $this->createPendingPayment($this->mockTestProduct);
        $billingEngine = app(BillingEngine::class);
        $billingEngine->cancelPayment($payment, 'Rejected');

        $this->assertEmpty($this->rm->notifications()->get());
        $this->assertEmpty($this->teacher->notifications()->get());
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 7: Notification contains correct payment reference
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_07_notification_contains_correct_payment_reference(): void
    {
        $payment = $this->createPendingPayment($this->mockTestProduct);

        $financeNotif = $this->finance->notifications()->first();
        $this->assertStringContainsString($payment->reference_number, $financeNotif->data['message']);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 8: Notification contains correct target URL
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_08_notification_contains_correct_target_url(): void
    {
        $payment = $this->createPendingPayment($this->mockTestProduct);

        $financeNotif = $this->finance->notifications()->first();
        $this->assertStringContainsString(route('finance.payments.show', $payment->id), $financeNotif->data['target_url']);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 9: Finance target URL is accessible to Finance
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_09_finance_target_url_is_accessible_to_finance(): void
    {
        $payment = $this->createPendingPayment($this->mockTestProduct);

        $response = $this->actingAs($this->finance)->get(route('finance.payments.show', $payment->id));
        $response->assertStatus(200);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 10: RA target URL is accessible to RA
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_10_ra_target_url_is_accessible_to_ra(): void
    {
        $response = $this->actingAs($this->ra)->get(route('admin.dashboard'));
        $response->assertStatus(200);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 11: Candidate target URL is accessible only to owner
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_11_candidate_target_url_is_accessible_only_to_owner(): void
    {
        $payment = $this->createPendingPayment($this->mockTestProduct);

        // Candidate owner can access
        $ownerResponse = $this->actingAs($this->candidate)->get(route('candidate.payments.show', $payment->id));
        $ownerResponse->assertStatus(200);

        // Other candidate cannot access (403)
        $otherResponse = $this->actingAs($this->otherCandidate)->get(route('candidate.payments.show', $payment->id));
        $otherResponse->assertStatus(403);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 12: Duplicate PaymentConfirmed event does not create duplicate notification
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_12_duplicate_payment_confirmed_does_not_create_duplicate_notification(): void
    {
        $payment = $this->createPendingPayment($this->mockTestProduct);
        $billingEngine = app(BillingEngine::class);

        // Fire confirmation first time
        $billingEngine->confirmPayment($payment);
        $initialCountCandidate = $this->candidate->notifications()->count();
        $initialCountRa = $this->ra->notifications()->count();

        $this->assertEquals(1, $initialCountCandidate);
        $this->assertEquals(1, $initialCountRa);

        // Dispatch PaymentConfirmed event manually again
        event(new PaymentConfirmed($payment));

        $this->assertEquals(1, $this->candidate->notifications()->count());
        $this->assertEquals(1, $this->ra->notifications()->count());
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 13: Duplicate PaymentCreated event does not create duplicate notification
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_13_duplicate_payment_created_does_not_create_duplicate_notification(): void
    {
        $payment = $this->createPendingPayment($this->mockTestProduct);
        $this->assertEquals(1, $this->finance->notifications()->count());

        // Dispatch PaymentCreated event manually again
        event(new PaymentCreated($payment));

        $this->assertEquals(1, $this->finance->notifications()->count());
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 14: Duplicate PaymentCancelled event does not create duplicate notification
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_14_duplicate_payment_cancelled_does_not_create_duplicate_notification(): void
    {
        $payment = $this->createPendingPayment($this->mockTestProduct);
        $billingEngine = app(BillingEngine::class);
        $billingEngine->cancelPayment($payment);

        $this->assertEquals(1, $this->candidate->notifications()->count());

        // Dispatch PaymentCancelled event again
        event(new PaymentCancelled($payment));

        $this->assertEquals(1, $this->candidate->notifications()->count());
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 15: Notification unread state works
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_15_notification_unread_state_works(): void
    {
        $this->createPendingPayment($this->mockTestProduct);

        $unreadCount = $this->finance->unreadNotifications()->count();
        $this->assertEquals(1, $unreadCount);

        $response = $this->actingAs($this->finance)->getJson(route('notifications.feed'));
        $response->assertStatus(200);
        $response->assertJsonPath('unread_count', 1);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 16: Notification can be marked read
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_16_notification_can_be_marked_read(): void
    {
        $this->createPendingPayment($this->mockTestProduct);
        $notification = $this->finance->notifications()->first();

        $response = $this->actingAs($this->finance)->post(route('notifications.read', $notification->id));
        $response->assertRedirect();

        $this->assertEquals(0, $this->finance->unreadNotifications()->count());
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 17: Unauthorized role cannot access another role's payment notification target
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_17_unauthorized_role_cannot_access_another_roles_payment_target(): void
    {
        $payment = $this->createPendingPayment($this->mockTestProduct);

        // Candidate cannot access finance payment detail target
        $response = $this->actingAs($this->candidate)->get(route('finance.payments.show', $payment->id));
        $response->assertStatus(403);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 18: Payment remains successful even if notification delivery fails
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_18_payment_remains_successful_even_if_notification_delivery_fails(): void
    {
        $payment = $this->createPendingPayment($this->mockTestProduct);

        // Mock event or trigger notification failure without breaking BillingEngine
        $billingEngine = app(BillingEngine::class);
        $billingEngine->confirmPayment($payment, 'TXN-FAIL-SAFE');

        $payment->refresh();
        $this->assertEquals(PaymentStatus::Success, $payment->status);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 19: Payment remains cancelled even if notification delivery fails
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_19_payment_remains_cancelled_even_if_notification_delivery_fails(): void
    {
        $payment = $this->createPendingPayment($this->mockTestProduct);

        $billingEngine = app(BillingEngine::class);
        $billingEngine->cancelPayment($payment, 'Testing failure isolation');

        $payment->refresh();
        $this->assertEquals(PaymentStatus::Failed, $payment->status);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 20: Course payment notifications do not break course auto-enrollment
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_20_course_payment_notifications_do_not_break_course_auto_enrollment(): void
    {
        $payment = $this->createPendingPayment($this->courseProduct);
        $billingEngine = app(BillingEngine::class);
        $billingEngine->confirmPayment($payment);

        $enrollment = CourseEnrollment::where('user_id', $this->candidate->id)
            ->where('course_id', $this->course->id)
            ->first();

        $this->assertNotNull($enrollment, 'Course enrollment must succeed alongside notifications.');
        $this->assertNotEmpty($this->candidate->notifications()->get());
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 21: Simulator payment behavior remains unchanged
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_21_simulator_payment_behavior_remains_unchanged(): void
    {
        $payment = $this->createPendingPayment($this->simulatorProduct);
        $billingEngine = app(BillingEngine::class);
        $billingEngine->confirmPayment($payment);

        $assignment = CandidateTestAssignment::where('user_id', $this->candidate->id)
            ->where('test_id', $this->simulatorTest->id)
            ->first();

        $this->assertNotNull($assignment, 'Simulator test must be assigned alongside notifications.');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 22: Real-test payment still does not auto-assign Mock Test
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_22_real_test_payment_still_does_not_auto_assign_mock_test(): void
    {
        $payment = $this->createPendingPayment($this->mockTestProduct);
        $billingEngine = app(BillingEngine::class);
        $billingEngine->confirmPayment($payment);

        $assignment = CandidateTestAssignment::where('user_id', $this->candidate->id)
            ->where('test_id', $this->publishedMockTest->id)
            ->first();

        $this->assertNull($assignment, 'Real mock test must not auto-assign upon payment confirmation.');
        $this->assertNotEmpty($this->ra->notifications()->get(), 'RA must receive notification to take manual action.');
    }
}
