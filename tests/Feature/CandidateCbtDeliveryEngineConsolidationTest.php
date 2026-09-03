<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\MediaAsset;
use App\Modules\Assessment\Engines\RandomizationEngine;
use App\Modules\Assessment\Enums\AttemptStatus;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\AttemptAudioPlay;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\Assessment\Models\TestQuestion;
use App\Modules\Assessment\Services\DeliveryUnitBuilder;
use App\Modules\QuestionBank\Models\AudioGroup;
use App\Modules\QuestionBank\Models\Passage;
use App\Modules\QuestionBank\Models\PassageGroup;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionChoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CandidateCbtDeliveryEngineConsolidationTest extends TestCase
{
    use RefreshDatabase;

    protected User $candidate;
    protected Test $toeicTest;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('student');
        Role::findOrCreate('candidate');

        $this->candidate = User::factory()->create([
            'theme_preference' => 'light',
        ]);
        $this->candidate->assignRole('student');
    }

    /**
     * Helper to construct a realistic 7-Part TOEIC test specimen.
     */
    protected function createRealisticToeicTest(): Test
    {
        $test = Test::create([
            'title' => 'TOEIC Listening & Reading Simulation Test Specimen',
            'slug' => 'toeic-listening-reading-simulation-test-' . uniqid(),
            'test_type' => 'toeic',
            'status' => 'published',
            'is_published' => true,
            'shuffle_questions' => true, // Legacy flag deliberately true to test invariant
            'shuffle_choices' => true,
            'duration_minutes' => 120,
            'created_by' => $this->candidate->id,
        ]);

        $partSpecs = [
            1 => ['title' => 'Part 1: Photographs', 'type' => 'listening', 'count' => 6],
            2 => ['title' => 'Part 2: Question-Response', 'type' => 'listening', 'count' => 5],
            3 => ['title' => 'Part 3: Conversations', 'type' => 'listening', 'groups' => 2, 'q_per_group' => 3],
            4 => ['title' => 'Part 4: Talks', 'type' => 'listening', 'groups' => 2, 'q_per_group' => 3],
            5 => ['title' => 'Part 5: Incomplete Sentences', 'type' => 'reading', 'count' => 10],
            6 => ['title' => 'Part 6: Text Completion', 'type' => 'reading', 'groups' => 1, 'q_per_group' => 4],
            7 => ['title' => 'Part 7: Reading Comprehension', 'type' => 'reading', 'groups' => 2, 'q_per_group' => 3],
        ];

        $globalIndex = 1;

        foreach ($partSpecs as $partNumber => $spec) {
            $section = TestSection::create([
                'test_id' => $test->id,
                'title' => $spec['title'],
                'section_type' => $spec['type'],
                'order' => $partNumber,
                'instructions' => "Instructions for Part {$partNumber}",
            ]);

            if (isset($spec['count'])) {
                for ($i = 1; $i <= $spec['count']; $i++) {
                    $mediaAsset = null;
                    if ($partNumber === 1) {
                        $mediaAsset = MediaAsset::create([
                            'filename' => "p1_{$i}.jpg",
                            'original_name' => "p1_{$i}.jpg",
                            'size_bytes' => 1024,
                            'title' => "Part 1 Image {$i}",
                            'type' => 'image',
                            'mime_type' => 'image/jpeg',
                            'path' => "test_media/p1_{$i}.jpg",
                            'uploaded_by' => $this->candidate->id,
                        ]);
                    }

                    $question = Question::create([
                        'created_by' => $this->candidate->id,
                        'test_type' => 'toeic',
                        'part_number' => $partNumber,
                        'question_type' => 'multiple_choice',
                        'prompt' => $partNumber === 1 ? 'Look at the photograph and choose the best statement.' : "Question {$globalIndex} prompt",
                        'media_asset_id' => $mediaAsset?->id,
                        'audio_url' => $partNumber <= 2 ? "audio/p{$partNumber}_{$i}.mp3" : null,
                    ]);

                    $numChoices = ($partNumber === 2) ? 3 : 4;
                    $labels = ['A', 'B', 'C', 'D'];
                    for ($c = 0; $c < $numChoices; $c++) {
                        QuestionChoice::create([
                            'question_id' => $question->id,
                            'label' => $labels[$c],
                            'content' => "Option {$labels[$c]} for Q{$globalIndex}",
                            'is_correct' => ($c === 0),
                        ]);
                    }

                    TestQuestion::create([
                        'test_id' => $test->id,
                        'test_section_id' => $section->id,
                        'question_id' => $question->id,
                        'order' => $i,
                        'score' => 5,
                    ]);

                    $globalIndex++;
                }
            } elseif (isset($spec['groups'])) {
                for ($g = 1; $g <= $spec['groups']; $g++) {
                    if ($partNumber === 3 || $partNumber === 4) {
                        $audioGroup = AudioGroup::create([
                            'created_by' => $this->candidate->id,
                            'title' => "Part {$partNumber} Audio Stimulus {$g}",
                            'audio_url' => "audio/p{$partNumber}_group_{$g}.mp3",
                            'group_type' => $partNumber === 3 ? 'conversation' : 'talk',
                        ]);

                        for ($q = 1; $q <= $spec['q_per_group']; $q++) {
                            $question = Question::create([
                                'created_by' => $this->candidate->id,
                                'test_type' => 'toeic',
                                'part_number' => $partNumber,
                                'audio_group_id' => $audioGroup->id,
                                'question_type' => 'multiple_choice',
                                'prompt' => "Group {$g} Question {$q} prompt",
                            ]);

                            foreach (['A', 'B', 'C', 'D'] as $cIdx => $lbl) {
                                QuestionChoice::create([
                                    'question_id' => $question->id,
                                    'label' => $lbl,
                                    'content' => "Option {$lbl} for Group {$g} Q{$q}",
                                    'is_correct' => ($cIdx === 0),
                                ]);
                            }

                            TestQuestion::create([
                                'test_id' => $test->id,
                                'test_section_id' => $section->id,
                                'question_id' => $question->id,
                                'order' => (($g - 1) * $spec['q_per_group']) + $q,
                                'score' => 5,
                            ]);

                            $globalIndex++;
                        }
                    } elseif ($partNumber === 6 || $partNumber === 7) {
                        $passageGroup = PassageGroup::create([
                            'created_by' => $this->candidate->id,
                            'title' => "Part {$partNumber} Passage Set {$g}",
                            'part_number' => $partNumber,
                            'passage_type' => $partNumber === 6 ? 'single' : ($g === 1 ? 'single' : 'double'),
                        ]);

                        Passage::create([
                            'created_by' => $this->candidate->id,
                            'passage_group_id' => $passageGroup->id,
                            'title' => "Passage {$g} Doc 1",
                            'content' => "Text for Passage {$g} Document 1",
                            'order_in_group' => 1,
                        ]);

                        for ($q = 1; $q <= $spec['q_per_group']; $q++) {
                            $question = Question::create([
                                'created_by' => $this->candidate->id,
                                'test_type' => 'toeic',
                                'part_number' => $partNumber,
                                'passage_group_id' => $passageGroup->id,
                                'question_type' => 'multiple_choice',
                                'prompt' => $partNumber === 6 ? '' : "Passage {$g} Q{$q} prompt",
                            ]);

                            foreach (['A', 'B', 'C', 'D'] as $cIdx => $lbl) {
                                QuestionChoice::create([
                                    'question_id' => $question->id,
                                    'label' => $lbl,
                                    'content' => "Option {$lbl} for Passage {$g} Q{$q}",
                                    'is_correct' => ($cIdx === 0),
                                ]);
                            }

                            TestQuestion::create([
                                'test_id' => $test->id,
                                'test_section_id' => $section->id,
                                'question_id' => $question->id,
                                'order' => (($g - 1) * $spec['q_per_group']) + $q,
                                'score' => 5,
                            ]);

                            $globalIndex++;
                        }
                    }
                }
            }
        }

        return $test;
    }

    /**
     * Test 1: RandomizationEngine respects standardized TOEIC tests and disallows question shuffling.
     */
    public function test_randomization_engine_disallows_question_shuffling_for_standardized_tests(): void
    {
        $test = $this->createRealisticToeicTest();
        $attempt = Attempt::create([
            'test_id' => $test->id,
            'user_id' => $this->candidate->id,
            'status' => AttemptStatus::InProgress,
        ]);

        $randomEngine = app(RandomizationEngine::class);
        $this->assertFalse($randomEngine->isQuestionShuffleAllowed($attempt));
    }

    /**
     * Test 2: DeliveryUnitBuilder produces canonical sequential Actual Global Numbering (AGN 1..N).
     */
    public function test_delivery_unit_builder_produces_canonical_agn_and_atomic_groups(): void
    {
        $test = $this->createRealisticToeicTest();
        $attempt = Attempt::create([
            'test_id' => $test->id,
            'user_id' => $this->candidate->id,
            'status' => AttemptStatus::InProgress,
        ]);

        $deliveryData = DeliveryUnitBuilder::build($test, $attempt);

        $this->assertArrayHasKey('deliveryUnits', $deliveryData);
        $this->assertArrayHasKey('questions', $deliveryData);
        $this->assertArrayHasKey('totalQuestionsCount', $deliveryData);

        $questions = $deliveryData['questions'];
        $this->assertCount(43, $questions); // 6 + 5 + 6 + 6 + 10 + 4 + 6 = 43

        // Verify sequential AGN 1..43
        foreach ($questions as $idx => $q) {
            $expectedAgn = $idx + 1;
            $this->assertEquals($expectedAgn, $q->canonical_global_number);
            $this->assertEquals($expectedAgn, $q->agn);
        }

        // Verify delivery unit types
        $units = $deliveryData['deliveryUnits'];
        $firstUnit = $units->first();
        $this->assertEquals('question', $firstUnit['type']);
        $this->assertEquals(1, $firstUnit['part_number']);

        // Part 3 audio group
        $p3Unit = $units->firstWhere('part_number', 3);
        $this->assertNotNull($p3Unit);
        $this->assertEquals('audio_group', $p3Unit['type']);
        $this->assertCount(3, $p3Unit['questions']);

        // Part 6 passage group
        $p6Unit = $units->firstWhere('part_number', 6);
        $this->assertNotNull($p6Unit);
        $this->assertEquals('passage_group', $p6Unit['type']);
        $this->assertCount(4, $p6Unit['questions']);

        // Part 7 passage group
        $p7Unit = $units->firstWhere('part_number', 7);
        $this->assertNotNull($p7Unit);
        $this->assertEquals('passage_group', $p7Unit['type']);
    }

    /**
     * Test 3: Section boundary calculations (first / last unit flags) are exact.
     */
    public function test_section_boundary_flags_are_computed_accurately(): void
    {
        $test = $this->createRealisticToeicTest();
        $attempt = Attempt::create([
            'test_id' => $test->id,
            'user_id' => $this->candidate->id,
            'status' => AttemptStatus::InProgress,
        ]);

        $deliveryData = DeliveryUnitBuilder::build($test, $attempt);
        $units = $deliveryData['deliveryUnits'];

        // Group units by section
        $groupedBySection = $units->groupBy('section_id');

        foreach ($groupedBySection as $sectionId => $sectionUnits) {
            $count = $sectionUnits->count();
            foreach ($sectionUnits as $pos => $u) {
                if ($pos === 0) {
                    $this->assertTrue($u['is_first_unit_of_section'], "Unit {$u['index']} should be first of section");
                } else {
                    $this->assertFalse($u['is_first_unit_of_section'], "Unit {$u['index']} should not be first of section");
                }

                if ($pos === $count - 1) {
                    $this->assertTrue($u['is_last_unit_of_section'], "Unit {$u['index']} should be last of section");
                } else {
                    $this->assertFalse($u['is_last_unit_of_section'], "Unit {$u['index']} should not be last of section");
                }
            }
        }
    }

    /**
     * Test 4: Candidate exam screen renders successfully with adaptive theme and canonical question units.
     */
    public function test_candidate_exam_screen_renders_successfully(): void
    {
        $test = $this->createRealisticToeicTest();
        $attempt = Attempt::create([
            'test_id' => $test->id,
            'user_id' => $this->candidate->id,
            'status' => AttemptStatus::InProgress,
        ]);

        $response = $this->actingAs($this->candidate)->get(route('candidate.exam', $attempt));

        $response->assertStatus(200);
        $response->assertSee('CBT Examination Session');
        $response->assertSee('Question Navigation');
        $response->assertSee('TEST SIMULATOR');
        // Check data-theme attribute
        $response->assertSee('data-theme="light"', false);
    }

    /**
     * Test 5: AutoSave API saves answer choice asynchronously and preserves state.
     */
    public function test_autosave_api_persists_candidate_response(): void
    {
        $test = $this->createRealisticToeicTest();
        $attempt = Attempt::create([
            'test_id' => $test->id,
            'user_id' => $this->candidate->id,
            'status' => AttemptStatus::InProgress,
        ]);

        $question = $test->sections->first()->testQuestions->first()->question;
        $choice = $question->choices->first();

        $response = $this->actingAs($this->candidate)->postJson(route('candidate.exam.autosave', $attempt), [
            'question_id' => $question->id,
            'selected_choice' => $choice->id,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'saved']);

        $this->assertDatabaseHas('answers', [
            'attempt_id' => $attempt->id,
            'question_id' => $question->id,
            'selected_choice_id' => $choice->id,
        ]);
    }

    /**
     * Test 6: Flag API toggles flag state on questions.
     */
    public function test_flag_api_toggles_flag_state(): void
    {
        $test = $this->createRealisticToeicTest();
        $attempt = Attempt::create([
            'test_id' => $test->id,
            'user_id' => $this->candidate->id,
            'status' => AttemptStatus::InProgress,
        ]);

        $question = $test->sections->first()->testQuestions->first()->question;

        // First toggle: flag question
        $response1 = $this->actingAs($this->candidate)->postJson(route('candidate.exam.flag', $attempt), [
            'question_id' => $question->id,
        ]);
        $response1->assertStatus(200);
        $response1->assertJson(['status' => 'flagged']);
        $this->assertContains($question->id, $attempt->fresh()->flagged_questions ?? []);

        // Second toggle: unflag question
        $response2 = $this->actingAs($this->candidate)->postJson(route('candidate.exam.flag', $attempt), [
            'question_id' => $question->id,
        ]);
        $response2->assertStatus(200);
        $response2->assertJson(['status' => 'flagged']);
        $this->assertNotContains($question->id, $attempt->fresh()->flagged_questions ?? []);
    }
}
