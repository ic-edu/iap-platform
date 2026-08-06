<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Assessment\Models\Test as AssessmentTest;
use App\Modules\QuestionBank\Models\QuestionBank;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IrqaContinuousImprovementPackTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;
    protected User $repoManager;
    protected User $superAdmin;
    protected QuestionBank $bank;
    protected AssessmentTest $test;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->teacher = User::factory()->create([
            'name'   => 'Teacher IRQA Pack',
            'email'  => 'teacher_irqa_pack@icedu.org',
            'status' => 'active',
        ]);
        $this->teacher->assignRole('teacher');

        $this->repoManager = User::factory()->create([
            'name'   => 'Repository Manager IRQA Pack',
            'email'  => 'repomanager_irqa_pack@icedu.org',
            'status' => 'active',
        ]);
        $this->repoManager->assignRole('repository-manager');

        $this->superAdmin = User::factory()->create([
            'name'   => 'Super Admin IRQA Pack',
            'email'  => 'superadmin_irqa_pack@icedu.org',
            'status' => 'active',
        ]);
        $this->superAdmin->assignRole('super-admin');

        $this->bank = QuestionBank::create([
            'title'      => 'IRQA Pack Bank 01',
            'slug'       => 'irqa-pack-bank-01',
            'test_type'  => 'toeic',
            'status'     => 'published',
            'created_by' => $this->teacher->id,
        ]);

        $this->test = AssessmentTest::create([
            'title'            => 'IRQA Pack Test 01',
            'slug'             => 'irqa-pack-test-01',
            'test_type'        => 'toeic',
            'duration_minutes' => 60,
            'pass_score'       => 700,
            'status'           => 'pending_approval',
            'created_by'       => $this->teacher->id,
        ]);
    }

    /**
     * TEST 1: All IRQA views render context-aware '← Back' buttons without hardcoded titles.
     */
    public function test_1_irqa_views_render_context_aware_back_navigation()
    {
        // Quality Dashboard
        $resQuality = $this->actingAs($this->repoManager)->get(route('admin.academic-library.quality'));
        $resQuality->assertStatus(200);
        $resQuality->assertSee('← Back');
        $resQuality->assertDontSee('Back to Repository Manager Dashboard');

        // Explorer
        $resExplorer = $this->actingAs($this->repoManager)->get(route('admin.academic-library.explorer'));
        $resExplorer->assertStatus(200);
        $resExplorer->assertSee('← Back');
        $resExplorer->assertDontSee('Back to Quality Dashboard');

        // Analytics
        $resAnalytics = $this->actingAs($this->repoManager)->get(route('admin.academic-library.analytics'));
        $resAnalytics->assertStatus(200);
        $resAnalytics->assertSee('← Back');
        $resAnalytics->assertDontSee('Back to Quality Dashboard');
    }

    /**
     * TEST 2: Duplicate URI segment cleaned up and legacy URI redirects cleanly.
     */
    public function test_2_duplicate_uri_cleaned_up_with_legacy_redirect()
    {
        // Cleaned URL: /admin/repository-manager/assessments/{id}/review
        $resClean = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.assessment-review', $this->test->id));
        $resClean->assertStatus(200);

        // Legacy URL: /admin/repository-manager/repository-manager/assessments/{id}
        $resLegacy = $this->actingAs($this->repoManager)->get('/admin/repository-manager/repository-manager/assessments/' . $this->test->id);
        $resLegacy->assertRedirect(route('admin.repository-manager.assessment-review', $this->test->id));
    }

    /**
     * TEST 3: Repository Manager Dashboard calculates real dynamic duplicate count from service layer.
     */
    public function test_3_repository_manager_dashboard_uses_real_service_duplicate_count()
    {
        $res = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.dashboard'));
        $res->assertStatus(200);
        $res->assertViewHas('duplicatesCount');
    }

    /**
     * TEST 4: Delete button terminology is updated to Request Deletion with modal structure.
     */
    public function test_4_delete_button_renamed_to_request_deletion_with_modal()
    {
        $resQb = $this->actingAs($this->repoManager)->get(route('admin.question-banks.index'));
        $resQb->assertStatus(200);
        $resQb->assertSee('Request Deletion');
        $resQb->assertSee('request-deletion-modal');

        $resTest = $this->actingAs($this->repoManager)->get(route('admin.tests.index'));
        $resTest->assertStatus(200);
        $resTest->assertSee('Request Deletion');
        $resTest->assertSee('request-deletion-modal');
    }
}
