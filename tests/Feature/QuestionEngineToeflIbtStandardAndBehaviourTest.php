<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Modules\QuestionEngine\Behaviours\GeneralEnglishAssessmentBehaviour;
use App\Modules\QuestionEngine\Behaviours\IeltsAssessmentBehaviour;
use App\Modules\QuestionEngine\Behaviours\ToeflIbtAssessmentBehaviour;
use App\Modules\QuestionEngine\Behaviours\ToeicAssessmentBehaviour;
use App\Modules\QuestionEngine\DTO\QuestionGenerationRequest;
use App\Modules\QuestionEngine\DTO\ToeicBlueprintRequest;
use App\Modules\QuestionEngine\Enums\AssessmentFamily;
use App\Modules\QuestionEngine\Enums\StandardStatus;
use App\Modules\QuestionEngine\Enums\ToeflClaim;
use App\Modules\QuestionEngine\Enums\ToeflLanguageUseContext;
use App\Modules\QuestionEngine\Enums\ToeflSkill;
use App\Modules\QuestionEngine\Enums\ToeflTaskType;
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
     * TEST A: Exactly 4 top-level TOEFL claims.
     */
    public function test_a_exactly_four_top_level_claims(): void
    {
        $claims = ToeflClaim::cases();
        $this->assertCount(4, $claims);
        $this->assertSame([
            'claim_1_reading',
            'claim_2_listening',
            'claim_3_writing',
            'claim_4_speaking',
        ], ToeflClaim::values());
    }

    /**
     * TEST B: Reading task types map to Reading claim.
     */
    public function test_b_reading_tasks_map_to_reading_claim(): void
    {
        $readingTasks = ToeflTaskType::forSection('reading');
        foreach ($readingTasks as $task) {
            $this->assertSame(ToeflClaim::Claim1Reading, $task->claim());
            $this->assertSame([ToeflClaim::Claim1Reading], $this->toeflBehaviour->claimsForTaskType($task));
        }
    }

    /**
     * TEST C: Listening task types map to Listening claim.
     */
    public function test_c_listening_tasks_map_to_listening_claim(): void
    {
        $listeningTasks = ToeflTaskType::forSection('listening');
        foreach ($listeningTasks as $task) {
            $this->assertSame(ToeflClaim::Claim2Listening, $task->claim());
            $this->assertSame([ToeflClaim::Claim2Listening], $this->toeflBehaviour->claimsForTaskType($task));
        }
    }

    /**
     * TEST D: Writing task types map to Writing claim.
     */
    public function test_d_writing_tasks_map_to_writing_claim(): void
    {
        $writingTasks = ToeflTaskType::forSection('writing');
        foreach ($writingTasks as $task) {
            $this->assertSame(ToeflClaim::Claim3Writing, $task->claim());
            $this->assertSame([ToeflClaim::Claim3Writing], $this->toeflBehaviour->claimsForTaskType($task));
        }
    }

    /**
     * TEST E: Speaking task types map to Speaking claim.
     */
    public function test_e_speaking_tasks_map_to_speaking_claim(): void
    {
        $speakingTasks = ToeflTaskType::forSection('speaking');
        foreach ($speakingTasks as $task) {
            $this->assertSame(ToeflClaim::Claim4Speaking, $task->claim());
            $this->assertSame([ToeflClaim::Claim4Speaking], $this->toeflBehaviour->claimsForTaskType($task));
        }
    }

    /**
     * TEST F: Complete the Words correct official skill.
     */
    public function test_f_complete_the_words_correct_official_skill(): void
    {
        $skills = ToeflTaskType::CompleteTheWords->skills();
        $this->assertContains(ToeflSkill::ReadingProcessMeaningAndForm, $skills);
        $this->assertSame('reading', ToeflSkill::ReadingProcessMeaningAndForm->section());
        $this->assertSame(ToeflClaim::Claim1Reading, ToeflSkill::ReadingProcessMeaningAndForm->claim());
    }

    /**
     * TEST G: Read in Daily Life correct skill/subskill mapping.
     */
    public function test_g_read_in_daily_life_correct_skills(): void
    {
        $skills = ToeflTaskType::ReadInDailyLife->skills();
        $this->assertContains(ToeflSkill::ReadingComprehendVariedFormats, $skills);
        $this->assertContains(ToeflSkill::ReadingShortNonacademicTexts, $skills);
    }

    /**
     * TEST H: Academic Passage correct skill/subskill mapping.
     */
    public function test_h_read_an_academic_passage_correct_skills(): void
    {
        $skills = ToeflTaskType::ReadAnAcademicPassage->skills();
        $this->assertContains(ToeflSkill::ReadingComprehendVariedFormats, $skills);
        $this->assertContains(ToeflSkill::ReadingAcademicTexts, $skills);
    }

    /**
     * TEST I: Build a Sentence context = Social Interpersonal.
     */
    public function test_i_build_a_sentence_context_is_social_interpersonal(): void
    {
        $contexts = ToeflTaskType::BuildASentence->allowedLanguageUseContexts();
        $this->assertSame([ToeflLanguageUseContext::SocialInterpersonal], $contexts);
    }

    /**
     * TEST J: Write an Email context = Academic Navigational.
     */
    public function test_j_write_an_email_context_is_academic_navigational(): void
    {
        $contexts = ToeflTaskType::WriteAnEmail->allowedLanguageUseContexts();
        $this->assertSame([ToeflLanguageUseContext::AcademicNavigational], $contexts);
    }

    /**
     * TEST K: Academic Discussion context = Academic.
     */
    public function test_k_academic_discussion_context_is_academic(): void
    {
        $contexts = ToeflTaskType::WriteForAnAcademicDiscussion->allowedLanguageUseContexts();
        $this->assertSame([ToeflLanguageUseContext::Academic], $contexts);
    }

    /**
     * TEST L: Listen and Repeat context = Academic Navigational.
     */
    public function test_l_listen_and_repeat_context_is_academic_navigational(): void
    {
        $contexts = ToeflTaskType::ListenAndRepeat->allowedLanguageUseContexts();
        $this->assertSame([ToeflLanguageUseContext::AcademicNavigational], $contexts);
    }

    /**
     * TEST M: Take an Interview context = Academic Navigational.
     */
    public function test_m_take_an_interview_context_is_academic_navigational(): void
    {
        $contexts = ToeflTaskType::TakeAnInterview->allowedLanguageUseContexts();
        $this->assertSame([ToeflLanguageUseContext::AcademicNavigational], $contexts);
    }

    /**
     * TEST N: Reading section contexts exactly official supported set.
     */
    public function test_n_reading_section_contexts_exact(): void
    {
        $spec = $this->toeflBehaviour->getSectionSpecification('reading');
        $ctxValues = array_map(fn (ToeflLanguageUseContext $c) => $c->value, $spec->supportedLanguageUseContexts);

        $this->assertSame(['academic', 'social_interpersonal'], $ctxValues);
    }

    /**
     * TEST O: Listening section contexts official supported set.
     */
    public function test_o_listening_section_contexts_exact(): void
    {
        $spec = $this->toeflBehaviour->getSectionSpecification('listening');
        $ctxValues = array_map(fn (ToeflLanguageUseContext $c) => $c->value, $spec->supportedLanguageUseContexts);

        $this->assertSame(['academic', 'academic_navigational', 'social_interpersonal'], $ctxValues);
    }

    /**
     * TEST P: Writing section contexts official supported set.
     */
    public function test_p_writing_section_contexts_exact(): void
    {
        $spec = $this->toeflBehaviour->getSectionSpecification('writing');
        $ctxValues = array_map(fn (ToeflLanguageUseContext $c) => $c->value, $spec->supportedLanguageUseContexts);

        $this->assertSame(['academic', 'academic_navigational', 'social_interpersonal'], $ctxValues);
    }

    /**
     * TEST Q: Speaking section context = Academic Navigational.
     */
    public function test_q_speaking_section_contexts_exact(): void
    {
        $spec = $this->toeflBehaviour->getSectionSpecification('speaking');
        $ctxValues = array_map(fn (ToeflLanguageUseContext $c) => $c->value, $spec->supportedLanguageUseContexts);

        $this->assertSame(['academic_navigational'], $ctxValues);
    }

    /**
     * TEST R: Reading totalItemsMin is unspecified/null.
     */
    public function test_r_reading_total_items_min_is_null(): void
    {
        $spec = $this->toeflBehaviour->getSectionSpecification('reading');
        $this->assertNull($spec->totalItemsMin);
        $this->assertNull($spec->fixedTotalItems);
    }

    /**
     * TEST S: Listening totalItemsMin is unspecified/null.
     */
    public function test_s_listening_total_items_min_is_null(): void
    {
        $spec = $this->toeflBehaviour->getSectionSpecification('listening');
        $this->assertNull($spec->totalItemsMin);
        $this->assertNull($spec->fixedTotalItems);
    }

    /**
     * TEST T: Reading max = 50.
     */
    public function test_t_reading_max_equals_50(): void
    {
        $spec = $this->toeflBehaviour->getSectionSpecification('reading');
        $this->assertSame(50, $spec->totalItemsMax);
    }

    /**
     * TEST U: Listening max = 47.
     */
    public function test_u_listening_max_equals_47(): void
    {
        $spec = $this->toeflBehaviour->getSectionSpecification('listening');
        $this->assertSame(47, $spec->totalItemsMax);
    }

    /**
     * TEST V: Writing fixed = 12.
     */
    public function test_v_writing_fixed_equals_12(): void
    {
        $spec = $this->toeflBehaviour->getSectionSpecification('writing');
        $this->assertSame(12, $spec->fixedTotalItems);
        $this->assertSame(12, $spec->totalItemsMin);
        $this->assertSame(12, $spec->totalItemsMax);
    }

    /**
     * TEST W: Speaking fixed = 11.
     */
    public function test_w_speaking_fixed_equals_11(): void
    {
        $spec = $this->toeflBehaviour->getSectionSpecification('speaking');
        $this->assertSame(11, $spec->fixedTotalItems);
        $this->assertSame(11, $spec->totalItemsMin);
        $this->assertSame(11, $spec->totalItemsMax);
    }

    /**
     * TEST X: Official primary source is specification document.
     */
    public function test_x_official_primary_source_is_specification_document(): void
    {
        $def = ToeflIbt2026StandardDefinition::getDefinition();
        $this->assertSame(ToeflIbt2026StandardDefinition::SOURCE_NAME, $def['source_name']);
        $this->assertSame(ToeflIbt2026StandardDefinition::SOURCE_URL, $def['source_url']);
        $this->assertSame('official_specification_pdf', $def['metadata']['primary_source']['type']);
    }

    /**
     * TEST Y: Source checked timestamp does not masquerade as effective date.
     */
    public function test_y_source_checked_timestamp_distinct_from_effective_date(): void
    {
        $def = ToeflIbt2026StandardDefinition::getDefinition();
        $this->assertNotSame($def['effective_from'], $def['source_checked_at']);
        $this->assertSame(ToeflIbt2026StandardDefinition::EFFECTIVE_FROM, $def['effective_from']);
        $this->assertSame(ToeflIbt2026StandardDefinition::SOURCE_CHECKED_AT, $def['source_checked_at']);
    }

    /**
     * TEST Z: Internal stimulus taxonomy clearly marked IAP-derived.
     */
    public function test_z_internal_stimulus_taxonomy_marked_iap_derived(): void
    {
        $taskSpec = $this->toeflBehaviour->getTaskSpecification('read_in_daily_life');
        $this->assertSame('daily_life_document', $taskSpec->stimulusType);
        $this->assertSame('iap_derived', $taskSpec->stimulusTypeProvenance);
    }

    /**
     * TEST AA: Invalid skill -> claim/task mapping rejected.
     */
    public function test_aa_invalid_skill_mapping_rejected(): void
    {
        $this->assertFalse($this->toeflBehaviour->isSkillCompatibleWithTaskType(
            ToeflSkill::SpeakingSpontaneousInterview,
            ToeflTaskType::CompleteTheWords
        ));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Skill 'speaking_spontaneous_interview' belongs to section 'speaking', not 'reading'.");

        $this->toeflBehaviour->validateStructure([
            'section' => 'reading',
            'task_type' => 'complete_the_words',
            'skill' => 'speaking_spontaneous_interview',
        ]);
    }

    /**
     * TEST AB: Invalid task -> claim mapping rejected.
     */
    public function test_ab_invalid_task_claim_mapping_rejected(): void
    {
        $this->assertFalse($this->toeflBehaviour->isClaimCompatibleWithTaskType(
            ToeflClaim::Claim4Speaking,
            ToeflTaskType::CompleteTheWords
        ));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Claim 'claim_4_speaking' belongs to section 'speaking', not 'reading'.");

        $this->toeflBehaviour->validateStructure([
            'section' => 'reading',
            'task_type' => 'complete_the_words',
            'claim' => 'claim_4_speaking',
        ]);
    }

    /**
     * TEST AC: Explicit TOEFL part_number still rejected.
     */
    public function test_ac_explicit_toefl_part_number_still_rejected(): void
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
     * TEST AD: TOEIC Sprint 1-3 regression PASS.
     */
    public function test_ad_toeic_sprint_1_to_3_regression_pass(): void
    {
        // Sprint 1
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

        // Sprint 2
        $toeicStd = $this->registry->registerStandard([
            'assessment_family' => AssessmentFamily::Toeic,
            'version' => '2026.REGRESSION',
            'status' => StandardStatus::Draft,
            'structure_definition' => [],
        ]);
        $this->registry->activateStandard($toeicStd);
        $this->assertSame($toeicStd->id, $this->registry->getActiveStandard(AssessmentFamily::Toeic)->id);

        // Sprint 3
        $planner = new ToeicBlueprintPlanner($this->registry);
        $bpReq = ToeicBlueprintRequest::fromArray(['mode' => 'custom', 'custom_parts' => [3 => 6]]);
        $plan = $planner->plan($bpReq);
        $this->assertSame(6, $plan->totalSlots);
    }

    /**
     * TEST AE: Question Bank governance PASS.
     */
    public function test_ae_question_bank_governance_pass(): void
    {
        $this->assertDatabaseCount('question_banks', 1);
        $this->assertTrue($this->questionBank->is_published);
        $this->assertSame($this->teacher->id, $this->questionBank->created_by);
    }
}
