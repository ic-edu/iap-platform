<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use App\Modules\Organization\Enums\MembershipRole;
use App\Modules\Organization\Enums\MembershipStatus;
use App\Modules\Organization\Enums\OrganizationStatus;
use App\Modules\Organization\Enums\OrganizationType;
use App\Modules\Organization\Models\Organization;
use App\Modules\Organization\Models\OrganizationMembership;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationProfileGovernanceProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected User $coordinator;
    protected User $otherCoordinator;
    protected User $adminRA;
    protected User $superAdmin;
    protected Organization $orgA;
    protected Organization $orgB;
    protected OrganizationMembership $membershipA;
    protected OrganizationMembership $membershipB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->coordinator = User::factory()->create([
            'name'             => 'Indra Wahyudi',
            'email'            => 'ic.edu.bdg@gmail.com',
            'status'           => 'active',
            'theme_preference' => 'dark',
        ]);
        $this->coordinator->assignRole('admin');

        $this->otherCoordinator = User::factory()->create([
            'name'             => 'Other Coordinator',
            'email'            => 'other.coord@example.org',
            'status'           => 'active',
            'theme_preference' => 'dark',
        ]);
        $this->otherCoordinator->assignRole('admin');

        $this->adminRA = User::factory()->create([
            'name'   => 'Registration Admin',
            'email'  => 'admin.ra@example.com',
            'status' => 'active',
        ]);
        $this->adminRA->assignRole('admin');

        $this->superAdmin = User::factory()->create([
            'name'   => 'Super Admin',
            'email'  => 'super.admin@example.com',
            'status' => 'active',
        ]);
        $this->superAdmin->assignRole('super-admin');

        // Organization A (Target UAT Organization)
        $this->orgA = Organization::create([
            'name'              => 'iC.edu UAT University',
            'slug'              => 'icedu-uat-university',
            'organization_type' => OrganizationType::University,
            'status'            => OrganizationStatus::Active,
            'email'             => 'info@icedu-uat.ac.id',
            'phone'             => '+62 22 7654321',
            'website'           => 'https://icedu-uat.ac.id',
            'address'           => 'Jl. Ganesha No. 10',
            'city'              => 'Bandung',
            'province'          => 'West Java',
            'country'           => 'Indonesia',
            'postal_code'       => '40132',
            'created_by'        => $this->adminRA->id,
            'submitted_by'      => $this->adminRA->id,
            'submitted_at'      => now()->subDays(2),
            'reviewed_by'       => $this->superAdmin->id,
            'reviewed_at'       => now()->subDay(),
        ]);

        $this->membershipA = OrganizationMembership::create([
            'organization_id'   => $this->orgA->id,
            'user_id'           => $this->coordinator->id,
            'role'              => MembershipRole::Coordinator,
            'status'            => MembershipStatus::Active,
            'joined_at'         => now()->subDay(),
            'member_identifier' => 'COORD-001',
        ]);

        // Organization B (Other Tenant)
        $this->orgB = Organization::create([
            'name'              => 'Global Enterprise Institute',
            'slug'              => 'global-enterprise-institute',
            'organization_type' => OrganizationType::Company,
            'status'            => OrganizationStatus::Active,
            'created_by'        => $this->adminRA->id,
            'submitted_by'      => $this->adminRA->id,
            'submitted_at'      => now()->subDays(3),
            'reviewed_by'       => $this->superAdmin->id,
            'reviewed_at'       => now()->subDays(2),
        ]);

        $this->membershipB = OrganizationMembership::create([
            'organization_id'   => $this->orgB->id,
            'user_id'           => $this->otherCoordinator->id,
            'role'              => MembershipRole::Coordinator,
            'status'            => MembershipStatus::Active,
            'joined_at'         => now()->subDays(2),
            'member_identifier' => 'COORD-002',
        ]);
    }

    /**
     * TEST ORG-PROFILE-UI-01: Coordinator profile shows Organization Name.
     */
    public function test_org_profile_ui_01_shows_organization_name(): void
    {
        $response = $this->actingAs($this->coordinator)
            ->get(route('organization.profile', $this->orgA->slug));

        $response->assertStatus(200);
        $response->assertSee('iC.edu UAT University');
        $response->assertSee('Organization Name');
    }

    /**
     * TEST ORG-PROFILE-UI-02: Coordinator profile shows Organization Type.
     */
    public function test_org_profile_ui_02_shows_organization_type(): void
    {
        $response = $this->actingAs($this->coordinator)
            ->get(route('organization.profile', $this->orgA->slug));

        $response->assertStatus(200);
        $response->assertSee('University / College');
        $response->assertSee('Organization Type');
    }

    /**
     * TEST ORG-PROFILE-UI-03: Name is not editable in the form.
     */
    public function test_org_profile_ui_03_name_is_not_editable_input(): void
    {
        $response = $this->actingAs($this->coordinator)
            ->get(route('organization.profile', $this->orgA->slug));

        $response->assertStatus(200);
        $content = $response->getContent();
        $this->assertStringNotContainsString('<input type="text" name="name"', $content);
        $this->assertStringNotContainsString('name="name"', $content);
    }

    /**
     * TEST ORG-PROFILE-UI-04: Type is not editable in the form.
     */
    public function test_org_profile_ui_04_type_is_not_editable_select_or_input(): void
    {
        $response = $this->actingAs($this->coordinator)
            ->get(route('organization.profile', $this->orgA->slug));

        $response->assertStatus(200);
        $content = $response->getContent();
        $this->assertStringNotContainsString('<select name="organization_type"', $content);
        $this->assertStringNotContainsString('name="organization_type"', $content);
    }

    /**
     * TEST ORG-PROFILE-UI-05: Operational fields remain editable in the form.
     */
    public function test_org_profile_ui_05_operational_fields_remain_editable(): void
    {
        $response = $this->actingAs($this->coordinator)
            ->get(route('organization.profile', $this->orgA->slug));

        $response->assertStatus(200);
        $response->assertSee('name="email"', false);
        $response->assertSee('name="phone"', false);
        $response->assertSee('name="website"', false);
        $response->assertSee('name="address"', false);
        $response->assertSee('name="city"', false);
        $response->assertSee('name="province"', false);
        $response->assertSee('name="country"', false);
        $response->assertSee('name="postal_code"', false);
    }

    /**
     * TEST ORG-PROFILE-UI-06: Read-only governance helper copy is displayed.
     */
    public function test_org_profile_ui_06_displays_governance_helper_copy(): void
    {
        $response = $this->actingAs($this->coordinator)
            ->get(route('organization.profile', $this->orgA->slug));

        $response->assertStatus(200);
        $response->assertSee('Organization identity fields are governed by iC.edu administration.');
        $response->assertSee('Read-Only');
    }

    /**
     * TEST ORG-PROFILE-SEC-01: Coordinator cannot mutate Organization Name via crafted request.
     */
    public function test_org_profile_sec_01_coordinator_cannot_mutate_organization_name(): void
    {
        $response = $this->actingAs($this->coordinator)
            ->put(route('organization.profile.update', $this->orgA->slug), [
                'name'        => 'Hacked University Name',
                'phone'       => '+62 22 9998888',
                'province'    => 'West Java',
            ]);

        $response->assertRedirect();
        $this->orgA->refresh();

        // Organization name MUST remain untouched
        $this->assertEquals('iC.edu UAT University', $this->orgA->name);
        // Allowed operational field updated
        $this->assertEquals('+62 22 9998888', $this->orgA->phone);
    }

    /**
     * TEST ORG-PROFILE-SEC-02: Coordinator cannot mutate Organization Type via crafted request.
     */
    public function test_org_profile_sec_02_coordinator_cannot_mutate_organization_type(): void
    {
        $response = $this->actingAs($this->coordinator)
            ->put(route('organization.profile.update', $this->orgA->slug), [
                'organization_type' => OrganizationType::Company->value,
                'type'              => 'company',
                'city'              => 'Bandung Kota',
            ]);

        $response->assertRedirect();
        $this->orgA->refresh();

        // Organization type MUST remain University
        $this->assertEquals(OrganizationType::University, $this->orgA->organization_type);
        // Allowed operational field updated
        $this->assertEquals('Bandung Kota', $this->orgA->city);
    }

    /**
     * TEST ORG-PROFILE-SEC-03: Coordinator can update allowed contact/profile fields.
     */
    public function test_org_profile_sec_03_coordinator_can_update_allowed_operational_fields(): void
    {
        $response = $this->actingAs($this->coordinator)
            ->put(route('organization.profile.update', $this->orgA->slug), [
                'email'       => 'new.contact@icedu-uat.ac.id',
                'phone'       => '+62 22 5554443',
                'website'     => 'https://new.icedu-uat.ac.id',
                'address'     => 'Jl. Ir. H. Juanda No. 99',
                'city'        => 'Bandung',
                'province'    => 'Jawa Barat',
                'country'     => 'Indonesia',
                'postal_code' => '40135',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('status', 'Organization profile updated successfully.');

        $this->orgA->refresh();
        $this->assertEquals('new.contact@icedu-uat.ac.id', $this->orgA->email);
        $this->assertEquals('+62 22 5554443', $this->orgA->phone);
        $this->assertEquals('https://new.icedu-uat.ac.id', $this->orgA->website);
        $this->assertEquals('Jl. Ir. H. Juanda No. 99', $this->orgA->address);
        $this->assertEquals('Bandung', $this->orgA->city);
        $this->assertEquals('Jawa Barat', $this->orgA->province);
        $this->assertEquals('Indonesia', $this->orgA->country);
        $this->assertEquals('40135', $this->orgA->postal_code);
    }

    /**
     * TEST ORG-PROFILE-SEC-04: Profile update does not alter Organization status.
     */
    public function test_org_profile_sec_04_profile_update_does_not_alter_organization_status(): void
    {
        $response = $this->actingAs($this->coordinator)
            ->put(route('organization.profile.update', $this->orgA->slug), [
                'status'   => 'suspended',
                'province' => 'West Java',
            ]);

        $response->assertRedirect();
        $this->orgA->refresh();

        $this->assertEquals(OrganizationStatus::Active, $this->orgA->status);
    }

    /**
     * TEST ORG-PROFILE-SEC-05: Profile update does not alter approval governance metadata.
     */
    public function test_org_profile_sec_05_profile_update_preserves_approval_governance_metadata(): void
    {
        $originalSubmittedBy = $this->orgA->submitted_by;
        $originalSubmittedAt = $this->orgA->submitted_at;
        $originalReviewedBy = $this->orgA->reviewed_by;
        $originalReviewedAt = $this->orgA->reviewed_at;

        $response = $this->actingAs($this->coordinator)
            ->put(route('organization.profile.update', $this->orgA->slug), [
                'reviewed_by'  => $this->coordinator->id,
                'reviewed_at'  => now()->addDay()->toDateTimeString(),
                'submitted_by' => $this->coordinator->id,
                'phone'        => '+62 22 1112223',
            ]);

        $response->assertRedirect();
        $this->orgA->refresh();

        $this->assertEquals($originalSubmittedBy, $this->orgA->submitted_by);
        $this->assertEquals($originalSubmittedAt->toDateTimeString(), $this->orgA->submitted_at->toDateTimeString());
        $this->assertEquals($originalReviewedBy, $this->orgA->reviewed_by);
        $this->assertEquals($originalReviewedAt->toDateTimeString(), $this->orgA->reviewed_at->toDateTimeString());
    }

    /**
     * TEST ORG-PROFILE-SEC-06: Profile update does not alter Organization slug.
     */
    public function test_org_profile_sec_06_profile_update_preserves_slug(): void
    {
        $response = $this->actingAs($this->coordinator)
            ->put(route('organization.profile.update', $this->orgA->slug), [
                'slug'     => 'tampered-slug',
                'province' => 'West Java',
            ]);

        $response->assertRedirect();
        $this->orgA->refresh();

        $this->assertEquals('icedu-uat-university', $this->orgA->slug);
    }

    /**
     * TEST ORG-PROFILE-TENANT-01: Coordinator of Organization A cannot update Organization B.
     */
    public function test_org_profile_tenant_01_coordinator_a_cannot_update_organization_b(): void
    {
        $response = $this->actingAs($this->coordinator)
            ->put(route('organization.profile.update', $this->orgB->slug), [
                'city' => 'Tampered City',
            ]);

        // org.context middleware rejects cross-tenant access
        $this->assertTrue(in_array($response->status(), [302, 403, 404]));

        $this->orgB->refresh();
        $this->assertNotEquals('Tampered City', $this->orgB->city);
    }

    /**
     * TEST ORG-PROFILE-LOG-01: Profile update logs ActivityLog without misleading identity change events.
     */
    public function test_org_profile_log_01_profile_update_logs_activity(): void
    {
        $this->actingAs($this->coordinator)
            ->put(route('organization.profile.update', $this->orgA->slug), [
                'city'     => 'Cimahi',
                'province' => 'West Java',
            ]);

        $log = ActivityLog::where('action', 'ORG_PROFILE_UPDATED')
            ->where('subject_id', (string) $this->orgA->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertStringContainsString("Coordinator updated contact & operational profile for 'iC.edu UAT University'", $log->description);
    }

    /**
     * TEST ORG-PROFILE-RA-01: RA operational management retains existing authority to edit organizations where permitted.
     */
    public function test_org_profile_ra_01_ra_retains_authority_to_edit_organizations(): void
    {
        // RA edits draft or active organization via AdminOrganizationController
        $draftOrg = Organization::create([
            'name'              => 'Draft Polytechnic',
            'slug'              => 'draft-polytechnic',
            'organization_type' => OrganizationType::School,
            'status'            => OrganizationStatus::Draft,
            'created_by'        => $this->adminRA->id,
        ]);

        $response = $this->actingAs($this->adminRA)
            ->put(route('admin.organizations.update', $draftOrg->id), [
                'name'              => 'Updated Draft Polytechnic',
                'organization_type' => OrganizationType::University->value,
                'action'            => 'draft',
            ]);

        $response->assertRedirect(route('admin.organizations.index'));
        $draftOrg->refresh();

        $this->assertEquals('Updated Draft Polytechnic', $draftOrg->name);
        $this->assertEquals(OrganizationType::University, $draftOrg->organization_type);
    }
}
