<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Modules\QuestionBank\Models\QuestionChoice;
use App\Services\RepositoryQualityService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class RepositoryManagerDashboardPerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;

    protected User $repoManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->teacher = User::factory()->create([
            'name' => 'Performance Test Teacher',
            'email' => 'perf_teacher@test.com',
            'status' => 'active',
        ]);
        $this->teacher->assignRole('teacher');

        $this->repoManager = User::factory()->create([
            'name' => 'Performance Test RM',
            'email' => 'perf_rm@test.com',
            'status' => 'active',
        ]);
        $this->repoManager->assignRole('repository-manager');
    }

    /**
     * TEST 1: RM dashboard invokes getGlobalQualitySummary exactly once per request.
     */
    public function test_rm_dashboard_invokes_get_global_quality_summary_exactly_once(): void
    {
        $bank = QuestionBank::create([
            'title' => 'TOEIC Listening Performance Bank',
            'slug' => 'toeic-listening-perf-bank-'.uniqid(),
            'test_type' => 'toeic',
            'status' => 'approved',
            'created_by' => $this->teacher->id,
            'description' => 'A valid repository description for performance testing.',
        ]);

        $q = Question::create([
            'question_bank_id' => $bank->id,
            'prompt' => 'Select the best response.',
            'type' => 'single_choice',
            'difficulty' => 'medium',
            'points' => 1,
            'explanation' => 'Detailed rationale for this item.',
        ]);

        QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => 'Option A', 'is_correct' => true]);
        QuestionChoice::create(['question_id' => $q->id, 'label' => 'B', 'content' => 'Option B', 'is_correct' => false]);

        // Spy on RepositoryQualityService
        $realQualityService = app(RepositoryQualityService::class);
        $spy = Mockery::spy($realQualityService)->makePartial();
        $this->app->instance(RepositoryQualityService::class, $spy);

        $response = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.dashboard'));
        $response->assertStatus(200);

        // Verify getGlobalQualitySummary is called exactly ONCE
        $spy->shouldHaveReceived('getGlobalQualitySummary')->once();

        // Verify getAnalyticsData is NOT called during dashboard render
        $spy->shouldNotHaveReceived('getAnalyticsData');
    }

    /**
     * TEST 2: Dashboard metrics semantic equivalence matches getAnalyticsData calculation.
     */
    public function test_dashboard_metrics_semantic_equivalence_with_analytics_data(): void
    {
        $bank = QuestionBank::create([
            'title' => 'TOEIC Vocabulary Bank',
            'slug' => 'toeic-vocab-bank-'.uniqid(),
            'test_type' => 'toeic',
            'status' => 'approved',
            'created_by' => $this->teacher->id,
            'description' => 'Comprehensive vocabulary repository.',
        ]);

        $q = Question::create([
            'question_bank_id' => $bank->id,
            'prompt' => 'Complete the sentence with appropriate vocabulary.',
            'type' => 'single_choice',
            'difficulty' => 'easy',
            'points' => 1,
            'explanation' => 'Explanation text.',
        ]);

        QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => 'Correct Word', 'is_correct' => true]);
        QuestionChoice::create(['question_id' => $q->id, 'label' => 'B', 'content' => 'Incorrect Word', 'is_correct' => false]);

        $qualityService = app(RepositoryQualityService::class);
        $globalSummary = $qualityService->getGlobalQualitySummary();
        $analyticsData = $qualityService->getAnalyticsData();

        $audits = $globalSummary['audits'] ?? [];
        $totalAudits = count($audits);
        $derivedMetadataCompleteness = $totalAudits > 0
            ? (int) round(array_sum(array_map(fn ($audit) => $audit['scores']['metadata'] ?? 0, $audits)) / $totalAudits)
            : 100;

        $this->assertEquals($analyticsData['metadata_completion'], $derivedMetadataCompleteness);

        $response = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.dashboard'));
        $response->assertStatus(200);
        $response->assertSee("{$derivedMetadataCompleteness}%");
        $response->assertSee("{$globalSummary['avg_health_score']}");
    }

    /**
     * TEST 3: Zero repositories fallback behavior renders 100% metadata compliance.
     */
    public function test_zero_repositories_fallback_renders_default_values(): void
    {
        $response = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.dashboard'));
        $response->assertStatus(200);
        $response->assertSee('100%');
        $response->assertSee('100<span class="text-xs font-normal text-slate-400">/100</span>', false);
    }
}
