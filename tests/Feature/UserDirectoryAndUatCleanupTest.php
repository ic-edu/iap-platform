<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Assessment\Enums\AssessmentMode;
use App\Modules\Assessment\Enums\AttemptStatus;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\Test as AssessmentTest;
use App\Modules\Certificate\Models\Certificate;
use App\Modules\Organization\Enums\MembershipRole;
use App\Modules\Organization\Enums\MembershipStatus;
use App\Modules\Organization\Enums\OrganizationStatus;
use App\Modules\Organization\Enums\OrganizationType;
use App\Modules\Organization\Models\Organization;
use App\Modules\Organization\Models\OrganizationMembership;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserDirectoryAndUatCleanupTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $adminRA;
    protected User $repoManager;
    protected User $teacher;
    protected User $finance;
    protected User $candidate;
    protected User $orgCoordinator;
    protected User $rolelessUser;
    protected Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->superAdmin = User::factory()->create(['name' => 'Super Admin Demo', 'email' => 'superadmin@iap.test']);
        $this->superAdmin->assignRole('super-admin');

        $this->adminRA = User::factory()->create(['name' => 'Registration Admin Demo', 'email' => 'admin.ra@iap.test']);
        $this->adminRA->assignRole('admin');

        $this->repoManager = User::factory()->create(['name' => 'Repo Manager Demo', 'email' => 'repo.manager@iap.test']);
        $this->repoManager->assignRole('repository-manager');

        $this->teacher = User::factory()->create(['name' => 'Teacher Demo', 'email' => 'teacher@iap.test']);
        $this->teacher->assignRole('teacher');

        $this->finance = User::factory()->create(['name' => 'Finance Demo', 'email' => 'finance@iap.test']);
        $this->finance->assignRole('finance');

        $this->candidate = User::factory()->create(['name' => 'Candidate Demo', 'email' => 'candidate@iap.test']);
        $this->candidate->assignRole('student');

        $this->orgCoordinator = User::factory()->create(['name' => 'Coordinator Demo', 'email' => 'coordinator@iap.test']);
        $this->orgCoordinator->assignRole('organization-coordinator');

        $this->organization = Organization::create([
            'name'              => 'Delta Academy',
            'slug'              => 'delta-academy',
            'organization_type' => OrganizationType::School,
            'status'            => OrganizationStatus::Active,
        ]);

        OrganizationMembership::create([
            'organization_id' => $this->organization->id,
            'user_id'         => $this->orgCoordinator->id,
            'role'            => MembershipRole::Coordinator,
            'status'          => MembershipStatus::Active,
            'joined_at'       => now(),
        ]);

        $this->rolelessUser = User::factory()->create([
            'name'       => 'Unassigned Person',
            'email'      => 'unassigned@iap.test',
            'created_at' => now()->addMinutes(10),
        ]);
        // No role assigned to $this->rolelessUser
    }

    /**
     * TEST USER-01: Manage Users CTA on Super Admin dashboard routes to All Users.
     */
    public function test_user_01_manage_users_cta_routes_to_all_users(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('super-admin.dashboard'));
        $response->assertStatus(200);
        $response->assertSee(route('admin.all-users.index'), false);
        $response->assertSee('Manage Users &rarr;', false);
    }

    /**
     * TEST USER-02: All Users contains internal staff (Super Admin, Admin, RM, Teacher, Finance).
     */
    public function test_user_02_all_users_contains_internal_staff(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('admin.all-users.index'));
        $response->assertStatus(200);
        $response->assertSee($this->superAdmin->name);
        $response->assertSee($this->adminRA->name);
        $response->assertSee($this->repoManager->name);
        $response->assertSee($this->teacher->name);
        $response->assertSee($this->finance->name);
        $response->assertSee('Internal Staff');
    }

    /**
     * TEST USER-03: All Users contains Candidate.
     */
    public function test_user_03_all_users_contains_candidate(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('admin.all-users.index'));
        $response->assertStatus(200);
        $response->assertSee($this->candidate->name);
        $response->assertSee('Candidate');
    }

    /**
     * TEST USER-04: Organization Coordinator appears in All Users with Organization context.
     */
    public function test_user_04_organization_coordinator_appears_in_all_users(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('admin.all-users.index'));
        $response->assertStatus(200);
        $response->assertSee($this->orgCoordinator->name);
        $response->assertSee('Organization User');
        $response->assertSee('Delta Academy');
    }

    /**
     * TEST USER-05: Candidate does NOT appear in Staff & Access Control (/admin/users).
     */
    public function test_user_05_candidate_does_not_appear_in_staff_workspace(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('admin.users.index'));
        $response->assertStatus(200);
        $response->assertDontSee($this->candidate->name);
        $response->assertDontSee($this->candidate->email);
    }

    /**
     * TEST USER-06: Organization Coordinator does NOT appear in Staff & Access Control (/admin/users).
     */
    public function test_user_06_organization_coordinator_does_not_appear_in_staff_workspace(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('admin.users.index'));
        $response->assertStatus(200);
        $response->assertDontSee($this->orgCoordinator->name);
        $response->assertDontSee($this->orgCoordinator->email);
    }

    /**
     * TEST USER-07: Repository Manager remains internal staff in Staff & Access Control.
     */
    public function test_user_07_repository_manager_remains_internal_staff(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('admin.users.index'));
        $response->assertStatus(200);
        $response->assertSee($this->repoManager->name);
        $response->assertSee($this->repoManager->email);
    }

    /**
     * TEST USER-08: Role-less account displays UNASSIGNED, not STUDENT.
     */
    public function test_user_08_role_less_account_displays_unassigned_not_student(): void
    {
        // On All Users page
        $allResp = $this->actingAs($this->superAdmin)->get(route('admin.all-users.index'));
        $allResp->assertStatus(200);
        $allResp->assertSee($this->rolelessUser->name);
        $allResp->assertSee('UNASSIGNED');

        // On Dashboard recent users feed
        $dashResp = $this->actingAs($this->superAdmin)->get(route('super-admin.dashboard'));
        $dashResp->assertStatus(200);
        $dashResp->assertSee('UNASSIGNED');
    }

    /**
     * TEST CLEAN-USER-01 to 03: Safe cleanup of role-less artifact accounts.
     */
    public function test_clean_user_01_to_03_cleanup_command_and_count_recalculation(): void
    {
        $countBefore = User::count();

        // Run the cleanup command
        $this->artisan('iap:clean-uat-artifacts')
            ->assertSuccessful();

        // Canonical users are NOT deleted
        $this->assertNotNull(User::find($this->superAdmin->id));
        $this->assertNotNull(User::find($this->adminRA->id));
        $this->assertNotNull(User::find($this->candidate->id));
        $this->assertNotNull(User::find($this->orgCoordinator->id));

        // Total users metric matches non-deleted users
        $totalUsers = User::count();
        $this->assertEquals($countBefore, $totalUsers);
    }

    /**
     * TEST CLEAN-CERT-01 to 05: Simulator certificate cleanup and policy invariant.
     */
    public function test_clean_cert_01_to_05_simulator_certificate_cleanup_and_invariance(): void
    {
        // 1. Create a simulator test and attempt
        $simTest = AssessmentTest::create([
            'title'           => 'TOEIC Simulator Test',
            'slug'            => 'toeic-simulator-test',
            'assessment_mode' => AssessmentMode::Simulator,
            'is_published'    => true,
            'created_by'      => $this->teacher->id,
        ]);

        $attempt = Attempt::create([
            'test_id'      => $simTest->id,
            'user_id'      => $this->candidate->id,
            'status'       => AttemptStatus::Submitted,
            'total_score'  => 850,
            'submitted_at' => now(),
        ]);

        // Manually attach legacy certificate artifact
        $cert = Certificate::create([
            'certificate_number' => 'CERT-20260904-K40Z',
            'attempt_id'         => $attempt->id,
            'user_id'            => $this->candidate->id,
            'issued_at'          => now(),
            'status'             => 'valid',
            'verification_code'  => 'VRF-TEST-1234',
        ]);

        $this->assertDatabaseHas('certificates', ['certificate_number' => 'CERT-20260904-K40Z']);

        // 2. Run cleanup
        $this->artisan('iap:clean-uat-artifacts')
            ->assertSuccessful();

        // 3. Certificate deleted / soft-deleted
        $this->assertSoftDeleted('certificates', ['certificate_number' => 'CERT-20260904-K40Z']);
        $this->assertNull(Certificate::find($cert->id));

        // 4. Attempt and Result data preserved 100%
        $this->assertDatabaseHas('attempts', [
            'id'          => $attempt->id,
            'user_id'     => $this->candidate->id,
            'total_score' => 850,
            'status'      => 'submitted',
        ]);

        // 5. Certificate KPI and Dashboard show 0 / empty state
        $dashResp = $this->actingAs($this->superAdmin)->get(route('super-admin.dashboard'));
        $dashResp->assertStatus(200);
        $dashResp->assertSee('No certificates issued yet.');
    }
}
