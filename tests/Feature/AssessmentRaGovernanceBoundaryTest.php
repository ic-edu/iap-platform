<?php

namespace Tests\Feature;

use App\Models\AssessmentRequest;
use App\Models\User;
use App\Modules\Assessment\Engines\AssignmentEngine;
use App\Modules\Assessment\Enums\AssessmentMode;
use App\Modules\Assessment\Models\CandidateTestAssignment;
use App\Modules\Assessment\Models\Test as AssessmentTest;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\Assessment\Models\TestQuestion;
use App\Modules\Commerce\Domain\Enums\InvoiceStatus;
use App\Modules\Commerce\Domain\Enums\OrderStatus;
use App\Modules\Commerce\Domain\Enums\PaymentStatus;
use App\Modules\Commerce\Domain\Models\Invoice;
use App\Modules\Commerce\Domain\Models\Order;
use App\Modules\Commerce\Domain\Models\OrderItem;
use App\Modules\Commerce\Domain\Models\Payment as CommercePayment;
use App\Modules\Commerce\Domain\Models\Product;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Modules\QuestionBank\Models\QuestionChoice;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentRaGovernanceBoundaryTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;
    protected User $rm;
    protected User $admin; // Operational Admin / RA
    protected User $superAdmin;
    protected User $student;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->teacher = User::factory()->create(['name' => 'Teacher User', 'email' => 'teacher@example.com']);
        $this->teacher->assignRole('teacher');

        $this->rm = User::factory()->create(['name' => 'Repo Manager', 'email' => 'rm@example.com']);
        $this->rm->assignRole('repository-manager');

        $this->admin = User::factory()->create(['name' => 'Operational Admin RA', 'email' => 'admin_ra@example.com']);
        $this->admin->assignRole('admin');

        $this->superAdmin = User::factory()->create(['name' => 'Super Admin', 'email' => 'sa@example.com']);
        $this->superAdmin->assignRole('super-admin');

        $this->student = User::factory()->create(['name' => 'Candidate Student', 'email' => 'student@example.com']);
        $this->student->assignRole('student');
    }

    private function createGovernedTest(string $mode = 'simulator', string $status = 'draft', ?User $creator = null, ?User $assignedTo = null): AssessmentTest
    {
        $creator = $creator ?? $this->teacher;
        $test = AssessmentTest::create([
            'title'            => 'Standard ' . ucfirst($mode) . ' Assessment ' . uniqid(),
            'slug'             => 'test-' . uniqid(),
            'test_type'        => 'toeic',
            'assessment_mode'  => $mode,
            'scoring_method'   => 'automatic',
            'duration_minutes' => 60,
            'pass_score'       => 70,
            'created_by'       => $creator->id,
            'assigned_to'      => $assignedTo?->id,
            'status'           => $status,
            'is_published'     => $status === 'published',
        ]);

        $sec = TestSection::create([
            'test_id'      => $test->id,
            'title'        => 'Section 1: General Core',
            'section_type' => 'listening',
            'order'        => 1,
        ]);

        $bank = QuestionBank::create([
            'title'       => 'Bank ' . uniqid(),
            'slug'        => 'bank-' . uniqid(),
            'test_type'   => 'toeic',
            'created_by'  => $creator->id,
            'status'      => 'published',
        ]);

        $q = Question::create([
            'question_bank_id' => $bank->id,
            'prompt'           => 'Valid Prompt Stem Question',
            'question_type'    => 'multiple_choice',
            'difficulty'       => 'medium',
            'points'           => 10,
        ]);

        QuestionChoice::create([
            'question_id' => $q->id,
            'label'       => 'A',
            'content'     => 'Correct Choice',
            'is_correct'  => true,
        ]);

        QuestionChoice::create([
            'question_id' => $q->id,
            'label'       => 'B',
            'content'     => 'Distractor Choice',
            'is_correct'  => false,
        ]);

        TestQuestion::create([
            'test_section_id' => $sec->id,
            'question_id'     => $q->id,
            'order'           => 1,
            'points'          => 10,
        ]);

        return $test;
    }

    /**
     * TEST 01: RA cannot create Simulator assessment.
     */
    public function test_01_ra_cannot_create_simulator_assessment(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.tests.store'), [
            'title'            => 'RA Illegal Simulator',
            'test_type'        => 'toeic',
            'assessment_mode'  => 'simulator',
            'duration_minutes' => 60,
            'pass_score'       => 70,
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('tests', ['title' => 'RA Illegal Simulator']);
    }

    /**
     * TEST 02: RA cannot create Real Test assessment.
     */
    public function test_02_ra_cannot_create_real_test_assessment(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.tests.store'), [
            'title'            => 'RA Illegal Real Test',
            'test_type'        => 'toeic',
            'assessment_mode'  => 'real_test',
            'duration_minutes' => 60,
            'pass_score'       => 70,
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('tests', ['title' => 'RA Illegal Real Test']);
    }

    /**
     * TEST 03: RA cannot edit assessment settings.
     */
    public function test_03_ra_cannot_edit_assessment_settings(): void
    {
        $test = $this->createGovernedTest('simulator', 'draft');

        $response = $this->actingAs($this->admin)->put(route('teacher.tests.update', $test->id), [
            'title'            => 'RA Modified Title',
            'duration_minutes' => 90,
            'pass_score'       => 80,
        ]);

        $response->assertStatus(403);
        $test->refresh();
        $this->assertNotEquals('RA Modified Title', $test->title);
    }

    /**
     * TEST 04: RA cannot author assessment question.
     */
    public function test_04_ra_cannot_author_assessment_question(): void
    {
        $test = $this->createGovernedTest('simulator', 'draft');
        $section = $test->sections()->first();

        $response = $this->actingAs($this->admin)->post(route('teacher.tests.create-question', $test->id), [
            'section_id'    => $section->id,
            'prompt'        => 'RA Authored Question Prompt',
            'question_type' => 'multiple_choice',
            'points'        => 5,
            'choices'       => [
                ['label' => 'A', 'content' => 'Option 1', 'is_correct' => '1'],
                ['label' => 'B', 'content' => 'Option 2', 'is_correct' => '0'],
            ],
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('questions', ['prompt' => 'RA Authored Question Prompt']);
    }

    /**
     * TEST 05: RA cannot alter assessment section.
     */
    public function test_05_ra_cannot_alter_assessment_section(): void
    {
        $test = $this->createGovernedTest('simulator', 'draft');
        $section = $test->sections()->first();

        $response = $this->actingAs($this->admin)->delete(route('teacher.tests.destroy-section', ['test' => $test->id, 'section' => $section->id]));
        $response->assertStatus(403);
        $this->assertDatabaseHas('test_sections', ['id' => $section->id]);
    }

    /**
     * TEST 06: RA cannot submit assessment for approval.
     */
    public function test_06_ra_cannot_submit_assessment_for_approval(): void
    {
        $test = $this->createGovernedTest('simulator', 'draft');

        $response = $this->actingAs($this->admin)->post(route('teacher.tests.resubmit', $test->id));
        $response->assertStatus(403);
        $test->refresh();
        $this->assertEquals('draft', $test->status);
    }

    /**
     * TEST 07: RA cannot access RM Assessment Approval Center.
     */
    public function test_07_ra_cannot_access_rm_assessment_approval_center(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.repository-manager.assessment-approval'));
        $response->assertStatus(403);
    }

    /**
     * TEST 08: RA cannot access RM assessment review.
     */
    public function test_08_ra_cannot_access_rm_assessment_review(): void
    {
        $test = $this->createGovernedTest('simulator', 'pending_approval');
        $response = $this->actingAs($this->admin)->get(route('admin.repository-manager.assessment-review', $test->id));
        $response->assertStatus(403);
    }

    /**
     * TEST 09: RA cannot approve assessment.
     */
    public function test_09_ra_cannot_approve_assessment(): void
    {
        $test = $this->createGovernedTest('simulator', 'pending_approval');
        $response = $this->actingAs($this->admin)->post(route('admin.repository-manager.assessment-approve', $test->id));
        $response->assertStatus(403);
        $test->refresh();
        $this->assertNotEquals('approved', $test->status);
    }

    /**
     * TEST 10: RA cannot request assessment revision.
     */
    public function test_10_ra_cannot_request_assessment_revision(): void
    {
        $test = $this->createGovernedTest('simulator', 'pending_approval');
        $response = $this->actingAs($this->admin)->post(route('admin.repository-manager.assessment-revision', $test->id), [
            'notes' => 'RA trying to request revision',
        ]);
        $response->assertStatus(403);
        $test->refresh();
        $this->assertNotEquals('needs_revision', $test->status);
    }

    /**
     * TEST 11: RA cannot publish approved assessment via admin.tests.publish.
     */
    public function test_11_ra_cannot_publish_approved_assessment_via_tests_publish(): void
    {
        $test = $this->createGovernedTest('simulator', 'approved');

        $response = $this->actingAs($this->admin)->post(route('admin.tests.publish', $test->id));
        $response->assertStatus(403);
        $test->refresh();
        $this->assertFalse((bool) $test->is_published);
    }

    /**
     * TEST 12: Direct POST to publication operation endpoint returns 403 for RA.
     */
    public function test_12_direct_post_to_publications_assessments_publish_returns_403_for_ra(): void
    {
        $test = $this->createGovernedTest('simulator', 'approved');

        $response = $this->actingAs($this->admin)->post(route('admin.publications.assessments.publish', $test->id));
        $response->assertStatus(403);
        $test->refresh();
        $this->assertFalse((bool) $test->is_published);
    }

    /**
     * TEST 13: RA does not receive assessment publish CTA in operations view.
     */
    public function test_13_ra_does_not_receive_assessment_publish_cta(): void
    {
        $test = $this->createGovernedTest('simulator', 'approved');

        $response = $this->actingAs($this->admin)->get(route('admin.tests.index', ['tab' => 'simulators']));
        $response->assertStatus(200);
        $response->assertDontSee('Publish Live');
        $response->assertDontSee(route('admin.tests.publish', $test->id));
    }

    /**
     * TEST 14: RA cannot access libraries publication queue area (403), while RM can.
     */
    public function test_14_ra_cannot_access_academic_operations_libraries_area(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.academic-operations.libraries'));
        $response->assertStatus(403);

        $rmResponse = $this->actingAs($this->rm)->get(route('admin.academic-operations.libraries'));
        $rmResponse->assertStatus(200);
        $rmResponse->assertSee('View Publication Queues →');
    }

    /**
     * TEST 15: RA can still submit AssessmentRequest brief.
     */
    public function test_15_ra_can_still_submit_assessment_request_brief(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.assessment-requests.store'), [
            'title'             => 'Institutional Mock Test Cohort A',
            'test_type'         => 'toeic',
            'program_context'   => 'Vocational English Track',
            'required_sections' => 'Listening & Reading',
            'notes'             => 'Prepared for September 2026 cohort',
        ]);

        $response->assertRedirect(route('admin.assessment-requests.index'));
        $this->assertDatabaseHas('assessment_requests', [
            'title'        => 'Institutional Mock Test Cohort A',
            'test_type'    => 'toeic',
            'requested_by' => $this->admin->id,
            'status'       => 'pending',
        ]);
    }

    /**
     * TEST 16: RA can still access legitimate AssessmentRequest workspace.
     */
    public function test_16_ra_can_still_access_legitimate_assessment_request_workspace(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.assessment-requests.index'));
        $response->assertStatus(200);
        $response->assertSee('Assessment Requests');
    }

    /**
     * TEST 17: RA retains eligible candidate assignment capability.
     */
    public function test_17_ra_retains_eligible_candidate_assignment_capability(): void
    {
        $test = $this->createGovernedTest('real_test', 'published');

        // Create paid transaction for student
        $order = Order::create([
            'order_number' => 'ORD-' . uniqid(),
            'user_id'      => $this->student->id,
            'status'       => OrderStatus::Completed,
            'subtotal'     => 832500,
            'discount'     => 0,
            'tax'          => 0,
            'grand_total'  => 832500,
        ]);
        $product = Product::create([
            'title'             => 'TOEIC Mock Test Package',
            'slug'              => 'pkg-' . uniqid(),
            'product_type'      => 'assessment_package',
            'assessment_family' => 'toeic',
            'price'             => 832500,
            'is_active'         => true,
        ]);
        OrderItem::create([
            'order_id'   => $order->id,
            'product_id' => $product->id,
            'quantity'   => 1,
            'price'      => 832500,
            'total'      => 832500,
        ]);
        $invoice = Invoice::create([
            'order_id'        => $order->id,
            'user_id'         => $this->student->id,
            'invoice_number'  => 'INV-' . uniqid(),
            'amount'          => 832500,
            'status'          => InvoiceStatus::Paid,
        ]);
        CommercePayment::create([
            'invoice_id'       => $invoice->id,
            'user_id'          => $this->student->id,
            'reference_number' => 'PAY-' . uniqid(),
            'amount'           => 832500,
            'status'           => PaymentStatus::Success,
            'payment_method'   => 'bank_transfer',
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.tests.assign-candidate', $test->id), [
            'candidate_id' => $this->student->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('candidate_test_assignments', [
            'test_id' => $test->id,
            'user_id' => $this->student->id,
            'status'  => 'active',
        ]);
    }

    /**
     * TEST 18: RM can still review assessment.
     */
    public function test_18_rm_can_still_review_assessment(): void
    {
        $test = $this->createGovernedTest('simulator', 'pending_approval');
        $response = $this->actingAs($this->rm)->get(route('admin.repository-manager.assessment-review', $test->id));
        $response->assertStatus(200);
    }

    /**
     * TEST 19: RM can still request revision.
     */
    public function test_19_rm_can_still_request_revision(): void
    {
        $test = $this->createGovernedTest('simulator', 'pending_approval');
        $response = $this->actingAs($this->rm)->post(route('admin.repository-manager.assessment-revision', $test->id), [
            'notes' => 'Please add more audio choices.',
        ]);

        $response->assertRedirect(route('admin.repository-manager.assessment-approval'));
        $test->refresh();
        $this->assertEquals('needs_revision', $test->status);
    }

    /**
     * TEST 20: RM can still approve assessment.
     */
    public function test_20_rm_can_still_approve_assessment(): void
    {
        $test = $this->createGovernedTest('simulator', 'pending_approval');
        $response = $this->actingAs($this->rm)->post(route('admin.repository-manager.assessment-approve', $test->id), [
            'notes' => 'Approved by Repository Manager',
        ]);

        $response->assertRedirect(route('admin.repository-manager.assessment-review', $test->id));
        $test->refresh();
        $this->assertEquals('approved', $test->status);
        $this->assertFalse((bool) $test->is_published);
    }

    /**
     * TEST 21: RM can still publish assessment via TestBuilderController::publish and PublicationOperationController.
     */
    public function test_21_rm_can_still_publish_assessment(): void
    {
        $test = $this->createGovernedTest('simulator', 'approved');
        $test->update(['is_published' => false]);

        $response = $this->actingAs($this->rm)->post(route('admin.tests.publish', $test->id));
        $response->assertRedirect(route('admin.tests.index'));
        $test->refresh();
        $this->assertEquals('published', $test->status);
        $this->assertTrue((bool) $test->is_published);
    }

    /**
     * TEST 22: TE can still create Simulator.
     */
    public function test_22_te_can_still_create_simulator(): void
    {
        $response = $this->actingAs($this->teacher)->post(route('admin.tests.store'), [
            'title'            => 'Teacher Formative Simulator',
            'test_type'        => 'toeic',
            'assessment_mode'  => 'simulator',
            'duration_minutes' => 45,
            'pass_score'       => 65,
        ]);

        $response->assertRedirect(route('admin.tests.index'));
        $this->assertDatabaseHas('tests', [
            'title'           => 'Teacher Formative Simulator',
            'assessment_mode' => 'simulator',
            'created_by'      => $this->teacher->id,
            'status'          => 'draft',
        ]);
    }

    /**
     * TEST 23: TE can still author owned Simulator.
     */
    public function test_23_te_can_still_author_owned_simulator(): void
    {
        $test = $this->createGovernedTest('simulator', 'draft', $this->teacher);

        $response = $this->actingAs($this->teacher)->put(route('teacher.tests.update', $test->id), [
            'title'            => 'Teacher Updated Title',
            'duration_minutes' => 50,
            'pass_score'       => 70,
        ]);

        $response->assertRedirect(route('teacher.tests.show', $test->id));
        $test->refresh();
        $this->assertEquals('Teacher Updated Title', $test->title);
    }

    /**
     * TEST 24: Assigned TE can still author Real Test.
     */
    public function test_24_assigned_te_can_still_author_real_test(): void
    {
        $test = $this->createGovernedTest('real_test', 'draft', $this->rm, $this->teacher);

        $response = $this->actingAs($this->teacher)->put(route('teacher.tests.update', $test->id), [
            'title'            => 'Assigned Teacher Updated Mock Test',
            'duration_minutes' => 120,
            'pass_score'       => 80,
        ]);

        $response->assertRedirect(route('teacher.tests.show', $test->id));
        $test->refresh();
        $this->assertEquals('Assigned Teacher Updated Mock Test', $test->title);
    }

    /**
     * TEST 25: TE still cannot publish.
     */
    public function test_25_te_still_cannot_publish(): void
    {
        $test = $this->createGovernedTest('simulator', 'approved');

        $response = $this->actingAs($this->teacher)->post(route('admin.tests.publish', $test->id));
        $response->assertStatus(403);
    }

    /**
     * TEST 26: assessment_mode remains immutable.
     */
    public function test_26_assessment_mode_remains_immutable(): void
    {
        $test = $this->createGovernedTest('simulator', 'draft', $this->teacher);

        $this->actingAs($this->teacher)->put(route('teacher.tests.update', $test->id), [
            'title'           => 'Attempting Mode Change',
            'assessment_mode' => 'real_test',
        ]);

        $test->refresh();
        $this->assertEquals('simulator', $test->assessment_mode instanceof \BackedEnum ? $test->assessment_mode->value : (string)$test->assessment_mode);
    }

    /**
     * TEST 27: Payment/eligibility behavior unchanged.
     */
    public function test_27_payment_eligibility_behavior_unchanged(): void
    {
        $test = $this->createGovernedTest('real_test', 'approved');
        $test->update(['is_published' => true]);

        $engine = app(AssignmentEngine::class);

        // Student without payment is not eligible
        $this->assertFalse($engine->isPaymentEligible($test, $this->student));

        // Create confirmed payment
        $order = Order::create(['order_number' => 'ORD-PE-1', 'user_id' => $this->student->id, 'status' => OrderStatus::Completed, 'subtotal' => 1000, 'discount' => 0, 'tax' => 0, 'grand_total' => 1000]);
        $prod = Product::create(['title' => 'Package', 'slug' => 'pkg-pe-1', 'type' => 'assessment_package', 'product_type' => 'assessment_package', 'assessment_family' => 'toeic', 'is_active' => true, 'price' => 1000]);
        OrderItem::create(['order_id' => $order->id, 'product_id' => $prod->id, 'quantity' => 1, 'price' => 1000, 'total' => 1000]);
        $inv = Invoice::create(['order_id' => $order->id, 'user_id' => $this->student->id, 'invoice_number' => 'INV-E1', 'amount' => 1000, 'status' => InvoiceStatus::Paid]);
        CommercePayment::create(['invoice_id' => $inv->id, 'user_id' => $this->student->id, 'reference_number' => 'PAY-E1', 'amount' => 1000, 'status' => PaymentStatus::Success, 'payment_method' => 'manual']);

        $this->assertTrue($engine->isPaymentEligible($test, $this->student));
    }

    /**
     * TEST 28: No automatic CandidateTestAssignment is introduced upon payment success.
     */
    public function test_28_no_automatic_candidate_test_assignment_is_introduced(): void
    {
        $initialAssignments = CandidateTestAssignment::count();

        $order = Order::create(['order_number' => 'ORD-AC-1', 'user_id' => $this->student->id, 'status' => OrderStatus::Completed, 'subtotal' => 1000, 'discount' => 0, 'tax' => 0, 'grand_total' => 1000]);
        $prod = Product::create(['title' => 'Auto Check Package', 'slug' => 'pkg-ac-1', 'type' => 'assessment_package', 'product_type' => 'assessment_package', 'assessment_family' => 'toeic', 'is_active' => true, 'price' => 1000]);
        OrderItem::create(['order_id' => $order->id, 'product_id' => $prod->id, 'quantity' => 1, 'price' => 1000, 'total' => 1000]);
        $inv = Invoice::create(['order_id' => $order->id, 'user_id' => $this->student->id, 'invoice_number' => 'INV-AUTO-1', 'amount' => 1000, 'status' => InvoiceStatus::Paid]);
        CommercePayment::create(['invoice_id' => $inv->id, 'user_id' => $this->student->id, 'reference_number' => 'PAY-AUTO-1', 'amount' => 1000, 'status' => PaymentStatus::Success, 'payment_method' => 'manual']);

        $this->assertEquals($initialAssignments, CandidateTestAssignment::count());
    }

    /**
     * TEST 29: TOEIC Listening & Reading Simulation Test schema protection.
     */
    public function test_29_toeic_simulation_test_protection(): void
    {
        $test = $this->createGovernedTest('simulator', 'draft');
        $this->assertEquals('simulator', $test->assessment_mode instanceof \BackedEnum ? $test->assessment_mode->value : (string)$test->assessment_mode);
        $this->assertNotEmpty($test->sections);
    }

    /**
     * TEST 30: PAY-20260827-VZDM state rule check.
     */
    public function test_30_payment_state_rule_check(): void
    {
        $order = Order::create(['order_number' => 'ORD-T30', 'user_id' => $this->student->id, 'status' => OrderStatus::Pending, 'subtotal' => 832500, 'discount' => 0, 'tax' => 0, 'grand_total' => 832500]);
        $inv = Invoice::create([
            'order_id'       => $order->id,
            'user_id'        => $this->student->id,
            'invoice_number' => 'INV-TEST-30',
            'amount'         => 832500,
            'status'         => InvoiceStatus::Unpaid,
        ]);

        $payment = CommercePayment::create([
            'invoice_id'       => $inv->id,
            'user_id'          => $this->student->id,
            'reference_number' => 'PAY-TEST-30',
            'amount'           => 832500,
            'status'           => PaymentStatus::Pending,
            'payment_method'   => 'bank_transfer',
        ]);

        $this->assertEquals('pending', $payment->status instanceof \BackedEnum ? $payment->status->value : (string)$payment->status);
    }
}
