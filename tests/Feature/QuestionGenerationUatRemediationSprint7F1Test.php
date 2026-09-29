<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\QuestionBank\Enums\TestType;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Modules\QuestionEngine\Contracts\QuestionGenerationProvider;
use App\Modules\QuestionEngine\DTO\GeneratedQuestionCandidate;
use App\Modules\QuestionEngine\DTO\GenerationProviderRequest;
use App\Modules\QuestionEngine\DTO\GenerationProviderResponse;
use App\Modules\QuestionEngine\DTO\ToeicBlueprintRequest;
use App\Modules\QuestionEngine\Enums\AssessmentFamily;
use App\Modules\QuestionEngine\Enums\GenerationBatchStatus;
use App\Modules\QuestionEngine\Enums\GenerationItemStatus;
use App\Modules\QuestionEngine\Enums\StandardStatus;
use App\Modules\QuestionEngine\Models\AssessmentStandard;
use App\Modules\QuestionEngine\Models\QuestionGenerationBatch;
use App\Modules\QuestionEngine\Models\QuestionGenerationItem;
use App\Modules\QuestionEngine\Services\GeneratedAnswerChoicePositioner;
use App\Modules\QuestionEngine\Services\QuestionGenerationOrchestrator;
use App\Modules\QuestionEngine\Services\ToeicBlueprintPlanner;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuestionGenerationUatRemediationSprint7F1Test extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;

    protected User $otherTeacher;

    protected AssessmentStandard $toeicStandard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->teacher = User::factory()->create(['status' => 'active']);
        $this->teacher->assignRole('teacher');

        $this->otherTeacher = User::factory()->create(['status' => 'active']);
        $this->otherTeacher->assignRole('teacher');

        $this->toeicStandard = AssessmentStandard::create([
            'assessment_family' => AssessmentFamily::Toeic,
            'standard_code' => 'TOEIC-2026.1',
            'version' => '2026.1',
            'title' => 'TOEIC Standard 2026.1',
            'provider' => 'ETS',
            'status' => StandardStatus::Active,
            'structure_definition' => ['sections' => ['listening', 'reading']],
        ]);
    }

    // =========================================================================
    // FINDING 7F-01: PRESERVE LIST STATE AFTER DELETE
    // =========================================================================

    public function test_7f01_delete_draft_from_filtered_list_preserves_draft_status(): void
    {
        $bank = QuestionBank::create([
            'title' => 'Draft Bank to Delete',
            'slug' => 'draft-bank-to-delete',
            'status' => 'draft',
            'test_type' => TestType::Toeic,
            'created_by' => $this->teacher->id,
        ]);

        $response = $this->actingAs($this->teacher)
            ->delete(route('admin.question-banks.destroy', [
                'questionBank' => $bank->id,
                'status' => 'draft',
            ]));

        $response->assertRedirect(route('admin.question-banks.index', ['status' => 'draft']));
        $this->assertSoftDeleted('question_banks', ['id' => $bank->id]);
    }

    public function test_7f01_delete_draft_preserves_search_parameter(): void
    {
        $bank = QuestionBank::create([
            'title' => 'Grammar Review Draft',
            'slug' => 'grammar-review-draft',
            'status' => 'draft',
            'test_type' => TestType::Toeic,
            'created_by' => $this->teacher->id,
        ]);

        $response = $this->actingAs($this->teacher)
            ->delete(route('admin.question-banks.destroy', [
                'questionBank' => $bank->id,
                'search' => 'Grammar',
            ]));

        $response->assertRedirect(route('admin.question-banks.index', ['search' => 'Grammar']));
        $this->assertSoftDeleted('question_banks', ['id' => $bank->id]);
    }

    public function test_7f01_delete_draft_preserves_sort_parameter(): void
    {
        $bank = QuestionBank::create([
            'title' => 'Draft Bank Alphabetical',
            'slug' => 'draft-bank-alphabetical',
            'status' => 'draft',
            'test_type' => TestType::Toeic,
            'created_by' => $this->teacher->id,
        ]);

        $response = $this->actingAs($this->teacher)
            ->delete(route('admin.question-banks.destroy', [
                'questionBank' => $bank->id,
                'sort' => 'created',
            ]));

        $response->assertRedirect(route('admin.question-banks.index', ['sort' => 'created']));
        $this->assertSoftDeleted('question_banks', ['id' => $bank->id]);
    }

    public function test_7f01_delete_draft_preserves_combined_filter_state(): void
    {
        $bank = QuestionBank::create([
            'title' => 'Complex Filter Draft',
            'slug' => 'complex-filter-draft',
            'status' => 'draft',
            'test_type' => TestType::Toeic,
            'created_by' => $this->teacher->id,
        ]);

        $response = $this->actingAs($this->teacher)
            ->delete(route('admin.question-banks.destroy', [
                'questionBank' => $bank->id,
                'status' => 'draft',
                'search' => 'Complex',
                'sort' => 'alphabetical',
                'test_type' => 'toeic',
                'page' => 2,
            ]));

        $response->assertRedirect(route('admin.question-banks.index', [
            'status' => 'draft',
            'search' => 'Complex',
            'sort' => 'alphabetical',
            'page' => 2,
            'test_type' => 'toeic',
        ]));
        $this->assertSoftDeleted('question_banks', ['id' => $bank->id]);
    }

    public function test_7f01_delete_draft_via_referer_header_preserves_query_state(): void
    {
        $bank = QuestionBank::create([
            'title' => 'Referer Preserved Draft',
            'slug' => 'referer-preserved-draft',
            'status' => 'draft',
            'test_type' => TestType::Toeic,
            'created_by' => $this->teacher->id,
        ]);

        $response = $this->actingAs($this->teacher)
            ->from(url('/admin/question-banks?status=draft&sort=updated'))
            ->delete(route('admin.question-banks.destroy', $bank->id));

        $response->assertRedirect(route('admin.question-banks.index', [
            'status' => 'draft',
            'sort' => 'updated',
        ]));
        $this->assertSoftDeleted('question_banks', ['id' => $bank->id]);
    }

    public function test_7f01_delete_draft_rejects_external_open_redirect_urls(): void
    {
        $bank = QuestionBank::create([
            'title' => 'Open Redirect Test Draft',
            'slug' => 'open-redirect-test-draft',
            'status' => 'draft',
            'test_type' => TestType::Toeic,
            'created_by' => $this->teacher->id,
        ]);

        $response = $this->actingAs($this->teacher)
            ->delete(route('admin.question-banks.destroy', [
                'questionBank' => $bank->id,
                'return_url' => 'https://evil.com/phish?param=1',
            ]));

        // Must redirect internally to IAP question-banks.index and NOT external URL
        $response->assertRedirect(route('admin.question-banks.index'));
        $this->assertFalse(str_contains($response->headers->get('Location'), 'evil.com'));
    }

    // =========================================================================
    // FINDING 7F-02: SYSTEM-CONTROLLED ANSWER POSITIONING
    // =========================================================================

    public function test_7f02_positioner_repositions_correct_answer_from_a_to_target_index(): void
    {
        $positioner = new GeneratedAnswerChoicePositioner;

        $candidate = new GeneratedQuestionCandidate(
            schemaVersion: 'generated_question_candidate_v1',
            prompt: 'Ms. Sato ______ the quarterly financial report yesterday.',
            choices: [
                ['label' => 'A', 'content' => 'submitted', 'is_correct' => true, 'explanation' => 'Correct past tense verb.'],
                ['label' => 'B', 'content' => 'submits', 'is_correct' => false, 'explanation' => 'Incorrect present tense.'],
                ['label' => 'C', 'content' => 'submitting', 'is_correct' => false, 'explanation' => 'Incorrect participle.'],
                ['label' => 'D', 'content' => 'submission', 'is_correct' => false, 'explanation' => 'Incorrect noun.'],
            ],
            correctAnswer: 'A',
            assessmentFamily: AssessmentFamily::Toeic,
            partNumber: 5
        );

        $item = new QuestionGenerationItem([
            'id' => '01JITEM00000000000000000001',
            'generation_batch_id' => '01JBATCH0000000000000000001',
            'slot_sequence' => 2,
        ]);

        $repositioned = $positioner->reposition($candidate, $item);

        $this->assertTrue($repositioned->isMultipleChoice());
        $this->assertCount(4, $repositioned->choices);

        // Exactly one correct choice
        $correctChoices = array_filter($repositioned->choices, fn ($c) => !empty($c['is_correct']));
        $this->assertCount(1, $correctChoices);

        $correct = reset($correctChoices);
        $this->assertSame('submitted', $correct['content']);
        $this->assertSame('Correct past tense verb.', $correct['explanation']);
        $this->assertSame($repositioned->correctAnswer, $correct['label']);
        $this->assertContains($correct['label'], ['A', 'B', 'C', 'D']);

        // Canonical sequential labels
        $labels = array_column($repositioned->choices, 'label');
        $this->assertSame(['A', 'B', 'C', 'D'], $labels);

        // Distractors maintain their explanations
        foreach ($repositioned->choices as $c) {
            if ($c['content'] === 'submits') {
                $this->assertFalse($c['is_correct']);
                $this->assertSame('Incorrect present tense.', $c['explanation']);
            }
            if ($c['content'] === 'submission') {
                $this->assertFalse($c['is_correct']);
                $this->assertSame('Incorrect noun.', $c['explanation']);
            }
        }
    }

    public function test_7f02_positioner_repositions_correct_answer_from_b_correctly(): void
    {
        $positioner = new GeneratedAnswerChoicePositioner;

        $candidate = new GeneratedQuestionCandidate(
            schemaVersion: 'generated_question_candidate_v1',
            prompt: 'The new office furniture will be ______ tomorrow.',
            choices: [
                ['label' => 'A', 'content' => 'deliver', 'is_correct' => false, 'explanation' => 'Incorrect base form.'],
                ['label' => 'B', 'content' => 'delivered', 'is_correct' => true, 'explanation' => 'Correct passive participle.'],
                ['label' => 'C', 'content' => 'delivering', 'is_correct' => false, 'explanation' => 'Incorrect active participle.'],
                ['label' => 'D', 'content' => 'delivery', 'is_correct' => false, 'explanation' => 'Incorrect noun.'],
            ],
            correctAnswer: 'B',
            assessmentFamily: AssessmentFamily::Toeic,
            partNumber: 5
        );

        $item = new QuestionGenerationItem([
            'id' => '01JITEM00000000000000000002',
            'generation_batch_id' => '01JBATCH0000000000000000001',
            'slot_sequence' => 1,
        ]);

        $repositioned = $positioner->reposition($candidate, $item);

        $correct = $repositioned->getCorrectChoice();
        $this->assertNotNull($correct);
        $this->assertSame('delivered', $correct['content']);
        $this->assertSame('Correct passive participle.', $correct['explanation']);
        $this->assertSame($repositioned->correctAnswer, $correct['label']);
    }

    public function test_7f02_deterministic_retry_produces_identical_target_position(): void
    {
        $positioner = new GeneratedAnswerChoicePositioner;

        $candidate = new GeneratedQuestionCandidate(
            schemaVersion: 'generated_question_candidate_v1',
            prompt: 'Test prompt for retry stability.',
            choices: [
                ['label' => 'A', 'content' => 'Option A', 'is_correct' => true],
                ['label' => 'B', 'content' => 'Option B', 'is_correct' => false],
                ['label' => 'C', 'content' => 'Option C', 'is_correct' => false],
                ['label' => 'D', 'content' => 'Option D', 'is_correct' => false],
            ],
            correctAnswer: 'A'
        );

        $item = new QuestionGenerationItem([
            'id' => '01JITEM_RETRY_STABLE_0001',
            'generation_batch_id' => '01JBATCH_STABLE_0000000001',
            'slot_sequence' => 3,
        ]);

        $run1 = $positioner->reposition($candidate, $item);
        $run2 = $positioner->reposition($candidate, $item);
        $run3 = $positioner->reposition($candidate, $item);

        $this->assertSame($run1->correctAnswer, $run2->correctAnswer);
        $this->assertSame($run2->correctAnswer, $run3->correctAnswer);
        $this->assertSame(array_column($run1->choices, 'content'), array_column($run2->choices, 'content'));
    }

    public function test_7f02_four_item_batch_covers_all_four_distinct_positions(): void
    {
        $positioner = new GeneratedAnswerChoicePositioner;
        $batchId = '01JBATCH_DISTRIBUTION_4ITEM';

        $correctPositions = [];

        for ($seq = 1; $seq <= 4; $seq++) {
            $candidate = new GeneratedQuestionCandidate(
                schemaVersion: 'generated_question_candidate_v1',
                prompt: "Question prompt {$seq}",
                choices: [
                    ['label' => 'A', 'content' => "Correct {$seq}", 'is_correct' => true],
                    ['label' => 'B', 'content' => "Distractor B {$seq}", 'is_correct' => false],
                    ['label' => 'C', 'content' => "Distractor C {$seq}", 'is_correct' => false],
                    ['label' => 'D', 'content' => "Distractor D {$seq}", 'is_correct' => false],
                ],
                correctAnswer: 'A'
            );

            $item = new QuestionGenerationItem([
                'id' => "01JITEM_4B_{$seq}",
                'generation_batch_id' => $batchId,
                'slot_sequence' => $seq,
            ]);

            $repositioned = $positioner->reposition($candidate, $item);
            $correctPositions[] = $repositioned->correctAnswer;
        }

        // Must cover all 4 positions A, B, C, D exactly once
        $uniquePositions = array_unique($correctPositions);
        sort($uniquePositions);
        $this->assertSame(['A', 'B', 'C', 'D'], $uniquePositions);
    }

    public function test_7f02_two_item_batch_produces_distinct_positions(): void
    {
        $positioner = new GeneratedAnswerChoicePositioner;
        $batchId = '01JBATCH_DISTRIBUTION_2ITEM';

        $candidateTemplate = fn ($i) => new GeneratedQuestionCandidate(
            schemaVersion: 'generated_question_candidate_v1',
            prompt: "Question prompt {$i}",
            choices: [
                ['label' => 'A', 'content' => 'Correct', 'is_correct' => true],
                ['label' => 'B', 'content' => 'Distractor 1', 'is_correct' => false],
                ['label' => 'C', 'content' => 'Distractor 2', 'is_correct' => false],
                ['label' => 'D', 'content' => 'Distractor 3', 'is_correct' => false],
            ],
            correctAnswer: 'A'
        );

        $item1 = new QuestionGenerationItem(['id' => 'item_1', 'generation_batch_id' => $batchId, 'slot_sequence' => 1]);
        $item2 = new QuestionGenerationItem(['id' => 'item_2', 'generation_batch_id' => $batchId, 'slot_sequence' => 2]);

        $rep1 = $positioner->reposition($candidateTemplate(1), $item1);
        $rep2 = $positioner->reposition($candidateTemplate(2), $item2);

        $this->assertNotSame($rep1->correctAnswer, $rep2->correctAnswer);
    }

    public function test_7f02_distinct_single_item_batches_are_not_structurally_fixed_to_a(): void
    {
        $positioner = new GeneratedAnswerChoicePositioner;
        $positions = [];

        for ($b = 1; $b <= 10; $b++) {
            $candidate = new GeneratedQuestionCandidate(
                schemaVersion: 'generated_question_candidate_v1',
                prompt: "Single item run {$b}",
                choices: [
                    ['label' => 'A', 'content' => 'Correct', 'is_correct' => true],
                    ['label' => 'B', 'content' => 'Distractor 1', 'is_correct' => false],
                    ['label' => 'C', 'content' => 'Distractor 2', 'is_correct' => false],
                    ['label' => 'D', 'content' => 'Distractor 3', 'is_correct' => false],
                ],
                correctAnswer: 'A'
            );

            $item = new QuestionGenerationItem([
                'id' => "item_single_{$b}",
                'generation_batch_id' => "batch_single_uuid_{$b}",
                'slot_sequence' => 1,
            ]);

            $rep = $positioner->reposition($candidate, $item);
            $positions[] = $rep->correctAnswer;
        }

        // Across 10 distinct batches, we should see multiple different target positions, not just 'A'
        $unique = array_unique($positions);
        $this->assertGreaterThan(1, count($unique));
    }

    public function test_7f02_full_pipeline_orchestration_repositions_choices_and_materializes_correctly(): void
    {
        $bank = QuestionBank::create([
            'title' => 'TOEIC Pipeline Bank',
            'slug' => 'toeic-pipeline-bank',
            'status' => 'draft',
            'test_type' => TestType::Toeic,
            'created_by' => $this->teacher->id,
        ]);

        $fakeProvider = new class implements QuestionGenerationProvider
        {
            public function getProviderName(): string
            {
                return 'mock_provider';
            }

            public function getSupportedCapabilities(): array
            {
                return ['multiple_choice'];
            }

            public function generate(GenerationProviderRequest $request): GenerationProviderResponse
            {
                // Always returns correct answer at 'A'
                return new GenerationProviderResponse(
                    isSuccess: true,
                    rawContent: json_encode([
                        'schema_version' => 'generated_question_candidate_v1',
                        'assessment_family' => 'toeic',
                        'standard_version' => '2026.1',
                        'section' => 'reading',
                        'part_number' => 5,
                        'prompt' => 'Mr. Tanaka ______ the meeting promptly at 9:00 AM.',
                        'choices' => [
                            ['label' => 'A', 'content' => 'started', 'is_correct' => true, 'explanation' => 'Correct past tense verb.'],
                            ['label' => 'B', 'content' => 'starting', 'is_correct' => false, 'explanation' => 'Participle.'],
                            ['label' => 'C', 'content' => 'start', 'is_correct' => false, 'explanation' => 'Base form.'],
                            ['label' => 'D', 'content' => 'starts', 'is_correct' => false, 'explanation' => 'Present tense.'],
                        ],
                        'correct_answer' => 'A',
                        'explanation' => 'The subject Tanaka requires the past tense verb started.',
                        'construct' => 'grammar',
                        'proficiency_target' => 'b1_standard',
                        'difficulty' => 'medium',
                    ]),
                    parsedPayload: null,
                    providerName: 'mock_provider',
                    modelName: 'mock-model-v1',
                    latencyMs: 120,
                    metadata: ['status_code' => 200]
                );
            }
        };

        $planner = app(ToeicBlueprintPlanner::class);
        $plan = $planner->plan(ToeicBlueprintRequest::fromArray([
            'mode' => 'part',
            'part_number' => 5,
            'item_count' => 1,
            'difficultyDistribution' => ['medium' => 100],
            'proficiencyDistribution' => ['b1_standard' => 100],
            'constructDistribution' => [5 => ['grammar' => 100]],
        ]));

        $orchestrator = app(QuestionGenerationOrchestrator::class);
        $batch = $orchestrator->createBatchFromPlan($plan, [
            'question_bank_id' => $bank->id,
            'created_by' => $this->teacher->id,
        ]);

        $processedBatch = $orchestrator->processBatch($batch, $fakeProvider);

        $this->assertSame(GenerationBatchStatus::Completed, $processedBatch->status);
        $item = $processedBatch->items->first();
        $this->assertSame(GenerationItemStatus::Materialized, $item->status);
        $this->assertNotNull($item->question_id);

        $question = Question::with('choices')->find($item->question_id);
        $this->assertNotNull($question);

        // Verify choices in DB match the repositioned candidate
        $this->assertCount(4, $question->choices);
        $correctDbChoices = $question->choices->where('is_correct', true);
        $this->assertCount(1, $correctDbChoices);

        $correctDb = $correctDbChoices->first();
        $this->assertSame('started', $correctDb->content);

        // Verify raw_output preserves original provider content (auditable)
        $rawOutput = $item->raw_output;
        $this->assertNotNull($rawOutput);
        $this->assertTrue($rawOutput['is_success']);
        $this->assertStringContainsString('"correct_answer":"A"', $rawOutput['raw_content']);
    }

    // =========================================================================
    // FINDING 7F-03: TARGET BANK COMPATIBILITY
    // =========================================================================

    public function test_7f03_generator_landing_shows_toeic_banks_and_excludes_general_banks(): void
    {
        $toeicBank = QuestionBank::create([
            'title' => 'TOEIC Prep Bank 2026',
            'slug' => 'toeic-prep-bank-2026',
            'status' => 'draft',
            'test_type' => TestType::Toeic,
            'created_by' => $this->teacher->id,
        ]);

        $generalBank = QuestionBank::create([
            'title' => 'General English Bank 2026',
            'slug' => 'general-english-bank-2026',
            'status' => 'draft',
            'test_type' => TestType::General,
            'created_by' => $this->teacher->id,
        ]);

        $response = $this->actingAs($this->teacher)->get(route('teacher.question-generator.index'));

        $response->assertOk();
        $response->assertSee('TOEIC Prep Bank 2026');
        $response->assertDontSee('General English Bank 2026');
    }

    public function test_7f03_generator_landing_shows_empty_state_when_teacher_only_has_general_banks(): void
    {
        QuestionBank::create([
            'title' => 'General Bank Only',
            'slug' => 'general-bank-only',
            'status' => 'draft',
            'test_type' => TestType::General,
            'created_by' => $this->teacher->id,
        ]);

        $response = $this->actingAs($this->teacher)->get(route('teacher.question-generator.index'));

        $response->assertOk();
        $response->assertSee('No Editable Question Banks Found');
        $response->assertSee('No editable TOEIC Question Bank is available for generation');
        $response->assertDontSee('General Bank Only');
    }

    public function test_7f03_direct_get_generation_workspace_denied_for_general_bank(): void
    {
        $generalBank = QuestionBank::create([
            'title' => 'General Target Bank',
            'slug' => 'general-target-bank',
            'status' => 'draft',
            'test_type' => TestType::General,
            'created_by' => $this->teacher->id,
        ]);

        $response = $this->actingAs($this->teacher)
            ->get(route('admin.question-banks.generation.index', $generalBank->id));

        $response->assertStatus(403);
    }

    public function test_7f03_direct_post_generation_denied_for_general_bank(): void
    {
        $generalBank = QuestionBank::create([
            'title' => 'General Target Bank POST',
            'slug' => 'general-target-bank-post',
            'status' => 'draft',
            'test_type' => TestType::General,
            'created_by' => $this->teacher->id,
        ]);

        $response = $this->actingAs($this->teacher)
            ->post(route('admin.question-banks.generation.store', $generalBank->id), [
                'quantity' => 2,
                'assessment' => 'toeic',
                'part' => 5,
                'difficulty' => 'medium',
                'proficiency_target' => 'b1_standard',
                'construct' => 'grammar',
            ]);

        $response->assertStatus(403);

        // No batch or questions created
        $this->assertDatabaseMissing('question_generation_batches', [
            'question_bank_id' => $generalBank->id,
        ]);
        $this->assertDatabaseMissing('questions', [
            'question_bank_id' => $generalBank->id,
        ]);
    }

    public function test_7f03_direct_post_retry_denied_for_general_parent_bank(): void
    {
        $generalBank = QuestionBank::create([
            'title' => 'General Bank For Retry',
            'slug' => 'general-bank-for-retry',
            'status' => 'draft',
            'test_type' => TestType::General,
            'created_by' => $this->teacher->id,
        ]);

        $batch = QuestionGenerationBatch::create([
            'question_bank_id' => $generalBank->id,
            'assessment_family' => AssessmentFamily::Toeic,
            'assessment_standard_id' => $this->toeicStandard->id,
            'standard_version' => '2026.1',
            'planner_type' => 'toeic_blueprint_planner',
            'planner_strategy_version' => '2026.1',
            'plan_fingerprint' => 'fingerprint_test_123',
            'idempotency_key' => 'idempotency_test_123',
            'prompt_contract_version' => 'question_generation_v1',
            'total_slots' => 1,
            'requested_slots' => 1,
            'validated_slots' => 0,
            'failed_slots' => 1,
            'status' => GenerationBatchStatus::Failed,
            'created_by' => $this->teacher->id,
        ]);

        $response = $this->actingAs($this->teacher)
            ->post(route('admin.question-banks.generation.retry-batch', [
                'questionBank' => $generalBank->id,
                'batch' => $batch->id,
            ]));

        $response->assertStatus(403);
    }

    public function test_7f03_toeic_owned_draft_and_needs_revision_banks_are_authorized(): void
    {
        $draftBank = QuestionBank::create([
            'title' => 'Draft TOEIC Bank',
            'slug' => 'draft-toeic-bank',
            'status' => 'draft',
            'test_type' => TestType::Toeic,
            'created_by' => $this->teacher->id,
        ]);

        $revisionBank = QuestionBank::create([
            'title' => 'Revision TOEIC Bank',
            'slug' => 'revision-toeic-bank',
            'status' => 'needs_revision',
            'test_type' => TestType::Toeic,
            'created_by' => $this->teacher->id,
        ]);

        $resDraft = $this->actingAs($this->teacher)->get(route('admin.question-banks.generation.index', $draftBank->id));
        $resDraft->assertOk();

        $resRev = $this->actingAs($this->teacher)->get(route('admin.question-banks.generation.index', $revisionBank->id));
        $resRev->assertOk();
    }

    public function test_7f03_non_owner_toeic_bank_is_denied(): void
    {
        $otherBank = QuestionBank::create([
            'title' => 'Other Teacher TOEIC Bank',
            'slug' => 'other-teacher-toeic-bank',
            'status' => 'draft',
            'test_type' => TestType::Toeic,
            'created_by' => $this->otherTeacher->id,
        ]);

        $response = $this->actingAs($this->teacher)->get(route('admin.question-banks.generation.index', $otherBank->id));
        $response->assertStatus(403);
    }
}
