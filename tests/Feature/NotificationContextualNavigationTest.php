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
     * TEST 1: RM resubmission notification navigates directly to RM governance review route.
     */
    public function test_rm_resubmission_notification_navigates_to_rm_governance_route()
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

        $response->assertRedirect(route('admin.repository-manager.question-bank-validate', $bank->id));

        // Assert marked read
        $this->assertDatabaseHas('notifications', [
            'id' => $notifId,
        ]);
        $this->assertNotNull(DB::table('notifications')->where('id', $notifId)->value('read_at'));
    }

    /**
     * TEST 2: RM IRQA passed notification navigates to RM governance route.
     */
    public function test_rm_irqa_passed_notification_navigates_to_rm_governance_route()
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

        $response->assertRedirect(route('admin.repository-manager.question-bank-validate', $bank->id));
    }

    /**
     * TEST 3: Teacher revision request notification navigates to Teacher revision workflow.
     */
    public function test_teacher_revision_notification_navigates_to_teacher_revision_route()
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

        $response->assertRedirect(route('teacher.repository-revisions.show', $revReq->id));
    }

    /**
     * TEST 4: Legacy notification without destination context does not crash and falls back safely.
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

    /**
     * TEST 5: Unauthorized user cannot bypass role authorization via notification link.
     */
    public function test_unauthorized_user_cannot_bypass_role_authorization()
    {
        $bank = QuestionBank::create([
            'title'           => 'Secret Governance Bank',
            'slug'            => 'secret-bank-' . Str::random(5),
            'test_type'       => 'toeic',
            'current_version' => '1.0',
            'status'          => 'pending_approval',
            'created_by'      => $this->teacher->id,
        ]);

        // Malicious or mismatched notification targeting RM route given to a Teacher
        $notifId = (string) Str::uuid();
        DB::table('notifications')->insert([
            'id'              => $notifId,
            'type'            => 'mismatched_target',
            'notifiable_type' => 'App\Models\User',
            'notifiable_id'   => $this->teacher->id,
            'data'            => json_encode([
                'title'   => 'Governance Alert',
                'message' => 'RM internal view link',
                'link'    => route('admin.repository-manager.question-bank-validate', $bank->id),
            ]),
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $response = $this->actingAs($this->teacher)
            ->post(route('notifications.read', $notifId));

        // Teacher should be redirected to Teacher Revision Index, NOT the forbidden RM route
        $response->assertRedirect(route('teacher.repository-revisions.index'));
    }

    /**
     * TEST 6: Full Notification Center index page renders in IAP dark theme.
     */
    public function test_notification_center_renders_in_dark_theme()
    {
        $response = $this->actingAs($this->repoManager)
            ->get(route('notifications.index'));

        $response->assertStatus(200);
        $response->assertSee('Notification Center');
        $response->assertSee('notif-page-container', false);
    }

    /**
     * TEST 7: Mark All Read action clears unread count for user.
     */
    public function test_mark_all_read_clears_unread_notifications()
    {
        DB::table('notifications')->insert([
            'id'              => (string) Str::uuid(),
            'type'            => 'test_alert',
            'notifiable_type' => 'App\Models\User',
            'notifiable_id'   => $this->teacher->id,
            'data'            => json_encode(['title' => 'Alert 1']),
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $this->assertEquals(1, $this->teacher->unreadNotifications()->count());

        $response = $this->actingAs($this->teacher)
            ->post(route('notifications.read-all'));

        $response->assertRedirect(route('notifications.index'));
        $this->assertEquals(0, $this->teacher->unreadNotifications()->count());
    }
}
