<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserCreationRequest;
use App\Modules\Commerce\Domain\Models\PriceChangeRequest;
use App\Modules\Commerce\Domain\Models\Product;
use App\Modules\Organization\Enums\OrganizationStatus;
use App\Modules\Organization\Enums\OrganizationType;
use App\Modules\Organization\Models\Organization;
use App\Notifications\EnterpriseSystemNotification;
use App\Services\ApprovalEngine;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminGlobalApprovalCenterOrganizationIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $adminRA;
    protected User $rm;
    protected User $teacher;
    protected User $finance;
    protected User $student;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->superAdmin = User::factory()->create(['name' => 'Super Admin Demo', 'email' => 'superadmin@iap.test']);
        $this->superAdmin->assignRole('super-admin');

        $this->adminRA = User::factory()->create(['name' => 'Registration Admin Demo', 'email' => 'admin.ra@iap.test']);
        $this->adminRA->assignRole('admin');

        $this->rm = User::factory()->create(['name' => 'Repo Manager Demo', 'email' => 'rm@iap.test']);
        $this->rm->assignRole('repository-manager');

        $this->teacher = User::factory()->create(['name' => 'Teacher Demo', 'email' => 'teacher@iap.test']);
        $this->teacher->assignRole('teacher');

        $this->finance = User::factory()->create(['name' => 'Finance Demo', 'email' => 'finance@iap.test']);
        $this->finance->assignRole('finance');

        $this->student = User::factory()->create(['name' => 'Student Demo', 'email' => 'student@iap.test']);
        $this->student->assignRole('student');
    }

    public function test_approval_count_01_global_pending_count_with_one_org_and_zero_others_is_one(): void
    {
        Organization::create([
            'name'              => 'Alpha University',
            'slug'              => 'alpha-university',
            'organization_type' => OrganizationType::University,
            'status'            => OrganizationStatus::Pending,
            'created_by'        => $this->adminRA->id,
        ]);

        $this->assertEquals(1, ApprovalEngine::getTotalPendingCount());

        $response = $this->actingAs($this->superAdmin)->get(route('super-admin.dashboard'));
        $response->assertOk();
        $response->assertSee('Pending Approvals');
        $response->assertSee('1');
        $response->assertSee('Approval Center queue &rarr;', false);
    }

    public function test_approval_count_02_global_approval_center_shows_pending_organizations_count_one(): void
    {
        Organization::create([
            'name'              => 'Beta Institute',
            'slug'              => 'beta-institute',
            'organization_type' => OrganizationType::TrainingInstitution,
            'status'            => OrganizationStatus::Pending,
            'created_by'        => $this->adminRA->id,
        ]);

        $response = $this->actingAs($this->superAdmin)->get(route('admin.approvals.index'));
        $response->assertOk();
        $response->assertSee('Super Admin Governance & Approval Command Center', false);
        $response->assertSee('Pending Organizations');
        $response->assertSee('Organization Queue &rarr;', false);
        $response->assertSee(route('admin.approvals.organizations'));
    }

    public function test_approval_count_03_dedicated_organization_queue_shows_one_pending_record(): void
    {
        Organization::create([
            'name'              => 'iC.edu UAT University',
            'slug'              => 'icedu-uat-university',
            'organization_type' => OrganizationType::University,
            'status'            => OrganizationStatus::Pending,
            'created_by'        => $this->adminRA->id,
        ]);

        $response = $this->actingAs($this->superAdmin)->get(route('admin.approvals.organizations'));
        $response->assertOk();
        $response->assertSee('Organization Approvals & Governance', false);
        $response->assertSee('Pending Organization Submissions');
        $response->assertSee('1 waiting for review');
        $response->assertSee('iC.edu UAT University');
    }

    public function test_approval_count_04_multi_domain_aggregation_and_breakdown_synchronization(): void
    {
        // 1 pending Organization
        Organization::create([
            'name'              => 'Gamma College',
            'slug'              => 'gamma-college',
            'organization_type' => OrganizationType::University,
            'status'            => OrganizationStatus::Pending,
            'created_by'        => $this->adminRA->id,
        ]);

        // 2 pending Price Change Requests
        $product = Product::create([
            'title'             => 'TOEIC Exam Package',
            'slug'              => 'toeic-exam-package',
            'product_type'      => 'assessment_package',
            'assessment_family' => 'toeic',
            'price'             => 500000,
            'is_active'         => true,
        ]);

        PriceChangeRequest::create([
            'product_id'             => $product->id,
            'current_price_snapshot' => 500000,
            'proposed_price'         => 550000,
            'reason'                 => 'Market adjustment',
            'status'                 => 'pending',
            'requested_by'           => $this->adminRA->id,
        ]);

        PriceChangeRequest::create([
            'product_id'             => $product->id,
            'current_price_snapshot' => 500000,
            'proposed_price'         => 600000,
            'reason'                 => 'Institutional package expansion',
            'status'                 => 'pending',
            'requested_by'           => $this->adminRA->id,
        ]);

        // 1 pending Staff Creation Request
        $newTeacher = User::factory()->create(['name' => 'New Teacher Candidate', 'email' => 'new.teacher@iap.test']);
        UserCreationRequest::create([
            'user_id'        => $newTeacher->id,
            'requested_role' => 'teacher',
            'status'         => 'pending',
            'requested_by'   => $this->adminRA->id,
        ]);

        // Global sum = 1 (org) + 2 (price) + 1 (staff) = 4
        $this->assertEquals(4, ApprovalEngine::getTotalPendingCount());

        $dashboardResp = $this->actingAs($this->superAdmin)->get(route('super-admin.dashboard'));
        $dashboardResp->assertOk();
        $dashboardResp->assertSee('4');

        $centerResp = $this->actingAs($this->superAdmin)->get(route('admin.approvals.index'));
        $centerResp->assertOk();
        $centerResp->assertSee('Pending Organizations');
        $centerResp->assertSee('Pending Price Changes');
        $centerResp->assertSee('Pending Staff Creations');
    }

    public function test_nav_active_01_approval_center_route_has_approval_center_active_and_organization_inactive(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('admin.approvals.index'));
        $response->assertOk();

        $content = $response->getContent();

        // Approval Center link should have active background
        $approvalCenterPattern = '/<a\s+href="[^"]*admin\/approvals"[^>]*class="[^"]*bg-indigo-600 text-white[^"]*"/';
        $this->assertMatchesRegularExpression($approvalCenterPattern, $content);

        // Organization Approvals link should NOT have active background
        $orgApprovalsPattern = '/<a\s+href="[^"]*admin\/approvals\/organizations"[^>]*class="[^"]*bg-indigo-600 text-white[^"]*"/';
        $this->assertDoesNotMatchRegularExpression($orgApprovalsPattern, $content);
    }

    public function test_nav_active_02_organization_approvals_route_has_organization_active_and_approval_center_inactive(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('admin.approvals.organizations'));
        $response->assertOk();

        $content = $response->getContent();

        // Organization Approvals link should have active background
        $orgApprovalsPattern = '/<a\s+href="[^"]*admin\/approvals\/organizations"[^>]*class="[^"]*bg-indigo-600 text-white[^"]*"/';
        $this->assertMatchesRegularExpression($orgApprovalsPattern, $content);

        // Approval Center link should NOT have active background
        $approvalCenterPattern = '/<a\s+href="[^"]*admin\/approvals"[^>]*class="[^"]*bg-indigo-600 text-white[^"]*"/';
        $this->assertDoesNotMatchRegularExpression($approvalCenterPattern, $content);
    }

    public function test_nav_active_03_sub_approval_queues_keep_approval_center_active_and_organization_inactive(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('admin.approvals.assessments'));
        $response->assertOk();

        $content = $response->getContent();

        // Approval Center link should be active
        $approvalCenterPattern = '/<a\s+href="[^"]*admin\/approvals"[^>]*class="[^"]*bg-indigo-600 text-white[^"]*"/';
        $this->assertMatchesRegularExpression($approvalCenterPattern, $content);

        // Organization Approvals link should be inactive
        $orgApprovalsPattern = '/<a\s+href="[^"]*admin\/approvals\/organizations"[^>]*class="[^"]*bg-indigo-600 text-white[^"]*"/';
        $this->assertDoesNotMatchRegularExpression($orgApprovalsPattern, $content);
    }

    public function test_approval_route_01_pending_organizations_card_routes_to_organization_approvals_queue(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('admin.approvals.index'));
        $response->assertOk();
        $response->assertSee(route('admin.approvals.organizations'));
        $response->assertSee('Organization Queue &rarr;', false);
    }

    public function test_notif_org_01_organization_notification_routes_to_dedicated_organization_approval_page(): void
    {
        $notif = new EnterpriseSystemNotification(
            title: 'New Organization Approval Request',
            message: 'Registration Admin submitted organization for Super Admin approval.',
            type: 'ORGANIZATION_APPROVAL_REQUEST',
            priority: 'HIGH',
            entityType: 'organization',
            entityId: '01m1rm1nc1pn1p7ydpwdd6s1pc',
            targetUrl: route('admin.approvals.organizations')
        );

        $payload = $notif->toArray($this->superAdmin);
        $this->assertEquals(route('admin.approvals.organizations'), $payload['target_url']);
    }

    public function test_auth_01_unauthorized_roles_cannot_access_approval_center_or_organization_approvals(): void
    {
        // 1. Registration Admin (admin role)
        $this->actingAs($this->adminRA)->get(route('admin.approvals.index'))->assertForbidden();
        $this->actingAs($this->adminRA)->get(route('admin.approvals.organizations'))->assertForbidden();

        // 2. Repository Manager
        $this->actingAs($this->rm)->get(route('admin.approvals.index'))->assertForbidden();
        $this->actingAs($this->rm)->get(route('admin.approvals.organizations'))->assertForbidden();

        // 3. Teacher
        $this->actingAs($this->teacher)->get(route('admin.approvals.index'))->assertForbidden();
        $this->actingAs($this->teacher)->get(route('admin.approvals.organizations'))->assertForbidden();

        // 4. Finance
        $this->actingAs($this->finance)->get(route('admin.approvals.index'))->assertForbidden();
        $this->actingAs($this->finance)->get(route('admin.approvals.organizations'))->assertForbidden();

        // 5. Candidate / Student
        $this->actingAs($this->student)->get(route('admin.approvals.index'))->assertForbidden();
        $this->actingAs($this->student)->get(route('admin.approvals.organizations'))->assertForbidden();
    }
}
