<?php

namespace Tests\Feature;

use App\Models\AclAuditTrail;
use App\Models\MediaAsset;
use App\Models\RepositoryActivityLog;
use App\Models\RepositoryReviewRequest;
use App\Models\User;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TeacherMyMediaAndGovernanceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;
    protected User $otherTeacher;
    protected User $repoManager;
    protected User $regularAdmin;
    protected User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('public');

        $this->teacher = User::factory()->create(['name' => 'Teacher Jane', 'email' => 'teacher_jane@icedu.org']);
        $this->teacher->assignRole('teacher');

        $this->otherTeacher = User::factory()->create(['name' => 'Teacher Bob', 'email' => 'teacher_bob@icedu.org']);
        $this->otherTeacher->assignRole('teacher');

        $this->repoManager = User::factory()->create(['name' => 'RM Alice', 'email' => 'rm_alice@icedu.com']);
        $this->repoManager->assignRole('repository-manager');

        $this->regularAdmin = User::factory()->create(['name' => 'Admin Ken', 'email' => 'admin_ken@icedu.com']);
        $this->regularAdmin->assignRole('admin');

        $this->superAdmin = User::factory()->create(['name' => 'Super Admin Root', 'email' => 'super_root@icedu.com']);
        $this->superAdmin->assignRole('super-admin');
    }

    /**
     * 1. Teacher media upload creates working media.
     */
    public function test_teacher_media_upload_creates_working_media(): void
    {
        $file = UploadedFile::fake()->image('diagram_working.png', 400, 300);

        $response = $this->actingAs($this->teacher)->postJson(route('admin.media.store'), [
            'file'        => $file,
            'title'       => 'Working Anatomy Diagram',
            'category'    => 'Biology',
            'exam_type'   => 'general',
            'description' => 'Working copy for upcoming test',
        ]);

        $response->assertStatus(200);

        $asset = MediaAsset::where('title', 'Working Anatomy Diagram')->first();
        $this->assertNotNull($asset);
        $this->assertSame($this->teacher->id, $asset->uploaded_by);
        $this->assertSame('draft', $asset->approval_status);
        $this->assertSame('active', $asset->status);
        $this->assertSame('1.0', $asset->version);
        $this->assertTrue($asset->isWorking());
        $this->assertFalse($asset->isInstitutional());
    }

    /**
     * 2. Working media does NOT automatically appear in institutional repository.
     */
    public function test_working_media_does_not_appear_in_institutional_repository(): void
    {
        $workingAsset = MediaAsset::create([
            'title'           => 'Private Draft Audio Track',
            'original_name'   => 'private_draft.mp3',
            'filename'        => 'private_draft.mp3',
            'mime_type'       => 'audio/mpeg',
            'type'            => 'audio',
            'path'            => 'question-media/private_draft.mp3',
            'size'            => 2048,
            'status'          => 'active',
            'approval_status' => 'draft',
            'version'         => '1.0',
            'uploaded_by'     => $this->teacher->id,
        ]);

        $approvedAsset = MediaAsset::create([
            'title'           => 'Official Institutional Listening 01',
            'original_name'   => 'institutional_01.mp3',
            'filename'        => 'institutional_01.mp3',
            'mime_type'       => 'audio/mpeg',
            'type'            => 'audio',
            'path'            => 'question-media/institutional_01.mp3',
            'size'            => 4096,
            'status'          => 'active',
            'approval_status' => 'approved',
            'version'         => '1.0',
            'uploaded_by'     => $this->teacher->id,
        ]);

        // Institutional Media Repository view
        $response = $this->actingAs($this->teacher)->get(route('admin.media.index'));
        $response->assertStatus(200);
        $response->assertSee('Official Institutional Listening 01');
        $response->assertDontSee('Private Draft Audio Track');

        // Teacher My Media workspace displays working asset
        $myMediaResponse = $this->actingAs($this->teacher)->get(route('teacher.media.index'));
        $myMediaResponse->assertStatus(200);
        $myMediaResponse->assertSee('Private Draft Audio Track');
        $myMediaResponse->assertSee('Working / Draft');
    }

    /**
     * 3. Teacher can attach working media to assessment.
     */
    public function test_teacher_can_attach_working_media_to_assessment(): void
    {
        $workingAsset = MediaAsset::create([
            'title'           => 'Working Chart Diagram',
            'original_name'   => 'chart.png',
            'filename'        => 'chart.png',
            'mime_type'       => 'image/png',
            'type'            => 'image',
            'path'            => 'question-media/chart.png',
            'size'            => 1024,
            'status'          => 'active',
            'approval_status' => 'draft',
            'uploaded_by'     => $this->teacher->id,
        ]);

        $test = Test::create([
            'title'            => 'Teacher Midterm Assessment',
            'slug'             => 'teacher-midterm-test',
            'test_type'        => 'general',
            'assessment_mode'  => 'simulator',
            'status'           => 'draft',
            'duration_minutes' => 60,
            'pass_score'       => 70,
            'created_by'       => $this->teacher->id,
        ]);

        $section = TestSection::create([
            'test_id'        => $test->id,
            'title'          => 'Section 1: Data Analysis',
            'section_order'  => 1,
            'duration_minutes'=> 30,
        ]);

        // Teacher attaches working media to section
        $attachResponse = $this->actingAs($this->teacher)->post(route('teacher.tests.sections.media.attach', [$test, $section]), [
            'media_asset_id' => $workingAsset->id,
            'caption'        => 'Explanatory Chart',
        ]);

        $attachResponse->assertSessionHas('status');
        $this->assertTrue($section->mediaAssets()->where('media_assets.id', $workingAsset->id)->exists());
        $this->assertTrue($workingAsset->isUsedInAssessment());
    }

    /**
     * 4. Teacher can submit selected media for repository review.
     */
    public function test_teacher_can_submit_selected_media_for_repository_review(): void
    {
        $asset1 = MediaAsset::create([
            'title'           => 'Candidate Grammar Reading',
            'original_name'   => 'reading1.pdf',
            'filename'        => 'reading1.pdf',
            'mime_type'       => 'application/pdf',
            'type'            => 'pdf',
            'path'            => 'question-media/reading1.pdf',
            'size'            => 5000,
            'status'          => 'active',
            'approval_status' => 'draft',
            'uploaded_by'     => $this->teacher->id,
        ]);

        $asset2 = MediaAsset::create([
            'title'           => 'Candidate Listening Clip',
            'original_name'   => 'clip1.mp3',
            'filename'        => 'clip1.mp3',
            'mime_type'       => 'audio/mpeg',
            'type'            => 'audio',
            'path'            => 'question-media/clip1.mp3',
            'size'            => 15000,
            'status'          => 'active',
            'approval_status' => 'draft',
            'uploaded_by'     => $this->teacher->id,
        ]);

        $response = $this->actingAs($this->teacher)->post(route('teacher.media.submit-selected'), [
            'selected_media' => [$asset1->id, $asset2->id],
        ]);

        $response->assertRedirect(route('teacher.media.index'));
        $response->assertSessionHas('status');

        $asset1->refresh();
        $asset2->refresh();

        $this->assertSame('pending_review', $asset1->approval_status);
        $this->assertSame('pending_review', $asset2->approval_status);
    }

    /**
     * 5. Submission creates governance record.
     */
    public function test_submission_creates_governance_record(): void
    {
        $asset = MediaAsset::create([
            'title'           => 'Science Illustration',
            'original_name'   => 'science.jpg',
            'filename'        => 'science.jpg',
            'mime_type'       => 'image/jpeg',
            'type'            => 'image',
            'path'            => 'question-media/science.jpg',
            'size'            => 2048,
            'status'          => 'active',
            'approval_status' => 'draft',
            'uploaded_by'     => $this->teacher->id,
        ]);

        $this->actingAs($this->teacher)->post(route('teacher.media.submit-selected'), [
            'selected_media' => [$asset->id],
        ]);

        $this->assertDatabaseHas('repository_review_requests', [
            'resource_type' => 'MediaAsset',
            'resource_id'   => $asset->id,
            'submitted_by'  => $this->teacher->id,
            'status'        => 'pending_review',
        ]);

        $this->assertDatabaseHas('acl_audit_trails', [
            'resource_type' => 'MediaAsset',
            'resource_id'   => $asset->id,
            'action'        => 'media_submitted_for_review',
            'actor_id'      => $this->teacher->id,
        ]);
    }

    /**
     * 6. RM sees pending media.
     */
    public function test_rm_sees_pending_media(): void
    {
        $asset = MediaAsset::create([
            'title'           => 'Pending Review TOEFL Dialogue',
            'original_name'   => 'toefl_dialogue.mp3',
            'filename'        => 'toefl_dialogue.mp3',
            'mime_type'       => 'audio/mpeg',
            'type'            => 'audio',
            'path'            => 'question-media/toefl_dialogue.mp3',
            'size'            => 3072,
            'status'          => 'active',
            'approval_status' => 'pending_review',
            'uploaded_by'     => $this->teacher->id,
        ]);

        RepositoryReviewRequest::create([
            'resource_type' => 'MediaAsset',
            'resource_id'   => $asset->id,
            'submitted_by'  => $this->teacher->id,
            'status'        => 'pending_review',
            'changes_data'  => [
                'media_type' => 'audio',
                'new_title'  => 'Pending Review TOEFL Dialogue',
            ],
        ]);

        $response = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.media-approval'));
        $response->assertStatus(200);
        $response->assertSee('Pending Review TOEFL Dialogue');
        $response->assertSee('Teacher Jane');
    }

    /**
     * 7. RM can approve media.
     */
    public function test_rm_can_approve_media(): void
    {
        $asset = MediaAsset::create([
            'title'           => 'Pre-Approved Graph',
            'original_name'   => 'graph.png',
            'filename'        => 'graph.png',
            'mime_type'       => 'image/png',
            'type'            => 'image',
            'path'            => 'question-media/graph.png',
            'size'            => 1024,
            'status'          => 'active',
            'approval_status' => 'pending_review',
            'version'         => '1.0',
            'uploaded_by'     => $this->teacher->id,
        ]);

        $reviewRequest = RepositoryReviewRequest::create([
            'resource_type' => 'MediaAsset',
            'resource_id'   => $asset->id,
            'submitted_by'  => $this->teacher->id,
            'status'        => 'pending_review',
            'changes_data'  => [
                'media_type' => 'image',
                'new_title'  => 'Approved Graph',
            ],
        ]);

        $response = $this->actingAs($this->repoManager)->post(route('admin.repository-manager.media-approve', $reviewRequest), [
            'notes' => 'Quality verified according to institutional guidelines.',
        ]);

        $response->assertRedirect(route('admin.repository-manager.media-approval'));
        $asset->refresh();
        $this->assertSame('approved', $asset->approval_status);
        $this->assertSame('published', $asset->status);
        $this->assertTrue($asset->isInstitutional());
    }

    /**
     * 8. Approved media appears in institutional repository.
     */
    public function test_approved_media_appears_in_institutional_repository(): void
    {
        $asset = MediaAsset::create([
            'title'           => 'Now Institutional Listening File',
            'original_name'   => 'listen_inst.mp3',
            'filename'        => 'listen_inst.mp3',
            'mime_type'       => 'audio/mpeg',
            'type'            => 'audio',
            'path'            => 'question-media/listen_inst.mp3',
            'size'            => 1024,
            'status'          => 'published',
            'approval_status' => 'approved',
            'uploaded_by'     => $this->teacher->id,
        ]);

        $response = $this->actingAs($this->teacher)->get(route('admin.media.index'));
        $response->assertStatus(200);
        $response->assertSee('Now Institutional Listening File');
    }

    /**
     * 9. RM can request revision.
     */
    public function test_rm_can_request_revision(): void
    {
        $asset = MediaAsset::create([
            'title'           => 'Low Resolution Map',
            'original_name'   => 'map_low.jpg',
            'filename'        => 'map_low.jpg',
            'mime_type'       => 'image/jpeg',
            'type'            => 'image',
            'path'            => 'question-media/map_low.jpg',
            'size'            => 1024,
            'status'          => 'active',
            'approval_status' => 'pending_review',
            'uploaded_by'     => $this->teacher->id,
        ]);

        $reviewRequest = RepositoryReviewRequest::create([
            'resource_type' => 'MediaAsset',
            'resource_id'   => $asset->id,
            'submitted_by'  => $this->teacher->id,
            'status'        => 'pending_review',
            'changes_data'  => [
                'media_type' => 'image',
                'new_title'  => 'Low Resolution Map',
            ],
        ]);

        $response = $this->actingAs($this->repoManager)->post(route('admin.repository-manager.media-revision', $reviewRequest), [
            'notes' => 'Please upload a higher-contrast scan of the map legend.',
        ]);

        $response->assertRedirect(route('admin.repository-manager.media-approval'));
        $asset->refresh();
        $this->assertSame('revision_requested', $asset->approval_status);
        $this->assertTrue($asset->isRevisionRequested());
        $this->assertFalse($asset->isInstitutional());
    }

    /**
     * 10. Revision status returns to Teacher workspace.
     */
    public function test_revision_status_returns_to_teacher_workspace(): void
    {
        $asset = MediaAsset::create([
            'title'           => 'Map Needing Legend Revision',
            'original_name'   => 'map.jpg',
            'filename'        => 'map.jpg',
            'mime_type'       => 'image/jpeg',
            'type'            => 'image',
            'path'            => 'question-media/map.jpg',
            'size'            => 1024,
            'status'          => 'active',
            'approval_status' => 'revision_requested',
            'uploaded_by'     => $this->teacher->id,
        ]);

        RepositoryReviewRequest::create([
            'resource_type' => 'MediaAsset',
            'resource_id'   => $asset->id,
            'submitted_by'  => $this->teacher->id,
            'status'        => 'revision_requested',
            'review_notes'  => 'Please improve clarity of legend markers.',
        ]);

        $response = $this->actingAs($this->teacher)->get(route('teacher.media.index'));
        $response->assertStatus(200);
        $response->assertSee('Map Needing Legend Revision');
        $response->assertSee('Revision Requested');
        $response->assertSee('Please improve clarity of legend markers.');
    }

    /**
     * 11. RM can reject media.
     */
    public function test_rm_can_reject_media(): void
    {
        $asset = MediaAsset::create([
            'title'           => 'Non-Compliant Commercial Audio',
            'original_name'   => 'commercial.mp3',
            'filename'        => 'commercial.mp3',
            'mime_type'       => 'audio/mpeg',
            'type'            => 'audio',
            'path'            => 'question-media/commercial.mp3',
            'size'            => 1024,
            'status'          => 'active',
            'approval_status' => 'pending_review',
            'uploaded_by'     => $this->teacher->id,
        ]);

        $reviewRequest = RepositoryReviewRequest::create([
            'resource_type' => 'MediaAsset',
            'resource_id'   => $asset->id,
            'submitted_by'  => $this->teacher->id,
            'status'        => 'pending_review',
            'changes_data'  => [
                'media_type' => 'audio',
                'new_title'  => 'Non-Compliant Commercial Audio',
            ],
        ]);

        $response = $this->actingAs($this->repoManager)->post(route('admin.repository-manager.media-reject', $reviewRequest), [
            'notes' => 'Asset violates copyright guidelines.',
        ]);

        $response->assertRedirect(route('admin.repository-manager.media-approval'));
        $asset->refresh();
        $this->assertSame('rejected', $asset->approval_status);
        $this->assertTrue($asset->isRejected());
    }

    /**
     * 12. Rejected media does not become institutional.
     */
    public function test_rejected_media_does_not_become_institutional(): void
    {
        $asset = MediaAsset::create([
            'title'           => 'Rejected Material Asset',
            'original_name'   => 'rejected.png',
            'filename'        => 'rejected.png',
            'mime_type'       => 'image/png',
            'type'            => 'image',
            'path'            => 'question-media/rejected.png',
            'size'            => 1024,
            'status'          => 'active',
            'approval_status' => 'rejected',
            'uploaded_by'     => $this->teacher->id,
        ]);

        $response = $this->actingAs($this->teacher)->get(route('admin.media.index'));
        $response->assertStatus(200);
        $response->assertDontSee('Rejected Material Asset');
    }

    /**
     * 13. Existing media assets remain intact.
     */
    public function test_existing_media_assets_remain_intact(): void
    {
        $existing = MediaAsset::create([
            'title'           => 'Legacy Approved Listening Audio',
            'original_name'   => 'legacy_audio.mp3',
            'filename'        => 'legacy_audio.mp3',
            'mime_type'       => 'audio/mpeg',
            'type'            => 'audio',
            'path'            => 'question-media/legacy_audio.mp3',
            'size'            => 2048,
            'status'          => 'published',
            'approval_status' => 'approved',
            'version'         => '1.0',
            'uploaded_by'     => $this->teacher->id,
        ]);

        $response = $this->actingAs($this->teacher)->get(route('admin.media.index'));
        $response->assertStatus(200);
        $response->assertSee('Legacy Approved Listening Audio');
    }

    /**
     * 14. No physical file duplication occurs.
     */
    public function test_no_physical_file_duplication_occurs(): void
    {
        $initialPath = 'question-media/single_file_test.png';
        $asset = MediaAsset::create([
            'title'           => 'Single Storage Copy Asset',
            'original_name'   => 'single_file.png',
            'filename'        => 'single_file.png',
            'mime_type'       => 'image/png',
            'type'            => 'image',
            'path'            => $initialPath,
            'size'            => 1024,
            'status'          => 'active',
            'approval_status' => 'pending_review',
            'uploaded_by'     => $this->teacher->id,
        ]);

        $reviewRequest = RepositoryReviewRequest::create([
            'resource_type' => 'MediaAsset',
            'resource_id'   => $asset->id,
            'submitted_by'  => $this->teacher->id,
            'status'        => 'pending_review',
            'changes_data'  => ['new_title' => 'Single Storage Copy Asset'],
        ]);

        $this->actingAs($this->repoManager)->post(route('admin.repository-manager.media-approve', $reviewRequest), [
            'notes' => 'Approved into repository.',
        ]);

        $asset->refresh();
        $this->assertSame($initialPath, $asset->path);
    }

    /**
     * 15. Unauthorized roles cannot approve media.
     */
    public function test_unauthorized_roles_cannot_approve_media(): void
    {
        $asset = MediaAsset::create([
            'title'           => 'Restricted Approval Test',
            'original_name'   => 'restricted.png',
            'filename'        => 'restricted.png',
            'mime_type'       => 'image/png',
            'type'            => 'image',
            'path'            => 'question-media/restricted.png',
            'size'            => 1024,
            'status'          => 'active',
            'approval_status' => 'pending_review',
            'uploaded_by'     => $this->teacher->id,
        ]);

        $reviewRequest = RepositoryReviewRequest::create([
            'resource_type' => 'MediaAsset',
            'resource_id'   => $asset->id,
            'submitted_by'  => $this->teacher->id,
            'status'        => 'pending_review',
        ]);

        // Regular Admin attempt -> 403 Forbidden
        $adminResponse = $this->actingAs($this->regularAdmin)->post(route('admin.repository-manager.media-approve', $reviewRequest));
        $adminResponse->assertStatus(403);

        // Teacher attempt -> 403 Forbidden
        $teacherResponse = $this->actingAs($this->teacher)->post(route('admin.repository-manager.media-approve', $reviewRequest));
        $teacherResponse->assertStatus(403);

        $asset->refresh();
        $this->assertSame('pending_review', $asset->approval_status);
    }

    /**
     * 16. Audit log records governance actions.
     */
    public function test_audit_log_records_governance_actions(): void
    {
        $asset = MediaAsset::create([
            'title'           => 'Audit Trail Media Asset',
            'original_name'   => 'audit.jpg',
            'filename'        => 'audit.jpg',
            'mime_type'       => 'image/jpeg',
            'type'            => 'image',
            'path'            => 'question-media/audit.jpg',
            'size'            => 1024,
            'status'          => 'active',
            'approval_status' => 'pending_review',
            'uploaded_by'     => $this->teacher->id,
        ]);

        $reviewRequest = RepositoryReviewRequest::create([
            'resource_type' => 'MediaAsset',
            'resource_id'   => $asset->id,
            'submitted_by'  => $this->teacher->id,
            'status'        => 'pending_review',
            'changes_data'  => ['new_title' => 'Audit Trail Media Asset'],
        ]);

        $this->actingAs($this->repoManager)->post(route('admin.repository-manager.media-approve', $reviewRequest), [
            'notes' => 'Passed full QA standards check.',
        ]);

        $this->assertDatabaseHas('repository_activity_logs', [
            'resource_type' => 'MediaAsset',
            'resource_id'   => $asset->id,
            'action'        => 'approved',
            'reviewer_id'   => $this->repoManager->id,
        ]);

        $this->assertDatabaseHas('acl_audit_trails', [
            'resource_type' => 'MediaAsset',
            'resource_id'   => $asset->id,
            'action'        => 'approved',
            'approver_id'   => $this->repoManager->id,
        ]);
    }
}
