<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Organization\Enums\OrganizationStatus;
use App\Modules\Organization\Enums\OrganizationType;
use App\Modules\Organization\Models\Organization;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RaDashboardInstitutionalOperationsRepositioningTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminRA;
    protected User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->adminRA = User::factory()->create(['name' => 'Operational Admin']);
        $this->adminRA->assignRole('admin');

        $this->superAdmin = User::factory()->create(['name' => 'Super Administrator']);
        $this->superAdmin->assignRole('super-admin');
    }

    public function test_ra_dashboard_controller_passes_both_pending_and_active_organization_counts(): void
    {
        Organization::create([
            'name'              => 'Pending Campus',
            'slug'              => 'pending-campus',
            'organization_type' => OrganizationType::University,
            'status'            => OrganizationStatus::Pending,
            'created_by'        => $this->adminRA->id,
        ]);

        Organization::create([
            'name'              => 'Active High School',
            'slug'              => 'active-high-school',
            'organization_type' => OrganizationType::School,
            'status'            => OrganizationStatus::Active,
            'created_by'        => $this->adminRA->id,
        ]);

        $response = $this->actingAs($this->adminRA)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertViewHas('pendingOrganizationsCount', 1);
        $response->assertViewHas('activeOrganizationsCount', 1);
    }

    public function test_ra_dashboard_renders_institutional_operations_section_with_pending_and_active_kpi_cards(): void
    {
        Organization::create([
            'name'              => 'Pending Campus',
            'slug'              => 'pending-campus-card',
            'organization_type' => OrganizationType::University,
            'status'            => OrganizationStatus::Pending,
            'created_by'        => $this->adminRA->id,
        ]);

        Organization::create([
            'name'              => 'Active Campus',
            'slug'              => 'active-campus-card',
            'organization_type' => OrganizationType::University,
            'status'            => OrganizationStatus::Active,
            'created_by'        => $this->adminRA->id,
        ]);

        $response = $this->actingAs($this->adminRA)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Institutional Operations');
        $response->assertSee('Pending Organization Approvals');
        $response->assertSee('Awaiting Super Admin review');
        $response->assertSee('View Pending Organizations');
        $response->assertSee(route('admin.organizations.index', ['status' => 'pending']), false);

        $response->assertSee('Active Organizations');
        $response->assertSee('Approved &amp; operational institutions', false);
        $response->assertSee('View Active Organizations');
        $response->assertSee(route('admin.organizations.index', ['status' => 'active']), false);
    }

    public function test_ra_dashboard_visual_hierarchy_places_institutional_operations_directly_below_candidate_metrics_and_before_candidates_requiring_action(): void
    {
        $response = $this->actingAs($this->adminRA)->get(route('admin.dashboard'));

        $response->assertOk();
        $html = $response->getContent();

        // Extract content after the main page heading to evaluate the body layout
        $contentHeadingPos = strpos($html, '<h1 class="text-2xl font-black');
        $this->assertNotFalse($contentHeadingPos, 'Operational Dashboard heading missing');
        $mainContent = substr($html, $contentHeadingPos);

        $commercialSnapshotPos = strpos($mainContent, 'id="commercial-voucher-snapshot"');
        $candidateMetricsPos = strpos($mainContent, 'Platform Candidate &amp; Testing Metrics');
        if ($candidateMetricsPos === false) {
            $candidateMetricsPos = strpos($mainContent, 'Platform Candidate & Testing Metrics');
        }
        $institutionalOpsPos = strpos($mainContent, 'id="institutional-operations"');
        $actionQueuePos = strpos($mainContent, 'id="action-queue"');
        $assignmentsPos = strpos($mainContent, 'Recent Active Assignments');

        $this->assertNotFalse($commercialSnapshotPos, 'Commercial snapshot section missing in main content');
        $this->assertNotFalse($candidateMetricsPos, 'Candidate metrics section missing in main content');
        $this->assertNotFalse($institutionalOpsPos, 'Institutional operations section missing in main content');
        $this->assertNotFalse($actionQueuePos, 'Action queue section missing in main content');
        $this->assertNotFalse($assignmentsPos, 'Recent active assignments section missing in main content');

        // Assert strictly ordered hierarchy
        $this->assertTrue(
            $commercialSnapshotPos < $candidateMetricsPos,
            'Commercial & Voucher Snapshot must appear before Platform Candidate & Testing Metrics'
        );
        $this->assertTrue(
            $candidateMetricsPos < $institutionalOpsPos,
            'Platform Candidate & Testing Metrics must appear before Institutional Operations'
        );
        $this->assertTrue(
            $institutionalOpsPos < $actionQueuePos,
            'Institutional Operations must appear before Candidates Requiring Action'
        );
        $this->assertTrue(
            $actionQueuePos < $assignmentsPos,
            'Candidates Requiring Action must appear before Recent Active Assignments'
        );
    }

    public function test_ra_directory_handles_status_filter_query_parameters_from_dashboard_ctas(): void
    {
        $pendingOrg = Organization::create([
            'name'              => 'Pending Alpha University',
            'slug'              => 'pending-alpha-univ',
            'organization_type' => OrganizationType::University,
            'status'            => OrganizationStatus::Pending,
            'created_by'        => $this->adminRA->id,
        ]);

        $activeOrg = Organization::create([
            'name'              => 'Active Beta Institute',
            'slug'              => 'active-beta-inst',
            'organization_type' => OrganizationType::University,
            'status'            => OrganizationStatus::Active,
            'created_by'        => $this->adminRA->id,
        ]);

        // Pending filter CTA
        $pendingResponse = $this->actingAs($this->adminRA)->get(route('admin.organizations.index', ['status' => 'pending']));
        $pendingResponse->assertOk();
        $pendingResponse->assertSee('Pending Alpha University');
        $pendingResponse->assertDontSee('Active Beta Institute');

        // Active filter CTA
        $activeResponse = $this->actingAs($this->adminRA)->get(route('admin.organizations.index', ['status' => 'active']));
        $activeResponse->assertOk();
        $activeResponse->assertSee('Active Beta Institute');
        $activeResponse->assertDontSee('Pending Alpha University');
    }
}
