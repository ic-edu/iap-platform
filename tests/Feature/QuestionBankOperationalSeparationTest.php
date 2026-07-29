<?php

namespace Tests\Feature;

use App\Models\QuestionBankArchiveRequest;
use App\Models\User;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuestionBankOperationalSeparationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_cannot_create_question_bank_or_author_questions(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        // Admin attempting to create Question Bank
        $resStoreBank = $this->actingAs($admin)->post(route('admin.question-banks.store'), [
            'title' => 'Admin Bank Attempt',
            'test_type' => 'toeic',
        ]);
        $resStoreBank->assertStatus(403);

        // Teacher creating Question Bank
        $teacher = User::factory()->create();
        $teacher->assignRole('teacher');

        $resTeacherStore = $this->actingAs($teacher)->post(route('admin.question-banks.store'), [
            'title' => 'Teacher Bank',
            'test_type' => 'toeic',
        ]);
        $resTeacherStore->assertRedirect();

        $bank = QuestionBank::where('title', 'Teacher Bank')->first();
        $this::assertNotNull($bank);

        // Admin attempting to store question
        $resStoreQ = $this->actingAs($admin)->post(route('admin.question-banks.store-question', $bank->id), [
            'prompt' => 'Admin Question Attempt',
            'question_type' => 'single_choice',
            'difficulty' => 'easy',
            'points' => 1,
        ]);
        $resStoreQ->assertStatus(403);
    }

    public function test_admin_may_publish_and_unpublish_approved_question_bank(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $teacher = User::factory()->create();
        $teacher->assignRole('teacher');

        $bank = QuestionBank::create([
            'title' => 'Approved Bank for Publish',
            'slug' => 'approved-bank-for-publish',
            'created_by' => $teacher->id,
            'test_type' => 'toeic',
            'status' => 'approved',
            'is_published' => false,
        ]);

        // Admin publishes
        $resPub = $this->actingAs($admin)->post(route('admin.question-banks.publish', $bank->id));
        $resPub->assertRedirect();
        $this::assertTrue($bank->fresh()->isPublished());

        // Teacher receives notification
        $this::assertCount(1, $teacher->fresh()->notifications);
        $this::assertStringContainsString('Published', $teacher->fresh()->notifications->first()->data['title']);

        // Admin unpublishes
        $resUnpub = $this->actingAs($admin)->post(route('admin.question-banks.unpublish', $bank->id));
        $resUnpub->assertRedirect();
        $this::assertFalse($bank->fresh()->is_published);
    }

    public function test_archive_workflow_admin_requests_and_super_admin_approves(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super-admin');

        $teacher = User::factory()->create();
        $teacher->assignRole('teacher');

        $bank = QuestionBank::create([
            'title' => 'Bank to Archive',
            'slug' => 'bank-to-archive',
            'created_by' => $teacher->id,
            'test_type' => 'toeic',
            'status' => 'approved',
            'is_published' => false,
        ]);

        // Admin requests archive
        $resReq = $this->actingAs($admin)->post(route('admin.question-banks.request-archive', $bank->id), [
            'reason' => 'Outdated curriculum content.',
        ]);
        $resReq->assertRedirect();

        $this::assertEquals('pending_archive_approval', $bank->fresh()->status);
        $archiveReq = QuestionBankArchiveRequest::where('question_bank_id', $bank->id)->first();
        $this::assertNotNull($archiveReq);
        $this::assertEquals('pending', $archiveReq->status);

        // Super Admin approves archive request
        $resApprove = $this->actingAs($superAdmin)->post(route('admin.approvals.question-banks.archives.approve', $archiveReq->id));
        $resApprove->assertRedirect();

        $this::assertEquals('approved', $archiveReq->fresh()->status);
        $this::assertEquals('archived', $bank->fresh()->status);

        // Teacher & Admin receive notifications
        $this::assertTrue($teacher->fresh()->notifications->pluck('data.title')->contains('Question Bank Archived'));
        $this::assertTrue($admin->fresh()->notifications->pluck('data.title')->contains('Question Bank Archive Approved'));
    }
}
