<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IrqaNavigationUiCleanupTest extends TestCase
{
    use RefreshDatabase;

    protected User $repoManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->repoManager = User::factory()->create([
            'name'   => 'Navigation Manager Audit',
            'email'  => 'nav_manager_audit@icedu.org',
            'status' => 'active',
        ]);
        $this->repoManager->assignRole('repository-manager');
    }

    /**
     * TEST 1: IRQA Overview does not contain redundant Open Repository Explorer header button.
     */
    public function test_irqa_overview_does_not_contain_redundant_header_cta()
    {
        $response = $this->actingAs($this->repoManager)
            ->get(route('admin.academic-library.quality'));

        $response->assertStatus(200);

        // Assert redundant header CTA button is removed
        $response->assertDontSee('Open Repository Explorer');

        // Assert KPI card links remain functional
        $response->assertSee(route('admin.academic-library.explorer', ['filter' => 'all']));
        $response->assertSee(route('admin.academic-library.explorer', ['filter' => 'healthy']));
        $response->assertSee(route('admin.academic-library.explorer', ['filter' => 'needs_improvement', 'sort' => 'health_asc']));
        $response->assertSee(route('admin.academic-library.explorer', ['filter' => 'reviewed_issues']));
    }

    /**
     * TEST 2: IRQA Repository Explorer Back button links to IRQA Overview.
     */
    public function test_irqa_explorer_back_button_links_to_irqa_overview()
    {
        $response = $this->actingAs($this->repoManager)
            ->get(route('admin.academic-library.explorer', ['filter' => 'needs_improvement']));

        $response->assertStatus(200);

        // Assert Back button links directly to IRQA Overview route (admin.academic-library.quality)
        $response->assertSee(route('admin.academic-library.quality'));
        $response->assertSee('← Back');
    }
}
