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
     * TEST A: Primary specification URL equals verified ETS official PDF path.
     */
    public function test_a_primary_specification_url_equals_verified_ets_path(): void
    {
        $expectedUrl = 'https://www.ets.org/content/dam/ets-org/pdfs/toefl/toefl-ibt-test-specifications-2026.pdf';
        $this->assertSame($expectedUrl, ToeflIbt2026StandardDefinition::SOURCE_URL);

        $def = ToeflIbt2026StandardDefinition::getDefinition();
        $this->assertSame($expectedUrl, $def['source_url']);
        $this->assertSame($expectedUrl, $def['metadata']['primary_source']['url']);
    }

    /**
     * TEST B: Exactly 4 stable claim machine IDs remain.
     */
    public function test_b_exactly_four_stable_claim_machine_ids(): void
    {
        $this->assertSame([
            'claim_1_reading',
            'claim_2_listening',
            'claim_3_writing',
            'claim_4_speaking',
        ], ToeflClaim::values());
    }

    /**
     * TEST C: Every claim has non-empty official_text.
     */
    public function test_c_every_claim_has_non_empty_official_text(): void
    {
        foreach (ToeflClaim::cases() as $claim) {
            $text = $claim->officialText();
            $this->assertNotEmpty($text);
            $this->assertSame($text, $claim->label());
            $this->assertSame('official_ets', $claim->provenance());
        }
    }

    /**
     * TEST D: Claim 1 official text matches canonical stored ETS text.
     */
    public function test_d_claim_1_official_text_matches_canonical_ets(): void
    {
        $claim = ToeflClaim::Claim1Reading;
        $this->assertSame(
            'Process and understand academic and nonacademic written texts for meaning and form across varied formats.',
            $claim->officialText()
        );
        $this->assertSame('Claim 1 (Reading)', $claim->shortLabel());
    }

    /**
     * TEST E: Claim 2 official text matches canonical stored ETS text.
     */
    public function test_e_claim_2_official_text_matches_canonical_ets(): void
    {
        $claim = ToeflClaim::Claim2Listening;
        $this->assertSame(
            'Understand conversational dialogue between two people and extended monologic speech across academic and navigational contexts.',
            $claim->officialText()
        );
        $this->assertSame('Claim 2 (Listening)', $claim->shortLabel());
    }

    /**
     * TEST F: Claim 3 official text matches canonical stored ETS text.
     */
    public function test_f_claim_3_official_text_matches_canonical_ets(): void
    {
        $claim = ToeflClaim::Claim3Writing;
        $this->assertSame(
            'Reconstruct sentence structures with appropriate grammar and write effective responses in academic and interpersonal contexts.',
            $claim->officialText()
        );
        $this->assertSame('Claim 3 (Writing)', $claim->shortLabel());
    }

    /**
     * TEST G: Claim 4 official text matches canonical stored ETS text.
     */
    public function test_g_claim_4_official_text_matches_canonical_ets(): void
    {
        $claim = ToeflClaim::Claim4Speaking;
        $this->assertSame(
            'Speak intelligibly and spontaneously in response to interview questions and repeat spoken sentences accurately.',
            $claim->officialText()
        );
        $this->assertSame('Claim 4 (Speaking)', $claim->shortLabel());
    }

    /**
     * TEST H: All ToeflSkill entries carry explicit provenance semantics.
     */
    public function test_h_all_toefl_skills_carry_explicit_provenance(): void
    {
        foreach (ToeflSkill::cases() as $skill) {
            $this->assertSame('official_ets', $skill->provenance());
            $this->assertNotEmpty($skill->officialText());
            $this->assertNotEmpty($skill->shortLabel());
        }
    }

    /**
     * TEST I: Academic Passage skill retains complete official meaning.
     */
    public function test_i_academic_passage_skill_retains_complete_official_meaning(): void
    {
        $skill = ToeflSkill::ReadingAcademicTexts;
        $text = $skill->officialText();
        $this->assertStringContainsString('main ideas', $text);
        $this->assertStringContainsString('details', $text);
        $this->assertStringContainsString('relationships', $text);
        $this->assertStringContainsString('rhetorical purpose', $text);
    }

    /**
     * TEST J: Conversation subskill identifies two-person conversation.
     */
    public function test_j_conversation_subskill_identifies_two_person_conversation(): void
    {
        $skill1 = ToeflSkill::ListeningSingleExchangeDialogue;
        $this->assertStringContainsString('two people', $skill1->officialText());

        $skill2 = ToeflSkill::ListeningShortConversations;
        $this->assertStringContainsString('two people', $skill2->officialText());
    }

    /**
     * TEST K: Announcement skill uses classroom/campus-related semantics.
     */
    public function test_k_announcement_skill_uses_campus_related_semantics(): void
    {
        $skill = ToeflSkill::ListeningAnnouncements;
        $this->assertStringContainsString('classroom-', $skill->officialText());
        $this->assertStringContainsString('campus-related', $skill->officialText());
    }

    /**
     * TEST L: Write Email skill matches official multi-sentence requirement.
     */
    public function test_l_write_email_skill_matches_multi_sentence_requirement(): void
    {
        $skill = ToeflSkill::WritingEffectiveResponsesAcademicContext;
        $this->assertStringContainsString('multi-sentence', $skill->officialText());
    }

    /**
     * TEST M: Academic Discussion skill preserves official argument requirement.
     */
    public function test_m_academic_discussion_skill_preserves_argument_requirement(): void
    {
        $skill = ToeflSkill::WritingAcademicDiscussion;
        $this->assertStringContainsString('paragraph', $skill->officialText());
        $this->assertStringContainsString('supporting an opinion', $skill->officialText());
    }

    /**
     * TEST N: Listen/Repeat skill preserves accuracy/intelligibility requirement.
     */
    public function test_n_listen_repeat_skill_preserves_accuracy_intelligibility(): void
    {
        $skill = ToeflSkill::SpeakingRepeatSpokenSentences;
        $this->assertStringContainsString('accurately', $skill->officialText());
        $this->assertStringContainsString('intelligible', $skill->officialText());
    }

    /**
     * TEST O: Take Interview skill preserves official response requirement.
     */
    public function test_o_take_interview_skill_preserves_official_response(): void
    {
        $skill = ToeflSkill::SpeakingSpontaneousInterview;
        $this->assertStringContainsString('spontaneously', $skill->officialText());
        $this->assertStringContainsString('interview questions', $skill->officialText());
    }

    /**
     * TEST P: Missing expected skill fails validator.
     */
    public function test_p_missing_expected_skill_fails_validator(): void
    {
        $def = ToeflIbt2026StandardDefinition::getDefinition();
        // Remove ReadingShortNonacademicTexts from read_in_daily_life
        $def['validation_definition']['skill_task_compatibility']['read_in_daily_life'] = [
            ToeflSkill::ReadingComprehendVariedFormats->value,
        ];

        $res = $this->validator->validate($def);
        $this->assertFalse($res['is_valid']);
        $this->assertStringContainsString('missing expected: reading_short_nonacademic_texts', $res['errors'][0]);
    }

    /**
     * TEST Q: Extra unexpected skill fails validator.
     */
    public function test_q_extra_unexpected_skill_fails_validator(): void
    {
        $def = ToeflIbt2026StandardDefinition::getDefinition();
        // Add an extra skill to complete_the_words
        $def['validation_definition']['skill_task_compatibility']['complete_the_words'][] = ToeflSkill::WritingAcademicDiscussion->value;

        $res = $this->validator->validate($def);
        $this->assertFalse($res['is_valid']);
        $this->assertStringContainsString('unexpected extra: writing_academic_discussion', $res['errors'][0]);
    }

    /**
     * TEST R: Wrong-task skill fails validator.
     */
    public function test_r_wrong_task_skill_fails_validator(): void
    {
        $def = ToeflIbt2026StandardDefinition::getDefinition();
        // Replace skill for build_a_sentence with speaking skill
        $def['validation_definition']['skill_task_compatibility']['build_a_sentence'] = [
            ToeflSkill::SpeakingIntelligibly->value,
        ];

        $res = $this->validator->validate($def);
        $this->assertFalse($res['is_valid']);
        $this->assertStringContainsString('Skill compatibility mismatch for task \'build_a_sentence\'', $res['errors'][0]);
    }

    /**
     * TEST S: Exact canonical skill set passes validator.
     */
    public function test_s_exact_canonical_skill_set_passes_validator(): void
    {
        $def = ToeflIbt2026StandardDefinition::getDefinition();
        $res = $this->validator->validate($def);
        $this->assertTrue($res['is_valid']);
        $this->assertEmpty($res['errors']);
    }

    /**
     * TEST T: source_checked_at differs semantically from effective_from.
     */
    public function test_t_source_checked_at_differs_from_effective_from(): void
    {
        $def = ToeflIbt2026StandardDefinition::getDefinition();
        $this->assertNotSame($def['effective_from'], $def['source_checked_at']);
        $this->assertSame('2026-01-21 00:00:00', $def['effective_from']);
        $this->assertSame('2026-09-28 16:30:00', $def['source_checked_at']);
        $this->assertArrayHasKey('verification_metadata', $def['metadata']);
        $this->assertSame('UTC+07:00 (Asia/Jakarta)', $def['metadata']['verification_metadata']['timezone']);
    }

    /**
     * TEST U: TOEFL four-claim hierarchy regression PASS.
     */
    public function test_u_toefl_four_claim_hierarchy_regression_pass(): void
    {
        $this->assertSame(4, count(ToeflClaim::cases()));
        $this->assertSame(ToeflClaim::Claim1Reading, ToeflTaskType::CompleteTheWords->claim());
        $this->assertSame(ToeflClaim::Claim2Listening, ToeflTaskType::ListenToAnAcademicTalk->claim());
        $this->assertSame(ToeflClaim::Claim3Writing, ToeflTaskType::BuildASentence->claim());
        $this->assertSame(ToeflClaim::Claim4Speaking, ToeflTaskType::TakeAnInterview->claim());
    }

    /**
     * TEST V: Reading/Listening adaptive range regression PASS.
     */
    public function test_v_reading_listening_adaptive_range_regression_pass(): void
    {
        $rSpec = $this->toeflBehaviour->getSectionSpecification('reading');
        $this->assertNull($rSpec->totalItemsMin);
        $this->assertSame(50, $rSpec->totalItemsMax);

        $lSpec = $this->toeflBehaviour->getSectionSpecification('listening');
        $this->assertNull($lSpec->totalItemsMin);
        $this->assertSame(47, $lSpec->totalItemsMax);
    }

    /**
     * TEST W: Writing/Speaking fixed-count regression PASS.
     */
    public function test_w_writing_speaking_fixed_count_regression_pass(): void
    {
        $wSpec = $this->toeflBehaviour->getSectionSpecification('writing');
        $this->assertSame(12, $wSpec->fixedTotalItems);

        $sSpec = $this->toeflBehaviour->getSectionSpecification('speaking');
        $this->assertSame(11, $sSpec->fixedTotalItems);
    }

    /**
     * TEST X: TOEIC Sprint 1-3 regression PASS.
     */
    public function test_x_toeic_sprint_1_to_3_regression_pass(): void
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
            'version' => '2026.REGRESSION_FINAL',
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
     * TEST Y: Question Bank governance PASS.
     */
    public function test_y_question_bank_governance_pass(): void
    {
        $this->assertDatabaseCount('question_banks', 1);
        $this->assertTrue($this->questionBank->is_published);
        $this->assertSame($this->teacher->id, $this->questionBank->created_by);
    }
}
