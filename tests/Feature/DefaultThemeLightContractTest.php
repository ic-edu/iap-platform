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
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DefaultThemeLightContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_user_model_defaults_to_light_theme_when_unpersisted_or_not_set(): void
    {
        $user = new User;
        $this->assertSame('light', $user->getThemePreference());

        $savedUser = User::factory()->create();
        $this->assertSame('light', $savedUser->getThemePreference());
    }

    public function test_user_model_preserves_explicit_dark_preference(): void
    {
        $user = User::factory()->create([
            'theme_preference' => 'dark',
        ]);

        $this->assertSame('dark', $user->getThemePreference());
    }

    public function test_user_model_preserves_explicit_system_preference(): void
    {
        $user = User::factory()->create([
            'theme_preference' => 'system',
        ]);

        $this->assertSame('system', $user->getThemePreference());
    }

    public function test_admin_layout_defaults_to_light_theme_for_new_teacher(): void
    {
        $teacher = User::factory()->create();
        $teacher->assignRole('teacher');

        $response = $this->actingAs($teacher)->get(route('teacher.question-generator.index'));

        $response->assertOk();
        $response->assertSee('data-theme="light"', false);
        $response->assertSee("var preference = 'light';", false);
        $response->assertSee("return pref === 'dark' ? 'dark' : 'light';", false);
        $response->assertSee("var currentPref = document.documentElement.getAttribute('data-preference') || 'light';", false);
    }

    public function test_admin_layout_preserves_saved_dark_preference(): void
    {
        $teacher = User::factory()->create([
            'theme_preference' => 'dark',
        ]);
        $teacher->assignRole('teacher');

        $response = $this->actingAs($teacher)->get(route('teacher.question-generator.index'));

        $response->assertOk();
        $response->assertSee('data-theme="dark"', false);
        $response->assertSee("var preference = 'dark';", false);
    }

    public function test_admin_layout_preserves_saved_system_preference(): void
    {
        $teacher = User::factory()->create([
            'theme_preference' => 'system',
        ]);
        $teacher->assignRole('teacher');

        $response = $this->actingAs($teacher)->get(route('teacher.question-generator.index'));

        $response->assertOk();
        $response->assertSee('data-theme="system"', false);
        $response->assertSee("var preference = 'system';", false);
        $response->assertSee("window.matchMedia('(prefers-color-scheme: dark)')", false);
    }

    public function test_candidate_layout_defaults_to_light_theme_for_student(): void
    {
        $candidate = User::factory()->create();
        $candidate->assignRole('student');

        $response = $this->actingAs($candidate)->get(route('candidate.portal'));

        $response->assertOk();
        $response->assertSee('data-theme="light"', false);
        $response->assertSee("var preference = 'light';", false);
        $response->assertSee("return pref === 'dark' ? 'dark' : 'light';", false);
        $response->assertSee("var currentPref = document.documentElement.getAttribute('data-preference') || 'light';", false);
    }

    public function test_organization_portal_defaults_to_light_theme_for_coordinator(): void
    {
        $coordinator = User::factory()->create();
        $coordinator->assignRole('admin');

        $org = Organization::create([
            'name' => 'Test Org',
            'slug' => 'test-org',
            'organization_type' => OrganizationType::University,
            'status' => OrganizationStatus::Active,
            'created_by' => $coordinator->id,
            'submitted_by' => $coordinator->id,
            'reviewed_by' => $coordinator->id,
            'reviewed_at' => now(),
        ]);

        OrganizationMembership::create([
            'organization_id' => $org->id,
            'user_id' => $coordinator->id,
            'role' => MembershipRole::Coordinator,
            'status' => MembershipStatus::Active,
            'created_by' => $coordinator->id,
        ]);

        $response = $this->actingAs($coordinator)->get(route('organization.dashboard', $org->slug));

        $response->assertOk();
        $response->assertSee('data-theme="light"', false);
        $response->assertSee("var preference = 'light';", false);
        $response->assertSee("return pref === 'dark' ? 'dark' : 'light';", false);
        $response->assertSee('id="current-theme-label">light</span>', false);
    }

    public function test_all_layout_views_fallback_to_light_without_session_or_user(): void
    {
        // Verify select.blade.php
        $renderedSelect = Blade::render(
            file_get_contents(base_path('app/Modules/Organization/Views/select.blade.php')),
            ['organizations' => collect([])]
        );
        $this->assertStringContainsString('data-theme="light"', $renderedSelect);
        $this->assertStringContainsString("var preference = 'light';", $renderedSelect);
        $this->assertStringContainsString("return pref === 'dark' ? 'dark' : 'light';", $renderedSelect);

        // Verify app.blade.php with default user
        $user = User::factory()->create();
        $this->actingAs($user);
        $renderedApp = Blade::render(
            file_get_contents(resource_path('views/layouts/app.blade.php')),
            ['slot' => '']
        );
        $this->assertStringContainsString('data-theme="light"', $renderedApp);
        $this->assertStringContainsString("var preference = 'light';", $renderedApp);
        $this->assertStringContainsString("return pref === 'dark' ? 'dark' : 'light';", $renderedApp);
    }

    public function test_appearance_settings_endpoint_persists_theme_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        $user->assignRole('teacher');

        $response = $this->actingAs($user)->postJson(route('settings.appearance.update'), [
            'theme' => 'dark',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true, 'theme' => 'dark']);

        $user->refresh();
        $this->assertSame('dark', $user->theme_preference);
        $this->assertSame('dark', session('theme_preference'));

        // Switch to system
        $responseSystem = $this->actingAs($user)->postJson(route('settings.appearance.update'), [
            'theme' => 'system',
        ]);
        $responseSystem->assertOk();
        $user->refresh();
        $this->assertSame('system', $user->theme_preference);
        $this->assertSame('system', session('theme_preference'));

        // Switch back to light
        $responseLight = $this->actingAs($user)->postJson(route('settings.appearance.update'), [
            'theme' => 'light',
        ]);
        $responseLight->assertOk();
        $user->refresh();
        $this->assertSame('light', $user->theme_preference);
        $this->assertSame('light', session('theme_preference'));
    }

    public function test_appearance_settings_endpoint_rejects_invalid_theme(): void
    {
        $user = User::factory()->create();
        $user->assignRole('teacher');

        $response = $this->actingAs($user)->postJson(route('settings.appearance.update'), [
            'theme' => 'neon-cyberpunk',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['theme']);
    }

    public function test_forward_migration_changes_column_default_to_light_and_preserves_existing_rows(): void
    {
        $userDark = User::factory()->create(['theme_preference' => 'dark']);
        $userLight = User::factory()->create(['theme_preference' => 'light']);
        $userSystem = User::factory()->create(['theme_preference' => 'system']);

        // Insert direct DB row without specifying theme_preference
        $userId = DB::table('users')->insertGetId([
            'name' => 'Raw DB User',
            'email' => 'raw_db_user@example.com',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $rawUser = DB::table('users')->where('id', $userId)->first();
        $this->assertSame('light', $rawUser->theme_preference);

        // Verify existing rows are untouched
        $this->assertSame('dark', $userDark->fresh()->theme_preference);
        $this->assertSame('light', $userLight->fresh()->theme_preference);
        $this->assertSame('system', $userSystem->fresh()->theme_preference);
    }

    public function test_forward_migration_down_reverts_default_to_dark_without_modifying_rows(): void
    {
        $userDark = User::factory()->create(['theme_preference' => 'dark']);
        $userLight = User::factory()->create(['theme_preference' => 'light']);
        $userSystem = User::factory()->create(['theme_preference' => 'system']);

        $migration = require database_path('migrations/2026_09_29_000001_change_theme_preference_default_to_light_in_users_table.php');
        $migration->down();

        // After down(), direct DB row inserted without theme_preference receives 'dark'
        $userId = DB::table('users')->insertGetId([
            'name' => 'Raw Down User',
            'email' => 'raw_down_user@example.com',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $rawUser = DB::table('users')->where('id', $userId)->first();
        $this->assertSame('dark', $rawUser->theme_preference);

        // Verify existing rows were NOT modified by down()
        $this->assertSame('dark', $userDark->fresh()->theme_preference);
        $this->assertSame('light', $userLight->fresh()->theme_preference);
        $this->assertSame('system', $userSystem->fresh()->theme_preference);

        // Re-run up() to restore schema state
        $migration->up();
        $this->assertSame('dark', $userDark->fresh()->theme_preference);
        $this->assertSame('light', $userLight->fresh()->theme_preference);
        $this->assertSame('system', $userSystem->fresh()->theme_preference);
    }
}
