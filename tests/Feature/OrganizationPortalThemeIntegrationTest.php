<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Organization\Enums\MembershipRole;
use App\Modules\Organization\Enums\MembershipStatus;
use App\Modules\Organization\Enums\OrganizationStatus;
use App\Modules\Organization\Enums\OrganizationType;
use App\Modules\Organization\Models\Organization;
use App\Modules\Organization\Models\OrganizationGroup;
use App\Modules\Organization\Models\OrganizationMembership;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationPortalThemeIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $coordinator;
    protected User $regularStudent;
    protected Organization $organization;
    protected OrganizationMembership $membership;

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

        $this->regularStudent = User::factory()->create([
            'name'   => 'Test Candidate',
            'email'  => 'candidate@example.org',
            'status' => 'active',
        ]);
        $this->regularStudent->assignRole('student');

        $this->organization = Organization::create([
            'name'              => 'iC.edu UAT University',
            'slug'              => 'icedu-uat-university',
            'organization_type' => OrganizationType::University,
            'status'            => OrganizationStatus::Active,
            'created_by'        => $this->coordinator->id,
            'submitted_by'      => $this->coordinator->id,
            'reviewed_by'       => $this->coordinator->id,
            'reviewed_at'       => now(),
        ]);

        $this->membership = OrganizationMembership::create([
            'organization_id'   => $this->organization->id,
            'user_id'           => $this->coordinator->id,
            'role'              => MembershipRole::Coordinator,
            'status'            => MembershipStatus::Active,
            'joined_at'         => now(),
            'member_identifier' => 'COORD-001',
        ]);
    }

    /**
     * TEST ORG-THEME-01: Layout renders canonical data-theme attribute and early theme initialization script in <head>.
     */
    public function test_org_theme_01_layout_renders_data_theme_and_early_head_script(): void
    {
        $response = $this->actingAs($this->coordinator)
            ->get(route('organization.dashboard', $this->organization->slug));

        $response->assertStatus(200);
        $response->assertSee('data-theme="dark"', false);
        $response->assertSee('Early Theme Initialization to prevent flash of wrong theme');
        $response->assertSee("window.matchMedia('(prefers-color-scheme: dark)')", false);
        $response->assertSee("root.setAttribute('data-theme', activeTheme)", false);
        $response->assertSee("root.setAttribute('data-preference', preference)", false);
    }

    /**
     * TEST ORG-THEME-02: Layout renders 3-button theme selector in sidebar footer.
     */
    public function test_org_theme_02_layout_renders_three_button_theme_selector_in_sidebar(): void
    {
        $response = $this->actingAs($this->coordinator)
            ->get(route('organization.dashboard', $this->organization->slug));

        $response->assertStatus(200);
        $response->assertSee('<span>Appearance</span>', false);
        $response->assertSee('role="group" aria-label="Theme selector"', false);
        $response->assertSee('id="theme-btn-light"', false);
        $response->assertSee('id="theme-btn-dark"', false);
        $response->assertSee('id="theme-btn-system"', false);
        $response->assertSee("onclick=\"setIapTheme('light')\"", false);
        $response->assertSee("onclick=\"setIapTheme('dark')\"", false);
        $response->assertSee("onclick=\"setIapTheme('system')\"", false);
    }

    /**
     * TEST ORG-THEME-03: Theme switcher engine script is included with live media query listener.
     */
    public function test_org_theme_03_theme_switcher_engine_includes_live_listener_and_persistence(): void
    {
        $response = $this->actingAs($this->coordinator)
            ->get(route('organization.dashboard', $this->organization->slug));

        $response->assertStatus(200);
        $response->assertSee('window.setIapTheme = function(mode)', false);
        $response->assertSee('updateSwitcherButtonsUI', false);
        $response->assertSee(route('settings.appearance.update'), false);
    }

    /**
     * TEST ORG-THEME-04: Appearance controller persists theme preference to DB & session for light, dark, system.
     */
    public function test_org_theme_04_appearance_controller_persists_theme_preference(): void
    {
        // 1. Switch to light
        $resLight = $this->actingAs($this->coordinator)
            ->postJson(route('settings.appearance.update'), ['theme' => 'light']);

        $resLight->assertStatus(200);
        $resLight->assertJson(['success' => true, 'theme' => 'light']);
        $this->assertEquals('light', $this->coordinator->fresh()->getThemePreference());

        // 2. Switch to system
        $resSystem = $this->actingAs($this->coordinator)
            ->postJson(route('settings.appearance.update'), ['theme' => 'system']);

        $resSystem->assertStatus(200);
        $resSystem->assertJson(['success' => true, 'theme' => 'system']);
        $this->assertEquals('system', $this->coordinator->fresh()->getThemePreference());

        // 3. Switch to dark
        $resDark = $this->actingAs($this->coordinator)
            ->postJson(route('settings.appearance.update'), ['theme' => 'dark']);

        $resDark->assertStatus(200);
        $resDark->assertJson(['success' => true, 'theme' => 'dark']);
        $this->assertEquals('dark', $this->coordinator->fresh()->getThemePreference());
    }

    /**
     * TEST ORG-THEME-05: Unauthenticated user cannot access Organization Portal or update appearance.
     */
    public function test_org_theme_05_guest_cannot_access_portal_or_update_appearance(): void
    {
        $response = $this->get(route('organization.dashboard', $this->organization->slug));
        $response->assertRedirect(route('login'));

        $resPost = $this->postJson(route('settings.appearance.update'), ['theme' => 'light']);
        $resPost->assertStatus(401);
    }

    /**
     * TEST ORG-THEME-PAGE-01: Dashboard renders with normalized light and dark tokens.
     */
    public function test_org_theme_page_01_dashboard_renders_normalized_theme_tokens(): void
    {
        $response = $this->actingAs($this->coordinator)
            ->get(route('organization.dashboard', $this->organization->slug));

        $response->assertStatus(200);
        $response->assertSee('Organization Overview');
        $response->assertSee('bg-white dark:bg-slate-900', false);
        $response->assertSee('border-slate-200/80 dark:border-slate-800', false);
        $response->assertSee('text-slate-900 dark:text-white', false);
    }

    /**
     * TEST ORG-THEME-PAGE-02: Candidates / Members page renders with normalized light and dark tokens.
     */
    public function test_org_theme_page_02_candidates_renders_normalized_theme_tokens(): void
    {
        $response = $this->actingAs($this->coordinator)
            ->get(route('organization.candidates', $this->organization->slug));

        $response->assertStatus(200);
        $response->assertSee('Organization Members &amp; Roster', false);
        $response->assertSee('bg-white dark:bg-slate-900', false);
        $response->assertSee('border-slate-200 dark:border-slate-700', false);
    }

    /**
     * TEST ORG-THEME-PAGE-03: Groups index & detail pages render with normalized light and dark tokens.
     */
    public function test_org_theme_page_03_groups_render_normalized_theme_tokens(): void
    {
        $group = OrganizationGroup::create([
            'organization_id' => $this->organization->id,
            'name'            => 'Class 12-A Cohort',
            'group_type'      => 'class',
            'description'     => 'Senior Year Biology Class',
            'is_active'       => true,
        ]);

        $resIndex = $this->actingAs($this->coordinator)
            ->get(route('organization.groups', $this->organization->slug));

        $resIndex->assertStatus(200);
        $resIndex->assertSee('Class 12-A Cohort');
        $resIndex->assertSee('bg-white dark:bg-slate-900', false);

        $resShow = $this->actingAs($this->coordinator)
            ->get(route('organization.groups.show', [$this->organization->slug, $group->id]));

        $resShow->assertStatus(200);
        $resShow->assertSee('Class 12-A Cohort');
        $resShow->assertSee('bg-white dark:bg-slate-900', false);
    }

    /**
     * TEST ORG-THEME-PAGE-04: Organization Profile page renders with normalized light and dark tokens.
     */
    public function test_org_theme_page_04_profile_renders_normalized_theme_tokens(): void
    {
        $response = $this->actingAs($this->coordinator)
            ->get(route('organization.profile', $this->organization->slug));

        $response->assertStatus(200);
        $response->assertSee('Organization Profile');
        $response->assertSee('bg-white dark:bg-slate-900', false);
        $response->assertSee('border-slate-300 dark:border-slate-700', false);
    }

    /**
     * TEST ORG-THEME-PAGE-05: Select Organization page renders with early theme script and tokens.
     */
    public function test_org_theme_page_05_select_page_renders_with_early_theme_script(): void
    {
        $secondOrg = Organization::create([
            'name'              => 'Beta Polytechnic High',
            'slug'              => 'beta-polytechnic-high',
            'organization_type' => OrganizationType::School,
            'status'            => OrganizationStatus::Active,
            'created_by'        => $this->coordinator->id,
            'submitted_by'      => $this->coordinator->id,
            'reviewed_by'       => $this->coordinator->id,
            'reviewed_at'       => now(),
        ]);

        OrganizationMembership::create([
            'organization_id'   => $secondOrg->id,
            'user_id'           => $this->coordinator->id,
            'role'              => MembershipRole::Coordinator,
            'status'            => MembershipStatus::Active,
            'joined_at'         => now(),
            'member_identifier' => 'COORD-002',
        ]);

        $response = $this->actingAs($this->coordinator)
            ->get(route('organization.select'));

        $response->assertStatus(200);
        $response->assertSee('Select Organization');
        $response->assertSee('Early Theme Initialization to prevent flash of wrong theme');
        $response->assertSee('bg-white dark:bg-slate-900', false);
    }
}
