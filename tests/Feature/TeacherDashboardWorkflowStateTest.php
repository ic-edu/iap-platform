<?php

namespace Tests\Feature;

use App\Models\RepositoryRevisionRequest;
use App\Models\User;
use App\Modules\QuestionBank\Models\QuestionBank;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use Tests\TestCase;

class TeacherDashboardWorkflowStateTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;
    protected User $repoManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->teacher = User::factory()->create([
            'name'   => 'Teacher Workflow State Test',
            'email'  => 'teacher_wf_state@icedu.org',
            'status' => 'active',
        ]);
        $this->teacher->assignRole('teacher');

        $this->repoManager = User::factory()->create([
            'name'   => 'Repository Manager Governance',
            'email'  => 'repomanager_gov_wf@icedu.org',
            'status' => 'active',
        ]);
        $this->repoManager->assignRole('repository-manager');
    }

    /**
     * ACCEPTANCE TEST 1: Teacher with Awaiting Approval = 1, Repository Revision = 1, Drafts = 0.
     * Expectation: "You're all caught up" MUST NOT appear. Work requiring attention MUST appear.
     */
    public function test_1_dashboard_does_not_show_all_caught_up_when_active_revisions_or_approvals_exist()
    {
        // 1. Create Question Bank 1: Awaiting Approval = 1
        $submittedBank = QuestionBank::create([
            'title'       => 'TOEIC Listening Bank Submitted',
            'slug'        => 'toeic-listening-bank-submitted',
            'test_type'   => 'toeic',
            'status'      => 'pending_approval',
            'created_by'  => $this->teacher->id,
            'description' => 'Awaiting approval bank',
        ]);

        // 2. Create Question Bank 2: Needs Revision / Repository Revision = 1
        $revisionBank = QuestionBank::create([
            'title'       => 'TOEIC Reading Bank Needs Revision',
            'slug'        => 'toeic-reading-bank-needs-revision',
            'test_type'   => 'toeic',
            'status'      => 'needs_revision',
            'created_by'  => $this->teacher->id,
            'description' => 'Needs revision bank',
        ]);

        RepositoryRevisionRequest::create([
            'question_bank_id' => $revisionBank->id,
            'teacher_id'       => $this->teacher->id,
            'requested_by_id'  => $this->repoManager->id,
            'status'           => 'OPEN',
            'notes'            => 'Please revise option choice labels.',
        ]);

        // 3. Render Teacher Dashboard
        $response = $this->actingAs($this->teacher)->get(route('teacher.dashboard'));

        $response->assertStatus(200);

        // MUST NOT see "You're all caught up" or "No unfinished authoring work"
        $response->assertDontSee("You're all caught up");
        $response->assertDontSee('No unfinished authoring work');

        // MUST see attention header & cards
        $response->assertSee('You have work requiring attention');
        $response->assertSee('Repository Revisions');
        $response->assertSee('Awaiting Approval');
    }

    /**
     * ACCEPTANCE TEST 2: Teacher with Awaiting Approval = 0, Repository Revision = 0, Drafts = 0.
     * Expectation: "You're all caught up" MUST appear.
     */
    public function test_2_dashboard_shows_all_caught_up_only_when_no_active_tasks_exist()
    {
        // No banks or all published
        QuestionBank::create([
            'title'       => 'TOEIC Published Bank',
            'slug'        => 'toeic-published-bank',
            'test_type'   => 'toeic',
            'status'      => 'published',
            'is_published'=> true,
            'created_by'  => $this->teacher->id,
            'description' => 'Published bank',
        ]);

        $response = $this->actingAs($this->teacher)->get(route('teacher.dashboard'));

        $response->assertStatus(200);
        $response->assertSee("You're all caught up", false);
        $response->assertSee('No unfinished authoring work');
    }

    /**
     * ACCEPTANCE TEST 3: Non-redundant UI rule — Hero CTA "+ New Question Bank" is retained as primary action; empty-state panel does NOT render duplicate "+ Create Question Bank" button.
     */
    public function test_3_empty_state_panel_does_not_contain_duplicate_create_cta()
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.dashboard'));

        $response->assertStatus(200);

        // Hero CTA is rendered as primary action
        $response->assertSee('＋ New Question Bank');
        $response->assertSee('openCreateModal()');

        // Empty-state status text is retained
        $response->assertSee("You're all caught up", false);
        $response->assertSee('No unfinished authoring work');

        // Duplicate empty-state CTA is ELIMINATED
        $response->assertDontSee('＋ Create Question Bank');
    }

    /**
     * ACCEPTANCE TEST 4: Hero does not render redundant notification badge; retains published badge and navbar bell.
     */
    public function test_4_hero_does_not_render_notification_badge_and_retains_published_badge()
    {
        // 1. Create published bank for teacher
        QuestionBank::create([
            'title'        => 'TOEIC Published Bank',
            'slug'         => 'toeic-published-bank-test',
            'test_type'    => 'toeic',
            'status'       => 'published',
            'is_published' => true,
            'created_by'   => $this->teacher->id,
            'description'  => 'Published bank',
        ]);

        // 2. Create unread notification for teacher
        \Illuminate\Support\Facades\DB::table('notifications')->insert([
            'id'              => (string) \Illuminate\Support\Str::uuid(),
            'type'            => 'App\Notifications\GenericNotification',
            'notifiable_type' => 'App\Models\User',
            'notifiable_id'   => $this->teacher->id,
            'data'            => json_encode(['title' => 'Test Notification', 'message' => 'You have an update.']),
            'read_at'         => null,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $response = $this->actingAs($this->teacher)->get(route('teacher.dashboard'));

        $response->assertStatus(200);

        // 1. Published badge still renders in hero
        $response->assertSee('🟢 1 Published');

        // 2. Hero no longer renders "Unread" notification badge
        $response->assertDontSee('🔔 1 Unread');
        $response->assertDontSee('class="tw-hero__pill tw-hero__pill--rose"', false);

        // 3. Navbar notification bell continues to function
        $response->assertSee('id="notifications-bell-btn"', false);
        $response->assertSee('id="notif-badge-dot"', false);
    }
}

