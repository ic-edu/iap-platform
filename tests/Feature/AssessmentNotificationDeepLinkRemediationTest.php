<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Assessment\Models\Test as AssessmentTest;
use App\Modules\Assessment\Models\TestQuestion;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AssessmentNotificationDeepLinkRemediationTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected User $repoManager;

    protected User $teacher;

    protected QuestionBank $bank;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->superAdmin = User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'sa_deep_link@test.com',
            'status' => 'active',
        ]);
        $this->superAdmin->assignRole('super-admin');

        $this->repoManager = User::factory()->create([
            'name' => 'Repository Manager',
            'email' => 'rm_deep_link@test.com',
            'status' => 'active',
        ]);
        $this->repoManager->assignRole('repository-manager');

        $this->teacher = User::factory()->create([
            'name' => 'Teacher Author',
            'email' => 'teacher_deep_link@test.com',
            'status' => 'active',
        ]);
        $this->teacher->assignRole('teacher');

        $this->bank = QuestionBank::create([
            'title' => 'Test Core Bank',
            'slug' => 'test-core-bank-'.uniqid(),
            'test_type' => 'toeic',
            'status' => 'approved',
            'created_by' => $this->teacher->id,
        ]);
    }

    private function createAssessment(string $status = 'pending_approval', bool $isPublished = false): AssessmentTest
    {
        $test = AssessmentTest::create([
            'title' => 'TOEIC Deep Link Test '.uniqid(),
            'slug' => 'toeic-deep-link-'.uniqid(),
            'test_type' => 'toeic',
            'duration_minutes' => 60,
            'pass_score' => 70,
            'status' => $status,
            'is_published' => $isPublished,
            'created_by' => $this->teacher->id,
        ]);

        $section = TestSection::create(['test_id' => $test->id, 'title' => 'Listening Section', 'order' => 1]);
        $q = Question::create([
            'question_bank_id' => $this->bank->id,
            'prompt' => 'Prompt stem for deep link verification',
            'type' => 'single_choice',
            'difficulty' => 'medium',
            'points' => 1,
        ]);
        TestQuestion::create(['test_section_id' => $section->id, 'question_id' => $q->id, 'order' => 1, 'points' => 1]);

        return $test;
    }

    /**
     * TEST A: RM clicks Assessment Approved notification -> no 403, lands on approved publication context.
     */
    public function test_rm_clicks_assessment_approved_notification_lands_on_approved_publication_context(): void
    {
        $test = $this->createAssessment('approved', false);

        $notifId = (string) Str::uuid();
        DB::table('notifications')->insert([
            'id' => $notifId,
            'type' => 'assessment_approved',
            'notifiable_type' => 'App\Models\User',
            'notifiable_id' => $this->repoManager->id,
            'data' => json_encode([
                'title' => 'Assessment Approved',
                'message' => "Assessment '{$test->title}' was approved.",
                'notification_type' => 'ASSESSMENT_APPROVED',
                'entity_type' => 'test',
                'entity_id' => (string) $test->id,
                'target_url' => route('admin.publications.assessments', ['status' => 'approved', 'highlight' => $test->id]),
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $res = $this->actingAs($this->repoManager)->post(route('notifications.read', $notifId));
        $res->assertStatus(302);

        $expectedTarget = route('admin.publications.assessments', ['status' => 'approved', 'highlight' => $test->id]).'&from=notifications';
        $res->assertRedirect($expectedTarget);

        // Follow redirect and ensure 200 OK without 403
        $pageRes = $this->actingAs($this->repoManager)->get($expectedTarget);
        $pageRes->assertStatus(200);
        $pageRes->assertSee($test->title);
        $pageRes->assertSee('Approved Assessments Ready for Publication');
    }

    /**
     * TEST B: RM clicks Assessment Published notification -> no 403, lands on published publication context.
     */
    public function test_rm_clicks_assessment_published_notification_lands_on_published_publication_context(): void
    {
        $test = $this->createAssessment('published', true);

        $notifId = (string) Str::uuid();
        DB::table('notifications')->insert([
            'id' => $notifId,
            'type' => 'assessment_published',
            'notifiable_type' => 'App\Models\User',
            'notifiable_id' => $this->repoManager->id,
            'data' => json_encode([
                'title' => 'Assessment Published',
                'message' => "Assessment '{$test->title}' has been published.",
                'notification_type' => 'ASSESSMENT_PUBLISHED',
                'entity_type' => 'assessment',
                'entity_id' => (string) $test->id,
                'target_url' => route('admin.publications.assessments', ['status' => 'published', 'highlight' => $test->id]),
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $res = $this->actingAs($this->repoManager)->post(route('notifications.read', $notifId));
        $res->assertStatus(302);

        $expectedTarget = route('admin.publications.assessments', ['status' => 'published', 'highlight' => $test->id]).'&from=notifications';
        $res->assertRedirect($expectedTarget);

        // Follow redirect and ensure 200 OK without 403
        $pageRes = $this->actingAs($this->repoManager)->get($expectedTarget);
        $pageRes->assertStatus(200);
        $pageRes->assertSee($test->title);
        $pageRes->assertSee('Published Assessments Registry');
    }

    /**
     * TEST C: Existing legacy notification containing /admin/tests/{id} is safely resolved for RM across states.
     */
    public function test_rm_legacy_notification_with_admin_tests_id_resolves_safely_by_state(): void
    {
        // Case 1: Published test with legacy URL /admin/tests/{id}
        $publishedTest = $this->createAssessment('published', true);
        $notifPubId = (string) Str::uuid();
        DB::table('notifications')->insert([
            'id' => $notifPubId,
            'type' => 'assessment_published',
            'notifiable_type' => 'App\Models\User',
            'notifiable_id' => $this->repoManager->id,
            'data' => json_encode([
                'title' => 'Legacy Published Notice',
                'message' => 'Legacy format link',
                'notification_type' => 'ASSESSMENT_PUBLISHED',
                'entity_type' => 'assessment',
                'entity_id' => (string) $publishedTest->id,
                'target_url' => '/admin/tests/'.$publishedTest->id,
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $resPub = $this->actingAs($this->repoManager)->post(route('notifications.read', $notifPubId));
        $resPub->assertStatus(302);
        $resPub->assertRedirect(route('admin.publications.assessments', ['status' => 'published', 'highlight' => $publishedTest->id]).'&from=notifications');

        // Case 2: Approved test with legacy URL /admin/tests/{id}
        $approvedTest = $this->createAssessment('approved', false);
        $notifAppId = (string) Str::uuid();
        DB::table('notifications')->insert([
            'id' => $notifAppId,
            'type' => 'assessment_approved',
            'notifiable_type' => 'App\Models\User',
            'notifiable_id' => $this->repoManager->id,
            'data' => json_encode([
                'title' => 'Legacy Approved Notice',
                'message' => 'Legacy format link',
                'notification_type' => 'ASSESSMENT_APPROVED',
                'entity_type' => 'test',
                'entity_id' => (string) $approvedTest->id,
                'target_url' => '/admin/tests/'.$approvedTest->id,
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $resApp = $this->actingAs($this->repoManager)->post(route('notifications.read', $notifAppId));
        $resApp->assertStatus(302);
        $resApp->assertRedirect(route('admin.publications.assessments', ['status' => 'approved', 'highlight' => $approvedTest->id]).'&from=notifications');

        // Case 3: Pending test with legacy URL /admin/tests/{id}
        $pendingTest = $this->createAssessment('pending', false);
        $notifPenId = (string) Str::uuid();
        DB::table('notifications')->insert([
            'id' => $notifPenId,
            'type' => 'assessment_submitted',
            'notifiable_type' => 'App\Models\User',
            'notifiable_id' => $this->repoManager->id,
            'data' => json_encode([
                'title' => 'Legacy Pending Notice',
                'message' => 'Legacy format link',
                'notification_type' => 'ASSESSMENT_SUBMITTED',
                'entity_type' => 'test',
                'entity_id' => (string) $pendingTest->id,
                'target_url' => '/admin/tests/'.$pendingTest->id,
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $resPen = $this->actingAs($this->repoManager)->post(route('notifications.read', $notifPenId));
        $resPen->assertStatus(302);
        $resPen->assertRedirect(route('admin.repository-manager.assessment-review', $pendingTest->id).'?from=notifications');
    }

    /**
     * TEST D: Teacher notification destinations remain authorized and never route to RM-only surfaces.
     */
    public function test_teacher_notification_destinations_remain_authorized(): void
    {
        $test = $this->createAssessment('approved', false);

        // Teacher receives notification with publication target_url
        $notifId = (string) Str::uuid();
        DB::table('notifications')->insert([
            'id' => $notifId,
            'type' => 'assessment_approved',
            'notifiable_type' => 'App\Models\User',
            'notifiable_id' => $this->teacher->id,
            'data' => json_encode([
                'title' => 'Assessment Approved',
                'message' => "Your Assessment '{$test->title}' was approved.",
                'notification_type' => 'ASSESSMENT_APPROVED',
                'entity_type' => 'test',
                'entity_id' => (string) $test->id,
                'target_url' => route('admin.publications.assessments', ['status' => 'approved', 'highlight' => $test->id]),
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $res = $this->actingAs($this->teacher)->post(route('notifications.read', $notifId));
        $res->assertStatus(302);

        // Teacher is routed to teacher.tests.show (or teacher workspace), NOT admin.publications.assessments
        $expectedTeacherTarget = route('teacher.tests.show', $test->id).'?from=notifications';
        $res->assertRedirect($expectedTeacherTarget);

        // Follow redirect and verify Teacher receives 200 OK
        $pageRes = $this->actingAs($this->teacher)->get($expectedTeacherTarget);
        $pageRes->assertStatus(200);
        $pageRes->assertSee($test->title);
    }

    /**
     * TEST E: Open redirect protections remain intact.
     */
    public function test_open_redirect_protections_remain_intact(): void
    {
        $test = $this->createAssessment('approved', false);

        $notifId = (string) Str::uuid();
        DB::table('notifications')->insert([
            'id' => $notifId,
            'type' => 'assessment_approved',
            'notifiable_type' => 'App\Models\User',
            'notifiable_id' => $this->repoManager->id,
            'data' => json_encode([
                'title' => 'Assessment Approved',
                'notification_type' => 'ASSESSMENT_APPROVED',
                'entity_type' => 'test',
                'entity_id' => (string) $test->id,
                'target_url' => route('admin.publications.assessments', ['status' => 'approved', 'highlight' => $test->id]),
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Attempt open redirect with external URL
        $res = $this->actingAs($this->repoManager)->post(route('notifications.read', $notifId), [
            'return_url' => 'https://malicious-site.com/exploit',
        ]);

        $res->assertStatus(302);
        $res->assertRedirect(route('admin.publications.assessments', ['status' => 'approved', 'highlight' => $test->id]).'&from=notifications');
        $this->assertStringNotContainsString('malicious-site.com', $res->headers->get('Location'));
    }

    /**
     * TEST F: RepositoryManagerController::approveAssessment dispatches notification with canonical publication targetUrl.
     */
    public function test_rm_approve_assessment_dispatches_canonical_notification(): void
    {
        $test = $this->createAssessment('pending_approval', false);

        $res = $this->actingAs($this->repoManager)->post(route('admin.repository-manager.assessment-approve', $test->id));
        $res->assertRedirect(route('admin.publications.assessments', ['status' => 'approved', 'highlight' => $test->id]));

        $notif = $this->teacher->notifications()->where('data->notification_type', 'ASSESSMENT_APPROVED')->first();
        $this->assertNotNull($notif);
        $this->assertEquals(
            route('admin.publications.assessments', ['status' => 'approved', 'highlight' => $test->id]),
            $notif->data['target_url']
        );
    }

    /**
     * TEST G: PublicationOperationController::publishAssessment dispatches notification with canonical publication targetUrl.
     */
    public function test_publish_assessment_dispatches_canonical_notification(): void
    {
        $test = $this->createAssessment('approved', false);

        $res = $this->actingAs($this->repoManager)->post(route('admin.publications.assessments.publish', $test->id));
        $res->assertRedirect();

        $notif = $this->teacher->notifications()->where('data->notification_type', 'ASSESSMENT_PUBLISHED')->first();
        $this->assertNotNull($notif);
        $this->assertEquals(
            route('admin.publications.assessments', ['status' => 'published', 'highlight' => $test->id]),
            $notif->data['target_url']
        );
    }
}
