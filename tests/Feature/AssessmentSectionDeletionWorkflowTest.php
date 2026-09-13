<?php

namespace Tests\Feature;

use App\Models\MediaAsset;
use App\Models\User;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestQuestion;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\Assessment\Services\TestBuilderService;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Modules\QuestionBank\Models\QuestionChoice;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentSectionDeletionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;
    protected User $otherTeacher;
    protected Test $test;
    protected TestSection $section1;
    protected TestSection $section2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->teacher = User::factory()->create([
            'name'   => 'Dr. Eleanor Vance',
            'email'  => 'vance_teacher@icedu.org',
            'status' => 'active',
        ]);
        $this->teacher->assignRole('teacher');

        $this->otherTeacher = User::factory()->create([
            'name'   => 'Prof. Marcus Brody',
            'email'  => 'brody_teacher@icedu.org',
            'status' => 'active',
        ]);
        $this->otherTeacher->assignRole('teacher');

        $this->test = Test::create([
            'title'            => 'TOEIC Listening & Reading for SMK Perhotelan',
            'slug'             => 'toeic-listening-reading-smk-perhotelan',
            'test_type'        => 'general',
            'duration_minutes' => 60,
            'pass_score'       => 70,
            'created_by'       => $this->teacher->id,
            'status'           => 'draft',
            'is_published'     => false,
        ]);

        $this->section1 = TestSection::create([
            'test_id'      => $this->test->id,
            'title'        => 'Section 1: General Core',
            'section_type' => 'reading',
            'order'        => 1,
        ]);

        $this->section2 = TestSection::create([
            'test_id'      => $this->test->id,
            'title'        => 'Listening Test',
            'section_type' => 'listening',
            'order'        => 2,
        ]);
    }

    /**
     * 1. Teacher can delete empty section in draft assessment.
     */
    public function test_teacher_can_delete_empty_section_in_draft_assessment(): void
    {
        $response = $this->actingAs($this->teacher)
            ->delete(route('teacher.tests.destroy-section', [
                'test'    => $this->test->id,
                'section' => $this->section1->id,
            ]));

        $response->assertRedirect(route('teacher.tests.show', $this->test->id));
        $response->assertSessionHas('status', "Section 'Section 1: General Core' removed successfully.");

        $this->assertDatabaseMissing('test_sections', [
            'id' => $this->section1->id,
        ]);

        $this->assertDatabaseHas('test_sections', [
            'id' => $this->section2->id,
        ]);
    }

    /**
     * 2. Deleting empty section clears empty section validation error.
     */
    public function test_deleting_empty_section_clears_empty_section_validation_error(): void
    {
        // Add 1 question to section 2 (Listening Test)
        $q = Question::create([
            'question_bank_id' => null,
            'prompt'           => 'What is being announced?',
            'question_type'    => 'multiple_choice',
            'points'           => 1,
        ]);
        QuestionChoice::create([
            'question_id' => $q->id,
            'label'       => 'A',
            'content'     => 'A train departure delay.',
            'is_correct'  => true,
            'order'       => 1,
        ]);
        QuestionChoice::create([
            'question_id' => $q->id,
            'label'       => 'B',
            'content'     => 'A flight boarding call.',
            'is_correct'  => false,
            'order'       => 2,
        ]);
        TestQuestion::create([
            'test_section_id' => $this->section2->id,
            'question_id'     => $q->id,
            'order'           => 1,
            'points'          => 1,
        ]);

        $service = app(TestBuilderService::class);

        // Before deleting Section 1: Validation fails due to empty Section 1
        $valBefore = $service->validateAssessment($this->test);
        $this->assertFalse($valBefore['is_valid']);
        $this->assertContains("Section 'Section 1: General Core' has no questions assigned.", $valBefore['errors']);

        // Delete empty Section 1
        $this->actingAs($this->teacher)
            ->delete(route('teacher.tests.destroy-section', [
                'test'    => $this->test->id,
                'section' => $this->section1->id,
            ]))
            ->assertRedirect(route('teacher.tests.show', $this->test->id));

        // After deleting Section 1: Validation passes without empty section error
        $this->test->refresh();
        $valAfter = $service->validateAssessment($this->test);
        $this->assertTrue($valAfter['is_valid']);
        $this->assertNotContains("Section 'Section 1: General Core' has no questions assigned.", $valAfter['errors']);
    }

    /**
     * 3. Deleting section detaches questions without deleting question records.
     */
    public function test_deleting_section_detaches_questions_without_deleting_question_records(): void
    {
        $question = Question::create([
            'question_bank_id' => null,
            'prompt'           => 'Authored question in section 1',
            'question_type'    => 'multiple_choice',
            'points'           => 1,
        ]);
        QuestionChoice::create([
            'question_id' => $question->id,
            'label'       => 'A',
            'content'     => 'Choice A',
            'is_correct'  => true,
            'order'       => 1,
        ]);

        $tq = TestQuestion::create([
            'test_section_id' => $this->section1->id,
            'question_id'     => $question->id,
            'order'           => 1,
            'points'          => 1,
        ]);

        $this->actingAs($this->teacher)
            ->delete(route('teacher.tests.destroy-section', [
                'test'    => $this->test->id,
                'section' => $this->section1->id,
            ]))
            ->assertRedirect(route('teacher.tests.show', $this->test->id));

        // test_questions pivot row is deleted
        $this->assertDatabaseMissing('test_questions', ['id' => $tq->id]);

        // Underlying question and choices records are PRESERVED
        $this->assertDatabaseHas('questions', ['id' => $question->id]);
        $this->assertDatabaseHas('question_choices', ['question_id' => $question->id]);
    }

    /**
     * 4. Deleting section does not delete master question from Question Bank.
     */
    public function test_deleting_section_does_not_delete_master_question(): void
    {
        $bank = QuestionBank::create([
            'title'      => 'Institutional Question Bank',
            'slug'       => 'institutional-question-bank',
            'test_type'  => 'toeic',
            'status'     => 'active',
            'created_by' => $this->teacher->id,
        ]);

        $masterQuestion = Question::create([
            'question_bank_id' => $bank->id,
            'prompt'           => 'Master question from institutional bank',
            'question_type'    => 'multiple_choice',
            'points'           => 1,
        ]);

        TestQuestion::create([
            'test_section_id' => $this->section1->id,
            'question_id'     => $masterQuestion->id,
            'order'           => 1,
            'points'          => 1,
        ]);

        $this->actingAs($this->teacher)
            ->delete(route('teacher.tests.destroy-section', [
                'test'    => $this->test->id,
                'section' => $this->section1->id,
            ]))
            ->assertRedirect(route('teacher.tests.show', $this->test->id));

        // Master question remains in DB under its Question Bank
        $this->assertDatabaseHas('questions', [
            'id'               => $masterQuestion->id,
            'question_bank_id' => $bank->id,
        ]);
    }

    /**
     * 5. Deleting section does not delete MediaAsset records from Media Library.
     */
    public function test_deleting_section_does_not_delete_media_assets(): void
    {
        $media = MediaAsset::create([
            'title'         => 'Listening Audio Prompt',
            'filename'      => 'listening_track.mp3',
            'original_name' => 'listening_track.mp3',
            'path'          => 'media/listening_track.mp3',
            'mime_type'     => 'audio/mpeg',
            'type'          => 'audio',
            'size'          => 10240,
            'uploaded_by'   => $this->teacher->id,
        ]);

        $this->section1->mediaAssets()->attach($media->id, [
            'id'      => (string) \Illuminate\Support\Str::ulid(),
            'caption' => 'Track 1',
            'order'   => 1,
        ]);

        $this->actingAs($this->teacher)
            ->delete(route('teacher.tests.destroy-section', [
                'test'    => $this->test->id,
                'section' => $this->section1->id,
            ]))
            ->assertRedirect(route('teacher.tests.show', $this->test->id));

        // test_section_media pivot is removed
        $this->assertDatabaseMissing('test_section_media', [
            'test_section_id' => $this->section1->id,
            'media_asset_id'  => $media->id,
        ]);

        // MediaAsset record remains intact in Media Library
        $this->assertDatabaseHas('media_assets', [
            'id' => $media->id,
        ]);
    }

    /**
     * 6. Cannot delete section in pending_approval state.
     */
    public function test_cannot_delete_section_in_pending_approval(): void
    {
        $this->test->update(['status' => 'pending_approval']);

        $response = $this->actingAs($this->teacher)
            ->delete(route('teacher.tests.destroy-section', [
                'test'    => $this->test->id,
                'section' => $this->section1->id,
            ]));

        $response->assertStatus(403);
        $this->assertDatabaseHas('test_sections', ['id' => $this->section1->id]);
    }

    /**
     * 7. Cannot delete section in approved assessment.
     */
    public function test_cannot_delete_section_in_approved_assessment(): void
    {
        $this->test->update(['status' => 'approved']);

        $response = $this->actingAs($this->teacher)
            ->delete(route('teacher.tests.destroy-section', [
                'test'    => $this->test->id,
                'section' => $this->section1->id,
            ]));

        $response->assertStatus(403);
        $this->assertDatabaseHas('test_sections', ['id' => $this->section1->id]);
    }

    /**
     * 8. Cannot delete section in published assessment.
     */
    public function test_cannot_delete_section_in_published_assessment(): void
    {
        $this->test->update([
            'status'       => 'published',
            'is_published' => true,
        ]);

        $response = $this->actingAs($this->teacher)
            ->delete(route('teacher.tests.destroy-section', [
                'test'    => $this->test->id,
                'section' => $this->section1->id,
            ]));

        $response->assertStatus(403);
        $this->assertDatabaseHas('test_sections', ['id' => $this->section1->id]);
    }

    /**
     * 9. Cannot delete last remaining section.
     */
    public function test_cannot_delete_last_remaining_section(): void
    {
        // Delete section 1 first
        $this->section1->delete();

        // Now test has only 1 section remaining (section 2)
        $this->assertEquals(1, $this->test->sections()->count());

        $response = $this->actingAs($this->teacher)
            ->delete(route('teacher.tests.destroy-section', [
                'test'    => $this->test->id,
                'section' => $this->section2->id,
            ]));

        $response->assertRedirect(route('teacher.tests.show', $this->test->id));
        $response->assertSessionHasErrors(['section']);

        $this->assertDatabaseHas('test_sections', [
            'id' => $this->section2->id,
        ]);
    }

    /**
     * 10. Unauthorized teacher cannot delete section.
     */
    public function test_unauthorized_teacher_cannot_delete_section(): void
    {
        $response = $this->actingAs($this->otherTeacher)
            ->delete(route('teacher.tests.destroy-section', [
                'test'    => $this->test->id,
                'section' => $this->section1->id,
            ]));

        $response->assertStatus(403);
        $this->assertDatabaseHas('test_sections', ['id' => $this->section1->id]);
    }
}
