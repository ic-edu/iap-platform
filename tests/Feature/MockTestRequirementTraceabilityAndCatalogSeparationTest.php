<?php

namespace Tests\Feature;

use App\Models\AssessmentRequest;
use App\Models\User;
use App\Modules\Assessment\Engines\AssignmentEngine;
use App\Modules\Assessment\Models\CandidateTestAssignment;
use App\Modules\Assessment\Models\Test;
use App\Modules\Commerce\Domain\Enums\OrderStatus;
use App\Modules\Commerce\Domain\Enums\PaymentStatus;
use App\Modules\Commerce\Domain\Models\Invoice;
use App\Modules\Commerce\Domain\Models\Order;
use App\Modules\Commerce\Domain\Models\OrderItem;
use App\Modules\Commerce\Domain\Models\Payment;
use App\Modules\Commerce\Domain\Models\Product;
use App\Modules\QuestionBank\Enums\TestType;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class MockTestRequirementTraceabilityAndCatalogSeparationTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $admin;
    protected User $repositoryManager;
    protected User $teacher;
    protected User $paidStudent;
    protected User $freeStudent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->superAdmin = User::factory()->create([
            'email'  => 'superadmin@icedu.org',
            'status' => 'active',
        ]);
        $this->superAdmin->assignRole('super-admin');

        $this->admin = User::factory()->create([
            'email'  => 'admin@icedu.org',
            'status' => 'active',
        ]);
        $this->admin->assignRole('admin');

        $this->repositoryManager = User::factory()->create([
            'email'  => 'rm@icedu.org',
            'status' => 'active',
        ]);
        $this->repositoryManager->assignRole('repository-manager');

        $this->teacher = User::factory()->create([
            'email'  => 'teacher@icedu.org',
            'status' => 'active',
        ]);
        $this->teacher->assignRole('teacher');

        $this->paidStudent = User::factory()->create([
            'email'  => 'paid.student@icedu.org',
            'name'   => 'Paid Student',
            'status' => 'active',
        ]);
        $this->paidStudent->assignRole('student');

        $this->freeStudent = User::factory()->create([
            'email'  => 'free.student@icedu.org',
            'name'   => 'Free Student',
            'status' => 'active',
        ]);
        $this->freeStudent->assignRole('student');

        // Create paid commerce order & payment for paid student
        $order = Order::create([
            'order_number'     => 'ORD-MOCK-001',
            'user_id'          => $this->paidStudent->id,
            'total_amount'     => 150000,
            'status'           => OrderStatus::Completed,
            'billing_details'  => ['name' => 'Paid Student'],
        ]);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-MOCK-001',
            'order_id'       => $order->id,
            'user_id'        => $this->paidStudent->id,
            'amount'         => 150000,
            'status'         => 'paid',
            'due_date'       => now()->addDays(7),
        ]);

        Payment::create([
            'invoice_id'       => $invoice->id,
            'user_id'          => $this->paidStudent->id,
            'amount'           => 150000,
            'payment_method'   => 'bank_transfer',
            'reference_number' => 'PAY-MOCK-001',
            'status'           => PaymentStatus::Paid,
            'paid_at'          => now(),
        ]);
    }

    /**
     * TEST 1 — Candidate Linkage:
     * Create an AssessmentRequest with candidate_id.
     * Verify request belongs to candidate, candidate_id persists, relationship resolves.
     */
    public function test_assessment_request_belongs_to_candidate_and_persists_linkage(): void
    {
        $request = AssessmentRequest::create([
            'title'              => 'TOEIC Hospitality Placement Brief',
            'test_type'          => 'toeic',
            'program_context'    => 'SMK Pariwisata & Perhotelan',
            'candidate_id'       => $this->paidStudent->id,
            'requested_by'       => $this->admin->id,
            'status'             => 'pending',
        ]);

        $this->assertDatabaseHas('assessment_requests', [
            'id'           => $request->id,
            'candidate_id' => $this->paidStudent->id,
            'requested_by' => $this->admin->id,
            'title'        => 'TOEIC Hospitality Placement Brief',
        ]);

        $this->assertNotNull($request->candidate);
        $this->assertSame($this->paidStudent->id, $request->candidate->id);
        $this->assertSame('Paid Student', $request->candidate->name);
    }

    /**
     * TEST 2 — RA Can Request for Paid Candidate:
     * Verify RA can create candidate-specific Mock Test request for paid/eligible student.
     */
    public function test_ra_can_create_mock_test_request_for_paid_candidate(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.assessment-requests.store'), [
            'title'              => 'TOEIC Advanced Placement for Paid Student',
            'test_type'          => 'toeic',
            'candidate_id'       => $this->paidStudent->id,
            'program_context'    => 'Vocational Tourism Program',
            'notes'              => 'Requires 3 listening and 4 reading modules',
        ]);

        $response->assertRedirect(route('admin.assessment-requests.index'));
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('assessment_requests', [
            'title'        => 'TOEIC Advanced Placement for Paid Student',
            'candidate_id' => $this->paidStudent->id,
            'status'       => 'pending',
        ]);
    }

    /**
     * TEST 3 — RA Cannot Request Mock Test for Free Candidate:
     * Verify request creation is rejected when candidate lacks paid eligibility.
     */
    public function test_ra_cannot_create_mock_test_request_for_unpaid_free_candidate(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.assessment-requests.store'), [
            'title'              => 'TOEIC Mock Test for Free Candidate',
            'test_type'          => 'toeic',
            'candidate_id'       => $this->freeStudent->id,
            'program_context'    => 'General Free Candidate',
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('assessment_requests', [
            'title'        => 'TOEIC Mock Test for Free Candidate',
            'candidate_id' => $this->freeStudent->id,
        ]);
    }

    /**
     * TEST 4 — RM Sees Candidate Context:
     * Verify RM intake queue displays candidate and requirement context without sensitive financial info.
     */
    public function test_rm_sees_candidate_context_in_intake_queue(): void
    {
        $req = AssessmentRequest::create([
            'title'           => 'TOEIC SMK Perhotelan Placement',
            'test_type'       => 'toeic',
            'program_context' => 'SMK Perhotelan Semester 1',
            'notes'           => 'Focus on hospitality workplace dialogues',
            'candidate_id'    => $this->paidStudent->id,
            'requested_by'    => $this->admin->id,
            'status'          => 'pending',
        ]);

        $response = $this->actingAs($this->repositoryManager)
            ->get(route('admin.repository-manager.assessment-requests.index'));

        $response->assertStatus(200);
        $response->assertSee('TOEIC SMK Perhotelan Placement');
        $response->assertSee('Target Candidate:');
        $response->assertSee('Paid Student');
        $response->assertSee('SMK Perhotelan Semester 1');
        $response->assertSee('Focus on hospitality workplace dialogues');

        // Verify sensitive financial transaction IDs are NOT exposed in the intake view
        $response->assertDontSee('INV-MOCK-001');
        $response->assertDontSee('PAY-MOCK-001');
    }

    /**
     * TEST 5 — RM Draft Preserves Request Link:
     * RM creates draft from request.
     * Verify Test.assessment_request_id points to request, Test mode is real_test, status is draft, is_published is false.
     */
    public function test_rm_draft_creation_preserves_request_link_and_mode(): void
    {
        $req = AssessmentRequest::create([
            'title'           => 'TOEIC Vocational Test Brief',
            'test_type'       => 'toeic',
            'candidate_id'    => $this->paidStudent->id,
            'requested_by'    => $this->admin->id,
            'status'          => 'pending',
        ]);

        $response = $this->actingAs($this->repositoryManager)->post(
            route('admin.repository-manager.assessment-requests.create-draft', $req->id),
            [
                'teacher_id'       => $this->teacher->id,
                'title'            => 'TOEIC Hospitality Special Mock Test',
                'test_type'        => 'toeic',
                'duration_minutes' => 120,
                'pass_score'       => 700,
            ]
        );

        $response->assertRedirect(route('admin.repository-manager.assessment-requests.index'));

        $createdTest = Test::where('assessment_request_id', $req->id)->first();
        $this->assertNotNull($createdTest);
        $this->assertSame('TOEIC Hospitality Special Mock Test', $createdTest->title);
        $this->assertSame('real_test', $createdTest->assessment_mode->value);
        $this->assertSame('draft', $createdTest->status);
        $this->assertFalse((bool) $createdTest->is_published);
        $this->assertSame($this->teacher->id, $createdTest->assigned_to);

        $req->refresh();
        $this->assertSame('draft_created', $req->status);
        $this->assertSame($createdTest->id, $req->test_id);
        $this->assertSame($this->paidStudent->id, $req->candidate_id);
    }

    /**
     * TEST 6 — Mock Test Catalog Separation:
     * Verify Mock Test tab contains only real_test AND published/live assessments.
     * Verify Simulator tab contains simulator assessments.
     */
    public function test_ra_catalog_separates_mock_tests_from_simulators(): void
    {
        // 1. Published Mock Test
        $publishedMock = Test::create([
            'title'            => 'Published Live Mock Test 01',
            'slug'             => 'pub-mock-01',
            'test_type'        => TestType::Toeic,
            'assessment_mode'  => 'real_test',
            'duration_minutes' => 120,
            'pass_score'       => 700,
            'status'           => 'published',
            'is_published'     => true,
            'created_by'       => $this->admin->id,
        ]);

        // 2. Draft Mock Test (Must NOT appear in RA Published Mock catalog)
        $draftMock = Test::create([
            'title'            => 'Unpublished Draft Mock Test',
            'slug'             => 'draft-mock-01',
            'test_type'        => TestType::Toeic,
            'assessment_mode'  => 'real_test',
            'duration_minutes' => 120,
            'pass_score'       => 700,
            'status'           => 'draft',
            'is_published'     => false,
            'created_by'       => $this->admin->id,
        ]);

        // 3. Practice Simulator
        $simulator = Test::create([
            'title'            => 'Self-Paced Practice Simulator 01',
            'slug'             => 'sim-practice-01',
            'test_type'        => TestType::Toeic,
            'assessment_mode'  => 'simulator',
            'duration_minutes' => 60,
            'pass_score'       => 500,
            'status'           => 'published',
            'is_published'     => true,
            'created_by'       => $this->teacher->id,
        ]);

        // Access Mock Tests Tab (Default)
        $mockTabResponse = $this->actingAs($this->admin)->get(route('admin.tests.index', ['tab' => 'mock_tests']));
        $mockTabResponse->assertStatus(200);
        $mockTabResponse->assertSee('Published Live Mock Test 01');
        $mockTabResponse->assertDontSee('Unpublished Draft Mock Test');
        $mockTabResponse->assertDontSee('Self-Paced Practice Simulator 01');

        // Access Simulators Tab
        $simTabResponse = $this->actingAs($this->admin)->get(route('admin.tests.index', ['tab' => 'simulators']));
        $simTabResponse->assertStatus(200);
        $simTabResponse->assertSee('Self-Paced Practice Simulator 01');
        $simTabResponse->assertDontSee('Published Live Mock Test 01');
    }

    /**
     * TEST 7 — Existing Published Mock Test Does Not Require New Request:
     * RA can assign an existing published Mock Test directly to a paid candidate.
     */
    public function test_existing_published_mock_test_does_not_require_new_request(): void
    {
        $publishedMock = Test::create([
            'title'            => 'Standard TOEIC Simulation 2026',
            'slug'             => 'std-toeic-2026',
            'test_type'        => TestType::Toeic,
            'assessment_mode'  => 'real_test',
            'duration_minutes' => 120,
            'pass_score'       => 700,
            'status'           => 'published',
            'is_published'     => true,
            'created_by'       => $this->repositoryManager->id,
        ]);

        // Link product to test for payment eligibility
        $product = Product::create([
            'title'        => 'Standard TOEIC Package',
            'slug'         => 'std-toeic-pkg',
            'product_type' => 'placement_test',
            'price'        => 150000,
            'test_id'      => $publishedMock->id,
            'is_active'    => true,
        ]);

        $order = Order::where('user_id', $this->paidStudent->id)->first();
        OrderItem::create([
            'order_id'   => $order->id,
            'product_id' => $product->id,
            'quantity'   => 1,
            'price'      => 150000,
            'total'      => 150000,
        ]);

        $assignmentEngine = app(AssignmentEngine::class);
        $assignment = $assignmentEngine->assignToUser($publishedMock, $this->paidStudent, $this->admin);

        $this->assertInstanceOf(CandidateTestAssignment::class, $assignment);
        $this->assertSame($this->paidStudent->id, $assignment->user_id);
        $this->assertSame($publishedMock->id, $assignment->test_id);
        $this->assertSame('active', $assignment->status);

        // Verify NO AssessmentRequest was required or created
        $this->assertSame(0, AssessmentRequest::count());
    }

    /**
     * TEST 8 — Free Candidate Cannot Be Assigned Mock Test:
     * Verify server-side rejection for unpaid candidate.
     */
    public function test_free_candidate_cannot_be_assigned_mock_test(): void
    {
        $publishedMock = Test::create([
            'title'            => 'Standard TOEIC Simulation 2026',
            'slug'             => 'std-toeic-2026',
            'test_type'        => TestType::Toeic,
            'assessment_mode'  => 'real_test',
            'duration_minutes' => 120,
            'pass_score'       => 700,
            'status'           => 'published',
            'is_published'     => true,
            'created_by'       => $this->repositoryManager->id,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Mock Test '{$publishedMock->title}' requires a confirmed PAID transaction");

        $assignmentEngine = app(AssignmentEngine::class);
        $assignmentEngine->assignToUser($publishedMock, $this->freeStudent, $this->admin);
    }

    /**
     * TEST 9 — Published Mock Test Is Reusable:
     * Assign the same published Mock Test to multiple eligible candidates in separate valid assignments.
     */
    public function test_published_mock_test_is_reusable_across_multiple_candidates(): void
    {
        $publishedMock = Test::create([
            'title'            => 'Standard TOEIC Simulation Reusable',
            'slug'             => 'std-toeic-reusable',
            'test_type'        => TestType::Toeic,
            'assessment_mode'  => 'real_test',
            'duration_minutes' => 120,
            'pass_score'       => 700,
            'status'           => 'published',
            'is_published'     => true,
            'created_by'       => $this->repositoryManager->id,
        ]);

        $secondPaidStudent = User::factory()->create([
            'email'  => 'paid2.student@icedu.org',
            'name'   => 'Second Paid Student',
            'status' => 'active',
        ]);
        $secondPaidStudent->assignRole('student');

        $product = Product::create([
            'title'        => 'Standard TOEIC Reusable Product',
            'slug'         => 'std-toeic-reusable-pkg',
            'product_type' => 'placement_test',
            'price'        => 150000,
            'test_id'      => $publishedMock->id,
            'is_active'    => true,
        ]);

        // Payment for student 1
        $order1 = Order::where('user_id', $this->paidStudent->id)->first();
        OrderItem::create([
            'order_id'   => $order1->id,
            'product_id' => $product->id,
            'quantity'   => 1,
            'price'      => 150000,
            'total'      => 150000,
        ]);

        // Payment for student 2
        $order2 = Order::create([
            'order_number'    => 'ORD-MOCK-002',
            'user_id'         => $secondPaidStudent->id,
            'total_amount'    => 150000,
            'status'          => OrderStatus::Completed,
            'billing_details' => ['name' => 'Second Paid Student'],
        ]);
        $inv2 = Invoice::create([
            'invoice_number' => 'INV-MOCK-002',
            'order_id'       => $order2->id,
            'user_id'        => $secondPaidStudent->id,
            'amount'         => 150000,
            'status'         => 'paid',
            'due_date'       => now()->addDays(7),
        ]);
        Payment::create([
            'invoice_id'       => $inv2->id,
            'user_id'          => $secondPaidStudent->id,
            'amount'           => 150000,
            'payment_method'   => 'bank_transfer',
            'reference_number' => 'PAY-MOCK-002',
            'status'           => PaymentStatus::Paid,
            'paid_at'          => now(),
        ]);
        OrderItem::create([
            'order_id'   => $order2->id,
            'product_id' => $product->id,
            'quantity'   => 1,
            'price'      => 150000,
            'total'      => 150000,
        ]);

        $assignmentEngine = app(AssignmentEngine::class);

        $assignment1 = $assignmentEngine->assignToUser($publishedMock, $this->paidStudent, $this->admin);
        $assignment2 = $assignmentEngine->assignToUser($publishedMock, $secondPaidStudent, $this->admin);

        $this->assertNotSame($assignment1->id, $assignment2->id);
        $this->assertSame($this->paidStudent->id, $assignment1->user_id);
        $this->assertSame($secondPaidStudent->id, $assignment2->user_id);
        $this->assertSame($publishedMock->id, $assignment1->test_id);
        $this->assertSame($publishedMock->id, $assignment2->test_id);

        // Verify only ONE Test record exists (no duplicate test creation)
        $this->assertSame(1, Test::where('slug', 'std-toeic-reusable')->count());
        $this->assertSame(2, CandidateTestAssignment::where('test_id', $publishedMock->id)->count());
    }

    /**
     * TEST 10 — Unpublished Mock Test Cannot Be Assigned:
     * Draft/pending/needs_revision Mock Test must be rejected by assignment logic.
     */
    public function test_unpublished_mock_test_cannot_be_assigned(): void
    {
        $draftMock = Test::create([
            'title'            => 'Draft Mock Test',
            'slug'             => 'draft-mock-unpub',
            'test_type'        => TestType::Toeic,
            'assessment_mode'  => 'real_test',
            'duration_minutes' => 120,
            'pass_score'       => 700,
            'status'           => 'draft',
            'is_published'     => false,
            'created_by'       => $this->teacher->id,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Cannot assign unpublished Mock Test '{$draftMock->title}'");

        $assignmentEngine = app(AssignmentEngine::class);
        $assignmentEngine->assignToUser($draftMock, $this->paidStudent, $this->admin);
    }
}
