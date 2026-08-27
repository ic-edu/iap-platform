<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Assessment\Models\Test as AssessmentTest;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GranularPermissionsAndFinanceAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_teacher_can_create_and_edit_question_bank_and_draft_test(): void
    {
        $teacher = User::factory()->create();
        $teacher->assignRole('teacher');

        // Create Question Bank
        $bankResponse = $this->actingAs($teacher)->post(route('admin.question-banks.store'), [
            'title' => 'Grammar Pool 101',
            'test_type' => 'toefl',
            'description' => 'Draft grammar questions pool',
        ]);
        $bankResponse->assertRedirect(route('admin.question-banks.index'));
        $this->assertDatabaseHas('question_banks', ['title' => 'Grammar Pool 101']);

        $bank = QuestionBank::where('title', 'Grammar Pool 101')->first();

        // Create Question
        $qResponse = $this->actingAs($teacher)->post(route('admin.question-banks.store-question', $bank), [
            'prompt' => 'Which sentence is grammatically correct?',
            'question_type' => 'multiple_choice',
            'difficulty' => 'easy',
            'points' => 10,
            'choices' => [
                ['label' => 'A', 'content' => 'He go to school.'],
                ['label' => 'B', 'content' => 'He goes to school.'],
            ],
            'correct_choice' => 1,
        ]);
        $qResponse->assertRedirect(route('admin.question-banks.show', $bank->id));
        $this->assertDatabaseHas('questions', ['prompt' => 'Which sentence is grammatically correct?']);

        // Create Draft Test
        $testResponse = $this->actingAs($teacher)->post(route('admin.tests.store'), [
            'title' => 'TOEFL Preparation Practice 01',
            'test_type' => 'toefl',
            'duration_minutes' => 60,
            'pass_score' => 50,
        ]);
        $testResponse->assertRedirect(route('admin.tests.index'));
        $this->assertDatabaseHas('tests', ['title' => 'TOEFL Preparation Practice 01', 'is_published' => false]);
    }

    public function test_teacher_cannot_delete_question_and_receives_403(): void
    {
        $teacher = User::factory()->create();
        $teacher->assignRole('teacher');

        $bank = QuestionBank::create(['title' => 'Bank A', 'slug' => 'bank-a', 'test_type' => 'toefl', 'created_by' => $teacher->id]);
        $question = Question::create(['question_bank_id' => $bank->id, 'prompt' => 'Sample Prompt', 'question_type' => 'multiple_choice', 'difficulty' => 'easy', 'points' => 5]);

        $response = $this->actingAs($teacher)->delete(route('admin.question-banks.destroy-question', $question->id));
        $response->assertStatus(403);
        $this->assertDatabaseHas('questions', ['id' => $question->id, 'deleted_at' => null]);
    }

    public function test_teacher_cannot_delete_question_bank_and_receives_403(): void
    {
        $teacher = User::factory()->create();
        $teacher->assignRole('teacher');

        $bank = QuestionBank::create(['title' => 'Protected Bank', 'slug' => 'protected-bank', 'test_type' => 'toefl', 'created_by' => $teacher->id]);

        $response = $this->actingAs($teacher)->delete(route('admin.question-banks.destroy', $bank->id));
        $response->assertStatus(403);
        $this->assertDatabaseHas('question_banks', ['id' => $bank->id]);
    }

    public function test_teacher_cannot_delete_or_publish_test_and_receives_403(): void
    {
        $teacher = User::factory()->create();
        $teacher->assignRole('teacher');

        $test = AssessmentTest::create([
            'title' => 'Draft Exam',
            'slug' => 'draft-exam',
            'test_type' => 'toefl',
            'duration_minutes' => 60,
            'pass_score' => 50,
            'is_published' => false,
            'created_by' => $teacher->id,
        ]);

        // Teacher cannot publish
        $pubResponse = $this->actingAs($teacher)->post(route('admin.tests.publish', $test));
        $pubResponse->assertStatus(403);

        // Teacher cannot delete
        $delResponse = $this->actingAs($teacher)->delete(route('admin.tests.destroy', $test));
        $delResponse->assertStatus(403);
        $this->assertDatabaseHas('tests', ['id' => $test->id]);
    }

    public function test_teacher_cannot_access_user_management_and_receives_403(): void
    {
        $teacher = User::factory()->create();
        $teacher->assignRole('teacher');

        $response = $this->actingAs($teacher)->get(route('admin.users.index'));
        $response->assertStatus(403);
    }

    public function test_admin_question_deletion_creates_question_delete_audit_log(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $bank = QuestionBank::create(['title' => 'Bank B', 'slug' => 'bank-b', 'test_type' => 'toefl', 'created_by' => $admin->id]);
        $question = Question::create(['question_bank_id' => $bank->id, 'prompt' => 'Question to be deleted', 'question_type' => 'multiple_choice', 'difficulty' => 'medium', 'points' => 10]);

        $response = $this->actingAs($admin)->delete(route('admin.question-banks.destroy-question', $question->id));
        $response->assertRedirect(route('admin.question-banks.show', $bank->id));
        $this->assertSoftDeleted('questions', ['id' => $question->id]);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'QUESTION_DELETE',
            'user_id' => $admin->id,
        ]);
    }

    public function test_finance_role_access_boundaries(): void
    {
        $finance = User::factory()->create();
        $finance->assignRole('finance');

        // Finance CANNOT access Commerce & Billing (G1 Governance Separation)
        $commResponse = $this->actingAs($finance)->get(route('admin.commerce.index'));
        $commResponse->assertStatus(403);

        // Finance CAN access Finance Dashboard
        $finDashResponse = $this->actingAs($finance)->get(route('finance.dashboard'));
        $finDashResponse->assertOk();

        // Finance CANNOT access Academic Reporting
        $repResponse = $this->actingAs($finance)->get(route('admin.reporting.index'));
        $repResponse->assertStatus(403);

        // Finance CANNOT access User Management
        $userResponse = $this->actingAs($finance)->get(route('admin.users.index'));
        $userResponse->assertStatus(403);
    }
}
