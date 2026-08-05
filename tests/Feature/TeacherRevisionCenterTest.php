<?php

namespace Tests\Feature;

use App\Models\RepositoryActivityLog;
use App\Models\User;
use App\Models\AclCategory;
use App\Modules\QuestionBank\Models\QuestionBank;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherRevisionCenterTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacherA;
    protected User $teacherB;
    protected User $repoManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->teacherA = User::factory()->create([
            'name'   => 'Dr. Eleanor Vance',
            'email'  => 'vance_teacher@icedu.org',
            'status' => 'active',
        ]);
        $this->teacherA->assignRole('teacher');

        $this->teacherB = User::factory()->create([
            'name'   => 'Prof. Marcus Brody',
            'email'  => 'brody_teacher@icedu.org',
            'status' => 'active',
        ]);
        $this->teacherB->assignRole('teacher');

        $this->repoManager = User::factory()->create([
            'name'   => 'Repository Governance Manager',
            'email'  => 'repomanager_rev@icedu.org',
            'status' => 'active',
        ]);
        $this->repoManager->assignRole('repository-manager');
    }

    /**
     * TEST 1: Dashboard Needs Revision card opens Teacher Revision Center (200 OK).
     */
    public function test_1_teacher_dashboard_needs_revision_card_opens_revision_center()
    {
        $res = $this->actingAs($this->teacherA)->get(route('teacher.revision-center'));
        $res->assertStatus(200);
        $res->assertSee('Teacher Revision Center');
    }

    /**
     * TEST 2: Revision Center displays teacher-owned returned Question Banks with Repository Manager feedback comments.
     */
    public function test_2_revision_center_displays_returned_question_banks_with_feedback()
    {
        $bank = QuestionBank::create([
            'title'      => 'TOEIC Listening Part 1 Draft Pool',
            'slug'       => 'toeic-listening-part-1-draft-pool',
            'test_type'  => 'toeic',
            'status'     => 'needs_revision',
            'created_by' => $this->teacherA->id,
        ]);

        RepositoryActivityLog::create([
            'resource_type' => 'QuestionBank',
            'resource_id'   => (string) $bank->id,
            'actor_id'      => $this->teacherA->id,
            'reviewer_id'   => $this->repoManager->id,
            'action'        => 'revision_requested',
            'approval_note' => 'Audio timestamps contain 2-second misalignments. Please adjust audio cues.',
        ]);

        $res = $this->actingAs($this->teacherA)->get(route('teacher.revision-center'));
        $res->assertStatus(200);
        $res->assertSee('TOEIC Listening Part 1 Draft Pool');
        $res->assertSee('Audio timestamps contain 2-second misalignments. Please adjust audio cues.');
        $res->assertSee('Repository Governance Manager');
    }

    /**
     * TEST 3: Clicking Continue Revision opens Question Bank editor (200 OK).
     */
    public function test_3_continue_revision_opens_editor()
    {
        $bank = QuestionBank::create([
            'title'      => 'IELTS Academic Writing Pool',
            'slug'       => 'ielts-academic-writing-pool',
            'test_type'  => 'ielts',
            'status'     => 'needs_revision',
            'created_by' => $this->teacherA->id,
        ]);

        $res = $this->actingAs($this->teacherA)->get(route('teacher.question-banks.show', $bank->id));
        $res->assertStatus(200);
        $res->assertSee('IELTS Academic Writing Pool');
    }

    /**
     * TEST 4: Teacher B cannot see Teacher A's returned Question Banks or access editor.
     */
    public function test_4_teacher_b_cannot_see_teacher_a_revisions_or_open_editor()
    {
        $bankA = QuestionBank::create([
            'title'      => 'Teacher A Confidential Bank',
            'slug'       => 'teacher-a-confidential-bank',
            'test_type'  => 'toefl',
            'status'     => 'needs_revision',
            'created_by' => $this->teacherA->id,
        ]);

        // Teacher B visiting Revision Center does not see Teacher A's bank
        $resB = $this->actingAs($this->teacherB)->get(route('teacher.revision-center'));
        $resB->assertStatus(200);
        $resB->assertDontSee('Teacher A Confidential Bank');

        // Teacher B trying to access Teacher A's bank editor gets 403
        $resEditor = $this->actingAs($this->teacherB)->get(route('teacher.question-banks.show', $bankA->id));
        $resEditor->assertStatus(403);
    }

    /**
     * TEST 5: Institutional Repository does NOT display needs_revision Question Banks.
     */
    public function test_5_institutional_repository_does_not_display_needs_revision_banks()
    {
        $category = AclCategory::create([
            'name'      => 'TOEFL Structure & Expression',
            'slug'      => 'toefl-structure-expression',
            'test_type' => 'toefl',
            'is_active' => true,
        ]);

        $returnedBank = QuestionBank::create([
            'title'           => 'Returned Bank Hidden From Public Repo',
            'slug'            => 'returned-bank-hidden-from-public-repo',
            'test_type'       => 'toefl',
            'acl_category_id' => $category->id,
            'status'          => 'needs_revision',
            'created_by'      => $this->teacherA->id,
        ]);

        $approvedBank = QuestionBank::create([
            'title'           => 'Approved Public Institutional Bank',
            'slug'            => 'approved-public-institutional-bank',
            'test_type'       => 'toefl',
            'acl_category_id' => $category->id,
            'status'          => 'approved',
            'is_published'    => true,
            'created_by'      => $this->teacherA->id,
        ]);

        $res = $this->actingAs($this->teacherA)->get(route('admin.academic-library.show', $category->slug));
        $res->assertStatus(200);
        $res->assertSee('Approved Public Institutional Bank');
        $res->assertDontSee('Returned Bank Hidden From Public Repo');
    }
}
