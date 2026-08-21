<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Assessment\Engines\AssignmentEngine;
use App\Modules\Assessment\Enums\AssessmentMode;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\CandidateTestAssignment;
use App\Modules\Assessment\Models\Test;
use App\Modules\Commerce\Application\BillingEngine;
use App\Modules\Commerce\Application\CheckoutEngine;
use App\Modules\Commerce\Application\InvoiceEngine;
use App\Modules\Commerce\Application\PricingEngine;
use App\Modules\Commerce\Domain\Enums\PaymentStatus;
use App\Modules\Commerce\Domain\Models\Product;
use App\Modules\Commerce\Domain\Models\ProductCategory;
use App\Modules\QuestionBank\Enums\TestType;
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

        // Assert UI Text
        $response->assertSee('Operational Dashboard');
        $response->assertSee('OPERATIONAL WORKSPACE');
        $response->assertSee('Total Registered Candidates');
        $response->assertSee('Paid &amp; Eligible Candidates', false);
        $response->assertSee('Active Test Assignments');
        $response->assertSee('Completed Assessments');
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
     * 3. Regular Admin Cannot Access Super Admin Approval Governance Routes
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
     * 4. Regular Admin Cannot Access Publication Queues
     */
    public function test_regular_admin_cannot_access_publication_queues(): void
    {
        $this->actingAs($this->admin)->get(route('admin.publications.question-banks'))->assertStatus(403);
        $this->actingAs($this->admin)->get(route('admin.publications.assessments'))->assertStatus(403);
        $this->actingAs($this->admin)->get(route('admin.publications.published'))->assertStatus(403);
        $this->actingAs($this->admin)->get(route('admin.publications.archive-requests'))->assertStatus(403);
    }

    /**
     * 5. Regular Admin Cannot Access Teacher Authoring Workspace Routes
     */
    public function test_regular_admin_cannot_access_teacher_authoring_routes(): void
    {
        $this->actingAs($this->admin)->get(route('teacher.dashboard'))->assertStatus(403);
        $this->actingAs($this->admin)->get(route('teacher.revision-center'))->assertStatus(403);
    }

    /**
     * 6. Regular Admin Can Access Candidate Management and Assessment Catalog
     */
    public function test_regular_admin_can_access_candidate_management_and_assessment_catalog(): void
    {
        $this->actingAs($this->admin)->get(route('admin.users.index'))->assertStatus(200);
        $this->actingAs($this->admin)->get(route('admin.certificates.index'))->assertStatus(200);
        $this->actingAs($this->admin)->get(route('admin.tests.index'))->assertStatus(200);
    }

    /**
     * 7. Regular Admin Dashboard Shows Candidates Requiring Action When Real Test is Paid
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
     * 8. Other Roles Maintain Respective Authorizations
     */
    public function test_teacher_rm_and_super_admin_routes_remain_functional(): void
    {
        // Teacher
        $this->actingAs($this->teacher)->get(route('teacher.dashboard'))->assertStatus(200);

        // Repository Manager
        $this->actingAs($this->rm)->get(route('admin.repository-manager.dashboard'))->assertStatus(200);
        $this->actingAs($this->rm)->get(route('admin.publications.question-banks'))->assertStatus(200);
        $this->actingAs($this->rm)->get(route('admin.publications.assessments'))->assertStatus(200);

        // Super Admin
        $this->actingAs($this->superAdmin)->get(route('super-admin.dashboard'))->assertStatus(200);
        $this->actingAs($this->superAdmin)->get(route('admin.approvals.index'))->assertStatus(200);
    }
}
