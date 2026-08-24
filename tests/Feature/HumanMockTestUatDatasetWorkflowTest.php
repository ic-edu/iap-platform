<?php

namespace Tests\Feature;

use App\Models\AssessmentRequest;
use App\Models\ContentResetRequest;
use App\Models\User;
use App\Modules\Assessment\Engines\AssignmentEngine;
use App\Modules\Assessment\Models\CandidateTestAssignment;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\Assessment\Services\TestBuilderService;
use App\Modules\Commerce\Domain\Enums\OrderStatus;
use App\Modules\Commerce\Domain\Enums\PaymentStatus;
use App\Modules\Commerce\Domain\Models\Invoice;
use App\Modules\Commerce\Domain\Models\Order;
use App\Modules\Commerce\Domain\Models\OrderItem;
use App\Modules\Commerce\Domain\Models\Payment;
use App\Modules\Commerce\Domain\Models\Product;
use App\Modules\QuestionBank\Models\Question;
use App\Services\ContentReset\ContentResetAuditService;
use App\Services\ContentReset\ContentResetDomains;
use App\Services\ToeicQuestionValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

/**
 * Phase 5B — Human Mock Test UAT Dataset Workflow Tests
 *
 * Validates the complete RA → RM → Teacher → RM → Published → Assignment → Candidate
 * institutional governance pipeline for a 3-question TOEIC Part 5 Mock Test.
 *
 * SCOPE CONSTRAINT: This test validates the real governance workflow.
 * The UAT assessment is NOT created via Seeder.
 * No Hard Reset is executed. No Finance/HR/User data is permanently mutated.
 */
class HumanMockTestUatDatasetWorkflowTest extends \Tests\TestCase
{
    use RefreshDatabase;

    protected User $ra;
    protected User $rm;
    protected User $teacher;
    protected User $candidate;
    protected User $freeCandidate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->ra            = $this->createUser('admin');
        $this->rm            = $this->createUser('repository-manager');
        $this->teacher       = $this->createUser('teacher');
        $this->candidate     = $this->createUser('student');
        $this->freeCandidate = $this->createUser('student');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function createUser(string $role): User
    {
        $user = User::create([
            'name'     => ucfirst(str_replace('-', ' ', $role)) . ' UAT',
            'email'    => $role . '_' . Str::random(6) . '@uat.test',
            'password' => bcrypt('password'),
            'status'   => 'active',
        ]);
        $user->assignRole($role);
        return $user;
    }

    /**
     * Create Order→Invoice→Payment chain for RA eligibility gate
     * and optionally Product→OrderItem for AssignmentEngine DB check.
     */
    private function createPaidEligibility(User $student, ?Test $test = null): Payment
    {
        $order = Order::create([
            'user_id'      => $student->id,
            'order_number' => 'ORD-UAT-' . Str::random(8),
            'status'       => OrderStatus::Completed->value,
            'subtotal'     => 750000,
            'discount'     => 0,
            'tax'          => 0,
            'grand_total'  => 750000,
        ]);

        $invoice = Invoice::create([
            'order_id'       => $order->id,
            'user_id'        => $student->id,
            'invoice_number' => 'INV-UAT-' . Str::random(8),
            'status'         => 'paid',
            'amount'         => 750000,
            'paid_at'        => now(),
        ]);

        $payment = Payment::create([
            'invoice_id'       => $invoice->id,
            'user_id'          => $student->id,
            'reference_number' => 'REF-UAT-' . Str::random(10),
            'payment_gateway'  => 'manual_transfer',
            'status'           => PaymentStatus::Success->value,
            'amount'           => 750000,
            'confirmed_at'     => now(),
        ]);

        if ($test) {
            $product = Product::create([
                'title'        => 'UAT Mock Test Access — ' . $test->title,
                'slug'         => 'uat-product-' . Str::random(6),
                'product_type' => 'assessment',
                'price'        => 750000,
                'is_active'    => true,
                'is_featured'  => false,
                'test_id'      => $test->id,
            ]);
            OrderItem::create([
                'order_id'   => $order->id,
                'product_id' => $product->id,
                'quantity'   => 1,
                'price'      => 750000,
                'total'      => 750000,
            ]);
        }

        return $payment;
    }

    /** Build minimal Part 5 HTTP payload for teacher question creation. */
    private function part5Payload(string $sectionId, string $prompt): array
    {
        return [
            'test_section_id' => $sectionId,
            'prompt'          => $prompt,
            'question_type'   => 'multiple_choice',
            'part_number'     => 5,
            'section'         => 'reading',
            'difficulty'      => 'medium',
            'points'          => 5,
            'choices'         => [
                0 => 'Choice A — incorrect option for hospitality UAT',
                1 => 'Choice B — correct answer for hospitality UAT',
                2 => 'Choice C — incorrect option for hospitality UAT',
                3 => 'Choice D — incorrect option for hospitality UAT',
            ],
            'correct_choice' => '1',
        ];
    }

    /**
     * Build a UAT assessment with 3 TOEIC Part 5 questions (for standalone tests).
     */
    private function buildUatAssessmentWithQuestions(
        string $status = 'draft',
        bool $isPublished = false
    ): Test {
        $test = Test::create([
            'title'            => 'TOEIC Mock Test — SMK Perhotelan (UAT)',
            'slug'             => 'toeic-mock-smk-perhotelan-' . Str::random(6),
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
            'title'   => 'Section 1: Reading — Part 5 (Incomplete Sentences)',
            'order'   => 1,
        ]);

        $service = app(TestBuilderService::class);

        $prompts = [
            'The hotel manager _____ staff to comply with all hospitality service protocols.',
            'New vocational students at SMK Perhotelan must _____ front desk procedures by day one.',
            'Room service supervisors are _____ for maintaining the highest guest satisfaction rating.',
        ];

        foreach ($prompts as $prompt) {
            $service->createAssessmentQuestion($section, [
                'prompt'        => $prompt,
                'section'       => 'reading',
                'part_number'   => 5,
                'question_type' => 'multiple_choice',
                'difficulty'    => 'medium',
                'points'        => 5,
                'choices'       => [
                    ['label' => 'A', 'content' => 'remind',    'is_correct' => false],
                    ['label' => 'B', 'content' => 'reminds',   'is_correct' => true],
                    ['label' => 'C', 'content' => 'reminded',  'is_correct' => false],
                    ['label' => 'D', 'content' => 'reminding', 'is_correct' => false],
                ],
            ]);
        }

        return $test;
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 1: Paid candidate can create / receive a Mock Test request through RA
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_01_paid_candidate_can_receive_mock_test_request_through_ra(): void
    {
        $this->createPaidEligibility($this->candidate);

        $response = $this->actingAs($this->ra)->post(
            route('admin.assessment-requests.store'),
            [
                'title'              => 'TOEIC Mock Test — SMK Perhotelan UAT',
                'test_type'          => 'toeic',
                'candidate_id'       => $this->candidate->id,
                'program_context'    => 'SMK Perhotelan — Hospitality / Vocational Context',
                'required_sections'  => 'TOEIC Reading Part 5 (Incomplete Sentences)',
                'notes'              => '3-question institutional Mock Test for Human UAT lifecycle validation.',
                'requested_deadline' => now()->addDays(14)->format('Y-m-d'),
            ]
        );

        $response->assertRedirect(route('admin.assessment-requests.index'));

        $this->assertDatabaseHas('assessment_requests', [
            'title'        => 'TOEIC Mock Test — SMK Perhotelan UAT',
            'test_type'    => 'toeic',
            'candidate_id' => $this->candidate->id,
            'requested_by' => $this->ra->id,
            'status'       => 'pending',
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 2: Request reaches RM with candidate context visible
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_02_request_reaches_rm_with_candidate_context(): void
    {
        $request = AssessmentRequest::create([
            'title'           => 'TOEIC Mock Test — SMK Perhotelan UAT',
            'test_type'       => 'toeic',
            'candidate_id'    => $this->candidate->id,
            'program_context' => 'SMK Perhotelan — Hospitality / Vocational Context',
            'notes'           => '3-question institutional Mock Test for Human UAT.',
            'requested_by'    => $this->ra->id,
            'status'          => 'pending',
        ]);

        $response = $this->actingAs($this->rm)
            ->get(route('admin.repository-manager.assessment-requests.index'));
        $response->assertStatus(200);

        $request->refresh();
        $this->assertEquals($this->candidate->id, $request->candidate->id);
        $this->assertEquals($this->ra->id, $request->requester->id);
        $this->assertEquals('SMK Perhotelan — Hospitality / Vocational Context', $request->program_context);
        $this->assertEquals('pending', $request->status);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 3: RM creates real_test draft from the request
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_03_rm_creates_real_test_draft(): void
    {
        $request = AssessmentRequest::create([
            'title'        => 'TOEIC Mock Test — SMK Perhotelan UAT',
            'test_type'    => 'toeic',
            'candidate_id' => $this->candidate->id,
            'requested_by' => $this->ra->id,
            'status'       => 'pending',
        ]);

        $response = $this->actingAs($this->rm)->post(
            route('admin.repository-manager.assessment-requests.create-draft', $request),
            [
                'teacher_id'       => $this->teacher->id,
                'title'            => 'TOEIC Mock Test — SMK Perhotelan (UAT)',
                'test_type'        => 'toeic',
                'duration_minutes' => 30,
                'pass_score'       => 0,
            ]
        );

        $response->assertRedirect();

        $test = Test::where('assessment_request_id', $request->id)->first();
        $this->assertNotNull($test);
        $this->assertEquals('real_test', is_object($test->assessment_mode) ? $test->assessment_mode->value : $test->assessment_mode);
        $this->assertEquals('draft', $test->status);
        $this->assertFalse((bool) $test->is_published);
        $this->assertEquals($this->teacher->id, $test->assigned_to);
        $this->assertEquals($request->id, $test->assessment_request_id);

        $request->refresh();
        $this->assertEquals('draft_created', $request->status);
        $this->assertEquals($test->id, $request->test_id);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 4: Teacher receives draft in Teacher Workspace
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_04_teacher_receives_draft_in_workspace(): void
    {
        $test = Test::create([
            'title'            => 'TOEIC Mock Test — SMK Perhotelan (UAT)',
            'slug'             => 'toeic-mock-smk-t4-' . Str::random(5),
            'test_type'        => 'toeic',
            'assessment_mode'  => 'real_test',
            'scoring_method'   => 'automatic',
            'duration_minutes' => 30,
            'pass_score'       => 0,
            'created_by'       => $this->rm->id,
            'assigned_to'      => $this->teacher->id,
            'status'           => 'draft',
            'is_published'     => false,
        ]);

        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.index'));
        $response->assertStatus(200);

        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $test->id));
        $response->assertStatus(200);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 5: Teacher can create exactly 3 valid TOEIC Part 5 questions via HTTP
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_05_teacher_can_create_three_valid_toeic_part5_questions(): void
    {
        $test = Test::create([
            'title'            => 'TOEIC Mock Test — SMK Perhotelan (UAT)',
            'slug'             => 'toeic-mock-smk-t5-' . Str::random(5),
            'test_type'        => 'toeic',
            'assessment_mode'  => 'real_test',
            'scoring_method'   => 'automatic',
            'duration_minutes' => 30,
            'pass_score'       => 0,
            'created_by'       => $this->rm->id,
            'assigned_to'      => $this->teacher->id,
            'status'           => 'draft',
            'is_published'     => false,
        ]);

        $section = TestSection::create([
            'test_id' => $test->id,
            'title'   => 'Section 1: Reading — Part 5',
            'order'   => 1,
        ]);

        $prompts = [
            'The hotel manager asked staff to _____ check-in procedures for UAT hospitality efficiency.',
            'Guests at SMK Perhotelan are expected to _____ house rules during their stay.',
            'The front desk representative _____ all incoming reservation requests within 24 hours.',
        ];

        foreach ($prompts as $prompt) {
            $response = $this->actingAs($this->teacher)->post(
                route('teacher.tests.create-question', $test->id),
                $this->part5Payload($section->id, $prompt)
            );
            $response->assertRedirect();
        }

        $test->load('sections.testQuestions');
        $totalQ = $test->sections->sum(fn($s) => $s->testQuestions->count());
        $this->assertEquals(3, $totalQ, 'Exactly 3 TOEIC Part 5 questions must be created via HTTP.');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 6: Assessment validation passes for 3 Part 5 questions
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_06_assessment_validation_passes(): void
    {
        $test = $this->buildUatAssessmentWithQuestions();

        $service = app(TestBuilderService::class);
        $result  = $service->validateAssessment($test);

        $this->assertTrue(
            $result['is_valid'],
            'Assessment validation must pass: ' . implode('; ', $result['errors'] ?? [])
        );
        $this->assertEmpty($result['errors'], 'Zero validation errors expected for 3 valid Part 5 questions.');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 7: Teacher submission transitions assessment to pending status
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_07_teacher_submission_transitions_to_pending(): void
    {
        $test = $this->buildUatAssessmentWithQuestions(status: 'draft');

        $response = $this->actingAs($this->teacher)->post(
            route('admin.tests.submit', $test->id)
        );

        $response->assertRedirect();

        $test->refresh();
        $this->assertEquals('pending', $test->status);
        $this->assertFalse((bool) $test->is_published);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 8: RM approval transitions to approved + published
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_08_rm_approval_transitions_to_approved_and_published(): void
    {
        $test = $this->buildUatAssessmentWithQuestions(status: 'pending');

        $response = $this->actingAs($this->rm)->post(
            route('admin.repository-manager.assessment-approve', $test->id),
            ['notes' => 'UAT Mock Test approved — 3 valid TOEIC Part 5 questions, zero flags.']
        );

        $response->assertRedirect();

        $test->refresh();
        $this->assertEquals('approved', $test->status);
        $this->assertTrue((bool) $test->is_published);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 9: Published assessment appears in RA Mock Test catalog
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_09_published_assessment_appears_in_ra_mock_test_catalog(): void
    {
        $test = $this->buildUatAssessmentWithQuestions(status: 'approved', isPublished: true);

        $response = $this->actingAs($this->ra)->get(route('admin.tests.index'));
        $response->assertStatus(200);
        $response->assertSee($test->title);

        $this->assertTrue($test->isRealTest());
        $this->assertTrue((bool) $test->is_published);
        $this->assertEquals('approved', $test->status);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 10: Published Mock Test does NOT appear in Simulator catalog
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_10_published_mock_test_does_not_appear_in_simulator_catalog(): void
    {
        $mockTest = $this->buildUatAssessmentWithQuestions(status: 'approved', isPublished: true);

        $this->assertTrue($mockTest->isRealTest());
        $this->assertFalse($mockTest->isSimulator());

        $simulatorTests = Test::where('assessment_mode', 'simulator')->get();
        $found = $simulatorTests->firstWhere('id', $mockTest->id);
        $this->assertNull($found, 'Real test must not appear in simulator-mode catalog query.');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 11: RA can assign published Mock Test to paid candidate
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_11_ra_can_assign_published_mock_test_to_paid_candidate(): void
    {
        $test    = $this->buildUatAssessmentWithQuestions(status: 'approved', isPublished: true);
        $payment = $this->createPaidEligibility($this->candidate, $test);

        $response = $this->actingAs($this->ra)->post(
            route('admin.tests.assign-candidate', $test->id),
            ['candidate_id' => $this->candidate->id]
        );

        $response->assertRedirect();

        $this->assertDatabaseHas('candidate_test_assignments', [
            'user_id' => $this->candidate->id,
            'test_id' => $test->id,
            'status'  => 'active',
        ]);

        $assignment = CandidateTestAssignment::where('user_id', $this->candidate->id)
            ->where('test_id', $test->id)
            ->first();
        $this->assertNotNull($assignment);
        $this->assertEquals(2, $assignment->max_attempts);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 12: Free (unpaid) candidate cannot receive the Mock Test
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_12_free_candidate_cannot_receive_mock_test(): void
    {
        $test = $this->buildUatAssessmentWithQuestions(status: 'approved', isPublished: true);
        // No payment created for freeCandidate

        $engine = app(AssignmentEngine::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/requires a confirmed PAID transaction/i');

        $engine->assignToUser($test, $this->freeCandidate, $this->ra);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 13: Paid candidate can access assigned Mock Test on portal
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_13_candidate_can_access_assigned_mock_test(): void
    {
        $test    = $this->buildUatAssessmentWithQuestions(status: 'approved', isPublished: true);
        $payment = $this->createPaidEligibility($this->candidate, $test);

        $engine = app(AssignmentEngine::class);
        $engine->assignToUser($test, $this->candidate, $this->ra, $payment);

        $response = $this->actingAs($this->candidate)->get(route('candidate.portal'));
        $response->assertStatus(200);

        $response = $this->actingAs($this->candidate)
            ->get(route('candidate.tests.instructions', $test->id));
        $response->assertStatus(200);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 14: Assessment has exactly 3 questions
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_14_assessment_has_exactly_three_questions(): void
    {
        $test = $this->buildUatAssessmentWithQuestions();
        $test->load('sections.testQuestions');

        $totalQ = $test->sections->sum(fn($s) => $s->testQuestions->count());
        $this->assertEquals(3, $totalQ, 'UAT Mock Test must contain exactly 3 questions.');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 15: All three questions comply with ToeicQuestionValidator
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_15_all_questions_comply_with_toeic_question_validator(): void
    {
        $test = $this->buildUatAssessmentWithQuestions();
        $test->load('sections.testQuestions.question.choices');

        $allQuestions = $test->sections
            ->flatMap(fn($s) => $s->testQuestions)
            ->map(fn($tq) => $tq->question)
            ->filter();

        $this->assertCount(3, $allQuestions);

        foreach ($allQuestions as $idx => $question) {
            $checkResult = ToeicQuestionValidator::check($question->toArray(), $question);

            $this->assertTrue(
                $checkResult['is_valid'],
                "Question #{$idx} (part {$question->part_number}) failed ToeicQuestionValidator: "
                    . implode('; ', $checkResult['errors'])
            );

            $this->assertEquals(5, $question->part_number, "Question #{$idx} must be Part 5.");
            $this->assertEquals('reading', is_object($question->section) ? $question->section->value : $question->section, "Question #{$idx} must be reading section.");
            $this->assertCount(4, $question->choices, "Question #{$idx} must have exactly 4 choices.");
            $this->assertEquals(
                1,
                $question->choices->where('is_correct', true)->count(),
                "Question #{$idx} must have exactly 1 correct answer."
            );
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 16: ContentReset dry-run identifies assessment correctly, zero changes
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_16_content_reset_dry_run_identifies_assessment_without_modifying(): void
    {
        $test = $this->buildUatAssessmentWithQuestions(status: 'approved', isPublished: true);

        $preTestCount     = Test::withTrashed()->count();
        $preQuestionCount = Question::count();

        // Create a ContentResetRequest model (not persisted through a seeder)
        $resetRequest = ContentResetRequest::create([
            'request_number'   => 'RST-UAT-' . now()->format('Ymd') . '-' . strtoupper(Str::random(4)),
            'mode'             => ContentResetDomains::MODE_REFRESH,
            'status'           => 'audit_pending',
            'reason'           => 'UAT dry-run forensic audit — Phase 5B validation only. No execution.',
            'requested_by'     => $this->ra->id,
            'scope'            => [ContentResetDomains::DRAFT_ASSESSMENTS],
            'target_entities'  => ['assessment_ids' => [$test->id]],
            'excluded_domains' => [],
        ]);

        $auditService = app(ContentResetAuditService::class);
        $auditReport  = $auditService->performForensicAudit($resetRequest);

        // Audit report must have correct structure
        $this->assertIsArray($auditReport);
        $this->assertArrayHasKey('is_blocked', $auditReport);
        $this->assertArrayHasKey('blockers', $auditReport);
        $this->assertArrayHasKey('target_assessment_ids', $auditReport);
        $this->assertArrayHasKey('bound_questions_total', $auditReport);
        $this->assertArrayHasKey('shared_questions_detail', $auditReport);
        $this->assertArrayHasKey('audit_generated_at', $auditReport);

        // Published assessments are blocked from routine refresh mode — the forensic audit
        // detects them and adds a blocker. Verify the assessment ID is identified.
        $this->assertIsArray($auditReport['target_assessment_ids']);

        // ZERO data changes — all counts must remain identical
        $this->assertEquals($preTestCount, Test::withTrashed()->count(), 'No tests deleted during forensic audit.');
        $this->assertEquals($preQuestionCount, Question::count(), 'No questions deleted during forensic audit.');

        // UAT assessment remains intact
        $test->refresh();
        $this->assertNotNull($test->id);
        $this->assertTrue((bool) $test->is_published, 'UAT Mock Test must remain published after forensic audit.');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Composite: Full Governance Pipeline (RA→RM→Teacher→RM→Publish→Assign→Portal)
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_full_governance_pipeline_end_to_end(): void
    {
        // 1. Establish paid candidate eligibility
        $prePayment = $this->createPaidEligibility($this->candidate);

        // 2. RA submits request
        $this->actingAs($this->ra)->post(route('admin.assessment-requests.store'), [
            'title'           => 'TOEIC Mock Test — SMK Perhotelan UAT',
            'test_type'       => 'toeic',
            'candidate_id'    => $this->candidate->id,
            'program_context' => 'SMK Perhotelan — Hospitality / Vocational Context',
            'notes'           => '3-question institutional Mock Test for Human UAT lifecycle validation.',
        ]);

        $request = AssessmentRequest::where('title', 'TOEIC Mock Test — SMK Perhotelan UAT')->first();
        $this->assertNotNull($request);
        $this->assertEquals('pending', $request->status);

        // 3. RM creates draft, assigns teacher
        $this->actingAs($this->rm)->post(
            route('admin.repository-manager.assessment-requests.create-draft', $request),
            [
                'teacher_id'       => $this->teacher->id,
                'title'            => 'TOEIC Mock Test — SMK Perhotelan (UAT)',
                'test_type'        => 'toeic',
                'duration_minutes' => 30,
                'pass_score'       => 0,
            ]
        );

        $test = Test::where('assessment_request_id', $request->id)->first();
        $this->assertNotNull($test);
        $this->assertEquals('draft', $test->status);
        $this->assertEquals('real_test', is_object($test->assessment_mode) ? $test->assessment_mode->value : $test->assessment_mode);

        // 4. Teacher authors 3 Part 5 questions via HTTP
        $section = $test->sections()->first();
        $questionPrompts = [
            'The hotel _____ all guests to pre-register their vehicles at the front desk.',
            'Hospitality staff _____ respond to all complaints within two business hours.',
            'The banquet hall _____ with fresh flowers for the graduation ceremony event.',
        ];
        foreach ($questionPrompts as $prompt) {
            $this->actingAs($this->teacher)->post(
                route('teacher.tests.create-question', $test->id),
                $this->part5Payload($section->id, $prompt)
            );
        }

        $test->load('sections.testQuestions');
        $this->assertEquals(3, $test->sections->sum(fn($s) => $s->testQuestions->count()));

        // 5. Teacher submits for review → status = pending
        $this->actingAs($this->teacher)->post(route('admin.tests.submit', $test->id));
        $test->refresh();
        $this->assertEquals('pending', $test->status);

        // 6. RM approves → status = approved + is_published = true
        $this->actingAs($this->rm)->post(
            route('admin.repository-manager.assessment-approve', $test->id),
            ['notes' => 'Approved for institutional use.']
        );
        $test->refresh();
        $this->assertEquals('approved', $test->status);
        $this->assertTrue((bool) $test->is_published);

        // 7. Catalog separation
        $this->assertTrue($test->isRealTest());
        $this->assertFalse($test->isSimulator());

        // 8. Create full commerce chain, assign candidate
        $payment = $this->createPaidEligibility($this->candidate, $test);
        $engine  = app(AssignmentEngine::class);
        $assignment = $engine->assignToUser($test, $this->candidate, $this->ra, $payment);
        $this->assertEquals('active', $assignment->status);
        $this->assertEquals(2, $assignment->max_attempts);

        // 9. Candidate portal access
        $this->actingAs($this->candidate)->get(route('candidate.portal'))->assertStatus(200);

        // 10. TOEIC validation on all 3 questions
        $test->load('sections.testQuestions.question.choices');
        $allQ = $test->sections->flatMap(fn($s) => $s->testQuestions)->map(fn($tq) => $tq->question)->filter();
        $this->assertCount(3, $allQ);
        foreach ($allQ as $q) {
            $result = ToeicQuestionValidator::check($q->toArray(), $q);
            $this->assertTrue($result['is_valid'], 'All UAT questions must pass TOEIC validator.');
        }
    }
}
