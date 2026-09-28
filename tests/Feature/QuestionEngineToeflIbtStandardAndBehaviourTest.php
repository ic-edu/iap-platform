<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\QuestionBank\Enums\DifficultyLevel;
use App\Modules\QuestionBank\Enums\QuestionType;
use App\Modules\QuestionBank\Enums\SectionType;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Modules\QuestionEngine\Behaviours\GeneralEnglishAssessmentBehaviour;
use App\Modules\QuestionEngine\Behaviours\IeltsAssessmentBehaviour;
use App\Modules\QuestionEngine\Behaviours\ToeflIbtAssessmentBehaviour;
use App\Modules\QuestionEngine\Behaviours\ToeicAssessmentBehaviour;
use App\Modules\QuestionEngine\DTO\QuestionGenerationRequest;
use App\Modules\QuestionEngine\DTO\ToeicBlueprintRequest;
use App\Modules\QuestionEngine\Enums\AdaptiveType;
use App\Modules\QuestionEngine\Enums\AssessmentFamily;
use App\Modules\QuestionEngine\Enums\StandardStatus;
use App\Modules\QuestionEngine\Enums\ToeflClaim;
use App\Modules\QuestionEngine\Enums\ToeflItemScoringCategory;
use App\Modules\QuestionEngine\Enums\ToeflLanguageUseContext;
use App\Modules\QuestionEngine\Enums\ToeflResponseMode;
use App\Modules\QuestionEngine\Enums\ToeflScoringMode;
use App\Modules\QuestionEngine\Enums\ToeflTaskType;
use App\Modules\QuestionEngine\Models\AssessmentStandard;
use App\Modules\QuestionEngine\Services\AssessmentBehaviourResolver;
use App\Modules\QuestionEngine\Services\AssessmentStandardRegistry;
use App\Modules\QuestionEngine\Services\QuestionGenerationRequestValidator;
use App\Modules\QuestionEngine\Services\ToeflIbt2026StandardDefinition;
use App\Modules\QuestionEngine\Services\ToeflIbtStandardValidator;
use App\Modules\QuestionEngine\Services\ToeicBlueprintPlanner;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class QuestionEngineToeflIbtStandardAndBehaviourTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;

    protected QuestionBank $questionBank;

    protected AssessmentBehaviourResolver $resolver;

    protected AssessmentStandardRegistry $registry;

    protected ToeflIbtAssessmentBehaviour $toeflBehaviour;

    protected ToeflIbtStandardValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->teacher = User::factory()->create(['status' => 'active']);
        $this->teacher->assignRole('teacher');

        $this->questionBank = QuestionBank::create([
            'title' => 'TOEFL iBT Standard Question Bank',
            'slug' => 'toefl-ibt-standard-question-bank-'.uniqid(),
            'created_by' => $this->teacher->id,
            'is_published' => true,
        ]);

        $this->toeflBehaviour = new ToeflIbtAssessmentBehaviour;
        $this->resolver = new AssessmentBehaviourResolver(
            new ToeicAssessmentBehaviour,
            $this->toeflBehaviour,
            new GeneralEnglishAssessmentBehaviour,
            new IeltsAssessmentBehaviour
        );

        $this->registry = new AssessmentStandardRegistry;
        $this->validator = new ToeflIbtStandardValidator;
    }

    /**
     * TEST A: TOEFL family resolves to ToeflIbtAssessmentBehaviour.
     */
    public function test_a_toefl_family_resolves_to_toefl_behaviour(): void
    {
        $behaviour = $this->resolver->resolve(AssessmentFamily::ToeflIbt);
        $this->assertInstanceOf(ToeflIbtAssessmentBehaviour::class, $behaviour);
        $this->assertSame(AssessmentFamily::ToeflIbt, $behaviour->family());
    }

    /**
     * TEST B: TOEFL does not support part_number.
     */
    public function test_b_toefl_does_not_support_part_numbers(): void
    {
        $this->assertFalse($this->toeflBehaviour->supportsPartNumbers());
        $this->assertFalse($this->toeflBehaviour->getCapabilities()->supportsParts);
    }

    /**
     * TEST C: TOEFL supports task_type.
     */
    public function test_c_toefl_supports_task_types(): void
    {
        $this->assertTrue($this->toeflBehaviour->supportsTaskTypes());
        $this->assertTrue($this->toeflBehaviour->getCapabilities()->supportsTaskTypes);
    }

    /**
     * TEST D: TOEFL supports claims.
     */
    public function test_d_toefl_supports_claims(): void
    {
        $this->assertTrue($this->toeflBehaviour->supportsClaims());
        $this->assertTrue($this->toeflBehaviour->getCapabilities()->supportsClaims);
    }

    /**
     * TEST E: TOEFL supports adaptive blueprint capability.
     */
    public function test_e_toefl_supports_adaptive_blueprint(): void
    {
        $this->assertTrue($this->toeflBehaviour->supportsAdaptiveBlueprint());
        $this->assertFalse($this->toeflBehaviour->supportsFixedFullTestBlueprint());
        $this->assertTrue($this->toeflBehaviour->supportsCefrTargeting());
    }

    /**
     * TEST F: Exactly four sections exist.
     */
    public function test_f_four_sections_exist_exactly(): void
    {
        $sections = $this->toeflBehaviour->supportedSections();
        $this->assertCount(4, $sections);
        $this->assertSame(['reading', 'listening', 'writing', 'speaking'], $sections);
    }

    /**
     * TEST G: Reading task types exact (3).
     */
    public function test_g_reading_task_types_exact(): void
    {
        $tasks = $this->toeflBehaviour->taskTypesForSection('reading');
        $taskValues = array_map(fn (ToeflTaskType $t) => $t->value, $tasks);

        $this->assertCount(3, $tasks);
        $this->assertSame([
            'complete_the_words',
            'read_in_daily_life',
            'read_an_academic_passage',
        ], $taskValues);
    }

    /**
     * TEST H: Listening task types exact (4).
     */
    public function test_h_listening_task_types_exact(): void
    {
        $tasks = $this->toeflBehaviour->taskTypesForSection('listening');
        $taskValues = array_map(fn (ToeflTaskType $t) => $t->value, $tasks);

        $this->assertCount(4, $tasks);
        $this->assertSame([
            'listen_and_choose_a_response',
            'listen_to_a_conversation',
            'listen_to_an_announcement',
            'listen_to_an_academic_talk',
        ], $taskValues);
    }

    /**
     * TEST I: Writing task types exact (3).
     */
    public function test_i_writing_task_types_exact(): void
    {
        $tasks = $this->toeflBehaviour->taskTypesForSection('writing');
        $taskValues = array_map(fn (ToeflTaskType $t) => $t->value, $tasks);

        $this->assertCount(3, $tasks);
        $this->assertSame([
            'build_a_sentence',
            'write_an_email',
            'write_for_an_academic_discussion',
        ], $taskValues);
    }

    /**
     * TEST J: Speaking task types exact (2).
     */
    public function test_j_speaking_task_types_exact(): void
    {
        $tasks = $this->toeflBehaviour->taskTypesForSection('speaking');
        $taskValues = array_map(fn (ToeflTaskType $t) => $t->value, $tasks);

        $this->assertCount(2, $tasks);
        $this->assertSame([
            'listen_and_repeat',
            'take_an_interview',
        ], $taskValues);
    }

    /**
     * TEST K: Task type cannot cross section.
     */
    public function test_k_task_type_cannot_cross_section(): void
    {
        $this->assertTrue($this->toeflBehaviour->isTaskTypeCompatibleWithSection(ToeflTaskType::CompleteTheWords, 'reading'));
        $this->assertFalse($this->toeflBehaviour->isTaskTypeCompatibleWithSection(ToeflTaskType::CompleteTheWords, 'listening'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Task type 'complete_the_words' belongs to section 'reading', not 'listening'.");

        $this->toeflBehaviour->validateStructure([
            'section' => 'listening',
            'task_type' => 'complete_the_words',
        ]);
    }

    /**
     * TEST L: Complete the Words -> Reading.
     */
    public function test_l_complete_the_words_maps_to_reading(): void
    {
        $this->assertSame('reading', ToeflTaskType::CompleteTheWords->section());
        $this->assertSame(SectionType::Reading, $this->toeflBehaviour->resolveSection(taskType: 'complete_the_words'));
    }

    /**
     * TEST M: Listen to Academic Talk -> Listening.
     */
    public function test_m_listen_to_academic_talk_maps_to_listening(): void
    {
        $this->assertSame('listening', ToeflTaskType::ListenToAnAcademicTalk->section());
        $this->assertSame(SectionType::Listening, $this->toeflBehaviour->resolveSection(taskType: 'listen_to_an_academic_talk'));
    }

    /**
     * TEST N: Build a Sentence -> Writing.
     */
    public function test_n_build_a_sentence_maps_to_writing(): void
    {
        $this->assertSame('writing', ToeflTaskType::BuildASentence->section());
        $this->assertSame(SectionType::Writing, $this->toeflBehaviour->resolveSection(taskType: 'build_a_sentence'));
    }

    /**
     * TEST O: Take an Interview -> Speaking.
     */
    public function test_o_take_an_interview_maps_to_speaking(): void
    {
        $this->assertSame('speaking', ToeflTaskType::TakeAnInterview->section());
        $this->assertSame(SectionType::Speaking, $this->toeflBehaviour->resolveSection(taskType: 'take_an_interview'));
    }

    /**
     * TEST P: Reading marked two-stage adaptive.
     */
    public function test_p_reading_marked_two_stage_adaptive(): void
    {
        $spec = $this->toeflBehaviour->getSectionSpecification('reading');
        $this->assertTrue($spec->isAdaptive);
        $this->assertSame(AdaptiveType::TwoStage, $spec->adaptiveType);
        $this->assertNotNull($spec->adaptiveSpecification);
        $this->assertTrue($spec->adaptiveSpecification->isAdaptive);
        $this->assertSame(AdaptiveType::TwoStage, $spec->adaptiveSpecification->adaptiveType);
    }

    /**
     * TEST Q: Listening marked two-stage adaptive.
     */
    public function test_q_listening_marked_two_stage_adaptive(): void
    {
        $spec = $this->toeflBehaviour->getSectionSpecification('listening');
        $this->assertTrue($spec->isAdaptive);
        $this->assertSame(AdaptiveType::TwoStage, $spec->adaptiveType);
        $this->assertNotNull($spec->adaptiveSpecification);
        $this->assertTrue($spec->adaptiveSpecification->isAdaptive);
        $this->assertSame(AdaptiveType::TwoStage, $spec->adaptiveSpecification->adaptiveType);
    }

    /**
     * TEST R: Writing marked linear.
     */
    public function test_r_writing_marked_linear(): void
    {
        $spec = $this->toeflBehaviour->getSectionSpecification('writing');
        $this->assertFalse($spec->isAdaptive);
        $this->assertSame(AdaptiveType::Linear, $spec->adaptiveType);
        $this->assertSame(12, $spec->fixedTotalItems);
    }

    /**
     * TEST S: Speaking marked linear.
     */
    public function test_s_speaking_marked_linear(): void
    {
        $spec = $this->toeflBehaviour->getSectionSpecification('speaking');
        $this->assertFalse($spec->isAdaptive);
        $this->assertSame(AdaptiveType::Linear, $spec->adaptiveType);
        $this->assertSame(11, $spec->fixedTotalItems);
    }

    /**
     * TEST T: Reading variable item blueprint represented.
     */
    public function test_t_reading_variable_item_blueprint_represented(): void
    {
        $spec = $this->toeflBehaviour->getSectionSpecification('reading');
        $this->assertSame(50, $spec->totalItemsMax);

        $ctw = $spec->taskSpecifications['complete_the_words'];
        $this->assertSame(30, $ctw->itemCountFixed);

        $rdl = $spec->taskSpecifications['read_in_daily_life'];
        $this->assertSame(5, $rdl->itemCountMin);
        $this->assertSame(15, $rdl->itemCountMax);

        $rap = $spec->taskSpecifications['read_an_academic_passage'];
        $this->assertSame(5, $rap->itemCountMin);
        $this->assertSame(15, $rap->itemCountMax);
    }

    /**
     * TEST U: Listening variable item blueprint represented.
     */
    public function test_u_listening_variable_item_blueprint_represented(): void
    {
        $spec = $this->toeflBehaviour->getSectionSpecification('listening');
        $this->assertSame(47, $spec->totalItemsMax);

        $lcr = $spec->taskSpecifications['listen_and_choose_a_response'];
        $this->assertSame(15, $lcr->itemCountMin);
        $this->assertSame(19, $lcr->itemCountMax);

        $ltc = $spec->taskSpecifications['listen_to_a_conversation'];
        $this->assertSame(10, $ltc->itemCountFixed);

        $lta = $spec->taskSpecifications['listen_to_an_announcement'];
        $this->assertSame(6, $lta->itemCountMin);
        $this->assertSame(10, $lta->itemCountMax);

        $lat = $spec->taskSpecifications['listen_to_an_academic_talk'];
        $this->assertSame(8, $lat->itemCountMin);
        $this->assertSame(16, $lat->itemCountMax);
    }

    /**
     * TEST V: Writing total = 12.
     */
    public function test_v_writing_total_equals_12(): void
    {
        $spec = $this->toeflBehaviour->getSectionSpecification('writing');
        $this->assertSame(12, $spec->fixedTotalItems);
    }

    /**
     * TEST W: Speaking total = 11.
     */
    public function test_w_speaking_total_equals_11(): void
    {
        $spec = $this->toeflBehaviour->getSectionSpecification('speaking');
        $this->assertSame(11, $spec->fixedTotalItems);
    }

    /**
     * TEST X: Build a Sentence = 10.
     */
    public function test_x_build_a_sentence_equals_10(): void
    {
        $task = $this->toeflBehaviour->getTaskSpecification('build_a_sentence');
        $this->assertSame(10, $task->itemCountFixed);
    }

    /**
     * TEST Y: Write an Email = 1.
     */
    public function test_y_write_an_email_equals_1(): void
    {
        $task = $this->toeflBehaviour->getTaskSpecification('write_an_email');
        $this->assertSame(1, $task->itemCountFixed);
    }

    /**
     * TEST Z: Academic Discussion = 1.
     */
    public function test_z_academic_discussion_equals_1(): void
    {
        $task = $this->toeflBehaviour->getTaskSpecification('write_for_an_academic_discussion');
        $this->assertSame(1, $task->itemCountFixed);
    }

    /**
     * TEST AA: Listen and Repeat = 7.
     */
    public function test_aa_listen_and_repeat_equals_7(): void
    {
        $task = $this->toeflBehaviour->getTaskSpecification('listen_and_repeat');
        $this->assertSame(7, $task->itemCountFixed);
    }

    /**
     * TEST AB: Take an Interview = 4.
     */
    public function test_ab_take_an_interview_equals_4(): void
    {
        $task = $this->toeflBehaviour->getTaskSpecification('take_an_interview');
        $this->assertSame(4, $task->itemCountFixed);
    }

    /**
     * TEST AC: Official CEFR ranges represented.
     */
    public function test_ac_official_cefr_ranges_represented(): void
    {
        $expected = [
            'complete_the_words' => ['min' => 'B1', 'max' => 'C1+'],
            'read_in_daily_life' => ['min' => 'A1', 'max' => 'C1'],
            'read_an_academic_passage' => ['min' => 'B1', 'max' => 'C2'],
            'listen_and_choose_a_response' => ['min' => 'A1', 'max' => 'B2'],
            'listen_to_a_conversation' => ['min' => 'A2', 'max' => 'C1'],
            'listen_to_an_announcement' => ['min' => 'A2', 'max' => 'C1'],
            'listen_to_an_academic_talk' => ['min' => 'A2', 'max' => 'C2'],
            'build_a_sentence' => ['min' => 'A1', 'max' => 'C2'],
            'write_an_email' => ['min' => 'B1', 'max' => 'C2'],
            'write_for_an_academic_discussion' => ['min' => 'B1', 'max' => 'C2'],
            'listen_and_repeat' => ['min' => 'A1', 'max' => 'C2'],
            'take_an_interview' => ['min' => 'A1', 'max' => 'C2'],
        ];

        foreach ($expected as $taskVal => $range) {
            $task = ToeflTaskType::from($taskVal);
            $this->assertSame($range['min'], $task->cefrMin(), "Task {$taskVal} min CEFR mismatch");
            $this->assertSame($range['max'], $task->cefrMax(), "Task {$taskVal} max CEFR mismatch");
        }
    }

    /**
     * TEST AD: Invalid CEFR range fails validator.
     */
    public function test_ad_invalid_cefr_range_fails_validator(): void
    {
        $def = ToeflIbt2026StandardDefinition::getDefinition();
        $def['validation_definition']['cefr_target_ranges']['complete_the_words'] = ['min' => 'A1', 'max' => 'C2'];

        $result = $this->validator->validate($def);
        $this->assertFalse($result['is_valid']);
        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('CEFR range mismatch', $result['errors'][0]);
    }

    /**
     * TEST AE: Claim-task compatibility works.
     */
    public function test_ae_claim_task_compatibility_works(): void
    {
        $claims = $this->toeflBehaviour->claimsForTaskType(ToeflTaskType::CompleteTheWords);
        $this->assertContains(ToeflClaim::ReadingAcademicMeaningAndForm, $claims);

        $this->assertTrue($this->toeflBehaviour->isClaimCompatibleWithTaskType(
            ToeflClaim::ReadingAcademicMeaningAndForm,
            ToeflTaskType::CompleteTheWords
        ));
    }

    /**
     * TEST AF: Invalid claim-task mapping rejected.
     */
    public function test_af_invalid_claim_task_mapping_rejected(): void
    {
        $this->assertFalse($this->toeflBehaviour->isClaimCompatibleWithTaskType(
            ToeflClaim::SpeakingSpontaneousInterviewResponse,
            ToeflTaskType::CompleteTheWords
        ));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Claim 'speaking_spontaneous_interview_response' belongs to section 'speaking', not 'reading'.");

        $this->toeflBehaviour->validateStructure([
            'section' => 'reading',
            'task_type' => 'complete_the_words',
            'claim' => 'speaking_spontaneous_interview_response',
        ]);
    }

    /**
     * TEST AG: TOEFL structural identity keeps part_number = NULL.
     */
    public function test_ag_toefl_structural_identity_keeps_part_number_null(): void
    {
        $validated = $this->toeflBehaviour->validateStructure([
            'section' => 'reading',
            'task_type' => 'complete_the_words',
            'claim' => 'reading_academic_meaning_and_form',
        ]);

        $this->assertNull($validated['part_number']);
        $this->assertSame('reading', $validated['section']);
        $this->assertSame('complete_the_words', $validated['task_type']);
    }

    /**
     * TEST AH: Explicit TOEFL part_number rejected.
     */
    public function test_ah_explicit_toefl_part_number_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('TOEFL iBT does not support TOEIC part numbers.');

        $this->toeflBehaviour->validateStructure([
            'section' => 'reading',
            'part_number' => 5,
            'task_type' => 'complete_the_words',
        ]);
    }

    /**
     * TEST AI: Official language contexts represented.
     */
    public function test_ai_official_language_contexts_represented(): void
    {
        $contexts = ToeflLanguageUseContext::values();
        $this->assertSame([
            'academic',
            'academic_navigational',
            'social_interpersonal',
        ], $contexts);
    }

    /**
     * TEST AJ: Machine scoring categories represented.
     */
    public function test_aj_machine_scoring_categories_represented(): void
    {
        $this->assertSame(ToeflScoringMode::Machine, ToeflTaskType::CompleteTheWords->scoringMode());
        $this->assertSame(ToeflScoringMode::Machine, ToeflTaskType::ReadInDailyLife->scoringMode());
        $this->assertSame(ToeflScoringMode::Machine, ToeflTaskType::ReadAnAcademicPassage->scoringMode());
        $this->assertSame(ToeflScoringMode::Machine, ToeflTaskType::ListenAndChooseAResponse->scoringMode());
        $this->assertSame(ToeflScoringMode::Machine, ToeflTaskType::BuildASentence->scoringMode());
    }

    /**
     * TEST AK: Constructed written responses represented.
     */
    public function test_ak_constructed_written_responses_represented(): void
    {
        $this->assertSame(ToeflResponseMode::ConstructedWritten, ToeflTaskType::WriteAnEmail->responseMode());
        $this->assertSame(ToeflResponseMode::ConstructedWritten, ToeflTaskType::WriteForAnAcademicDiscussion->responseMode());
        $this->assertSame(ToeflScoringMode::AiScored, ToeflTaskType::WriteAnEmail->scoringMode());
    }

    /**
     * TEST AL: Constructed spoken responses represented.
     */
    public function test_al_constructed_spoken_responses_represented(): void
    {
        $this->assertSame(ToeflResponseMode::ConstructedSpoken, ToeflTaskType::ListenAndRepeat->responseMode());
        $this->assertSame(ToeflResponseMode::ConstructedSpoken, ToeflTaskType::TakeAnInterview->responseMode());
        $this->assertSame(ToeflScoringMode::AiScored, ToeflTaskType::TakeAnInterview->scoringMode());
    }

    /**
     * TEST AM: Pretest / non-scored capability represented.
     */
    public function test_am_pretest_non_scored_capability_represented(): void
    {
        $categories = ToeflItemScoringCategory::values();
        $this->assertContains('scored', $categories);
        $this->assertContains('pretest', $categories);
        $this->assertContains('unspecified', $categories);

        $readingSpec = $this->toeflBehaviour->getSectionSpecification('reading');
        $this->assertTrue($readingSpec->metadata['includes_pretest']);
    }

    /**
     * TEST AN: Standard source metadata present.
     */
    public function test_an_standard_source_metadata_present(): void
    {
        $def = ToeflIbt2026StandardDefinition::getDefinition();
        $this->assertSame('ETS', $def['provider']);
        $this->assertSame(ToeflIbt2026StandardDefinition::SOURCE_NAME, $def['source_name']);
        $this->assertSame(ToeflIbt2026StandardDefinition::SOURCE_URL, $def['source_url']);
        $this->assertTrue($def['metadata']['official']);

        $res = $this->validator->validate($def);
        $this->assertTrue($res['is_valid']);
    }

    /**
     * TEST AO: 2026.1 standard version can be registered.
     */
    public function test_ao_2026_1_standard_can_be_registered(): void
    {
        $def = ToeflIbt2026StandardDefinition::getDefinition();
        $def['status'] = StandardStatus::Draft;

        $standard = $this->registry->registerStandard($def);

        $this->assertInstanceOf(AssessmentStandard::class, $standard);
        $this->assertSame(AssessmentFamily::ToeflIbt, $standard->assessment_family);
        $this->assertSame('2026.1', $standard->version);
        $this->assertSame(StandardStatus::Draft, $standard->status);
    }

    /**
     * TEST AP: Active TOEFL standard resolution works.
     */
    public function test_ap_active_toefl_standard_resolution_works(): void
    {
        $def = ToeflIbt2026StandardDefinition::getDefinition();
        $def['status'] = StandardStatus::Draft;

        $draft = $this->registry->registerStandard($def);
        $this->registry->activateStandard($draft);

        $active = $this->registry->getActiveStandard(AssessmentFamily::ToeflIbt);
        $this->assertSame($draft->id, $active->id);
        $this->assertSame('2026.1', $active->version);
        $this->assertTrue($active->isActive());
    }

    /**
     * TEST AQ: Historical standard version binding preserved.
     */
    public function test_aq_historical_standard_binding_preserved(): void
    {
        $std2026 = $this->registry->registerStandard(array_merge(
            ToeflIbt2026StandardDefinition::getDefinition(),
            ['version' => '2026.1', 'status' => StandardStatus::Draft]
        ));
        $this->registry->activateStandard($std2026);

        $question = Question::create([
            'question_bank_id' => $this->questionBank->id,
            'prompt' => 'Complete the word in context.',
            'section' => SectionType::Reading,
            'task_type' => 'complete_the_words',
            'claim' => 'reading_academic_meaning_and_form',
            'question_type' => QuestionType::FillInTheBlank,
            'difficulty' => DifficultyLevel::Medium,
            'assessment_family' => AssessmentFamily::ToeflIbt,
            'assessment_standard_id' => $std2026->id,
            'standard_version' => '2026.1',
        ]);

        $std2027 = $this->registry->registerStandard(array_merge(
            ToeflIbt2026StandardDefinition::getDefinition(),
            ['version' => '2027.1', 'status' => StandardStatus::Draft]
        ));
        $this->registry->activateStandard($std2027);

        $question->refresh();
        $this->assertSame('2026.1', $question->standard_version);
        $this->assertSame($std2026->id, $question->assessment_standard_id);
        $this->assertSame(StandardStatus::Superseded, $question->assessmentStandard->status);
        $this->assertSame($std2027->id, $this->registry->getActiveStandard(AssessmentFamily::ToeflIbt)->id);
    }

    /**
     * TEST AR: No automatic standard mutation.
     */
    public function test_ar_no_automatic_standard_mutation(): void
    {
        $std = $this->registry->registerStandard(array_merge(
            ToeflIbt2026StandardDefinition::getDefinition(),
            ['status' => StandardStatus::Draft]
        ));

        $structureInitial = $std->structure_definition;
        $this->registry->activateStandard($std);

        $std->refresh();
        $this->assertSame($structureInitial, $std->structure_definition);
    }

    /**
     * TEST AS: TOEIC behaviour regression PASS.
     */
    public function test_as_toeic_behaviour_regression_pass(): void
    {
        $toeic = $this->resolver->resolve(AssessmentFamily::Toeic);
        $this->assertTrue($toeic->supportsPartNumbers());
        $this->assertFalse($toeic->supportsTaskTypes());
        $this->assertFalse($toeic->supportsAdaptiveBlueprint());
        $this->assertTrue($toeic->supportsFixedFullTestBlueprint());

        $validated = $toeic->validateStructure([
            'part_number' => 5,
            'section' => 'reading',
        ]);
        $this->assertSame(5, $validated['part_number']);
        $this->assertSame('reading', $validated['section']);
    }

    /**
     * TEST AT: TOEIC blueprint planner regression PASS.
     */
    public function test_at_toeic_blueprint_planner_regression_pass(): void
    {
        $toeicStandard = $this->registry->registerStandard([
            'assessment_family' => AssessmentFamily::Toeic,
            'standard_code' => 'TOEIC-2026.1',
            'version' => '2026.1',
            'title' => 'TOEIC Standard 2026.1',
            'status' => StandardStatus::Draft,
            'structure_definition' => [],
        ]);
        $this->registry->activateStandard($toeicStandard);

        $planner = new ToeicBlueprintPlanner($this->registry);
        $request = ToeicBlueprintRequest::fromArray(['mode' => 'full_test']);
        $plan = $planner->plan($request);

        $this->assertSame(200, $plan->totalSlots);
        $this->assertSame($toeicStandard->id, $plan->assessmentStandardId);
    }

    /**
     * TEST AU: Sprint 1 regression PASS.
     */
    public function test_au_sprint_1_regression_pass(): void
    {
        $validator = new QuestionGenerationRequestValidator;
        $req = QuestionGenerationRequest::fromArray([
            'part_number' => 5,
            'proficiency_target' => 'b1_standard',
            'difficulty' => 'medium',
            'content_mode' => 'general',
            'construct' => 'grammar',
            'item_count' => 1,
        ]);

        $validated = $validator->validate($req);
        $this->assertSame(5, $validated['part_number']);
        $this->assertSame('general_workplace', $validated['domain']);
    }

    /**
     * TEST AV: Sprint 2 regression PASS.
     */
    public function test_av_sprint_2_regression_pass(): void
    {
        $this->assertNotNull($this->registry);
        $this->assertNotNull($this->resolver);

        $activeToeic = $this->registry->registerStandard([
            'assessment_family' => AssessmentFamily::Toeic,
            'version' => '2026.SPRINT2_PASS',
            'status' => StandardStatus::Draft,
            'structure_definition' => [],
        ]);
        $this->registry->activateStandard($activeToeic);

        $this->assertSame($activeToeic->id, $this->registry->getActiveStandard(AssessmentFamily::Toeic)->id);
    }

    /**
     * TEST AW: Sprint 3 regression PASS.
     */
    public function test_aw_sprint_3_regression_pass(): void
    {
        $req = ToeicBlueprintRequest::fromArray([
            'mode' => 'custom',
            'custom_parts' => ['3' => '6'],
        ]);
        $this->assertSame([3 => 6], $req->customParts);
    }

    /**
     * TEST AX: Question Bank governance regression PASS.
     */
    public function test_ax_question_bank_governance_regression_pass(): void
    {
        $this->assertDatabaseCount('question_banks', 1);
        $this->assertTrue($this->questionBank->is_published);
        $this->assertSame($this->teacher->id, $this->questionBank->created_by);
    }
}
