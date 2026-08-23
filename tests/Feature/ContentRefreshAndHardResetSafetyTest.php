<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\ContentResetAuditLog;
use App\Models\ContentResetRequest;
use App\Models\User;
use App\Modules\Assessment\Engines\AssignmentEngine;
use App\Modules\Assessment\Engines\AttemptEngine;
use App\Modules\Assessment\Enums\AssessmentMode;
use App\Modules\Assessment\Enums\AttemptStatus;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\CandidateTestAssignment;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestQuestion;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\Certificate\Models\Certificate;
use App\Modules\Commerce\Domain\Enums\OrderStatus;
use App\Modules\Commerce\Domain\Enums\PaymentStatus;
use App\Modules\Commerce\Domain\Models\Invoice;
use App\Modules\Commerce\Domain\Models\Order;
use App\Modules\Commerce\Domain\Models\OrderItem;
use App\Modules\Commerce\Domain\Models\Payment;
use App\Modules\Commerce\Domain\Models\Product;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Modules\QuestionBank\Models\QuestionChoice;
use App\Services\ContentReset\ContentResetApprovalService;
use App\Services\ContentReset\ContentResetAuditService;
use App\Services\ContentReset\ContentResetDomains;
use App\Services\ContentReset\ContentResetExecutor;
use App\Services\ContentReset\ContentResetIntegrityService;
use App\Services\ToeicQuestionValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ContentRefreshAndHardResetSafetyTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $superAdmin;
    protected User $ceo;
    protected User $candidate;
    protected QuestionBank $bank;
    protected Question $q1;
    protected Question $q2;
    protected Question $sharedQ;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed Roles
        Role::firstOrCreate(['name' => 'super-admin']);
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'ceo']);
        Role::firstOrCreate(['name' => 'repository-manager']);
        Role::firstOrCreate(['name' => 'teacher']);
        Role::firstOrCreate(['name' => 'student']);

        // Create Users
        $this->admin = User::factory()->create(['name' => 'Admin Requester', 'email' => 'admin.req@icedu.org', 'status' => 'active']);
        $this->admin->assignRole('admin');

        $this->superAdmin = User::factory()->create(['name' => 'SA Approver', 'email' => 'sa.approver@icedu.org', 'status' => 'active']);
        $this->superAdmin->assignRole('super-admin');

        $this->ceo = User::factory()->create(['name' => 'CEO Approver', 'email' => 'ceo.approver@icedu.org', 'status' => 'active']);
        $this->ceo->assignRole('ceo');

        $this->candidate = User::factory()->create(['name' => 'Candidate User', 'email' => 'candidate@icedu.org', 'status' => 'active']);
        $this->candidate->assignRole('student');

        // Create Question Bank & Questions
        $this->bank = QuestionBank::create([
            'title'       => 'TOEIC Reset Bank',
            'slug'        => 'toeic-reset-bank',
            'type'        => 'toeic',
            'created_by'  => $this->admin->id,
            'is_approved' => true,
        ]);

        $this->q1 = Question::create([
            'question_bank_id' => $this->bank->id,
            'prompt'           => 'Draft assessment question 1',
            'points'           => 10,
        ]);
        QuestionChoice::create(['question_id' => $this->q1->id, 'label' => 'A', 'content' => 'Choice A', 'is_correct' => true]);

        $this->q2 = Question::create([
            'question_bank_id' => $this->bank->id,
            'prompt'           => 'Draft assessment question 2',
            'points'           => 10,
        ]);
        QuestionChoice::create(['question_id' => $this->q2->id, 'label' => 'A', 'content' => 'Choice A', 'is_correct' => true]);

        // Shared Question used across multiple tests
        $this->sharedQ = Question::create([
            'question_bank_id' => $this->bank->id,
            'prompt'           => 'Shared institutional repository question',
            'points'           => 10,
        ]);
        QuestionChoice::create(['question_id' => $this->sharedQ->id, 'label' => 'A', 'content' => 'Choice A', 'is_correct' => true]);
    }

    /**
     * Helper to create a Draft Test.
     */
    protected function createDraftTest(string $title = 'Obsolete Draft Test'): Test
    {
        $test = Test::create([
            'title'           => $title,
            'slug'            => 'draft-test-' . uniqid(),
            'assessment_mode' => AssessmentMode::RealTest,
            'status'          => 'draft',
            'is_published'    => false,
            'duration'        => 60,
            'pass_score'      => 70,
            'created_by'      => $this->admin->id,
        ]);

        $section = TestSection::create(['test_id' => $test->id, 'title' => 'Draft Section', 'order' => 1]);
        TestQuestion::create(['test_section_id' => $section->id, 'question_id' => $this->q1->id, 'order' => 1]);
        TestQuestion::create(['test_section_id' => $section->id, 'question_id' => $this->sharedQ->id, 'order' => 2]);

        return $test;
    }

    /**
     * Helper to create a Published Live Test.
     */
    protected function createPublishedTest(string $title = 'Active Live Test'): Test
    {
        $test = Test::create([
            'title'           => $title,
            'slug'            => 'published-test-' . uniqid(),
            'assessment_mode' => AssessmentMode::RealTest,
            'status'          => 'published',
            'is_published'    => true,
            'duration'        => 120,
            'pass_score'      => 700,
            'created_by'      => $this->admin->id,
        ]);

        $section = TestSection::create(['test_id' => $test->id, 'title' => 'Live Section', 'order' => 1]);
        TestQuestion::create(['test_section_id' => $section->id, 'question_id' => $this->sharedQ->id, 'order' => 1]);

        return $test;
    }

    /**
     * TEST 1: Content Refresh request is created.
     */
    public function test_content_refresh_request_can_be_created(): void
    {
        $test = $this->createDraftTest();

        $response = $this->actingAs($this->admin)->post(route('admin.content-reset.store'), [
            'mode'               => ContentResetDomains::MODE_REFRESH,
            'reason'             => 'Routine cleanup of obsolete draft assessment.',
            'scope'              => [ContentResetDomains::DRAFT_ASSESSMENTS],
            'target_assessments' => [$test->id],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('content_reset_requests', [
            'mode'         => 'refresh',
            'requested_by' => $this->admin->id,
            'status'       => 'audit_completed',
        ]);
    }

    /**
     * TEST 2: Forensic audit generates exact dependency map.
     */
    public function test_forensic_audit_generates_exact_dependency_map(): void
    {
        $draftTest = $this->createDraftTest();
        $otherTest = $this->createPublishedTest();

        $req = ContentResetRequest::create([
            'request_number'   => 'RST-TEST-002',
            'mode'             => ContentResetDomains::MODE_REFRESH,
            'status'           => 'draft',
            'requested_by'     => $this->admin->id,
            'reason'           => 'Audit dependency map test',
            'scope'            => [ContentResetDomains::DRAFT_ASSESSMENTS],
            'target_entities'  => ['assessment_ids' => [$draftTest->id]],
        ]);

        $auditReport = app(ContentResetAuditService::class)->performForensicAudit($req);

        $this->assertFalse($auditReport['is_blocked']);
        $this->assertSame(1, $auditReport['target_assessments_count']);
        $this->assertSame(1, $auditReport['target_sections_count']);
        $this->assertSame(2, $auditReport['test_question_bindings_count']);
        $this->assertSame(1, $auditReport['shared_questions_preserved']); // $sharedQ is used in $otherTest
        $this->assertSame(1, $auditReport['deletable_questions_count']);     // $q1 is only in $draftTest
    }

    /**
     * TEST 3: Dry run produces no data changes.
     */
    public function test_dry_run_produces_no_data_changes(): void
    {
        $draftTest = $this->createDraftTest();
        $initialTestCount = Test::count();
        $initialSectionCount = TestSection::count();
        $initialBindingCount = TestQuestion::count();

        $req = ContentResetRequest::create([
            'request_number'   => 'RST-TEST-003',
            'mode'             => ContentResetDomains::MODE_REFRESH,
            'status'           => 'draft',
            'requested_by'     => $this->admin->id,
            'reason'           => 'Dry run test',
            'scope'            => [ContentResetDomains::DRAFT_ASSESSMENTS],
            'target_entities'  => ['assessment_ids' => [$draftTest->id]],
        ]);

        $result = app(ContentResetExecutor::class)->execute($req, $this->admin, true);

        $this->assertTrue($result['dry_run']);
        $this->assertFalse($result['database_mutated']);
        $this->assertSame($initialTestCount, Test::count());
        $this->assertSame($initialSectionCount, TestSection::count());
        $this->assertSame($initialBindingCount, TestQuestion::count());
    }

    /**
     * TEST 4: Draft assessment can be included in refresh scope.
     */
    public function test_draft_assessment_can_be_executed_in_refresh_scope(): void
    {
        $draftTest = $this->createDraftTest();

        $req = ContentResetRequest::create([
            'request_number'   => 'RST-TEST-004',
            'mode'             => ContentResetDomains::MODE_REFRESH,
            'status'           => 'audit_completed',
            'requested_by'     => $this->admin->id,
            'reason'           => 'Routine draft purge',
            'scope'            => [ContentResetDomains::DRAFT_ASSESSMENTS, ContentResetDomains::ASSESSMENT_TEST_SECTIONS],
            'target_entities'  => ['assessment_ids' => [$draftTest->id]],
        ]);

        $result = app(ContentResetExecutor::class)->execute($req, $this->admin, false);

        $this->assertTrue($result['success']);
        $this->assertSame('completed', $req->fresh()->status);
        $this->assertDatabaseMissing('tests', ['id' => $draftTest->id]);
    }

    /**
     * TEST 5: Published assessment cannot be reset without explicit hard-reset scope.
     */
    public function test_published_assessment_cannot_be_reset_in_refresh_mode(): void
    {
        $publishedTest = $this->createPublishedTest();

        $req = ContentResetRequest::create([
            'request_number'   => 'RST-TEST-005',
            'mode'             => ContentResetDomains::MODE_REFRESH, // Routine Refresh
            'status'           => 'draft',
            'requested_by'     => $this->admin->id,
            'reason'           => 'Attempting to refresh published test',
            'scope'            => [ContentResetDomains::DRAFT_ASSESSMENTS],
            'target_entities'  => ['assessment_ids' => [$publishedTest->id]],
        ]);

        $auditReport = app(ContentResetAuditService::class)->performForensicAudit($req);

        $this->assertTrue($auditReport['is_blocked']);
        $this->assertStringContainsString('cannot be reset in routine Content Refresh mode', $auditReport['blockers'][0]);
    }

    /**
     * TEST 6: Active assignment blocks hard reset.
     */
    public function test_active_assignment_blocks_hard_reset(): void
    {
        $test = $this->createPublishedTest();

        // Create Active Assignment
        CandidateTestAssignment::create([
            'user_id'     => $this->candidate->id,
            'test_id'     => $test->id,
            'assigned_by' => $this->admin->id,
            'status'      => 'active',
            'assigned_at' => now(),
        ]);

        $req = ContentResetRequest::create([
            'request_number'   => 'RST-TEST-006',
            'mode'             => ContentResetDomains::MODE_HARD_RESET,
            'status'           => 'draft',
            'requested_by'     => $this->admin->id,
            'reason'           => 'Hard reset with active assignment',
            'scope'            => [ContentResetDomains::DRAFT_ASSESSMENTS],
            'target_entities'  => ['assessment_ids' => [$test->id]],
        ]);

        $auditReport = app(ContentResetAuditService::class)->performForensicAudit($req);

        $this->assertTrue($auditReport['is_blocked']);
        $this->assertStringContainsString('active candidate assignment(s)', $auditReport['blockers'][0]);
    }

    /**
     * TEST 7: Active in-progress attempt blocks hard reset.
     */
    public function test_active_attempt_blocks_hard_reset(): void
    {
        $test = $this->createPublishedTest();

        // Create In-Progress Attempt
        Attempt::create([
            'test_id'    => $test->id,
            'user_id'    => $this->candidate->id,
            'status'     => AttemptStatus::InProgress,
            'started_at' => now(),
        ]);

        $req = ContentResetRequest::create([
            'request_number'   => 'RST-TEST-007',
            'mode'             => ContentResetDomains::MODE_HARD_RESET,
            'status'           => 'draft',
            'requested_by'     => $this->admin->id,
            'reason'           => 'Hard reset with in-progress attempt',
            'scope'            => [ContentResetDomains::DRAFT_ASSESSMENTS],
            'target_entities'  => ['assessment_ids' => [$test->id]],
        ]);

        $auditReport = app(ContentResetAuditService::class)->performForensicAudit($req);

        $this->assertTrue($auditReport['is_blocked']);
        $this->assertStringContainsString('in-progress candidate attempt(s)', $auditReport['blockers'][0]);
    }

    /**
     * TEST 8: Certificate dependency blocks hard reset.
     */
    public function test_certificate_dependency_blocks_hard_reset(): void
    {
        $test = $this->createPublishedTest();

        $attempt = Attempt::create([
            'test_id'      => $test->id,
            'user_id'      => $this->candidate->id,
            'status'       => AttemptStatus::Submitted,
            'started_at'   => now()->subHour(),
            'submitted_at' => now(),
            'total_score'  => 850,
            'is_final'     => true,
        ]);

        Certificate::create([
            'certificate_number' => 'CERT-TEST-BLOCK',
            'verification_code'  => 'VRF-TEST-001',
            'attempt_id'         => $attempt->id,
            'user_id'            => $this->candidate->id,
            'status'             => 'valid',
            'issued_at'          => now(),
        ]);

        $req = ContentResetRequest::create([
            'request_number'   => 'RST-TEST-008',
            'mode'             => ContentResetDomains::MODE_HARD_RESET,
            'status'           => 'draft',
            'requested_by'     => $this->admin->id,
            'reason'           => 'Hard reset with certificate',
            'scope'            => [ContentResetDomains::DRAFT_ASSESSMENTS],
            'target_entities'  => ['assessment_ids' => [$test->id]],
        ]);

        $auditReport = app(ContentResetAuditService::class)->performForensicAudit($req);

        $this->assertTrue($auditReport['is_blocked']);
        $this->assertStringContainsString('active digital certificate(s) issued', $auditReport['blockers'][0]);
    }

    /**
     * TEST 9: Finance dependency blocks hard reset.
     */
    public function test_finance_dependency_blocks_hard_reset(): void
    {
        $test = $this->createPublishedTest();

        $product = Product::create([
            'title'        => 'Test Product',
            'slug'         => 'test-prod-' . uniqid(),
            'product_type' => 'placement_test',
            'price'        => 250000,
            'test_id'      => $test->id,
            'is_active'    => true,
        ]);

        $order = Order::create([
            'order_number'    => 'ORD-TEST-009',
            'user_id'         => $this->candidate->id,
            'total_amount'    => 250000,
            'status'          => OrderStatus::Completed,
            'billing_details' => ['name' => 'Candidate User'],
        ]);

        OrderItem::create([
            'order_id'   => $order->id,
            'product_id' => $product->id,
            'quantity'   => 1,
            'price'      => 250000,
            'total'      => 250000,
        ]);

        $req = ContentResetRequest::create([
            'request_number'   => 'RST-TEST-009',
            'mode'             => ContentResetDomains::MODE_HARD_RESET,
            'status'           => 'draft',
            'requested_by'     => $this->admin->id,
            'reason'           => 'Hard reset with commerce item',
            'scope'            => [ContentResetDomains::DRAFT_ASSESSMENTS],
            'target_entities'  => ['assessment_ids' => [$test->id]],
        ]);

        $auditReport = app(ContentResetAuditService::class)->performForensicAudit($req);

        $this->assertTrue($auditReport['is_blocked']);
        $this->assertStringContainsString('commercial order item(s)', $auditReport['blockers'][0]);
    }

    /**
     * TEST 10: Shared Question cannot be deleted when referenced elsewhere.
     */
    public function test_shared_question_is_preserved_when_referenced_elsewhere(): void
    {
        $draftTest = $this->createDraftTest();
        $otherActiveTest = $this->createPublishedTest('Retained Production Test');

        $req = ContentResetRequest::create([
            'request_number'   => 'RST-TEST-010',
            'mode'             => ContentResetDomains::MODE_REFRESH,
            'status'           => 'audit_completed',
            'requested_by'     => $this->admin->id,
            'reason'           => 'Shared question protection test',
            'scope'            => [ContentResetDomains::DRAFT_ASSESSMENTS, ContentResetDomains::QUESTION_CONTENT],
            'target_entities'  => ['assessment_ids' => [$draftTest->id]],
        ]);

        app(ContentResetExecutor::class)->execute($req, $this->admin, false);

        // $draftTest was deleted
        $this->assertDatabaseMissing('tests', ['id' => $draftTest->id]);

        // $sharedQ is preserved because $otherActiveTest references it
        $this->assertDatabaseHas('questions', ['id' => $this->sharedQ->id]);

        // $otherActiveTest is intact
        $this->assertDatabaseHas('tests', ['id' => $otherActiveTest->id]);
    }

    /**
     * TEST 11: Hard Reset requires CEO approval.
     */
    public function test_hard_reset_requires_ceo_approval(): void
    {
        $draftTest = $this->createDraftTest();

        $req = ContentResetRequest::create([
            'request_number'   => 'RST-TEST-011',
            'mode'             => ContentResetDomains::MODE_HARD_RESET,
            'status'           => 'awaiting_ceo_approval',
            'requested_by'     => $this->admin->id,
            'reason'           => 'Hard reset approval test',
            'scope'            => [ContentResetDomains::DRAFT_ASSESSMENTS],
            'target_entities'  => ['assessment_ids' => [$draftTest->id]],
        ]);

        // Super Admin approves alone
        app(ContentResetApprovalService::class)->approveSuperAdmin($req, $this->superAdmin);

        $this->assertFalse($req->fresh()->isFullyApproved());

        // Executor refuses to execute without CEO approval
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('dual approvals');
        app(ContentResetExecutor::class)->execute($req->fresh(), $this->superAdmin, false);
    }

    /**
     * TEST 12: Hard Reset requires Super Admin approval.
     */
    public function test_hard_reset_requires_super_admin_approval(): void
    {
        $draftTest = $this->createDraftTest();

        $req = ContentResetRequest::create([
            'request_number'   => 'RST-TEST-012',
            'mode'             => ContentResetDomains::MODE_HARD_RESET,
            'status'           => 'awaiting_ceo_approval',
            'requested_by'     => $this->admin->id,
            'reason'           => 'Hard reset approval test',
            'scope'            => [ContentResetDomains::DRAFT_ASSESSMENTS],
            'target_entities'  => ['assessment_ids' => [$draftTest->id]],
        ]);

        // CEO approves alone
        app(ContentResetApprovalService::class)->approveCeo($req, $this->ceo);

        $this->assertFalse($req->fresh()->isFullyApproved());

        // Executor refuses to execute without Super Admin approval
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('dual approvals');
        app(ContentResetExecutor::class)->execute($req->fresh(), $this->ceo, false);
    }

    /**
     * TEST 13: One approval alone cannot execute.
     */
    public function test_one_approval_alone_cannot_execute(): void
    {
        $draftTest = $this->createDraftTest();

        $req = ContentResetRequest::create([
            'request_number'   => 'RST-TEST-013',
            'mode'             => ContentResetDomains::MODE_HARD_RESET,
            'status'           => 'awaiting_ceo_approval',
            'requested_by'     => $this->admin->id,
            'reason'           => 'Single approval execution test',
            'scope'            => [ContentResetDomains::DRAFT_ASSESSMENTS],
            'target_entities'  => ['assessment_ids' => [$draftTest->id]],
        ]);

        app(ContentResetApprovalService::class)->approveCeo($req, $this->ceo);

        $this->assertSame('awaiting_sa_approval', $req->fresh()->status);
        $this->assertFalse($req->fresh()->isFullyApproved());
    }

    /**
     * TEST 14: Scope change invalidates previous approvals.
     */
    public function test_scope_change_invalidates_previous_approvals(): void
    {
        $draftTest = $this->createDraftTest();

        $req = ContentResetRequest::create([
            'request_number'   => 'RST-TEST-014',
            'mode'             => ContentResetDomains::MODE_HARD_RESET,
            'status'           => 'awaiting_ceo_approval',
            'requested_by'     => $this->admin->id,
            'reason'           => 'Scope mutation test',
            'scope'            => [ContentResetDomains::DRAFT_ASSESSMENTS],
            'target_entities'  => ['assessment_ids' => [$draftTest->id]],
        ]);

        $approvalService = app(ContentResetApprovalService::class);
        $approvalService->approveCeo($req, $this->ceo);
        $approvalService->approveSuperAdmin($req, $this->superAdmin);

        $this->assertTrue($req->fresh()->isFullyApproved());

        // Scope changes (e.g. adding question_content)
        $approvalService->mutateScope($req, [ContentResetDomains::DRAFT_ASSESSMENTS, ContentResetDomains::QUESTION_CONTENT]);

        $req->refresh();
        $this->assertFalse($req->isFullyApproved());
        $this->assertNull($req->ceo_approver_id);
        $this->assertNull($req->sa_approver_id);
        $this->assertSame('audit_pending', $req->status);
    }

    /**
     * TEST 15: Executor rejects unapproved request.
     */
    public function test_executor_rejects_unapproved_request(): void
    {
        $draftTest = $this->createDraftTest();

        $req = ContentResetRequest::create([
            'request_number'   => 'RST-TEST-015',
            'mode'             => ContentResetDomains::MODE_HARD_RESET,
            'status'           => 'draft',
            'requested_by'     => $this->admin->id,
            'reason'           => 'Unapproved execution rejection test',
            'scope'            => [ContentResetDomains::DRAFT_ASSESSMENTS],
            'target_entities'  => ['assessment_ids' => [$draftTest->id]],
        ]);

        $this->expectException(\InvalidArgumentException::class);
        app(ContentResetExecutor::class)->execute($req, $this->admin, false);
    }

    /**
     * TEST 16: Protected domains cannot be added to reset scope.
     */
    public function test_protected_domains_cannot_be_added_to_reset_scope(): void
    {
        $req = ContentResetRequest::create([
            'request_number'   => 'RST-TEST-016',
            'mode'             => ContentResetDomains::MODE_HARD_RESET,
            'status'           => 'draft',
            'requested_by'     => $this->admin->id,
            'reason'           => 'Attempting to include finance in reset scope',
            'scope'            => [ContentResetDomains::FINANCE, ContentResetDomains::USERS],
        ]);

        $auditReport = app(ContentResetAuditService::class)->performForensicAudit($req);

        $this->assertTrue($auditReport['is_blocked']);
        $this->assertStringContainsString('is absolutely protected', $auditReport['blockers'][0]);
    }

    /**
     * TEST 17: Post-reset integrity validation detects no orphan records.
     */
    public function test_post_reset_integrity_validation_verifies_clean_state(): void
    {
        $preCounts = app(ContentResetIntegrityService::class)->capturePreResetCounts();
        $integrity = app(ContentResetIntegrityService::class)->verifyPostResetIntegrity($preCounts);

        $this->assertTrue($integrity['passed']);
        $this->assertSame(0, $integrity['orphan_sections']);
        $this->assertSame(0, $integrity['orphan_bindings']);
        $this->assertSame(0, $integrity['orphan_choices']);
        $this->assertTrue($integrity['protected_domain_ok']);
    }

    /**
     * TEST 18: Users, Roles, Permissions remain intact.
     */
    public function test_users_roles_permissions_remain_intact_after_execution(): void
    {
        $initialUsersCount = User::count();
        $initialRolesCount = Role::count();
        $initialPermsCount = Permission::count();

        $draftTest = $this->createDraftTest();

        $req = ContentResetRequest::create([
            'request_number'   => 'RST-TEST-018',
            'mode'             => ContentResetDomains::MODE_HARD_RESET,
            'status'           => 'awaiting_ceo_approval',
            'requested_by'     => $this->admin->id,
            'reason'           => 'Full hard reset execution test',
            'scope'            => [ContentResetDomains::DRAFT_ASSESSMENTS],
            'target_entities'  => ['assessment_ids' => [$draftTest->id]],
        ]);

        $approvalService = app(ContentResetApprovalService::class);
        $approvalService->approveCeo($req, $this->ceo);
        $approvalService->approveSuperAdmin($req, $this->superAdmin);

        $result = app(ContentResetExecutor::class)->execute($req, $this->superAdmin, false);

        $this->assertTrue($result['success']);
        $this->assertSame($initialUsersCount, User::count());
        $this->assertSame($initialRolesCount, Role::count());
        $this->assertSame($initialPermsCount, Permission::count());
    }

    /**
     * TEST 19: Finance records remain intact.
     */
    public function test_finance_remains_intact_after_reset(): void
    {
        $order = Order::create([
            'order_number'    => 'ORD-PERSIST-001',
            'user_id'         => $this->candidate->id,
            'total_amount'    => 100000,
            'status'          => OrderStatus::Completed,
            'billing_details' => ['name' => 'Candidate User'],
        ]);

        $draftTest = $this->createDraftTest();

        $req = ContentResetRequest::create([
            'request_number'   => 'RST-TEST-019',
            'mode'             => ContentResetDomains::MODE_REFRESH,
            'status'           => 'audit_completed',
            'requested_by'     => $this->admin->id,
            'reason'           => 'Finance safety test',
            'scope'            => [ContentResetDomains::DRAFT_ASSESSMENTS],
            'target_entities'  => ['assessment_ids' => [$draftTest->id]],
        ]);

        app(ContentResetExecutor::class)->execute($req, $this->admin, false);

        $this->assertDatabaseHas('orders', ['id' => $order->id]);
    }

    /**
     * TEST 20: Audit logs remain intact and are not deleted by reset.
     */
    public function test_audit_logs_remain_intact_and_are_appended(): void
    {
        $initialLogsCount = ActivityLog::count();
        $draftTest = $this->createDraftTest();

        $req = ContentResetRequest::create([
            'request_number'   => 'RST-TEST-020',
            'mode'             => ContentResetDomains::MODE_REFRESH,
            'status'           => 'audit_completed',
            'requested_by'     => $this->admin->id,
            'reason'           => 'Audit log preservation test',
            'scope'            => [ContentResetDomains::DRAFT_ASSESSMENTS],
            'target_entities'  => ['assessment_ids' => [$draftTest->id]],
        ]);

        app(ContentResetExecutor::class)->execute($req, $this->admin, false);

        $this->assertGreaterThanOrEqual($initialLogsCount, ActivityLog::count());
        $this->assertDatabaseHas('content_reset_audit_logs', [
            'request_id' => $req->id,
            'action'     => 'EXECUTION_COMMITTED',
        ]);
    }

    /**
     * TEST 21: Critical Routes remain intact.
     */
    public function test_critical_application_routes_remain_registered(): void
    {
        $this->assertTrue(Route::has('login'));
        $this->assertTrue(Route::has('candidate.portal'));
        $this->assertTrue(Route::has('admin.repository-manager.dashboard'));
        $this->assertTrue(Route::has('finance.dashboard'));
        $this->assertTrue(Route::has('admin.certificates.index'));
        $this->assertTrue(Route::has('admin.content-reset.index'));
    }

    /**
     * TEST 22: TOEIC Validator remains functional and intact.
     */
    public function test_toeic_validator_remains_functional_and_intact(): void
    {
        // ToeicQuestionValidator is a static utility — verify the core static method resolves correctly
        $result = ToeicQuestionValidator::check([
            'prompt'      => 'Sample TOEIC question prompt',
            'points'      => 5,
            'part_number' => 1,
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('is_valid', $result);
        $this->assertArrayHasKey('errors', $result);
    }
}

