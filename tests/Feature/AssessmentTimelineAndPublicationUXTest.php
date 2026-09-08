<?php

namespace Tests\Feature;

use App\Models\RepositoryActivityLog;
use App\Models\User;
use App\Modules\Assessment\Models\Test as AssessmentTest;
use App\Modules\Assessment\Models\TestQuestion;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Modules\QuestionBank\Models\QuestionChoice;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentTimelineAndPublicationUXTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;
    protected User $repoManager;
    protected QuestionBank $bank;
    protected AssessmentTest $test;
    protected Question $q1;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->teacher = User::factory()->create([
            'name'   => 'Dr. Timeline Teacher',
            'email'  => 'teacher_timeline@icedu.org',
            'status' => 'active',
        ]);
        $this->teacher->assignRole('teacher');

        $this->repoManager = User::factory()->create([
            'name'   => 'Repository Manager Timeline',
            'email'  => 'rm_timeline@icedu.org',
            'status' => 'active',
        ]);
        $this->repoManager->assignRole('repository-manager');

        $this->bank = QuestionBank::create([
            'title'      => 'Timeline Test Bank',
            'slug'       => 'timeline-test-bank',
            'test_type'  => 'toeic',
            'status'     => 'published',
            'created_by' => $this->teacher->id,
        ]);

        $this->test = AssessmentTest::create([
            'title'            => 'Timeline Verification Assessment',
            'slug'             => 'timeline-verification-assessment',
            'test_type'        => 'toeic',
            'status'           => 'pending_approval',
            'duration_minutes' => 60,
            'pass_score'       => 70,
            'created_by'       => $this->teacher->id,
        ]);

        $section = TestSection::create([
            'test_id' => $this->test->id,
            'title'   => 'Reading Comprehension',
            'order'   => 1,
        ]);

        $this->q1 = Question::create([
            'question_bank_id' => $this->bank->id,
            'prompt'           => 'Timeline test question prompt?',
            'question_type'    => 'multiple_choice',
            'difficulty'       => 'medium',
        ]);

        QuestionChoice::create([
            'question_id' => $this->q1->id,
            'label'       => 'A',
            'content'     => 'Choice A',
            'is_correct'  => true,
            'order'       => 1,
        ]);

        QuestionChoice::create([
            'question_id' => $this->q1->id,
            'label'       => 'B',
            'content'     => 'Choice B',
            'is_correct'  => false,
            'order'       => 2,
        ]);

        TestQuestion::create([
            'test_section_id' => $section->id,
            'question_id'     => $this->q1->id,
            'order'           => 1,
        ]);
    }

    /**
     * TEST A01: approved + false renders Approved completed.
     */
    public function test_a01_approved_false_renders_approved_completed(): void
    {
        $this->test->update([
            'status'       => 'approved',
            'is_published' => false,
        ]);

        RepositoryActivityLog::create([
            'resource_type' => 'Test',
            'resource_id'   => (string) $this->test->id,
            'actor_id'      => $this->repoManager->id,
            'reviewer_id'   => $this->repoManager->id,
            'action'        => 'approved',
            'approval_note' => 'Governance approval accepted.',
        ]);

        $res = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $res->assertStatus(200);

        // Timeline renders Approved completed
        $res->assertSee('Approved');
        $res->assertSee('Governance accepted, ready for publication.');
    }

    /**
     * TEST A02: approved + false does NOT render Published completed.
     */
    public function test_a02_approved_false_does_not_render_published_completed(): void
    {
        $this->test->update([
            'status'       => 'approved',
            'is_published' => false,
        ]);

        $res = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $res->assertStatus(200);

        // Published is in pending state, not completed
        $res->assertSee('Pending / not yet published');
        $res->assertDontSee('Live and available to candidates');
    }

    /**
     * TEST A03: approved + false does NOT display "Approved & Live".
     */
    public function test_a03_approved_false_does_not_display_approved_and_live(): void
    {
        $this->test->update([
            'status'       => 'approved',
            'is_published' => false,
        ]);

        $res = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $res->assertStatus(200);

        // Must not equate Approved to Live
        $res->assertDontSee('Approved & Live', false);
        $res->assertDontSee('Approved &amp; Live', false);
        $res->assertDontSee('🟢 Published & Live', false);
        $res->assertSee('✅ Approved');
    }

    /**
     * TEST A04: published + true renders Approved + Published completed.
     */
    public function test_a04_published_true_renders_approved_and_published_completed(): void
    {
        $this->test->update([
            'status'       => 'published',
            'is_published' => true,
        ]);

        RepositoryActivityLog::create([
            'resource_type' => 'Test',
            'resource_id'   => (string) $this->test->id,
            'actor_id'      => $this->repoManager->id,
            'reviewer_id'   => $this->repoManager->id,
            'action'        => 'approved',
            'approval_note' => 'Governance approval accepted.',
        ]);

        RepositoryActivityLog::create([
            'resource_type' => 'Test',
            'resource_id'   => (string) $this->test->id,
            'actor_id'      => $this->repoManager->id,
            'reviewer_id'   => $this->repoManager->id,
            'action'        => 'published',
            'approval_note' => 'Assessment published live.',
        ]);

        $res = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $res->assertStatus(200);

        $res->assertSee('Live and available to candidates');
        $res->assertSee('🟢 Published & Live', false);
    }

    /**
     * TEST A05: unpublished approved + false does not claim currently Live.
     */
    public function test_a05_unpublished_approved_false_does_not_claim_currently_live(): void
    {
        $this->test->update([
            'status'       => 'approved',
            'is_published' => false,
        ]);

        RepositoryActivityLog::create([
            'resource_type' => 'Test',
            'resource_id'   => (string) $this->test->id,
            'actor_id'      => $this->repoManager->id,
            'reviewer_id'   => $this->repoManager->id,
            'action'        => 'published',
            'approval_note' => 'Initial publish',
        ]);

        RepositoryActivityLog::create([
            'resource_type' => 'Test',
            'resource_id'   => (string) $this->test->id,
            'actor_id'      => $this->repoManager->id,
            'reviewer_id'   => $this->repoManager->id,
            'action'        => 'unpublished',
            'approval_note' => 'Unpublished by RM',
        ]);

        $res = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $res->assertStatus(200);

        $res->assertSee('Currently unpublished / not live');
        $res->assertDontSee('🟢 Published & Live', false);
        $res->assertSee('✅ Approved');
    }

    /**
     * TEST A06: timeline does not falsely mark Needs Revision if that lifecycle event did not occur.
     */
    public function test_a06_timeline_does_not_falsely_mark_needs_revision(): void
    {
        $this->test->update([
            'status'       => 'approved',
            'is_published' => false,
        ]);

        $res = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $res->assertStatus(200);

        // Timeline must not contain Needs Revision milestone if never flagged
        $res->assertDontSee('Needs Revision');
    }

    /**
     * TEST A07: after approval Assessment leaves Pending Approval Queue.
     */
    public function test_a07_after_approval_assessment_leaves_pending_approval_queue(): void
    {
        // Approve via RM
        $this->actingAs($this->repoManager)->post(route('admin.repository-manager.assessment-approve', $this->test->id), [
            'notes' => 'Passed inspection.',
        ]);

        $this->test->refresh();
        $this->assertEquals('approved', $this->test->status);
        $this->assertFalse((bool) $this->test->is_published);

        // Pending queue does not list it
        $resPending = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.assessment-approval', ['status' => 'pending']));
        $resPending->assertDontSee($this->test->title);
    }

    /**
     * TEST A08: after approval Assessment enters Ready for Publication surface.
     */
    public function test_a08_after_approval_assessment_enters_ready_for_publication_surface(): void
    {
        $this->test->update([
            'status'       => 'approved',
            'is_published' => false,
        ]);

        // Ready for publication filter in assessment approval
        $res = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.assessment-approval', ['status' => 'ready_for_publication']));
        $res->assertStatus(200);
        $res->assertSee($this->test->title);

        // Canonical Publication Queue
        $resPub = $this->actingAs($this->repoManager)->get(route('admin.publications.assessments', ['status' => 'approved']));
        $resPub->assertStatus(200);
        $resPub->assertSee($this->test->title);
    }

    /**
     * TEST A09: RM has a visible path to Ready for Publication.
     */
    public function test_a09_rm_has_visible_path_to_ready_for_publication(): void
    {
        $this->test->update([
            'status'       => 'approved',
            'is_published' => false,
        ]);

        // Command Center exposes Assessment Governance CTA
        $resDash = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.dashboard'));
        $resDash->assertStatus(200);
        $resDash->assertSee('Assessment Governance');
        $resDash->assertSee(route('admin.repository-manager.assessment-governance'));

        // Assessment Governance queue exposes Ready to Publish tab
        $resApproval = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.assessment-approval'));
        $resApproval->assertStatus(200);
        $resApproval->assertSee('Ready to Publish');
    }

    /**
     * TEST A10: approved Assessment has Publish Assessment CTA.
     */
    public function test_a10_approved_assessment_has_publish_assessment_cta(): void
    {
        $this->test->update([
            'status'       => 'approved',
            'is_published' => false,
        ]);

        // In RM Assessment Review workspace -> redirects to publications center
        $resReview = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.assessment-review', $this->test->id));
        $resReview->assertRedirect(route('admin.publications.assessments', ['status' => 'approved', 'highlight' => $this->test->id]));

        // In Assessment Approval Queue
        $resQueue = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.assessment-approval', ['status' => 'ready_for_publication']));
        $resQueue->assertStatus(200);
        $resQueue->assertSee('Publish');
        $resQueue->assertSee(route('admin.publications.assessments.publish', $this->test->id));
    }

    /**
     * TEST A11: Publish CTA uses canonical publication operation.
     */
    public function test_a11_publish_cta_uses_canonical_publication_operation(): void
    {
        $this->test->update([
            'status'       => 'approved',
            'is_published' => false,
        ]);

        // POST to canonical publish route
        $res = $this->actingAs($this->repoManager)->post(route('admin.publications.assessments.publish', $this->test->id));
        $res->assertRedirect();

        $this->test->refresh();
        $this->assertEquals('published', $this->test->status);
        $this->assertTrue((bool) $this->test->is_published);

        // Verify ActivityLog & RepositoryActivityLog recorded
        $this->assertDatabaseHas('repository_activity_logs', [
            'resource_type' => 'Test',
            'resource_id'   => (string) $this->test->id,
            'action'        => 'published',
        ]);
    }

    /**
     * TEST A12: published Assessment disappears from Ready for Publication.
     */
    public function test_a12_published_assessment_disappears_from_ready_for_publication(): void
    {
        $this->test->update([
            'status'       => 'published',
            'is_published' => true,
        ]);

        $resReady = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.assessment-approval', ['status' => 'ready_for_publication']));
        $resReady->assertDontSee($this->test->title);

        $resPubQueue = $this->actingAs($this->repoManager)->get(route('admin.publications.assessments', ['status' => 'approved']));
        $resPubQueue->assertDontSee($this->test->title);
    }

    /**
     * TEST A13: published Assessment exposes Unpublish.
     */
    public function test_a13_published_assessment_exposes_unpublish(): void
    {
        $this->test->update([
            'status'       => 'published',
            'is_published' => true,
        ]);

        // In RM Review workspace -> redirects to publications center
        $resReview = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.assessment-review', $this->test->id));
        $resReview->assertRedirect(route('admin.publications.assessments', ['status' => 'published', 'highlight' => $this->test->id]));

        // In Publication Queue (published tab)
        $resPub = $this->actingAs($this->repoManager)->get(route('admin.publications.assessments', ['tab' => 'published']));
        $resPub->assertStatus(200);
        $resPub->assertSee('Unpublish');
        $resPub->assertSee(route('admin.publications.assessments.unpublish', $this->test->id));
    }

    /**
     * TEST A14: unpublished Assessment returns to Ready for Publication.
     */
    public function test_a14_unpublished_assessment_returns_to_ready_for_publication(): void
    {
        $this->test->update([
            'status'       => 'published',
            'is_published' => true,
        ]);

        // Unpublish via canonical operation
        $res = $this->actingAs($this->repoManager)->post(route('admin.publications.assessments.unpublish', $this->test->id));
        $res->assertRedirect();

        $this->test->refresh();
        $this->assertEquals('approved', $this->test->status);
        $this->assertFalse((bool) $this->test->is_published);

        // Verify RepositoryActivityLog recorded
        $this->assertDatabaseHas('repository_activity_logs', [
            'resource_type' => 'Test',
            'resource_id'   => (string) $this->test->id,
            'action'        => 'unpublished',
        ]);

        // Returns to Ready for Publication
        $resReady = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.assessment-approval', ['status' => 'ready_for_publication']));
        $resReady->assertSee($this->test->title);

        $resPubQueue = $this->actingAs($this->repoManager)->get(route('admin.publications.assessments', ['status' => 'approved']));
        $resPubQueue->assertSee($this->test->title);
    }
}
