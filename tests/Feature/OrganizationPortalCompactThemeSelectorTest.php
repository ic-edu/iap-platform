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

class OrganizationPortalCompactThemeSelectorTest extends TestCase
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
     * TEST ORG-THEME-ICON-01: Three icon controls render in Organization Portal layout.
     */
    public function test_org_theme_icon_01_renders_three_icon_buttons_with_svgs(): void
    {
        $response = $this->actingAs($this->coordinator)
            ->get(route('organization.dashboard', $this->organization->slug));

        $response->assertStatus(200);
        $response->assertSee('Theme &amp; Appearance', false);
        $response->assertSee('role="group" aria-label="Theme selector"', false);

        // Verify button IDs
        $response->assertSee('id="theme-btn-light"', false);
        $response->assertSee('id="theme-btn-dark"', false);
        $response->assertSee('id="theme-btn-system"', false);

        // Verify click handlers
        $response->assertSee("onclick=\"setIapTheme('light')\"", false);
        $response->assertSee("onclick=\"setIapTheme('dark')\"", false);
        $response->assertSee("onclick=\"setIapTheme('system')\"", false);

        // Verify SVG elements exist inside the selector
        $response->assertSee('<svg class="w-4 h-4 shrink-0"', false);
    }

    /**
     * TEST ORG-THEME-ICON-02: Visible Light/Dark/System text labels removed from buttons.
     */
    public function test_org_theme_icon_02_visible_text_labels_removed(): void
    {
        $response = $this->actingAs($this->coordinator)
            ->get(route('organization.dashboard', $this->organization->slug));

        $response->assertStatus(200);

        // Old text structures should NOT be present in the button markup
        $response->assertDontSee('<span class="text-[11px]">Light</span>', false);
        $response->assertDontSee('<span class="text-[11px]">Dark</span>', false);
        $response->assertDontSee('<span class="text-[11px]">System</span>', false);
        $response->assertDontSee('<span class="text-sm">☼</span>', false);
        $response->assertDontSee('<span class="text-sm">☾</span>', false);
        $response->assertDontSee('<span class="text-sm">▣</span>', false);
    }

    /**
     * TEST ORG-THEME-ICON-03: Accessible aria-label and title attributes exist for all 3 buttons.
     */
    public function test_org_theme_icon_03_accessible_attributes_present(): void
    {
        $response = $this->actingAs($this->coordinator)
            ->get(route('organization.dashboard', $this->organization->slug));

        $response->assertStatus(200);

        // Light button accessibility
        $response->assertSee('aria-label="Light mode"', false);
        $response->assertSee('title="Light mode"', false);

        // Dark button accessibility
        $response->assertSee('aria-label="Dark mode"', false);
        $response->assertSee('title="Dark mode"', false);

        // System button accessibility
        $response->assertSee('aria-label="Use system theme"', false);
        $response->assertSee('title="Use system theme"', false);
    }

    /**
     * TEST ORG-THEME-ICON-04: Active state JavaScript engine properly marks selected theme.
     */
    public function test_org_theme_icon_04_active_state_js_engine(): void
    {
        $response = $this->actingAs($this->coordinator)
            ->get(route('organization.dashboard', $this->organization->slug));

        $response->assertStatus(200);
        $response->assertSee('function updateSwitcherButtonsUI(mode)', false);
        $response->assertSee("btn.classList.add('active')", false);
        $response->assertSee("btn.setAttribute('aria-pressed', 'true')", false);
        $response->assertSee("btn.classList.remove('active')", false);
        $response->assertSee("btn.setAttribute('aria-pressed', 'false')", false);
    }

    /**
     * TEST ORG-THEME-ICON-05: Theme persistence endpoint remains unchanged and functional.
     */
    public function test_org_theme_icon_05_theme_persistence_endpoint_unaffected(): void
    {
        // Light persistence
        $resLight = $this->actingAs($this->coordinator)
            ->postJson(route('settings.appearance.update'), ['theme' => 'light']);
        $resLight->assertStatus(200);
        $resLight->assertJson(['success' => true, 'theme' => 'light']);
        $this->assertEquals('light', $this->coordinator->fresh()->getThemePreference());

        // Dark persistence
        $resDark = $this->actingAs($this->coordinator)
            ->postJson(route('settings.appearance.update'), ['theme' => 'dark']);
        $resDark->assertStatus(200);
        $resDark->assertJson(['success' => true, 'theme' => 'dark']);
        $this->assertEquals('dark', $this->coordinator->fresh()->getThemePreference());

        // System persistence
        $resSystem = $this->actingAs($this->coordinator)
            ->postJson(route('settings.appearance.update'), ['theme' => 'system']);
        $resSystem->assertStatus(200);
        $resSystem->assertJson(['success' => true, 'theme' => 'system']);
        $this->assertEquals('system', $this->coordinator->fresh()->getThemePreference());
    }

    /**
     * TEST ORG-THEME-ICON-06: System preference engine and live matchMedia listener intact.
     */
    public function test_org_theme_icon_06_system_preference_engine_intact(): void
    {
        $response = $this->actingAs($this->coordinator)
            ->get(route('organization.dashboard', $this->organization->slug));

        $response->assertStatus(200);
        $response->assertSee("window.matchMedia('(prefers-color-scheme: dark)')", false);
        $response->assertSee("window.setIapTheme = function(mode)", false);
        $response->assertSee("root.setAttribute('data-theme', activeTheme)", false);
        $response->assertSee("root.setAttribute('data-preference', preference)", false);
    }
}
