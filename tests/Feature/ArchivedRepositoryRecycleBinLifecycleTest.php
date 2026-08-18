<?php

namespace Tests\Feature;

use App\Models\AclAuditTrail;
use App\Models\RepositoryActivityLog;
use App\Models\User;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Modules\QuestionBank\Models\QuestionChoice;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArchivedRepositoryRecycleBinLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $teacher;
    protected User $regularAdmin;
    protected User $repoManager;
    protected QuestionBank $archivedBank;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->superAdmin = User::factory()->create([
            'name'   => 'Super Admin User',
            'email'  => 'superadmin_archive_test@icedu.org',
            'status' => 'active',
        ]);
        $this->superAdmin->assignRole('super-admin');

        $this->teacher = User::factory()->create([
            'name'   => 'Teacher Author User',
            'email'  => 'teacher_archive_test@icedu.org',
            'status' => 'active',
        ]);
        $this->teacher->assignRole('teacher');

        $this->regularAdmin = User::factory()->create([
            'name'   => 'Regular Admin User',
            'email'  => 'admin_archive_test@icedu.org',
            'status' => 'active',
        ]);
        $this->regularAdmin->assignRole('admin');

        $this->repoManager = User::factory()->create([
            'name'   => 'Repository Manager User',
            'email'  => 'rm_archive_test@icedu.org',
            'status' => 'active',
        ]);
        $this->repoManager->assignRole('repository-manager');

        // Create an archived question bank with questions
        $this->archivedBank = QuestionBank::create([
            'title'           => 'Archived Biology Exam Repository',
            'slug'            => 'archived-biology-exam-repository',
            'test_type'       => 'general',
            'status'          => 'archived',
            'is_published'    => false,
            'created_by'      => $this->teacher->id,
            'description'     => 'Legacy archived biology repository.',
            'current_version' => '1.0',
        ]);

        $q = Question::create([
            'question_bank_id' => $this->archivedBank->id,
            'prompt'           => 'What is the powerhouse of the cell?',
            'question_type'    => 'multiple_choice',
            'points'           => 1,
        ]);

        QuestionChoice::create([
            'question_id' => (string) $q->id,
            'label'       => 'A',
            'content'     => 'Mitochondria',
            'is_correct'  => true,
        ]);
    }

    /**
     * TEST 1: Teacher can view archived repository page and individual archived repository.
     */
    public function test_1_teacher_can_view_archived_repositories_page_and_details()
    {
        $response = $this->actingAs($this->teacher)
            ->get(route('teacher.archived-repositories.index'));

        $response->assertStatus(200);
        $response->assertSee('📦 Archived Repositories');
        $response->assertSee('Archived Biology Exam Repository');
        $response->assertSee('👁 View');
        $response->assertSee('↻ Request Restore');

        // Teacher CANNOT see Move to Recycle Bin or Delete buttons
        $response->assertDontSee('Move to Recycle Bin');
        $response->assertDontSee('🗑 Move to Recycle Bin');

        // View single archived repository
        $showResponse = $this->actingAs($this->teacher)
            ->get(route('teacher.question-banks.show', [
                'questionBank' => $this->archivedBank->id,
                'from'         => 'archived_repositories',
            ]));

        $showResponse->assertStatus(200);
        $showResponse->assertSee('Archived Biology Exam Repository');
        $showResponse->assertSee('← Back to Archived Repositories');
    }

    /**
     * TEST 2: Teacher can request restore from archived repository (ARCHIVED -> PENDING_RESTORE_APPROVAL).
     */
    public function test_2_teacher_can_request_restore_from_archived_repository()
    {
        $response = $this->actingAs($this->teacher)
            ->post(route('admin.question-banks.request-restore', $this->archivedBank->id), [
                'reason' => 'Need to reuse biology questions for next semester.',
            ]);

        $response->assertSessionHas('status');

        $freshBank = $this->archivedBank->fresh();
        $this->assertEquals('pending_restore_approval', $freshBank->status);

        // Verify audit log created
        $this->assertDatabaseHas('acl_audit_trails', [
            'resource_type' => 'QuestionBank',
            'resource_id'   => $this->archivedBank->id,
            'action'        => 'restore_requested',
            'actor_id'      => $this->teacher->id,
        ]);
    }

    /**
     * TEST 3: Teacher and Regular Admin CANNOT move archived repository to Recycle Bin.
     */
    public function test_3_teacher_and_admin_cannot_move_archived_repository_to_recycle_bin()
    {
        // Teacher attempt
        $teacherResp = $this->actingAs($this->teacher)
            ->post(route('admin.archived-repositories.move-to-recycle-bin', $this->archivedBank->id));
        $teacherResp->assertStatus(403);

        // Admin attempt
        $adminResp = $this->actingAs($this->regularAdmin)
            ->post(route('admin.archived-repositories.move-to-recycle-bin', $this->archivedBank->id));
        $adminResp->assertStatus(403);

        // Bank remains active and untouched
        $this->assertNull($this->archivedBank->fresh()->deleted_at);
    }

    /**
     * TEST 4 & 5 & 6: Super Admin can move archived repository to Recycle Bin without hard delete.
     */
    public function test_4_super_admin_can_move_archived_repository_to_recycle_bin_via_soft_delete()
    {
        $response = $this->actingAs($this->superAdmin)
            ->post(route('admin.archived-repositories.move-to-recycle-bin', $this->archivedBank->id));

        $response->assertRedirect(route('admin.archived-repositories.index'));
        $response->assertSessionHas('status');

        // 1. Soft deleted (deleted_at is set)
        $this->assertSoftDeleted('question_banks', [
            'id' => $this->archivedBank->id,
        ]);

        // 2. NO Hard Delete occurred: record still exists in DB
        $trashedBank = QuestionBank::withTrashed()->find($this->archivedBank->id);
        $this->assertNotNull($trashedBank);
        $this->assertNotNull($trashedBank->deleted_at);

        // 3. Child questions and choices are completely preserved
        $this->assertEquals(1, $trashedBank->questions()->count());

        // 4. Audit trail recorded
        $this->assertDatabaseHas('acl_audit_trails', [
            'resource_type' => 'QuestionBank',
            'resource_id'   => $this->archivedBank->id,
            'action'        => 'moved_to_recycle_bin',
            'actor_id'      => $this->superAdmin->id,
        ]);
        $this->assertDatabaseHas('repository_activity_logs', [
            'resource_type' => 'QuestionBank',
            'resource_id'   => $this->archivedBank->id,
            'action'        => 'moved_to_recycle_bin',
            'actor_id'      => $this->superAdmin->id,
        ]);
    }

    /**
     * TEST 7 & 8: Super Admin can restore repository from Recycle Bin back to ARCHIVED status.
     */
    public function test_7_super_admin_can_restore_from_recycle_bin_to_archived_status()
    {
        // Move to recycle bin first
        $this->archivedBank->delete();
        $this->assertTrue($this->archivedBank->fresh()->trashed());

        // Super Admin restores it
        $response = $this->actingAs($this->superAdmin)
            ->post(route('admin.recycle-bin.restore', $this->archivedBank->id));

        $response->assertRedirect(route('admin.recycle-bin.index'));
        $response->assertSessionHas('status');

        // Verify restored
        $restoredBank = QuestionBank::find($this->archivedBank->id);
        $this->assertNotNull($restoredBank);
        $this->assertFalse($restoredBank->trashed());

        // MUST be restored to 'archived' status and NOT 'approved' or 'published'
        $this->assertEquals('archived', $restoredBank->status);
        $this->assertFalse((bool) $restoredBank->is_published);

        // Audit trails recorded
        $this->assertDatabaseHas('acl_audit_trails', [
            'resource_type' => 'QuestionBank',
            'resource_id'   => $this->archivedBank->id,
            'action'        => 'restored_from_recycle_bin',
            'actor_id'      => $this->superAdmin->id,
        ]);
        $this->assertDatabaseHas('repository_activity_logs', [
            'resource_type' => 'QuestionBank',
            'resource_id'   => $this->archivedBank->id,
            'action'        => 'restored_from_recycle_bin',
            'actor_id'      => $this->superAdmin->id,
        ]);
    }

    /**
     * TEST 9: Restored repository still requires normal SA restore approval before becoming active.
     */
    public function test_9_restored_repository_follows_normal_restoration_governance_workflow()
    {
        // 1. Soft delete and restore back to ARCHIVED
        $this->archivedBank->delete();
        $this->archivedBank->restore();
        $this->archivedBank->update(['status' => 'archived', 'is_published' => false]);

        // 2. Teacher requests restore -> pending_restore_approval
        $this->actingAs($this->teacher)
            ->post(route('admin.question-banks.request-restore', $this->archivedBank->id), [
                'reason' => 'Formal request after recycle bin recovery.',
            ]);
        $this->assertEquals('pending_restore_approval', $this->archivedBank->fresh()->status);

        // 3. Super Admin approves restore -> status becomes approved
        $this->actingAs($this->superAdmin)
            ->post(route('admin.question-banks.approve-restore', $this->archivedBank->id), [
                'decision_notes' => 'Restoration approved by SA.',
            ]);
        $this->assertEquals('approved', $this->archivedBank->fresh()->status);

        // 4. Repository Manager approves and publishes -> status becomes published
        $this->actingAs($this->repoManager)
            ->post(route('admin.repository-manager.question-bank-approve', $this->archivedBank->id), [
                'notes' => 'Institutional publishing after restoration verification.',
            ]);
        $this->assertEquals('published', $this->archivedBank->fresh()->status);
        $this->assertTrue((bool) $this->archivedBank->fresh()->is_published);
    }

    /**
     * TEST 10: Recycle Bin pages are strictly forbidden to Teacher and Regular Admin.
     */
    public function test_10_recycle_bin_pages_are_forbidden_to_teacher_and_regular_admin()
    {
        // Teacher forbidden
        $this->actingAs($this->teacher)
            ->get(route('admin.recycle-bin.index'))
            ->assertStatus(403);

        $this->actingAs($this->teacher)
            ->get(route('admin.recycle-bin.show', $this->archivedBank->id))
            ->assertStatus(403);

        $this->actingAs($this->teacher)
            ->post(route('admin.recycle-bin.restore', $this->archivedBank->id))
            ->assertStatus(403);

        // Regular Admin forbidden
        $this->actingAs($this->regularAdmin)
            ->get(route('admin.recycle-bin.index'))
            ->assertStatus(403);

        $this->actingAs($this->regularAdmin)
            ->get(route('admin.recycle-bin.show', $this->archivedBank->id))
            ->assertStatus(403);

        $this->actingAs($this->regularAdmin)
            ->post(route('admin.recycle-bin.restore', $this->archivedBank->id))
            ->assertStatus(403);

        // Super Admin has full access
        $this->actingAs($this->superAdmin)
            ->get(route('admin.recycle-bin.index'))
            ->assertStatus(200);
    }

    /**
     * TEST 11: Navigation Service correctly yields menu items per role.
     */
    public function test_11_navigation_service_role_menus()
    {
        // Super Admin sees Archived Repositories and Recycle Bin under Governance
        $this->actingAs($this->superAdmin);
        $saMenu = \App\Services\NavigationService::getMenuItems();
        $saRoutes = array_column($saMenu, 'route');
        $this->assertContains('admin.archived-repositories.index', $saRoutes);
        $this->assertContains('admin.recycle-bin.index', $saRoutes);

        // Teacher sees Archived Repositories under Authoring, but NOT Recycle Bin
        $this->actingAs($this->teacher);
        $teacherMenu = \App\Services\NavigationService::getMenuItems();
        $teacherRoutes = array_column($teacherMenu, 'route');
        $this->assertContains('teacher.archived-repositories.index', $teacherRoutes);
        $this->assertNotContains('admin.recycle-bin.index', $teacherRoutes);

        // Regular Admin does NOT see Question Bank Archived or Recycle Bin
        $this->actingAs($this->regularAdmin);
        $adminMenu = \App\Services\NavigationService::getMenuItems();
        $adminRoutes = array_column($adminMenu, 'route');
        $this->assertNotContains('admin.archived-repositories.index', $adminRoutes);
        $this->assertNotContains('admin.recycle-bin.index', $adminRoutes);
        $this->assertNotContains('teacher.archived-repositories.index', $adminRoutes);
    }
}
