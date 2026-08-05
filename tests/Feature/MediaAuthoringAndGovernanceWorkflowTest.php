<?php

namespace Tests\Feature;

use App\Models\AclAuditTrail;
use App\Models\AclVersion;
use App\Models\MediaAsset;
use App\Models\RepositoryReviewRequest;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class MediaAuthoringAndGovernanceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;
    protected User $repoManager;
    protected User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->teacher = User::factory()->create(['name' => 'Teacher User', 'email' => 'teacher_test@icedu.org']);
        $this->teacher->assignRole('teacher');

        $this->repoManager = User::factory()->create(['name' => 'Repo Manager', 'email' => 'repomanager_test@icedu.com']);
        $this->repoManager->assignRole('repository-manager');

        $this->superAdmin = User::factory()->create(['name' => 'Super Admin User', 'email' => 'superadmin_test@icedu.org']);
        $this->superAdmin->assignRole('super-admin');
    }

    public function test_teacher_can_access_media_repository_index_with_action_buttons()
    {
        $media = MediaAsset::create([
            'title'           => 'Sample TOEFL Diagram',
            'original_name'   => 'toefl_diagram.jpg',
            'filename'        => 'toefl_diagram.jpg',
            'mime_type'       => 'image/jpeg',
            'type'            => 'image',
            'path'            => 'images/toefl_diagram.jpg',
            'size'            => 1024,
            'status'          => 'active',
            'approval_status' => 'approved',
            'version'         => '1.0',
            'uploaded_by'     => $this->teacher->id,
        ]);

        $response = $this->actingAs($this->teacher)->get(route('admin.media.index'));

        $response->assertStatus(200);
        $response->assertSee('Sample TOEFL Diagram');
        $response->assertSee(route('admin.media.edit', $media->id));
        $response->assertSee(route('admin.media.versions', $media->id));
    }

    public function test_teacher_can_open_media_editor_with_structured_sections()
    {
        $media = MediaAsset::create([
            'title'           => 'TOEFL Listening Lecture Track',
            'original_name'   => 'lecture.wav',
            'filename'        => 'lecture.wav',
            'mime_type'       => 'audio/wav',
            'type'            => 'audio',
            'path'            => 'media/lecture.wav',
            'size'            => 2048,
            'status'          => 'active',
            'approval_status' => 'approved',
            'version'         => '1.0',
            'uploaded_by'     => $this->teacher->id,
        ]);

        $response = $this->actingAs($this->teacher)->get(route('admin.media.edit', $media->id));

        $response->assertStatus(200);
        $response->assertSee('General Information');
        $response->assertSee('Governance Information');
        $response->assertSee('Reusable Slot Mapping');
    }

    public function test_passage_editor_supports_text_image_and_hybrid_modes()
    {
        $passage = MediaAsset::create([
            'title'           => 'IELTS Reading Passage Vol 1',
            'original_name'   => 'passage1.txt',
            'filename'        => 'passage1.txt',
            'mime_type'       => 'text/plain',
            'type'            => 'passage',
            'path'            => 'media/passage1.txt',
            'size'            => 512,
            'status'          => 'active',
            'approval_status' => 'approved',
            'version'         => '1.0',
            'content_text'    => 'Paragraph A: Geothermal energy systems extract steam...',
            'uploaded_by'     => $this->teacher->id,
        ]);

        $response = $this->actingAs($this->teacher)->get(route('admin.media.edit', $passage->id));

        $response->assertStatus(200);
        $response->assertSee('Passage Editor (Format Selector)');
        $response->assertSee('Hybrid (Text + Image)');
    }

    public function test_teacher_saving_draft_creates_working_copy_without_modifying_published_version()
    {
        $media = MediaAsset::create([
            'title'           => 'Original Published Title v1.0',
            'original_name'   => 'original.jpg',
            'filename'        => 'original.jpg',
            'mime_type'       => 'image/jpeg',
            'type'            => 'image',
            'path'            => 'images/original.jpg',
            'size'            => 1024,
            'status'          => 'active',
            'approval_status' => 'approved',
            'version'         => '1.0',
            'uploaded_by'     => $this->teacher->id,
        ]);

        $response = $this->actingAs($this->teacher)->put(route('admin.media.update', $media->id), [
            'title'       => 'Updated Working Copy Draft Title',
            'description' => 'Draft description update',
            'category'    => 'TOEFL Images',
            'exam_type'   => 'toefl',
        ]);

        $response->assertRedirect(route('admin.media.edit', $media->id));
        $media->refresh();

        $this->assertEquals('Updated Working Copy Draft Title', $media->title);
        $this->assertEquals('draft', $media->approval_status);
        $this->assertEquals('1.0', $media->version);

        $this->assertDatabaseHas('acl_audit_trails', [
            'resource_id' => $media->id,
            'action'      => 'saved_draft',
        ]);
    }

    public function test_teacher_submitting_for_review_creates_pending_review_request()
    {
        $media = MediaAsset::create([
            'title'           => 'Draft Asset Candidate',
            'original_name'   => 'candidate.jpg',
            'filename'        => 'candidate.jpg',
            'mime_type'       => 'image/jpeg',
            'type'            => 'image',
            'path'            => 'images/candidate.jpg',
            'size'            => 1024,
            'status'          => 'active',
            'approval_status' => 'draft',
            'version'         => '1.0',
            'uploaded_by'     => $this->teacher->id,
        ]);

        $response = $this->actingAs($this->teacher)->post(route('admin.media.submit-review', $media->id), [
            'reason' => 'Ready for institutional publication',
        ]);

        $response->assertRedirect(route('admin.media.edit', $media->id));

        $media->refresh();
        $this->assertEquals('pending_review', $media->approval_status);

        $this->assertDatabaseHas('repository_review_requests', [
            'resource_type' => 'MediaAsset',
            'resource_id'   => $media->id,
            'status'        => 'pending_review',
        ]);

        $this->assertDatabaseHas('acl_audit_trails', [
            'resource_id' => $media->id,
            'action'      => 'submitted_for_review',
        ]);
    }

    public function test_repository_manager_approving_increments_version_number()
    {
        $media = MediaAsset::create([
            'title'           => 'Pre-Review Asset v1.0',
            'original_name'   => 'asset.jpg',
            'filename'        => 'asset.jpg',
            'mime_type'       => 'image/jpeg',
            'type'            => 'image',
            'path'            => 'images/asset.jpg',
            'size'            => 1024,
            'status'          => 'active',
            'approval_status' => 'pending_review',
            'version'         => 'v1.0',
            'uploaded_by'     => $this->teacher->id,
        ]);

        $reviewRequest = RepositoryReviewRequest::create([
            'resource_type' => 'MediaAsset',
            'resource_id'   => $media->id,
            'submitted_by'  => $this->teacher->id,
            'status'        => 'pending_review',
            'changes_data'  => [
                'old_data'  => ['title' => 'Pre-Review Asset v1.0'],
                'new_title' => 'Approved Institutional Asset v1.1',
            ],
        ]);

        $response = $this->actingAs($this->repoManager)->post(route('admin.repository-manager.media-approve', $reviewRequest->id), [
            'notes' => 'Quality assurance audit passed.',
        ]);

        $response->assertRedirect(route('admin.repository-manager.media-approval'));

        $media->refresh();
        $this->assertEquals('Approved Institutional Asset v1.1', $media->title);
        $this->assertEquals('v1.1', $media->version);
        $this->assertEquals('approved', $media->approval_status);

        $this->assertDatabaseHas('acl_versions', [
            'resource_id'    => $media->id,
            'version_number' => 'v1.1',
            'is_current'     => true,
        ]);
    }

    public function test_rejected_revision_does_not_overwrite_published_version()
    {
        $media = MediaAsset::create([
            'title'           => 'Stable Published Asset v1.0',
            'original_name'   => 'stable.jpg',
            'filename'        => 'stable.jpg',
            'mime_type'       => 'image/jpeg',
            'type'            => 'image',
            'path'            => 'images/stable.jpg',
            'size'            => 1024,
            'status'          => 'active',
            'approval_status' => 'pending_review',
            'version'         => '1.0',
            'uploaded_by'     => $this->teacher->id,
        ]);

        $reviewRequest = RepositoryReviewRequest::create([
            'resource_type' => 'MediaAsset',
            'resource_id'   => $media->id,
            'submitted_by'  => $this->teacher->id,
            'status'        => 'pending_review',
            'changes_data'  => [
                'new_title' => 'Flawed Candidate Title',
            ],
        ]);

        $response = $this->actingAs($this->repoManager)->post(route('admin.repository-manager.media-reject', $reviewRequest->id), [
            'notes' => 'Fails institutional clarity guidelines.',
        ]);

        $response->assertRedirect(route('admin.repository-manager.media-approval'));

        $media->refresh();
        $this->assertEquals('Stable Published Asset v1.0', $media->title);
        $this->assertEquals('1.0', $media->version);
        $this->assertEquals('rejected', $media->approval_status);
    }

    public function test_direct_download_blocked_for_teacher_and_repository_manager()
    {
        $media = MediaAsset::create([
            'title'           => 'Protected Document',
            'original_name'   => 'protected.pdf',
            'filename'        => 'protected.pdf',
            'mime_type'       => 'application/pdf',
            'type'            => 'pdf',
            'path'            => 'media/protected.pdf',
            'size'            => 2048,
            'status'          => 'active',
            'approval_status' => 'approved',
            'version'         => '1.0',
            'uploaded_by'     => $this->teacher->id,
        ]);

        $resTeacher = $this->actingAs($this->teacher)->get(route('admin.media.download', $media->id));
        $resTeacher->assertStatus(403);

        $resManager = $this->actingAs($this->repoManager)->get(route('admin.media.download', $media->id));
        $resManager->assertStatus(403);
    }

    public function test_super_admin_can_download_and_signed_url_allows_download()
    {
        $media = MediaAsset::create([
            'title'           => 'Protected Audio File',
            'original_name'   => 'audio.wav',
            'filename'        => 'audio.wav',
            'mime_type'       => 'audio/wav',
            'type'            => 'audio',
            'path'            => 'media/audio.wav',
            'size'            => 2048,
            'status'          => 'active',
            'approval_status' => 'approved',
            'version'         => '1.0',
            'uploaded_by'     => $this->teacher->id,
        ]);

        // Super Admin direct download
        $resSuperAdmin = $this->actingAs($this->superAdmin)->get(route('admin.media.download', $media->id));
        $resSuperAdmin->assertStatus(200);

        // Signed URL download for Teacher
        $signedUrl = URL::temporarySignedRoute('admin.media.download', now()->addMinutes(15), ['media' => $media->id]);
        $resSigned = $this->actingAs($this->teacher)->get($signedUrl);
        $resSigned->assertStatus(200);

        $this->assertDatabaseHas('acl_audit_trails', [
            'resource_id' => $media->id,
            'action'      => 'download_executed',
        ]);
    }

    public function test_download_request_workflow_from_repository_manager_to_super_admin()
    {
        $media = MediaAsset::create([
            'title'           => 'Audit Target Document',
            'original_name'   => 'audit.pdf',
            'filename'        => 'audit.pdf',
            'mime_type'       => 'application/pdf',
            'type'            => 'pdf',
            'path'            => 'media/audit.pdf',
            'size'            => 2048,
            'status'          => 'active',
            'approval_status' => 'approved',
            'version'         => '1.0',
            'uploaded_by'     => $this->teacher->id,
        ]);

        // Manager submits download request
        $resReq = $this->actingAs($this->repoManager)->post(route('admin.media.request-download', $media->id), [
            'purpose' => 'Academic Audit',
        ]);
        $resReq->assertSessionHas('status');

        $reqObj = RepositoryReviewRequest::where('resource_id', $media->id)->where('status', 'pending_download')->first();
        $this->assertNotNull($reqObj);

        // Super Admin approves request
        $resApprove = $this->actingAs($this->superAdmin)->post(route('admin.media.approve-download', $reqObj->id));
        $resApprove->assertSessionHas('status');

        $reqObj->refresh();
        $this->assertEquals('approved', $reqObj->status);

        $this->assertDatabaseHas('acl_audit_trails', [
            'resource_id' => $media->id,
            'action'      => 'approve_download',
        ]);
    }

    public function test_role_governance_matrix_buttons_visibility()
    {
        $media = MediaAsset::create([
            'title'           => 'Governance Matrix Test Asset',
            'original_name'   => 'gov.pdf',
            'filename'        => 'gov.pdf',
            'mime_type'       => 'application/pdf',
            'type'            => 'pdf',
            'path'            => 'media/gov.pdf',
            'size'            => 2048,
            'status'          => 'active',
            'approval_status' => 'approved',
            'version'         => '1.0',
            'uploaded_by'     => $this->teacher->id,
        ]);

        // Super Admin sees Download Asset (Super Admin)
        $resSuperAdmin = $this->actingAs($this->superAdmin)->get(route('admin.media.show', $media->id));
        $resSuperAdmin->assertSee('Download Asset (Super Admin)');
        $resSuperAdmin->assertDontSee('Request Download');

        // Repository Manager sees Request Download
        $resRepoManager = $this->actingAs($this->repoManager)->get(route('admin.media.show', $media->id));
        $resRepoManager->assertSee('Request Download');
        $resRepoManager->assertDontSee('Download Asset (Super Admin)');

        // Teacher sees NO download and NO request download
        $resTeacher = $this->actingAs($this->teacher)->get(route('admin.media.show', $media->id));
        $resTeacher->assertDontSee('Download Asset');
        $resTeacher->assertDontSee('Request Download');
    }
}
