<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Organization\Enums\OrganizationStatus;
use App\Modules\Organization\Enums\OrganizationType;
use App\Modules\Organization\Models\Organization;
use App\Services\ApprovalEngine;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationApprovalWorkflowSynchronizationTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $adminRA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->superAdmin = User::factory()->create(['name' => 'Super Admin Demo', 'email' => 'superadmin@iap.test']);
        $this->superAdmin->assignRole('super-admin');

        $this->adminRA = User::factory()->create(['name' => 'Registration Admin Demo', 'email' => 'admin.ra@iap.test']);
        $this->adminRA->assignRole('admin');
    }

    public function test_org_draft_01_ra_can_save_a_new_organization_as_draft_without_notifying_sa(): void
    {
        $response = $this->actingAs($this->adminRA)->post(route('admin.organizations.store'), [
            'name'              => 'Draft University',
            'organization_type' => OrganizationType::University->value,
            'email'             => 'admin@draftuni.edu',
            'phone'             => '+6281234567890',
            'website'           => 'https://draftuni.edu',
            'address'           => '123 Campus Way',
            'city'              => 'Jakarta',
            'action'            => 'draft',
        ]);

        $response->assertRedirect(route('admin.organizations.index'));
        $response->assertSessionHas('status');

        $org = Organization::where('name', 'Draft University')->first();
        $this->assertNotNull($org);
        $this->assertEquals(OrganizationStatus::Draft, $org->status);
        $this->assertTrue($org->isDraft());

        // Verify SA has no notifications
        $this->superAdmin->refresh();
        $this->assertEquals(0, $this->superAdmin->notifications()->count());

        // Verify ApprovalEngine pending count does not count draft
        $counts = ApprovalEngine::getPendingCounts();
        $this->assertEquals(0, $counts['organizations'] ?? 0);
    }

    public function test_org_draft_02_draft_organizations_are_visible_to_ra_and_editable(): void
    {
        $org = Organization::create([
            'name'              => 'Editable Draft College',
            'slug'              => 'editable-draft-college',
            'organization_type' => OrganizationType::University,
            'status'            => OrganizationStatus::Draft,
            'email'             => 'info@collegedraft.edu',
            'address'           => '456 College Blvd',
            'created_by'        => $this->adminRA->id,
        ]);

        // RA can access edit page
        $editResponse = $this->actingAs($this->adminRA)->get(route('admin.organizations.edit', $org));
        $editResponse->assertOk();
        $editResponse->assertSee('Save Draft');
        $editResponse->assertSee('Submit for Approval');

        // RA can update draft while keeping draft status
        $updateResponse = $this->actingAs($this->adminRA)->put(route('admin.organizations.update', $org), [
            'name'              => 'Updated Draft College',
            'organization_type' => OrganizationType::University->value,
            'email'             => 'updated@collegedraft.edu',
            'address'           => '789 Updated Blvd',
            'action'            => 'draft',
        ]);

        $updateResponse->assertRedirect(route('admin.organizations.index'));
        $org->refresh();
        $this->assertEquals('Updated Draft College', $org->name);
        $this->assertEquals('789 Updated Blvd', $org->address);
        $this->assertEquals(OrganizationStatus::Draft, $org->status);
    }

    public function test_org_sub_01_submitting_organization_transitions_to_pending_notifies_sa_and_enters_approval_queue(): void
    {
        $response = $this->actingAs($this->adminRA)->post(route('admin.organizations.store'), [
            'name'              => 'Pending Approval Institute',
            'organization_type' => OrganizationType::TrainingInstitution->value,
            'email'             => 'contact@pai.edu',
            'address'           => '99 Academic Rd',
            'action'            => 'submit',
        ]);

        $response->assertRedirect(route('admin.organizations.index'));
        $response->assertSessionHas('status');

        $org = Organization::where('name', 'Pending Approval Institute')->first();
        $this->assertNotNull($org);
        $this->assertEquals(OrganizationStatus::Pending, $org->status);

        // Check SA notification
        $this->superAdmin->refresh();
        $this->assertEquals(1, $this->superAdmin->notifications()->count());
        $notificationData = $this->superAdmin->notifications()->first()->data;
        $this->assertStringContainsString('New Organization Approval Request', $notificationData['title']);

        // Check ApprovalEngine reflects pending count
        $counts = ApprovalEngine::getPendingCounts();
        $this->assertEquals(1, $counts['organizations'] ?? 0);
    }

    public function test_org_edit_01_pending_organization_cannot_be_edited_by_ra(): void
    {
        $org = Organization::create([
            'name'              => 'Locked Pending Org',
            'slug'              => 'locked-pending-org',
            'organization_type' => OrganizationType::Company,
            'status'            => OrganizationStatus::Pending,
            'address'           => '100 Lock St',
            'email'             => 'lock@org.edu',
            'created_by'        => $this->adminRA->id,
        ]);

        // Attempting to visit edit page redirects with error
        $editResponse = $this->actingAs($this->adminRA)->get(route('admin.organizations.edit', $org));
        $editResponse->assertRedirect(route('admin.organizations.index'));
        $editResponse->assertSessionHas('error');

        // Attempting to PUT updates redirects with error and leaves data unchanged
        $updateResponse = $this->actingAs($this->adminRA)->put(route('admin.organizations.update', $org), [
            'name'              => 'Tampered Name',
            'organization_type' => OrganizationType::Company->value,
            'address'           => 'Tampered Address',
            'action'            => 'draft',
        ]);

        $updateResponse->assertRedirect(route('admin.organizations.index'));
        $updateResponse->assertSessionHas('error');

        $org->refresh();
        $this->assertEquals('Locked Pending Org', $org->name);
        $this->assertEquals(OrganizationStatus::Pending, $org->status);
    }

    public function test_org_sa_01_sa_can_approve_an_organization_notifying_the_submitter(): void
    {
        $org = Organization::create([
            'name'              => 'Institute to Approve',
            'slug'              => 'institute-to-approve',
            'organization_type' => OrganizationType::School,
            'status'            => OrganizationStatus::Pending,
            'address'           => '777 Approvals Way',
            'email'             => 'ita@edu.org',
            'created_by'        => $this->adminRA->id,
            'submitted_by'      => $this->adminRA->id,
        ]);

        $response = $this->actingAs($this->superAdmin)->post(route('admin.approvals.organizations.approve', $org));
        $response->assertRedirect();
        $response->assertSessionHas('status');

        $org->refresh();
        $this->assertEquals(OrganizationStatus::Active, $org->status);

        // Verify notification sent to RA
        $this->adminRA->refresh();
        $this->assertEquals(1, $this->adminRA->notifications()->count());
        $notifData = $this->adminRA->notifications()->first()->data;
        $this->assertStringContainsString('Organization Approved', $notifData['title']);
    }

    public function test_org_sa_02_sa_can_return_an_organization_for_revision_unlocking_edit_for_ra(): void
    {
        $org = Organization::create([
            'name'              => 'Institute Needing Revision',
            'slug'              => 'institute-needing-revision',
            'organization_type' => OrganizationType::Government,
            'status'            => OrganizationStatus::Pending,
            'address'           => '888 Revision Way',
            'email'             => 'inr@edu.org',
            'created_by'        => $this->adminRA->id,
            'submitted_by'      => $this->adminRA->id,
        ]);

        $response = $this->actingAs($this->superAdmin)->post(route('admin.approvals.organizations.return-revision', $org), [
            'revision_note' => 'Please provide valid institutional address and email domain.',
        ]);
        $response->assertRedirect();

        $org->refresh();
        $this->assertEquals(OrganizationStatus::NeedsRevision, $org->status);

        // Notification to RA
        $this->adminRA->refresh();
        $this->assertEquals(1, $this->adminRA->notifications()->count());
        $notifData = $this->adminRA->notifications()->first()->data;
        $this->assertStringContainsString('Needs Revision', $notifData['title']);

        // RA can now edit again
        $editResponse = $this->actingAs($this->adminRA)->get(route('admin.organizations.edit', $org));
        $editResponse->assertOk();
        $editResponse->assertSee('Save Draft');
        $editResponse->assertSee('Resubmit');
    }

    public function test_ra_kpi_org_01_ra_operational_dashboard_displays_accurate_pending_organization_approvals_count_and_cta(): void
    {
        Organization::create([
            'name'              => 'Active Org',
            'slug'              => 'active-org',
            'organization_type' => OrganizationType::University,
            'status'            => OrganizationStatus::Active,
        ]);

        Organization::create([
            'name'              => 'Draft Org',
            'slug'              => 'draft-org',
            'organization_type' => OrganizationType::University,
            'status'            => OrganizationStatus::Draft,
        ]);

        Organization::create([
            'name'              => 'Pending Org 1',
            'slug'              => 'pending-org-1',
            'organization_type' => OrganizationType::University,
            'status'            => OrganizationStatus::Pending,
        ]);

        Organization::create([
            'name'              => 'Pending Org 2',
            'slug'              => 'pending-org-2',
            'organization_type' => OrganizationType::University,
            'status'            => OrganizationStatus::Pending,
        ]);

        $response = $this->actingAs($this->adminRA)->get(route('admin.dashboard'));
        $response->assertOk();
        $response->assertSee('Pending Organization Approvals');
        $response->assertSee('Institutional Operations');
        $response->assertSee('Awaiting Super Admin review');
        $response->assertSee(route('admin.organizations.index', ['status' => 'pending']));
    }
}
