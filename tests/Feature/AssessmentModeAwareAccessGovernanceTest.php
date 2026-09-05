<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Assessment\Engines\AssignmentEngine;
use App\Modules\Assessment\Engines\ResultEngine;
use App\Modules\Assessment\Enums\AssessmentMode;
use App\Modules\Assessment\Enums\AttemptStatus;
use App\Modules\Assessment\Enums\EvaluationStatus;
use App\Modules\Assessment\Models\Answer;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\CandidateTestAssignment;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestQuestion;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\Commerce\Domain\Enums\InvoiceStatus;
use App\Modules\Commerce\Domain\Enums\OrderStatus;
use App\Modules\Commerce\Domain\Enums\PaymentStatus;
use App\Modules\Commerce\Domain\Models\Invoice;
use App\Modules\Commerce\Domain\Models\Order;
use App\Modules\Commerce\Domain\Models\OrderItem;
use App\Modules\Commerce\Domain\Models\Payment;
use App\Modules\Commerce\Domain\Models\Product;
use App\Modules\QuestionBank\Enums\QuestionType;
use App\Modules\QuestionBank\Enums\SectionType;
use App\Modules\QuestionBank\Enums\TestType;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionChoice;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class AssessmentModeAwareAccessGovernanceTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminRa;
    protected User $candidate;
    protected User $teacher;
    protected AssignmentEngine $assignmentEngine;
    protected Test $simulatorTest;
    protected Test $realTest;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->adminRa = User::factory()->create(['name' => 'RA Admin Demo', 'email' => 'admin.ra@iap.test']);
        $this->adminRa->assignRole('admin');

        $this->candidate = User::factory()->create(['name' => 'Candidate User', 'email' => 'candidate@iap.test']);
        $this->candidate->assignRole('student');

        $this->teacher = User::factory()->create(['name' => 'Teacher Author', 'email' => 'teacher@iap.test']);
        $this->teacher->assignRole('teacher');

        $this->assignmentEngine = app(AssignmentEngine::class);

        // 1. Published Simulator Test Fixture
        $this->simulatorTest = Test::create([
            'title'            => 'TOEIC Listening & Reading Simulation Test',
            'slug'             => 'toeic-sim-test-' . uniqid(),
            'test_type'        => TestType::Toeic,
            'assessment_mode'  => AssessmentMode::Simulator,
            'duration_minutes' => 120,
            'pass_score'       => 700,
            'status'           => 'published',
            'is_published'     => true,
            'created_by'       => $this->teacher->id,
            'instructions'     => 'Sample simulator instructions',
        ]);

        $simSection = TestSection::create([
            'test_id'      => $this->simulatorTest->id,
            'title'        => 'Part 1: Photos',
            'section_type' => SectionType::Listening,
            'order'        => 1,
        ]);

        $qSim = Question::create([
            'prompt'        => 'Simulator Q1',
            'question_type' => QuestionType::MultipleChoice,
            'points'        => 1,
        ]);

        QuestionChoice::create(['question_id' => $qSim->id, 'label' => 'A', 'content' => 'Correct A', 'is_correct' => true]);
        QuestionChoice::create(['question_id' => $qSim->id, 'label' => 'B', 'content' => 'Incorrect B', 'is_correct' => false]);
        TestQuestion::create(['test_section_id' => $simSection->id, 'question_id' => $qSim->id, 'order' => 1, 'points' => 1]);

        // 2. Published Real Test / Mock Test Fixture
        $this->realTest = Test::create([
            'title'            => 'Official TOEIC Governed Mock Test',
            'slug'             => 'official-toeic-mock-' . uniqid(),
            'test_type'        => TestType::Toeic,
            'assessment_mode'  => AssessmentMode::RealTest,
            'duration_minutes' => 120,
            'pass_score'       => 700,
            'status'           => 'published',
            'is_published'     => true,
            'created_by'       => $this->teacher->id,
            'instructions'     => 'Sample mock test instructions',
        ]);

        $realSection = TestSection::create([
            'test_id'      => $this->realTest->id,
            'title'        => 'Part 1: Official Photos',
            'section_type' => SectionType::Listening,
            'order'        => 1,
        ]);

        $qReal = Question::create([
            'prompt'        => 'Mock Q1',
            'question_type' => QuestionType::MultipleChoice,
            'points'        => 10,
        ]);

        QuestionChoice::create(['question_id' => $qReal->id, 'label' => 'A', 'content' => 'Correct Choice', 'is_correct' => true]);
        QuestionChoice::create(['question_id' => $qReal->id, 'label' => 'B', 'content' => 'Incorrect Choice', 'is_correct' => false]);
        TestQuestion::create(['test_section_id' => $realSection->id, 'question_id' => $qReal->id, 'order' => 1, 'points' => 10]);
    }

    /**
     * TEST SIM-ACCESS-01: Published Simulator appears in Candidate Available Tests without CandidateTestAssignment.
     */
    public function test_sim_access_01_published_simulator_appears_without_assignment(): void
    {
        $this->assertDatabaseMissing('candidate_test_assignments', [
            'test_id' => $this->simulatorTest->id,
            'user_id' => $this->candidate->id,
        ]);

        $response = $this->actingAs($this->candidate)->get(route('candidate.available-tests'));
        $response->assertStatus(200);
        $response->assertSee('TOEIC Listening & Reading Simulation Test');
        $response->assertSee('Test Simulator');
    }

    /**
     * TEST SIM-ACCESS-02 & 03: Simulator access does not require payment or RA assignment.
     */
    public function test_sim_access_02_and_03_simulator_requires_no_payment_or_assignment(): void
    {
        $this->assertTrue($this->assignmentEngine->isPaymentEligible($this->simulatorTest, $this->candidate));
        $this->assertTrue($this->assignmentEngine->isEligibleToStart($this->simulatorTest, $this->candidate));
    }

    /**
     * TEST SIM-ACCESS-04: Unpublished Simulator does NOT become Candidate-accessible.
     */
    public function test_sim_access_04_unpublished_simulator_is_not_accessible(): void
    {
        $draftSim = Test::create([
            'title'            => 'Unpublished Draft Simulator',
            'slug'             => 'unpub-sim-' . uniqid(),
            'test_type'        => TestType::Toeic,
            'assessment_mode'  => AssessmentMode::Simulator,
            'duration_minutes' => 60,
            'pass_score'       => 500,
            'status'           => 'draft',
            'is_published'     => false,
            'created_by'       => $this->teacher->id,
        ]);

        $response = $this->actingAs($this->candidate)->get(route('candidate.available-tests'));
        $response->assertDontSee('Unpublished Draft Simulator');

        $this->assertFalse($this->assignmentEngine->isEligibleToStart($draftSim, $this->candidate));
    }

    /**
     * TEST RA-SIM-01 to 04: RA Simulator detail renders informational semantics, no assignment controls.
     */
    public function test_ra_sim_01_to_04_ra_simulator_detail_renders_informational_overview_without_assignment_tool(): void
    {
        $response = $this->actingAs($this->adminRa)->get(route('admin.tests.show', $this->simulatorTest->id));
        $response->assertStatus(200);

        // Informational overview present
        $response->assertSee('Practice Test Simulator');
        $response->assertSee('Automatic / Open Candidate Access');
        $response->assertSee('75% Accuracy');
        $response->assertSee('Practice Activity Metrics');

        // Governed assignment controls NOT rendered
        $response->assertDontSee('<span>➕</span> Assign Candidate', false);
        $response->assertDontSee('Authorize &amp; Assign Candidate', false);
        $response->assertDontSee('Authorize & Assign Candidate', false);
        $response->assertDontSee('-- Choose Candidate --', false);
        $response->assertDontSee('Active Candidate Assignments', false);
    }

    /**
     * TEST RA-SIM-05: Server-side assignment endpoint rejects Simulator assignment.
     */
    public function test_ra_sim_05_server_side_assignment_blocked_for_simulator(): void
    {
        // 1. Controller HTTP action
        $response = $this->actingAs($this->adminRa)->post(route('admin.tests.assign-candidate', $this->simulatorTest->id), [
            'candidate_id' => $this->candidate->id,
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('candidate_test_assignments', [
            'test_id' => $this->simulatorTest->id,
            'user_id' => $this->candidate->id,
        ]);

        // 2. Direct Engine call
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('uses open candidate access and does not support manual candidate assignment');
        $this->assignmentEngine->assignToUser($this->simulatorTest, $this->candidate, $this->adminRa);
    }

    /**
     * TEST REAL-01 to 04: Mock / Real Governed Test assignment workflow.
     */
    public function test_real_01_to_04_mock_governed_workflow_and_ui(): void
    {
        // REAL-01: Published Mock is NOT accessible to unassigned candidate
        $unassignedResp = $this->actingAs($this->candidate)->get(route('candidate.available-tests'));
        $unassignedResp->assertDontSee('Official TOEIC Governed Mock Test');
        $this->assertFalse($this->assignmentEngine->isEligibleToStart($this->realTest, $this->candidate));

        // REAL-04: RA detail shows governed assignment UI
        $raShow = $this->actingAs($this->adminRa)->get(route('admin.tests.show', $this->realTest->id));
        $raShow->assertStatus(200);
        $raShow->assertSee('Assign Candidate');
        $raShow->assertSee('Active Candidate Assignments');
        $raShow->assertSee('Authorize &amp; Assign Candidate', false);

        // Candidate purchases Real Test
        $prod = Product::create([
            'title'        => 'Mock Test Entitlement',
            'slug'         => 'mock-entitle-' . uniqid(),
            'product_type' => 'assessment',
            'test_id'      => $this->realTest->id,
            'price'        => 500000,
            'is_active'    => true,
        ]);

        $order = Order::create([
            'user_id'      => $this->candidate->id,
            'order_number' => 'ORD-' . uniqid(),
            'total_amount' => 500000,
            'status'       => OrderStatus::Completed,
        ]);

        OrderItem::create([
            'order_id'   => $order->id,
            'product_id' => $prod->id,
            'quantity'   => 1,
            'price'      => 500000,
            'total'      => 500000,
        ]);

        $invoice = Invoice::create([
            'order_id'       => $order->id,
            'user_id'        => $this->candidate->id,
            'invoice_number' => 'INV-' . uniqid(),
            'amount'         => 500000,
            'status'         => InvoiceStatus::Paid,
            'paid_at'        => now(),
        ]);

        Payment::create([
            'invoice_id'       => $invoice->id,
            'user_id'          => $this->candidate->id,
            'reference_number' => 'PAY-' . uniqid(),
            'amount'           => 500000,
            'status'           => PaymentStatus::Success,
            'payment_method'   => 'manual',
            'paid_at'          => now(),
        ]);

        // REAL-02: Eligible Candidate can be assigned by RA
        $assignResp = $this->actingAs($this->adminRa)->post(route('admin.tests.assign-candidate', $this->realTest->id), [
            'candidate_id' => $this->candidate->id,
        ]);
        $assignResp->assertSessionHas('status');

        $assignment = CandidateTestAssignment::where('test_id', $this->realTest->id)
            ->where('user_id', $this->candidate->id)
            ->where('status', 'active')
            ->first();
        $this->assertNotNull($assignment);

        // REAL-03: Active CandidateTestAssignment makes governed test available
        $this->assertTrue($this->assignmentEngine->isEligibleToStart($this->realTest, $this->candidate));
        $assignedPortalResp = $this->actingAs($this->candidate)->get(route('candidate.available-tests'));
        $assignedPortalResp->assertSee('Official TOEIC Governed Mock Test');
    }

    /**
     * TEST SCORE-UI-01 & 02: Simulator candidate and RA views render 75% Accuracy, not 700 Points.
     */
    public function test_score_ui_01_and_02_simulator_threshold_presentation(): void
    {
        // 1. Candidate Available Tests
        $candResp = $this->actingAs($this->candidate)->get(route('candidate.available-tests'));
        $candResp->assertStatus(200);
        $candResp->assertSee('75% Accuracy');
        $candResp->assertDontSee('700 Points');

        // 2. Candidate Instructions Screen
        $instResp = $this->actingAs($this->candidate)->get(route('candidate.tests.instructions', $this->simulatorTest));
        $instResp->assertStatus(200);
        $instResp->assertSee('75% Accuracy');
        $instResp->assertDontSee('700 Points');

        // 3. RA Simulator Detail Screen
        $raResp = $this->actingAs($this->adminRa)->get(route('admin.tests.show', $this->simulatorTest->id));
        $raResp->assertStatus(200);
        $raResp->assertSee('120 min / 75% Accuracy');
        $raResp->assertDontSee('120 min / 700 pts');
    }

    /**
     * TEST SCORE-UI-03: Simulator Result continues to evaluate passing based on 75% accuracy.
     */
    public function test_score_ui_03_simulator_result_75_percent_threshold(): void
    {
        $attemptPass = Attempt::create([
            'test_id'           => $this->simulatorTest->id,
            'user_id'           => $this->candidate->id,
            'attempt_token'     => 'tok-' . uniqid(),
            'status'            => AttemptStatus::Submitted,
            'evaluation_status' => EvaluationStatus::NotRequired,
            'started_at'        => now()->subMinutes(10),
            'submitted_at'      => now(),
        ]);

        $q = Question::where('prompt', 'Simulator Q1')->first();
        Answer::create([
            'attempt_id'  => $attemptPass->id,
            'question_id' => $q->id,
            'is_correct'  => true,
            'points'      => 1,
        ]);

        $resultEngine = app(ResultEngine::class);
        $resPass = $resultEngine->generateResult($attemptPass);

        $this->assertEquals(100.0, $resPass['percentage']);
        $this->assertTrue($resPass['is_passed']);
    }

    /**
     * TEST SCORE-UI-04: Mock / Real preserves its points scoring presentation.
     */
    public function test_score_ui_04_mock_real_preserves_points_threshold_presentation(): void
    {
        $this->assertEquals('700 Points', $this->realTest->getPassingThresholdDisplay());

        $raResp = $this->actingAs($this->adminRa)->get(route('admin.tests.show', $this->realTest->id));
        $raResp->assertStatus(200);
        $raResp->assertSee('120 min / 700 Points');
    }
}
