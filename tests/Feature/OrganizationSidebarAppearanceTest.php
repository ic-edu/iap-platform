<?php

namespace Tests\Feature;

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

class OrganizationSidebarAppearanceTest extends TestCase
{
    use RefreshDatabase;

    protected User $coordinator;
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
     * TEST APPEAR-01: Theme controls are housed inside the user menu dropdown popover, not as permanent standalone sidebar block.
     */
    public function test_appear_01_theme_controls_housed_in_user_menu_popover(): void
    {
        $response = $this->actingAs($this->coordinator)
            ->get(route('organization.dashboard', $this->organization->slug));

        $response->assertStatus(200);

        // Verify user menu button and popover container exist
        $response->assertSee('id="org-user-menu-btn"', false);
        $response->assertSee('id="org-user-menu-dropdown"', false);
        $response->assertSee('x-data="{ open: false }"', false);
        $response->assertSee('@click.outside="open = false"', false);
        $response->assertSee('@keydown.escape.window="open = false"', false);

        // Verify appearance section exists inside dropdown
        $response->assertSee('role="group" aria-label="Theme selector"', false);
        $response->assertSee('id="current-theme-label"', false);
    }

    /**
     * TEST APPEAR-02: Profile/Appearance menu contains Light, Dark, and System controls.
     */
    public function test_appear_02_menu_contains_light_dark_system_triggers(): void
    {
        $response = $this->actingAs($this->coordinator)
            ->get(route('organization.dashboard', $this->organization->slug));

        $response->assertStatus(200);

        // Verify 3 theme buttons
        $response->assertSee('id="theme-btn-light"', false);
        $response->assertSee('id="theme-btn-dark"', false);
        $response->assertSee('id="theme-btn-system"', false);

        // Verify click handlers
        $response->assertSee("onclick=\"setIapTheme('light')\"", false);
        $response->assertSee("onclick=\"setIapTheme('dark')\"", false);
        $response->assertSee("onclick=\"setIapTheme('system')\"", false);

        // Verify accessible labels
        $response->assertSee('aria-label="Light mode"', false);
        $response->assertSee('aria-label="Dark mode"', false);
        $response->assertSee('aria-label="Use system theme"', false);
    }

    /**
     * TEST APPEAR-03: Current theme state is indicated and JS updater updates label.
     */
    public function test_appear_03_active_theme_state_and_js_updater(): void
    {
        $response = $this->actingAs($this->coordinator)
            ->get(route('organization.dashboard', $this->organization->slug));

        $response->assertStatus(200);
        $response->assertSee('function updateSwitcherButtonsUI(mode)', false);
        $response->assertSee("btn.classList.add('active')", false);
        $response->assertSee("btn.setAttribute('aria-pressed', 'true')", false);
        $response->assertSee("document.getElementById('current-theme-label')", false);
    }

    /**
     * TEST APPEAR-04: Single Sign Out action in user menu with no duplicate logouts.
     */
    public function test_appear_04_single_sign_out_action_without_duplicates(): void
    {
        $response = $this->actingAs($this->coordinator)
            ->get(route('organization.dashboard', $this->organization->slug));

        $response->assertStatus(200);

        // Count logout form occurrences in the page
        $content = $response->getContent();
        $logoutOccurrences = substr_count($content, 'action="' . route('logout') . '"');
        $this->assertEquals(1, $logoutOccurrences, 'There must be exactly one logout form in the organization layout');
        $response->assertSee('Sign Out');
    }

    /**
     * TEST APPEAR-05: Switch Organization link remains clearly visible in sidebar footer.
     */
    public function test_appear_05_switch_organization_link_remains_visible(): void
    {
        $response = $this->actingAs($this->coordinator)
            ->get(route('organization.dashboard', $this->organization->slug));

        $response->assertStatus(200);
        $response->assertSee(route('organization.select'), false);
        $response->assertSee('Switch Organization');
    }

    /**
     * TEST APPEAR-06: Accessibility attributes for keyboard and screen readers.
     */
    public function test_appear_06_accessibility_attributes_present(): void
    {
        $response = $this->actingAs($this->coordinator)
            ->get(route('organization.dashboard', $this->organization->slug));

        $response->assertStatus(200);
        $response->assertSee(':aria-expanded="open"', false);
        $response->assertSee('aria-haspopup="true"', false);
        $response->assertSee('aria-label="User menu"', false);
        $response->assertSee('role="menu"', false);
        $response->assertSee('aria-labelledby="org-user-menu-btn"', false);
    }

    /**
     * TEST APPEAR-07: Appearance persistence endpoint remains functional.
     */
    public function test_appear_07_appearance_endpoint_persists_settings(): void
    {
        $res = $this->actingAs($this->coordinator)
            ->postJson(route('settings.appearance.update'), ['theme' => 'light']);

        $res->assertStatus(200);
        $res->assertJson(['success' => true, 'theme' => 'light']);
        $this->assertEquals('light', $this->coordinator->fresh()->getThemePreference());
    }
}
