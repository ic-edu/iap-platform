<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Assessment\Engines\AssignmentEngine;
use App\Modules\Assessment\Enums\AssessmentMode;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\CandidateTestAssignment;
use App\Modules\Assessment\Models\Test;
use App\Modules\Certificate\Models\Certificate;
use App\Modules\Commerce\Application\BillingEngine;
use App\Modules\Commerce\Application\CheckoutEngine;
use App\Modules\Commerce\Application\InvoiceEngine;
use App\Modules\Commerce\Application\PricingEngine;
use App\Modules\Commerce\Domain\Enums\PaymentStatus;
use App\Modules\Commerce\Domain\Models\Product;
use App\Modules\Commerce\Domain\Models\ProductCategory;
use App\Modules\QuestionBank\Enums\TestType;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Services\NavigationService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOperationalWorkspaceRefactorTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $superAdmin;
    protected User $rm;
    protected User $teacher;
    protected User $student;
    protected Test $simulatorTest;
    protected Test $realTest;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create(['name' => 'Op Admin', 'status' => 'active']);
        $this->admin->assignRole('admin');

        $this->superAdmin = User::factory()->create(['name' => 'SA Boss', 'status' => 'active']);
        $this->superAdmin->assignRole('super-admin');

        $this->rm = User::factory()->create(['name' => 'RM Vance', 'status' => 'active']);
        $this->rm->assignRole('repository-manager');

        $this->teacher = User::factory()->create(['name' => 'Teacher Joe', 'status' => 'active']);
        $this->teacher->assignRole('teacher');

        $this->student = User::factory()->create(['name' => 'Student Ken', 'status' => 'active']);
        $this->student->assignRole('student');

        $this->simulatorTest = Test::create([
            'title'            => 'TOEIC Simulator 01',
            'slug'             => 'toeic-sim-01',
            'test_type'        => TestType::Toeic,
            'assessment_mode'  => AssessmentMode::Simulator,
            'duration_minutes' => 60,
            'pass_score'       => 400,
            'is_published'     => true,
            'status'           => 'approved',
            'created_by'       => $this->teacher->id,
        ]);

        $this->realTest = Test::create([
            'title'            => 'TOEIC Real Exam 01',
            'slug'             => 'toeic-real-01',
            'test_type'        => TestType::Toeic,
            'assessment_mode'  => AssessmentMode::RealTest,
            'duration_minutes' => 120,
            'pass_score'       => 600,
            'is_published'     => true,
            'status'           => 'approved',
            'created_by'       => $this->rm->id,
        ]);
    }

    /**
     * 1. Regular Admin Dashboard Loads Successfully with Operational KPIs
     */
    public function test_regular_admin_dashboard_loads_with_operational_metrics(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'));
        $response->assertStatus(200);

        // Assert Operational View & Data
        $response->assertViewIs('admin.operational_dashboard');
        $response->assertViewHas('totalCandidates', 1);
        $response->assertViewHas('paidEligibleCandidatesCount', 0);
        $response->assertViewHas('activeAssignmentsCount', 0);
        $response->assertViewHas('completedAttemptsCount', 0);

        // Assert UI Text & Links
        $response->assertSee('Operational Dashboard');
        $response->assertSee('OPERATIONAL WORKSPACE');
        $response->assertSee('Total Registered Candidates');
        $response->assertSee('Paid &amp; Eligible Candidates', false);
        $response->assertSee('Active Test Assignments');
        $response->assertSee('Completed Assessments');

        // Assert Clean CTAs
        $response->assertSee('View Candidates &rarr;', false);
        $response->assertSee('View Eligible Candidates &rarr;', false);
        $response->assertSee('View Active Assignments &rarr;', false);
        $response->assertSee('View Completed Results &rarr;', false);

        // Header redundant Assessment Catalog button must NOT be present
        $response->assertDontSee('Assessment Catalog</a>', false);

        // Assessment Inventory Browse Tests redundant link must NOT be present
        $response->assertDontSee('Browse Tests &rarr;', false);

        // Assessment Inventory must be present with Details link
        $response->assertSee('Assessment Inventory');
        $response->assertSee('Details &rarr;', false);
    }

    /**
     * 2. Governance KPI Cards & Publication Queues are Absent from Admin Dashboard
     */
    public function test_governance_cards_and_publication_queues_are_absent_from_admin_dashboard(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'));
        $response->assertStatus(200);

        // Governance metrics must not be present
        $response->assertDontSee('Question Banks Ready to Publish');
        $response->assertDontSee('Assessments Ready to Publish');
        $response->assertDontSee('Pending Archive Requests');
        $response->assertDontSee('Published Today');
        $response->assertDontSee('Published This Week');
        $response->assertDontSee('Publication Queues');
    }

    /**
     * 3. KPI Filtered Destinations Route Properly
     */
    public function test_kpi_destinations_route_and_filter_correctly(): void
    {
        // 1. Paid & Eligible Filter on Users
        $userResponse = $this->actingAs($this->admin)->get(route('admin.users.index', ['filter' => 'paid-eligible']));
        $userResponse->assertStatus(200);
        $userResponse->assertSee('Paid &amp; Eligible Candidates', false);

        // 2. Active Assignments Filter on Tests
        $testResponse = $this->actingAs($this->admin)->get(route('admin.tests.index', ['filter' => 'active-assignments']));
        $testResponse->assertStatus(200);
        $testResponse->assertSee('Assessments with Active Candidate Assignments', false);

        // 3. Completed Assessments routes to Reporting
        $reportResponse = $this->actingAs($this->admin)->get(route('admin.reporting.index'));
        $reportResponse->assertStatus(200);
    }

    /**
     * 4. Regular Admin Sidebar Excludes Media Library, Academic Libraries, and Course Management
     */
    public function test_regular_admin_sidebar_menu_isolation(): void
    {
        $this->actingAs($this->admin);
        $menuItems = NavigationService::getMenuItems();
        $routes = array_column($menuItems, 'route');

        $this->assertContains('admin.dashboard', $routes);
        $this->assertContains('admin.users.index', $routes);
        $this->assertContains('admin.certificates.index', $routes);
        $this->assertContains('admin.tests.index', $routes);
        $this->assertContains('admin.academic-operations.applications', $routes);
        $this->assertContains('admin.academic-operations.enrollments', $routes);
        $this->assertContains('admin.academic-operations.teacher-assignments', $routes);
        $this->assertContains('admin.commerce.index', $routes);
        $this->assertContains('admin.reporting.index', $routes);

        // Strictly Excluded Menus
        $this->assertNotContains('admin.media.index', $routes);
        $this->assertNotContains('admin.academic-library.index', $routes);
        $this->assertNotContains('admin.academic-operations.libraries', $routes);
        $this->assertNotContains('admin.academic-operations.courses', $routes);
        $this->assertNotContains('admin.publications.question-banks', $routes);
    }

    /**
     * 5. Regular Admin Cannot Access Super Admin Approval Governance Routes
     */
    public function test_regular_admin_cannot_access_super_admin_approval_governance_routes(): void
    {
        $this->actingAs($this->admin)->get(route('admin.approvals.index'))->assertStatus(403);
        $this->actingAs($this->admin)->get(route('admin.approvals.assessments'))->assertStatus(403);
        $this->actingAs($this->admin)->get(route('admin.approvals.question-banks'))->assertStatus(403);
        $this->actingAs($this->admin)->get(route('admin.monitoring.index'))->assertStatus(403);
        $this->actingAs($this->admin)->get(route('admin.settings.index'))->assertStatus(403);
        $this->actingAs($this->admin)->get(route('admin.archived-repositories.index'))->assertStatus(403);
    }

    /**
     * 6. Regular Admin Cannot Access Publication Queues
     */
    public function test_regular_admin_cannot_access_publication_queues(): void
    {
        $this->actingAs($this->admin)->get(route('admin.publications.question-banks'))->assertStatus(403);
        $this->actingAs($this->admin)->get(route('admin.publications.assessments'))->assertStatus(403);
        $this->actingAs($this->admin)->get(route('admin.publications.published'))->assertStatus(403);
        $this->actingAs($this->admin)->get(route('admin.publications.archive-requests'))->assertStatus(403);
    }

    /**
     * 7. Regular Admin Cannot Access Teacher Authoring Workspace Routes
     */
    public function test_regular_admin_cannot_access_teacher_authoring_routes(): void
    {
        $this->actingAs($this->admin)->get(route('teacher.dashboard'))->assertStatus(403);
        $this->actingAs($this->admin)->get(route('teacher.revision-center'))->assertStatus(403);

        // Cannot create assessment tests
        $response = $this->actingAs($this->admin)->post(route('admin.tests.store'), [
            'title' => 'Admin Unauthorized Test',
            'test_type' => 'toeic',
            'duration_minutes' => 60,
            'pass_score' => 500,
        ]);
        $response->assertStatus(403);

        // Cannot edit assessment definitions
        $updateResp = $this->actingAs($this->admin)->put(route('teacher.tests.update', $this->simulatorTest), [
            'title' => 'Admin Updated Test',
        ]);
        $updateResp->assertStatus(403);
    }

    /**
     * 8. Regular Admin Cannot Access Question Bank Authoring
     */
    public function test_regular_admin_cannot_author_question_banks_or_questions(): void
    {
        $bank = QuestionBank::create([
            'title' => 'Sample Pool',
            'slug' => 'sample-pool',
            'test_type' => 'toeic',
            'created_by' => $this->teacher->id,
        ]);

        // Regular Admin cannot create question bank
        $bankResp = $this->actingAs($this->admin)->post(route('admin.question-banks.store'), [
            'title' => 'Admin Bank',
            'test_type' => 'toeic',
        ]);
        $bankResp->assertStatus(403);

        // Regular Admin cannot add question to bank
        $qResp = $this->actingAs($this->admin)->post(route('admin.question-banks.store-question', $bank), [
            'prompt' => 'Question prompt',
            'question_type' => 'multiple_choice',
            'difficulty' => 'easy',
            'points' => 5,
        ]);
        $qResp->assertStatus(403);
    }

    /**
     * 9. Regular Admin Cannot Access Media Library or Academic Libraries
     */
    public function test_regular_admin_cannot_access_media_or_academic_libraries(): void
    {
        $this->actingAs($this->admin)->get(route('admin.media.index'))->assertStatus(403);
        $this->actingAs($this->admin)->get(route('admin.academic-library.index'))->assertStatus(403);
        $this->actingAs($this->admin)->get(route('admin.academic-operations.libraries'))->assertStatus(403);
    }

    /**
     * 10. Regular Admin Cannot Access RM Dashboard
     */
    public function test_regular_admin_cannot_access_rm_dashboard(): void
    {
        $this->actingAs($this->admin)->get(route('admin.repository-manager.dashboard'))->assertStatus(403);
    }

    /**
     * 11. Regular Admin Opens Assessment Assignment & Operations Workspace Without Authoring Controls
     */
    public function test_regular_admin_opens_assessment_operations_workspace_cleanly(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.tests.index'));
        $response->assertStatus(200);
        $response->assertViewIs('assessment::admin_operations');
        $response->assertSee('Assessment Assignment &amp; Operations', false);
        $response->assertSee('TOEIC Simulator 01');
        $response->assertSee('TOEIC Real Exam 01');
        $response->assertSee('Manage Assignments');

        // Authoring controls must be absent
        $response->assertDontSee('+ New Assessment');
        $response->assertDontSee('Continue Draft');
        $response->assertDontSee('Question Banks');

        // Detail show page renders assignment management workspace
        $showResponse = $this->actingAs($this->admin)->get(route('admin.tests.show', $this->realTest));
        $showResponse->assertStatus(200);
        $showResponse->assertViewIs('assessment::admin_show');
        $showResponse->assertSee('Assessment Assignment &amp; Candidates', false);
        $showResponse->assertSee('Assign Candidate');
        $showResponse->assertSee('Real Test Payment Rule');
        $showResponse->assertDontSee('Add Question');
        $showResponse->assertDontSee('Add Section');
        $showResponse->assertDontSee('Submit for Approval');
    }

    /**
     * 12. Regular Admin Dashboard Shows Candidates Requiring Action When Real Test is Paid
     */
    public function test_regular_admin_dashboard_shows_paid_candidate_in_action_panel(): void
    {
        $cat = ProductCategory::create(['name' => 'Vouchers', 'slug' => 'vouchers']);
        $product = Product::create([
            'title'        => 'Real Test Voucher',
            'slug'         => 'real-test-voucher',
            'product_type' => 'assessment',
            'category_id'  => $cat->id,
            'price'        => 500000,
            'is_active'    => true,
            'test_id'      => $this->realTest->id,
        ]);

        $checkout = new CheckoutEngine(new PricingEngine, new InvoiceEngine);
        $orderRes = $checkout->checkout($this->student, $product);

        $billing = new BillingEngine;
        $payment = $billing->createPayment($orderRes['invoice'], 'manual_transfer');
        $billing->confirmPayment($payment, 'TXN-OPS-001');

        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'));
        $response->assertStatus(200);

        // Candidate appears in "Candidates Requiring Action" panel
        $response->assertSee('Candidates Requiring Action');
        $response->assertSee('Student Ken');
        $response->assertSee('TOEIC Real Exam 01');
        $response->assertSee('Assign Real Test');

        // Admin triggers explicit assignment
        $assignResponse = $this->actingAs($this->admin)->post(route('admin.tests.assign-candidate', $this->realTest), [
            'candidate_id' => $this->student->id,
        ]);
        $assignResponse->assertSessionHas('status');

        $assignment = CandidateTestAssignment::where('user_id', $this->student->id)->where('test_id', $this->realTest->id)->first();
        $this->assertNotNull($assignment);
        $this->assertTrue($assignment->isActive());
        $this->assertSame($this->admin->id, $assignment->assigned_by);
    }

    /**
     * 13. Regular Admin Operational Domain Access (Candidates, Certificates, Commerce, Reports, Academic Ops)
     */
    public function test_regular_admin_operational_domain_access(): void
    {
        // Candidates
        $this->actingAs($this->admin)->get(route('admin.users.index'))->assertStatus(200);

        // Certificates
        $this->actingAs($this->admin)->get(route('admin.certificates.index'))->assertStatus(200);

        // Commerce & Billing
        $this->actingAs($this->admin)->get(route('admin.commerce.index'))->assertStatus(200);

        // Reports & Analytics
        $this->actingAs($this->admin)->get(route('admin.reporting.index'))->assertStatus(200);

        // Student Applications
        $this->actingAs($this->admin)->get(route('admin.academic-operations.applications'))->assertStatus(200);

        // Student Enrollments
        $this->actingAs($this->admin)->get(route('admin.academic-operations.enrollments'))->assertStatus(200);

        // Teacher Assignments
        $this->actingAs($this->admin)->get(route('admin.academic-operations.teacher-assignments'))->assertStatus(200);

        // Courses backend route exists and returns 200 (though menu is hidden from sidebar)
        $this->actingAs($this->admin)->get(route('admin.academic-operations.courses'))->assertStatus(200);
    }

    /**
     * 14. Teacher, RM, and Super Admin Access Rights Remain Fully Intact
     */
    public function test_teacher_rm_and_super_admin_routes_remain_functional(): void
    {
        // Teacher
        $this->actingAs($this->teacher)->get(route('teacher.dashboard'))->assertStatus(200);
        $this->actingAs($this->teacher)->get(route('admin.media.index'))->assertStatus(200);

        // Repository Manager
        $this->actingAs($this->rm)->get(route('admin.repository-manager.dashboard'))->assertStatus(200);
        $this->actingAs($this->rm)->get(route('admin.publications.question-banks'))->assertStatus(200);
        $this->actingAs($this->rm)->get(route('admin.publications.assessments'))->assertStatus(200);
        $this->actingAs($this->rm)->get(route('admin.academic-library.index'))->assertStatus(200);
        $this->actingAs($this->rm)->get(route('admin.media.index'))->assertStatus(200);

        // Super Admin
        $this->actingAs($this->superAdmin)->get(route('super-admin.dashboard'))->assertStatus(200);
        $this->actingAs($this->superAdmin)->get(route('admin.approvals.index'))->assertStatus(200);
    }

    /**
     * 15. PaymentStatus Enum Integrity and Commerce Hydration
     */
    public function test_payment_status_enum_integrity_and_commerce_hydration(): void
    {
        // Enum cases verification
        $this->assertSame('pending', PaymentStatus::Pending->value);
        $this->assertSame('success', PaymentStatus::Success->value);
        $this->assertSame('paid', PaymentStatus::Paid->value);
        $this->assertSame('failed', PaymentStatus::Failed->value);
        $this->assertSame('refunded', PaymentStatus::Refunded->value);

        $this->assertTrue(PaymentStatus::Success->isSuccess());
        $this->assertTrue(PaymentStatus::Paid->isSuccess());
        $this->assertFalse(PaymentStatus::Pending->isSuccess());
        $this->assertFalse(PaymentStatus::Failed->isSuccess());

        // Test Hydration of Payment model with both success and paid statuses
        $cat = ProductCategory::create(['name' => 'Assessments', 'slug' => 'assessments']);
        $product = Product::create([
            'title'        => 'TOEIC Exam Voucher',
            'slug'         => 'toeic-exam-voucher',
            'product_type' => 'assessment',
            'category_id'  => $cat->id,
            'price'        => 250000,
            'is_active'    => true,
            'test_id'      => $this->simulatorTest->id,
        ]);

        $checkout = new CheckoutEngine(new PricingEngine, new InvoiceEngine);
        $orderRes = $checkout->checkout($this->student, $product);

        $billing = new BillingEngine;
        $payment = $billing->createPayment($orderRes['invoice'], 'manual_transfer');
        $billing->confirmPayment($payment, 'TXN-TEST-12345');

        // Verify /admin/commerce renders 200 without ValueError
        $response = $this->actingAs($this->admin)->get(route('admin.commerce.index'));
        $response->assertStatus(200);
        $response->assertSee('Commerce &amp; Finance Management', false);
        $response->assertSee($payment->reference_number);
        $response->assertSee('SUCCESS');
    }

    /**
     * 16. Reporting Analytics Dashboard & CSV Export
     */
    public function test_reporting_dashboard_comprehensive_metrics_and_csv_export(): void
    {
        // Update test type to general for scoring
        $this->simulatorTest->update(['test_type' => TestType::General, 'pass_score' => 500]);

        // Create an attempt with result summary
        $attempt = Attempt::create([
            'test_id' => $this->simulatorTest->id,
            'user_id' => $this->student->id,
            'status' => 'submitted',
            'total_score' => 750,
            'started_at' => now()->subHour(),
            'completed_at' => now(),
            'submitted_at' => now(),
            'current_section_index' => 0,
            'answers' => [],
            'section_scores' => [],
        ]);

        // Create Certificate
        Certificate::create([
            'attempt_id' => $attempt->id,
            'user_id' => $this->student->id,
            'certificate_number' => 'CERT-2026-9999',
            'verification_code' => 'VERIF-9999',
            'status' => 'valid',
            'issued_at' => now(),
        ]);

        // Access Reporting Dashboard
        $response = $this->actingAs($this->admin)->get(route('admin.reporting.index'));
        $response->assertStatus(200);
        $response->assertViewIs('reporting::index');
        $response->assertViewHas('totalStudents', 1);
        $response->assertViewHas('totalTests', 2);
        $response->assertViewHas('simulatorTests', 1);
        $response->assertViewHas('realTests', 1);
        $response->assertViewHas('totalAttempts', 1);
        $response->assertViewHas('totalPassed', 1);
        $response->assertViewHas('passRate', 100.0);
        $response->assertViewHas('totalCertificates', 1);

        $response->assertSee('OPERATIONAL ANALYTICS');
        $response->assertSee('Assessment Reports &amp; Analytics', false);
        $response->assertSee('TOEIC Simulator 01');
        $response->assertDontSee('Recent Assessment Submissions');

        // Test CSV Export
        $csvResponse = $this->actingAs($this->admin)->get(route('admin.reporting.export-csv'));
        $csvResponse->assertStatus(200);
        $this->assertStringContainsString('text/csv', (string) $csvResponse->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment; filename="assessment_report_', $csvResponse->headers->get('Content-Disposition'));
    }

    public function test_operational_dashboard_header_polished_and_compact_empty_state(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'));
        $response->assertStatus(200);

        // Manage Candidates button removed from header
        $response->assertDontSee('>Manage Candidates</a>', false);

        // Compact empty state when 0 action candidates
        $response->assertSee('All Clear:');
        $response->assertSee('No paid candidates are currently waiting for Real Test assignment.');

        // Topbar has Dashboard button for Regular Admin instead of + Quick Action
        $response->assertSee('🏠 Dashboard');
        $response->assertDontSee('+ Quick Action');
    }

    public function test_platform_wide_appearance_settings_accessible_for_all_five_roles(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super-admin');

        $rm = User::factory()->create();
        $rm->assignRole('repository-manager');

        $teacher = User::factory()->create();
        $teacher->assignRole('teacher');

        $roles = [$superAdmin, $this->admin, $rm, $teacher, $this->student];

        foreach ($roles as $user) {
            $response = $this->actingAs($user)->get(route('settings.appearance'));
            $response->assertStatus(200);
            $response->assertSee('Appearance &amp; Theme Settings', false);
            $response->assertSee('Dark Mode');
            $response->assertSee('Light Mode');
            $response->assertSee('System Default');
        }
    }

    public function test_appearance_settings_persists_theme_preference_per_user(): void
    {
        // Default is dark
        $this->assertSame('dark', $this->student->getThemePreference());

        // Update to light
        $response = $this->actingAs($this->student)->post(route('settings.appearance.update'), [
            'theme' => 'light',
        ]);
        $response->assertSessionHas('status', 'theme-updated');
        $this->student->refresh();
        $this->assertSame('light', $this->student->getThemePreference());
        $this->assertSame('light', session('theme_preference'));

        // Update to system via JSON
        $jsonResponse = $this->actingAs($this->admin)->postJson(route('settings.appearance.update'), [
            'theme' => 'system',
        ]);
        $jsonResponse->assertStatus(200);
        $jsonResponse->assertJson([
            'success' => true,
            'theme' => 'system',
        ]);
        $this->admin->refresh();
        $this->assertSame('system', $this->admin->getThemePreference());
    }
}

