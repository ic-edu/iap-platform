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
     * TEST 1: Dropdown notification clicked from RM Command Center preserves RM Dashboard as return origin.
     */
    public function test_dropdown_notification_from_rm_dashboard_returns_to_rm_dashboard()
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
                'title'   => 'Repository Resubmitted for Governance Approval',
                'message' => "Repository '{$bank->title}' has been resubmitted.",
                'link'    => route('admin.repository-manager.question-bank-validate', $bank->id),
            ]),
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        // Simulating dropdown notification click from RM Dashboard
        $originUrl = '/admin/repository-manager/dashboard';
        $response = $this->actingAs($this->repoManager)
            ->post(route('notifications.read', $notifId), [
                'return_url' => $originUrl,
            ]);

        $expectedTarget = route('admin.repository-manager.question-bank-validate', $bank->id) . '?from_url=' . urlencode($originUrl);
        $response->assertRedirect($expectedTarget);

        // Follow redirect and verify Back button returns to origin page (/admin/repository-manager/dashboard)
        $destResponse = $this->actingAs($this->repoManager)->get($expectedTarget);
        $destResponse->assertStatus(200);
        $destResponse->assertSee($originUrl);
        $destResponse->assertDontSee('Back to Notifications');
    }

    /**
     * TEST 2: Notification clicked from Notification Center page returns to Notification Center (/notifications).
     */
    public function test_notification_center_notification_returns_to_notification_center()
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

        // Simulating notification click directly from Notification Center page (/notifications)
        $response = $this->actingAs($this->repoManager)
            ->post(route('notifications.read', $notifId), [
                'from' => 'notifications',
            ]);

        $expectedTarget = route('admin.repository-manager.question-bank-validate', $bank->id) . '?from=notifications';
        $response->assertRedirect($expectedTarget);

        $destResponse = $this->actingAs($this->repoManager)->get($expectedTarget);
        $destResponse->assertStatus(200);
        $destResponse->assertSee('Back to Notifications');
        $destResponse->assertSee(route('notifications.index'));
    }

    /**
     * TEST 3: Dropdown notification clicked by Teacher from Teacher Dashboard returns to Teacher Dashboard.
     */
    public function test_dropdown_notification_from_teacher_dashboard_returns_to_teacher_dashboard()
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

        // Simulating dropdown notification click from Teacher Dashboard
        $teacherOrigin = '/teacher/dashboard';
        $response = $this->actingAs($this->teacher)
            ->post(route('notifications.read', $notifId), [
                'return_url' => $teacherOrigin,
            ]);

        $expectedTarget = route('teacher.repository-revisions.show', $revReq->id) . '?from_url=' . urlencode($teacherOrigin);
        $response->assertRedirect($expectedTarget);

        $destResponse = $this->actingAs($this->teacher)->get($expectedTarget);
        $destResponse->assertStatus(200);
        $destResponse->assertSee($teacherOrigin);
        $destResponse->assertDontSee('Back to Notifications');
    }

    /**
     * TEST 4: Approval Queue -> Question Bank Validation preserves Back to Approval Queue when opened directly.
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

        $response = $this->actingAs($this->repoManager)
            ->get(route('admin.repository-manager.question-bank-validate', $bank->id));

        $response->assertStatus(200);
        $response->assertSee('Back to Approval Queue');
        $response->assertSee(route('admin.repository-manager.questions-approval'));
        $response->assertDontSee('Back to Notifications');
    }

    /**
     * TEST 5: Open redirect security check - arbitrary external URLs in return_url are rejected.
     */
    public function test_open_redirect_security_protection_rejects_external_urls()
    {
        $bank = QuestionBank::create([
            'title'           => 'Security Bank',
            'slug'            => 'sec-bank-' . Str::random(5),
            'test_type'       => 'toeic',
            'current_version' => '1.0',
            'status'          => 'pending_approval',
            'created_by'      => $this->teacher->id,
        ]);

        $notifId = (string) Str::uuid();
        DB::table('notifications')->insert([
            'id'              => $notifId,
            'type'            => 'test_alert',
            'notifiable_type' => 'App\Models\User',
            'notifiable_id'   => $this->repoManager->id,
            'data'            => json_encode([
                'title' => 'Security Alert',
                'link'  => route('admin.repository-manager.question-bank-validate', $bank->id),
            ]),
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        // Attempting malicious open redirect via return_url
        $response = $this->actingAs($this->repoManager)
            ->post(route('notifications.read', $notifId), [
                'return_url' => 'https://evil.com/phishing',
            ]);

        // Should NOT include evil.com in redirect target
        $response->assertRedirect(route('admin.repository-manager.question-bank-validate', $bank->id) . '?from=notifications');

        $destResponse = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.question-bank-validate', $bank->id));
        $destResponse->assertDontSee('evil.com');
    }

    /**
     * TEST 6: Legacy notification without destination context falls back safely to notifications index.
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
