<?php

namespace Tests\Feature;

use App\Models\MediaAsset;
use App\Models\User;
use App\Modules\Assessment\Enums\AssessmentMode;
use App\Modules\Assessment\Enums\AttemptStatus;
use App\Modules\Assessment\Enums\EvaluationStatus;
use App\Modules\Assessment\Models\Answer;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestQuestion;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\QuestionBank\Enums\QuestionType;
use App\Modules\QuestionBank\Enums\SectionType;
use App\Modules\QuestionBank\Enums\TestType;
use App\Modules\QuestionBank\Models\AudioGroup;
use App\Modules\QuestionBank\Models\Passage;
use App\Modules\QuestionBank\Models\PassageGroup;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionChoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SimulatorWrongAnswerReviewTest extends TestCase
{
    use RefreshDatabase;

    protected User $candidate1;
    protected User $candidate2;
    protected User $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);

        $this->candidate1 = User::factory()->create(['name' => 'Candidate One', 'email' => 'c1@example.com']);
        $this->candidate1->assignRole('student');

        $this->candidate2 = User::factory()->create(['name' => 'Candidate Two', 'email' => 'c2@example.com']);
        $this->candidate2->assignRole('student');

        $this->teacher = User::factory()->create(['name' => 'Teacher One', 'email' => 't1@example.com']);
        $this->teacher->assignRole('teacher');
    }

    /**
     * Helper to create a comprehensive TOEIC Simulator attempt with mixed question types.
     */
    protected function createToeicSimulatorFixture(array $incorrectQuestionIndices = [17, 59]): array
    {
        Storage::fake('public');

        $test = Test::create([
            'title'            => 'TOEIC Full Practice Simulator',
            'slug'             => 'toeic-sim-' . uniqid(),
            'test_type'        => TestType::Toeic,
            'assessment_mode'  => AssessmentMode::Simulator,
            'duration_minutes' => 60,
            'pass_score'       => 700,
            'scoring_method'   => 'automatic',
            'status'           => 'published',
            'is_published'     => true,
            'created_by'       => $this->teacher->id,
        ]);

        $secL = TestSection::create([
            'test_id'      => $test->id,
            'title'        => 'Listening Section',
            'section_type' => SectionType::Listening,
            'order'        => 1,
        ]);

        $secR = TestSection::create([
            'test_id'      => $test->id,
            'title'        => 'Reading Section',
            'section_type' => SectionType::Reading,
            'order'        => 2,
        ]);

        $attempt = Attempt::create([
            'test_id'           => $test->id,
            'user_id'           => $this->candidate1->id,
            'attempt_token'     => 'sim-tok-' . uniqid(),
            'status'            => AttemptStatus::Submitted,
            'evaluation_status' => EvaluationStatus::NotRequired,
            'started_at'        => now()->subMinutes(50),
            'submitted_at'      => now(),
        ]);

        $photoFile = UploadedFile::fake()->image('q1_photo.jpg', 600, 400);
        $photoPath = $photoFile->store('media', 'public');
        $photoAsset = MediaAsset::create([
            'title'         => 'Q1 Photo',
            'filename'      => 'q1_photo.jpg',
            'original_name' => 'q1_photo.jpg',
            'path'          => $photoPath,
            'mime_type'     => 'image/jpeg',
            'size'          => 10240,
            'type'          => 'image',
            'status'        => 'active',
            'uploaded_by'   => $this->teacher->id,
        ]);

        $audioFile = UploadedFile::fake()->create('q1_audio.mp3', 200, 'audio/mpeg');
        $audioPath = $audioFile->store('media', 'public');
        $audioAsset = MediaAsset::create([
            'title'         => 'Q1 Audio',
            'filename'      => 'q1_audio.mp3',
            'original_name' => 'q1_audio.mp3',
            'path'          => $audioPath,
            'mime_type'     => 'audio/mpeg',
            'size'          => 20480,
            'type'          => 'audio',
            'status'        => 'active',
            'uploaded_by'   => $this->teacher->id,
        ]);

        $q1 = Question::create([
            'prompt'                => 'Look at the photograph and choose the best statement.',
            'question_type'         => QuestionType::MultipleChoice,
            'points'                => 1,
            'section'               => SectionType::Listening,
            'part_number'           => 1,
            'media_asset_id'        => $photoAsset->id,
            'audio_media_asset_id'  => $audioAsset->id,
            'explanation'           => 'Statement (A) accurately describes the scene in the photograph.',
        ]);

        TestQuestion::create(['test_section_id' => $secL->id, 'question_id' => $q1->id, 'order' => 1]);
        $q1cA = QuestionChoice::create(['question_id' => $q1->id, 'label' => 'A', 'content' => 'The woman is typing.', 'is_correct' => true]);
        $q1cB = QuestionChoice::create(['question_id' => $q1->id, 'label' => 'B', 'content' => 'The woman is standing.', 'is_correct' => false]);
        $q1cC = QuestionChoice::create(['question_id' => $q1->id, 'label' => 'C', 'content' => 'The man is cooking.', 'is_correct' => false]);
        $q1cD = QuestionChoice::create(['question_id' => $q1->id, 'label' => 'D', 'content' => 'The room is empty.', 'is_correct' => false]);

        $isQ1Wrong = in_array(1, $incorrectQuestionIndices, true);
        Answer::create([
            'attempt_id'         => $attempt->id,
            'question_id'        => $q1->id,
            'selected_choice_id' => $isQ1Wrong ? $q1cB->id : $q1cA->id,
            'is_correct'         => !$isQ1Wrong,
            'score_earned'       => $isQ1Wrong ? 0 : 1,
        ]);

        // 2. Part 2 Standalone Question (Q2) with Audio
        $q2 = Question::create([
            'prompt'                => 'Part 2 Question Audio Prompt',
            'question_type'         => QuestionType::MultipleChoice,
            'points'                => 1,
            'section'               => SectionType::Listening,
            'part_number'           => 2,
            'audio_media_asset_id'  => $audioAsset->id,
            'explanation'           => 'Choice (B) is the direct appropriate response to the question.',
        ]);
        TestQuestion::create(['test_section_id' => $secL->id, 'question_id' => $q2->id, 'order' => 2]);
        $q2cA = QuestionChoice::create(['question_id' => $q2->id, 'label' => 'A', 'content' => 'At nine o\'clock.', 'is_correct' => false]);
        $q2cB = QuestionChoice::create(['question_id' => $q2->id, 'label' => 'B', 'content' => 'Yes, in the conference room.', 'is_correct' => true]);
        $q2cC = QuestionChoice::create(['question_id' => $q2->id, 'label' => 'C', 'content' => 'To the airport.', 'is_correct' => false]);

        $isQ2Wrong = in_array(2, $incorrectQuestionIndices, true);
        Answer::create([
            'attempt_id'         => $attempt->id,
            'question_id'        => $q2->id,
            'selected_choice_id' => $isQ2Wrong ? $q2cA->id : $q2cB->id,
            'is_correct'         => !$isQ2Wrong,
            'score_earned'       => $isQ2Wrong ? 0 : 1,
        ]);

        // 3. Fill intermediate standalone questions Q3–Q15
        for ($i = 3; $i <= 15; $i++) {
            $q = Question::create([
                'prompt'        => "Listening Prompt {$i}",
                'question_type' => QuestionType::MultipleChoice,
                'points'        => 1,
                'section'       => SectionType::Listening,
                'part_number'   => 2,
            ]);
            TestQuestion::create(['test_section_id' => $secL->id, 'question_id' => $q->id, 'order' => $i]);
            $cA = QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => 'Choice A', 'is_correct' => true]);
            $cB = QuestionChoice::create(['question_id' => $q->id, 'label' => 'B', 'content' => 'Choice B', 'is_correct' => false]);
            $isWrong = in_array($i, $incorrectQuestionIndices, true);
            Answer::create([
                'attempt_id'         => $attempt->id,
                'question_id'        => $q->id,
                'selected_choice_id' => $isWrong ? $cB->id : $cA->id,
                'is_correct'         => !$isWrong,
                'score_earned'       => $isWrong ? 0 : 1,
            ]);
        }

        $groupAudioFile = UploadedFile::fake()->create('group_audio_3.mp3', 300, 'audio/mpeg');
        $groupAudioPath = $groupAudioFile->store('media', 'public');
        $groupAudioAsset = MediaAsset::create([
            'title'         => 'Group Audio Conversation 1',
            'filename'      => 'group_audio_3.mp3',
            'original_name' => 'group_audio_3.mp3',
            'path'          => $groupAudioPath,
            'mime_type'     => 'audio/mpeg',
            'size'          => 30720,
            'type'          => 'audio',
            'status'        => 'active',
            'uploaded_by'   => $this->teacher->id,
        ]);

        $audioGroup = AudioGroup::create([
            'title'          => 'Office Schedule Discussion',
            'media_asset_id' => $groupAudioAsset->id,
            'group_type'     => 'conversation',
        ]);

        $groupQuestions = [];
        for ($i = 16; $i <= 18; $i++) {
            $q = Question::create([
                'prompt'         => "What does the speaker imply about topic {$i}?",
                'question_type'  => QuestionType::MultipleChoice,
                'points'         => 1,
                'section'        => SectionType::Listening,
                'part_number'    => 3,
                'audio_group_id' => $audioGroup->id,
                'explanation'    => "Explanation for Group question {$i}",
            ]);
            TestQuestion::create(['test_section_id' => $secL->id, 'question_id' => $q->id, 'order' => $i]);
            $cA = QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => "Option A for Q{$i}", 'is_correct' => ($i !== 17)]);
            $cB = QuestionChoice::create(['question_id' => $q->id, 'label' => 'B', 'content' => "Option B for Q{$i}", 'is_correct' => ($i === 17)]);
            $cC = QuestionChoice::create(['question_id' => $q->id, 'label' => 'C', 'content' => "Option C for Q{$i}", 'is_correct' => false]);
            $cD = QuestionChoice::create(['question_id' => $q->id, 'label' => 'D', 'content' => "Option D for Q{$i}", 'is_correct' => false]);

            $isWrong = in_array($i, $incorrectQuestionIndices, true);
            Answer::create([
                'attempt_id'         => $attempt->id,
                'question_id'        => $q->id,
                'selected_choice_id' => $isWrong ? $cC->id : ($i === 17 ? $cB->id : $cA->id),
                'is_correct'         => !$isWrong,
                'score_earned'       => $isWrong ? 0 : 1,
            ]);
            $groupQuestions[$i] = $q;
        }

        // 5. Fill intermediate questions Q19–Q57
        for ($i = 19; $i <= 57; $i++) {
            $q = Question::create([
                'prompt'        => "Reading Prompt {$i}",
                'question_type' => QuestionType::MultipleChoice,
                'points'        => 1,
                'section'       => SectionType::Reading,
                'part_number'   => 5,
            ]);
            TestQuestion::create(['test_section_id' => $secR->id, 'question_id' => $q->id, 'order' => $i]);
            $cA = QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => 'Choice A', 'is_correct' => true]);
            $cB = QuestionChoice::create(['question_id' => $q->id, 'label' => 'B', 'content' => 'Choice B', 'is_correct' => false]);
            $isWrong = in_array($i, $incorrectQuestionIndices, true);
            Answer::create([
                'attempt_id'         => $attempt->id,
                'question_id'        => $q->id,
                'selected_choice_id' => $isWrong ? $cB->id : $cA->id,
                'is_correct'         => !$isWrong,
                'score_earned'       => $isWrong ? 0 : 1,
            ]);
        }

        // 6. Part 7 PassageGroup (Q58–Q61)
        $passageGroup = PassageGroup::create([
            'title'        => 'Email Regarding Annual Conference',
            'part_number'  => 7,
            'passage_type' => 'single',
        ]);
        $passage = Passage::create([
            'passage_group_id' => $passageGroup->id,
            'title'            => 'Conference Schedule',
            'content'          => 'The annual corporate conference will take place next Friday in Hall B.',
            'document_type'    => 'email',
            'order_in_group'   => 1,
        ]);

        for ($i = 58; $i <= 61; $i++) {
            $q = Question::create([
                'prompt'           => "According to the email, what will happen at {$i}:00?",
                'question_type'    => QuestionType::MultipleChoice,
                'points'           => 1,
                'section'          => SectionType::Reading,
                'part_number'      => 7,
                'passage_group_id' => $passageGroup->id,
                'explanation'      => "Explanation for passage question {$i}",
            ]);
            TestQuestion::create(['test_section_id' => $secR->id, 'question_id' => $q->id, 'order' => $i]);
            $cA = QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => "Passage choice A for {$i}", 'is_correct' => ($i !== 59)]);
            $cB = QuestionChoice::create(['question_id' => $q->id, 'label' => 'B', 'content' => "Passage choice B for {$i}", 'is_correct' => ($i === 59)]);
            $cC = QuestionChoice::create(['question_id' => $q->id, 'label' => 'C', 'content' => "Passage choice C for {$i}", 'is_correct' => false]);

            $isWrong = in_array($i, $incorrectQuestionIndices, true);
            Answer::create([
                'attempt_id'         => $attempt->id,
                'question_id'        => $q->id,
                'selected_choice_id' => $isWrong ? $cC->id : ($i === 59 ? $cB->id : $cA->id),
                'is_correct'         => !$isWrong,
                'score_earned'       => $isWrong ? 0 : 1,
            ]);
        }

        // 7. Q62–Q68
        for ($i = 62; $i <= 68; $i++) {
            $q = Question::create([
                'prompt'        => "Reading Question {$i}",
                'question_type' => QuestionType::MultipleChoice,
                'points'        => 1,
                'section'       => SectionType::Reading,
                'part_number'   => 7,
            ]);
            TestQuestion::create(['test_section_id' => $secR->id, 'question_id' => $q->id, 'order' => $i]);
            $cA = QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => 'Choice A', 'is_correct' => true]);
            $cB = QuestionChoice::create(['question_id' => $q->id, 'label' => 'B', 'content' => 'Choice B', 'is_correct' => false]);
            $isWrong = in_array($i, $incorrectQuestionIndices, true);
            Answer::create([
                'attempt_id'         => $attempt->id,
                'question_id'        => $q->id,
                'selected_choice_id' => $isWrong ? $cB->id : $cA->id,
                'is_correct'         => !$isWrong,
                'score_earned'       => $isWrong ? 0 : 1,
            ]);
        }

        $correctCount = 68 - count($incorrectQuestionIndices);
        $attempt->update(['total_score' => $correctCount]);

        return [
            'test'        => $test,
            'attempt'     => $attempt,
            'photoAsset'  => $photoAsset,
            'audioAsset'  => $audioAsset,
            'audioGroup'  => $audioGroup,
            'passage'     => $passage,
        ];
    }

    /**
     * TEST REVIEW-01: Incorrect Answers KPI links to Simulator wrong-answer review when incorrect_count > 0.
     */
    public function test_review_01_incorrect_answers_kpi_links_to_wrong_answers_review()
    {
        $fixture = $this->createToeicSimulatorFixture([17, 59]);
        $attempt = $fixture['attempt'];

        $response = $this->actingAs($this->candidate1)
            ->get(route('candidate.review', $attempt));

        $response->assertStatus(200);
        $response->assertSee(route('candidate.simulator.wrong-answers', $attempt));
        $response->assertSee('Review incorrect items');
    }

    /**
     * TEST REVIEW-02 & 03: Review contains only incorrect delivery units and excludes standalone correct questions.
     */
    public function test_review_02_and_03_review_contains_only_incorrect_delivery_units()
    {
        $fixture = $this->createToeicSimulatorFixture([17, 59]);
        $attempt = $fixture['attempt'];

        $response = $this->actingAs($this->candidate1)
            ->get(route('candidate.simulator.wrong-answers', $attempt));

        $response->assertStatus(200);
        $response->assertSee('Wrong Answer Review');
        $response->assertSeeText('Reviewing 2 incorrect questions');

        // Q1 is a standalone correct question -> its prompt must NOT appear in wrong-answers review
        $response->assertDontSee('Look at the photograph and choose the best statement.');

        // Q2 is a standalone correct question -> must NOT appear
        $response->assertDontSee('Part 2 Question Audio Prompt');

        // Q17 (inside AudioGroup) and Q59 (inside PassageGroup) MUST appear
        $response->assertSee('Office Schedule Discussion');
        $response->assertSee('EMAIL REGARDING ANNUAL CONFERENCE');
        $response->assertSee('Q17');
        $response->assertSee('Q59');
    }

    /**
     * TEST REVIEW-04 & 05: Security - Candidate can review own attempt but unauthorized candidate gets 403.
     */
    public function test_review_04_and_05_authorization_boundaries()
    {
        $fixture = $this->createToeicSimulatorFixture([17]);
        $attempt = $fixture['attempt'];

        // Candidate 1 (owner) -> 200 OK
        $responseOwner = $this->actingAs($this->candidate1)
            ->get(route('candidate.simulator.wrong-answers', $attempt));
        $responseOwner->assertStatus(200);

        // Candidate 2 (different user) -> 403 Forbidden
        $responseOther = $this->actingAs($this->candidate2)
            ->get(route('candidate.simulator.wrong-answers', $attempt));
        $responseOther->assertStatus(403);
    }

    /**
     * TEST REVIEW-06, 07, 08: Security - In-progress, Mock Test, and Real Test attempts cannot access wrong answers review.
     */
    public function test_review_06_07_08_in_progress_and_mock_tests_cannot_access_review()
    {
        $fixture = $this->createToeicSimulatorFixture([17]);
        $attempt = $fixture['attempt'];

        // 1. In-progress attempt -> 403 Forbidden
        $attempt->update(['status' => AttemptStatus::InProgress]);
        $responseInProgress = $this->actingAs($this->candidate1)
            ->get(route('candidate.simulator.wrong-answers', $attempt));
        $responseInProgress->assertStatus(403);

        // 2. RealTest mode (Mock Test) -> 403 Forbidden
        $attempt->update(['status' => AttemptStatus::Submitted]);
        $fixture['test']->update(['assessment_mode' => AssessmentMode::RealTest]);
        $responseMock = $this->actingAs($this->candidate1)
            ->get(route('candidate.simulator.wrong-answers', $attempt));
        $responseMock->assertStatus(403);
    }

    /**
     * TEST REVIEW-09: Canonical question numbering (AGN) is preserved.
     */
    public function test_review_09_canonical_agn_is_preserved()
    {
        $fixture = $this->createToeicSimulatorFixture([17, 59]);
        $attempt = $fixture['attempt'];

        $response = $this->actingAs($this->candidate1)
            ->get(route('candidate.simulator.wrong-answers', $attempt));

        $response->assertStatus(200);
        $response->assertSee('Q17');
        $response->assertSee('Q59');
    }

    /**
     * TEST REVIEW-10, 11, 12: AudioGroup context preserved, shared audio renders, CandidateExamAudioManager active.
     */
    public function test_review_10_11_12_audiogroup_context_and_audio_lifecycle()
    {
        $fixture = $this->createToeicSimulatorFixture([17]);
        $attempt = $fixture['attempt'];

        $response = $this->actingAs($this->candidate1)
            ->get(route('candidate.simulator.wrong-answers', $attempt));

        $response->assertStatus(200);
        $response->assertSee('Office Schedule Discussion');
        $response->assertSee('data-exam-audio="true"', false);
        $response->assertSee('CandidateExamAudioManager');
        $response->assertSee('Shared Conversation Audio');
    }

    /**
     * TEST REVIEW-13, 14, 15: PassageGroup context preserved, passage content rendered, wrong child highlighted.
     */
    public function test_review_13_14_15_passagegroup_context_and_highlight()
    {
        $fixture = $this->createToeicSimulatorFixture([59]);
        $attempt = $fixture['attempt'];

        $response = $this->actingAs($this->candidate1)
            ->get(route('candidate.simulator.wrong-answers', $attempt));

        $response->assertStatus(200);
        $response->assertSee('EMAIL REGARDING ANNUAL CONFERENCE');
        $response->assertSee('The annual corporate conference will take place next Friday in Hall B.');
        $response->assertSee('Q59');
        $response->assertSee('INCORRECT');
    }

    /**
     * TEST REVIEW-16, 17, 18: Part 1 photograph & Part 2 Candidate vs Correct answers.
     */
    public function test_review_16_17_18_part1_and_part2_media_and_answer_visualization()
    {
        // Misanswer Q1 (Part 1) and Q2 (Part 2)
        $fixture = $this->createToeicSimulatorFixture([1, 2]);
        $attempt = $fixture['attempt'];

        $response = $this->actingAs($this->candidate1)
            ->get(route('candidate.simulator.wrong-answers', $attempt));

        $response->assertStatus(200);
        // Part 1 Image
        $response->assertSee(route('media.preview', $fixture['photoAsset']->id));
        // Audio streaming routes
        $response->assertSee('candidate/exam/' . $attempt->id . '/questions/', false);
        // Candidate Answer vs Correct Answer
        $response->assertSee('Your Answer (Incorrect)');
        $response->assertSee('Correct Answer');
        $response->assertSee('Explanation &amp; Rationale', false);
    }

    /**
     * TEST REVIEW-19: Perfect score (0 incorrect answers) displays clean non-broken state.
     */
    public function test_review_19_zero_incorrect_answers_displays_clean_state()
    {
        // 0 incorrect answers
        $fixture = $this->createToeicSimulatorFixture([]);
        $attempt = $fixture['attempt'];

        // Result page renders non-actionable 0 state
        $responseResult = $this->actingAs($this->candidate1)
            ->get(route('candidate.review', $attempt));
        $responseResult->assertStatus(200);
        $responseResult->assertSee('No review required');

        // Accessing wrong answers directly displays celebratory perfect score state
        $responseReview = $this->actingAs($this->candidate1)
            ->get(route('candidate.simulator.wrong-answers', $attempt));
        $responseReview->assertStatus(200);
        $responseReview->assertSee('Perfect Score!');
    }
}
