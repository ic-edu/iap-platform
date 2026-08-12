<?php

namespace Tests\Feature;

use App\Models\RepositoryRevisionRequest;
use App\Models\User;
use App\Modules\QuestionBank\Models\QuestionBank;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

use Database\Seeders\RolesAndPermissionsSeeder;

class NotificationContextualNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;
    protected User $repoManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->teacher = User::factory()->create([
            'email'  => 'teacher_notif@icedu.org',
            'status' => 'active',
        ]);
        $this->teacher->assignRole('teacher');

        $this->repoManager = User::factory()->create([
            'email'  => 'rm_notif@icedu.com',
            'status' => 'active',
        ]);
        $this->repoManager->assignRole('repository-manager');
    }

    /**
     * TEST 1: RM resubmission notification navigates directly to RM governance review route with from=notifications.
     */
    public function test_rm_resubmission_notification_navigates_to_rm_governance_route_with_context()
    {
        $bank = QuestionBank::create([
            'title'           => 'TOEIC Resubmitted Bank',
            'slug'            => 'toeic-resubmit-' . Str::random(5),
            'test_type'       => 'toeic',
            'current_version' => '1.0',
            'status'          => 'pending_approval',
            'created_by'      => $this->teacher->id,
        ]);

        $notifId = (string) Str::uuid();
        DB::table('notifications')->insert([
            'id'              => $notifId,
            'type'            => 'repository_resubmitted_for_approval',
            'notifiable_type' => 'App\Models\User',
            'notifiable_id'   => $this->repoManager->id,
            'data'            => json_encode([
                'title'      => 'Repository Resubmitted for Governance Approval',
                'message'    => "Repository '{$bank->title}' has been resubmitted.",
                'link'       => route('admin.repository-manager.question-bank-validate', $bank->id),
            ]),
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $response = $this->actingAs($this->repoManager)
            ->post(route('notifications.read', $notifId));

        $expectedTarget = route('admin.repository-manager.question-bank-validate', $bank->id) . '?from=notifications';
        $response->assertRedirect($expectedTarget);

        // Assert marked read
        $this->assertNotNull(DB::table('notifications')->where('id', $notifId)->value('read_at'));

        // Follow redirect and assert destination renders "← Back to Notifications"
        $destResponse = $this->actingAs($this->repoManager)->get($expectedTarget);
        $destResponse->assertStatus(200);
        $destResponse->assertSee('Back to Notifications');
        $destResponse->assertSee(route('notifications.index'));
    }

    /**
     * TEST 2: RM IRQA passed notification navigates to RM governance route with from=notifications context.
     */
    public function test_rm_irqa_passed_notification_navigates_to_rm_governance_route_with_context()
    {
        $bank = QuestionBank::create([
            'title'           => 'IRQA Passed Bank',
            'slug'            => 'irqa-passed-' . Str::random(5),
            'test_type'       => 'toeic',
            'current_version' => '1.0',
            'status'          => 'pending_approval',
            'created_by'      => $this->teacher->id,
        ]);

        $notifId = (string) Str::uuid();
        DB::table('notifications')->insert([
            'id'              => $notifId,
            'type'            => 'repository_resubmitted_irqa_passed',
            'notifiable_type' => 'App\Models\User',
            'notifiable_id'   => $this->repoManager->id,
            'data'            => json_encode([
                'title'   => 'Repository IRQA Verification Passed',
                'message' => 'Ready for governance review.',
                'link'    => route('admin.repository-manager.question-bank-validate', $bank->id),
            ]),
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $response = $this->actingAs($this->repoManager)
            ->post(route('notifications.read', $notifId));

        $expectedTarget = route('admin.repository-manager.question-bank-validate', $bank->id) . '?from=notifications';
        $response->assertRedirect($expectedTarget);

        $destResponse = $this->actingAs($this->repoManager)->get($expectedTarget);
        $destResponse->assertStatus(200);
        $destResponse->assertSee('Back to Notifications');
    }

    /**
     * TEST 3: Teacher revision request notification navigates to Teacher revision workflow with from=notifications.
     */
    public function test_teacher_revision_notification_navigates_to_teacher_revision_route_with_context()
    {
        $bank = QuestionBank::create([
            'title'           => 'Teacher Task Bank',
            'slug'            => 'teacher-task-' . Str::random(5),
            'test_type'       => 'toeic',
            'current_version' => '1.0',
            'status'          => 'needs_revision',
            'created_by'      => $this->teacher->id,
        ]);

        $revReq = RepositoryRevisionRequest::create([
            'question_bank_id' => $bank->id,
            'teacher_id'       => $this->teacher->id,
            'requested_by_id'  => $this->repoManager->id,
            'status'           => 'OPEN',
            'notes'            => 'Fix content',
        ]);

        $notifId = (string) Str::uuid();
        DB::table('notifications')->insert([
            'id'              => $notifId,
            'type'            => 'repository_revision_requested',
            'notifiable_type' => 'App\Models\User',
            'notifiable_id'   => $this->teacher->id,
            'data'            => json_encode([
                'title'   => 'Repository Revision Requested',
                'message' => "Revision requested for '{$bank->title}'",
                'link'    => route('teacher.repository-revisions.show', $revReq->id),
            ]),
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $response = $this->actingAs($this->teacher)
            ->post(route('notifications.read', $notifId));

        $expectedTarget = route('teacher.repository-revisions.show', $revReq->id) . '?from=notifications';
        $response->assertRedirect($expectedTarget);

        $destResponse = $this->actingAs($this->teacher)->get($expectedTarget);
        $destResponse->assertStatus(200);
        $destResponse->assertSee('Back to Notifications');
        $destResponse->assertSee(route('notifications.index'));
    }

    /**
     * TEST 4: Approval Queue -> Question Bank Validation preserves Back to Approval Queue when opened directly without notifications.
     */
    public function test_approval_queue_to_validation_preserves_default_back_link()
    {
        $bank = QuestionBank::create([
            'title'           => 'Queue Bank',
            'slug'            => 'queue-bank-' . Str::random(5),
            'test_type'       => 'toeic',
            'current_version' => '1.0',
            'status'          => 'pending_approval',
            'created_by'      => $this->teacher->id,
        ]);

        // Direct request without from=notifications query param
        $response = $this->actingAs($this->repoManager)
            ->get(route('admin.repository-manager.question-bank-validate', $bank->id));

        $response->assertStatus(200);
        $response->assertSee('Back to Approval Queue');
        $response->assertSee(route('admin.repository-manager.questions-approval'));
        $response->assertDontSee('Back to Notifications');
    }

    /**
     * TEST 5: Teacher Revision Center -> Revision Detail preserves Back to Revision Tasks when opened directly.
     */
    public function test_teacher_revision_center_to_detail_preserves_default_back_link()
    {
        $bank = QuestionBank::create([
            'title'           => 'Direct Teacher Bank',
            'slug'            => 'direct-teacher-' . Str::random(5),
            'test_type'       => 'toeic',
            'current_version' => '1.0',
            'status'          => 'needs_revision',
            'created_by'      => $this->teacher->id,
        ]);

        $revReq = RepositoryRevisionRequest::create([
            'question_bank_id' => $bank->id,
            'teacher_id'       => $this->teacher->id,
            'requested_by_id'  => $this->repoManager->id,
            'status'           => 'OPEN',
            'notes'            => 'Direct workflow notes',
        ]);

        // Direct request without from=notifications
        $response = $this->actingAs($this->teacher)
            ->get(route('teacher.repository-revisions.show', $revReq->id));

        $response->assertStatus(200);
        $response->assertSee('Back to Revision Tasks');
        $response->assertSee(route('teacher.repository-revisions.index'));
        $response->assertDontSee('Back to Notifications');
    }

    /**
     * TEST 6: Repository Manager Dashboard resolves to Repository Manager Command Center.
     */
    public function test_rm_dashboard_resolves_to_command_center()
    {
        $response = $this->actingAs($this->repoManager)
            ->get(route('admin.repository-manager.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Repository Manager Command Center');
    }

    /**
     * TEST 7: Open redirect security check - arbitrary from query values cannot cause external redirects.
     */
    public function test_open_redirect_security_protection()
    {
        $bank = QuestionBank::create([
            'title'           => 'Security Bank',
            'slug'            => 'sec-bank-' . Str::random(5),
            'test_type'       => 'toeic',
            'current_version' => '1.0',
            'status'          => 'pending_approval',
            'created_by'      => $this->teacher->id,
        ]);

        // Attempting to pass an evil URL as from parameter
        $response = $this->actingAs($this->repoManager)
            ->get(route('admin.repository-manager.question-bank-validate', [$bank->id, 'from' => 'https://evil.com']));

        $response->assertStatus(200);
        // Falls back to safe default Back to Approval Queue
        $response->assertSee('Back to Approval Queue');
        $response->assertDontSee('evil.com');
    }

    /**
     * TEST 8: Legacy notification without destination context falls back safely to notifications index.
     */
    public function test_legacy_notification_without_link_falls_back_safely()
    {
        $notifId = (string) Str::uuid();
        DB::table('notifications')->insert([
            'id'              => $notifId,
            'type'            => 'legacy_system_alert',
            'notifiable_type' => 'App\Models\User',
            'notifiable_id'   => $this->teacher->id,
            'data'            => json_encode([
                'title'   => 'Legacy Maintenance Notice',
                'message' => 'System will undergo routine maintenance.',
            ]),
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $response = $this->actingAs($this->teacher)
            ->post(route('notifications.read', $notifId));

        $response->assertStatus(302);
        $response->assertRedirect(route('notifications.index'));
    }
}
