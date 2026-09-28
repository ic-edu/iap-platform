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
     * TEST C: Every claim has non-empty official_text, label(), and provenance().
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
     * TEST D: Claim 1 Reading official text matches exact ETS statement.
     */
    public function test_d_claim_1_reading_official_text_matches_canonical_ets(): void
    {
        $claim = ToeflClaim::Claim1Reading;
        $this->assertSame(
            'The test taker can read and understand academic and nonacademic written texts presented in a variety of formats.',
            $claim->officialText()
        );
        $this->assertSame('Claim 1 (Reading)', $claim->shortLabel());
        $this->assertSame('reading', $claim->section());
    }

    /**
     * TEST E: Claim 2 Listening official text matches exact ETS statement.
     */
    public function test_e_claim_2_listening_official_text_matches_canonical_ets(): void
    {
        $claim = ToeflClaim::Claim2Listening;
        $this->assertSame(
            'The test taker can understand spoken English in academic and social contexts, including single exchanges, short conversations, announcements, and academic talks, in order to identify meaning, purpose, and appropriate responses.',
            $claim->officialText()
        );
        $this->assertSame('Claim 2 (Listening)', $claim->shortLabel());
        $this->assertSame('listening', $claim->section());
    }

    /**
     * TEST F: Claim 3 Writing official text matches exact ETS statement.
     */
    public function test_f_claim_3_writing_official_text_matches_canonical_ets(): void
    {
        $claim = ToeflClaim::Claim3Writing;
        $this->assertSame(
            'The test taker can produce grammatically accurate and contextually appropriate written English across a range of academic and interpersonal situations, including sentence-level construction, multi-sentence responses, and extended academic discourse.',
            $claim->officialText()
        );
        $this->assertSame('Claim 3 (Writing)', $claim->shortLabel());
        $this->assertSame('writing', $claim->section());
    }

    /**
     * TEST G: Claim 4 Speaking official text matches exact ETS statement.
     */
    public function test_g_claim_4_speaking_official_text_matches_canonical_ets(): void
    {
        $claim = ToeflClaim::Claim4Speaking;
        $this->assertSame(
            'The test taker can produce intelligible and coherent spoken English to effectively communicate in both brief and extended interactions across general and academic contexts.',
            $claim->officialText()
        );
        $this->assertSame('Claim 4 (Speaking)', $claim->shortLabel());
        $this->assertSame('speaking', $claim->section());
    }

    /**
     * TEST H: All ToeflSkill entries carry explicit provenance semantics.
     */
    public function test_h_all_toefl_skills_carry_explicit_provenance(): void
    {
        $this->assertCount(16, ToeflSkill::cases());
        foreach (ToeflSkill::cases() as $skill) {
            $this->assertSame('official_ets', $skill->provenance());
            $this->assertNotEmpty($skill->officialText());
            $this->assertSame($skill->officialText(), $skill->label());
            $this->assertNotEmpty($skill->shortLabel());
            $this->assertNotEmpty($skill->section());
            $this->assertInstanceOf(ToeflClaim::class, $skill->claim());
        }
    }

    /**
     * TEST I: Reading skills official text exact match.
     */
    public function test_i_reading_skills_official_text_exact_match(): void
    {
        $this->assertSame(
            'Process academic written texts for meaning and form',
            ToeflSkill::ReadingProcessMeaningAndForm->officialText()
        );
        $this->assertSame(
            'Read and comprehend information presented in a variety of formats',
            ToeflSkill::ReadingComprehendVariedFormats->officialText()
        );
        $this->assertSame(
            'Understand short nonacademic written texts',
            ToeflSkill::ReadingShortNonacademicTexts->officialText()
        );
        $this->assertSame(
            'Understand academic text by identifying main ideas, key details, inferred meanings, idea relationships, and rhetorical structures',
            ToeflSkill::ReadingAcademicTexts->officialText()
        );
    }

    /**
     * TEST J: Listening skills official text exact match.
     */
    public function test_j_listening_skills_official_text_exact_match(): void
    {
        $this->assertSame(
            'Listen to conversational dialogue between two people',
            ToeflSkill::ListeningConversationalDialogue->officialText()
        );
        $this->assertSame(
            'Understand a single-exchange dialogue between two people',
            ToeflSkill::ListeningSingleExchangeDialogue->officialText()
        );
        $this->assertSame(
            'Understand short conversations between two people',
            ToeflSkill::ListeningShortConversations->officialText()
        );
        $this->assertSame(
            'Listen to and comprehend extended monologic speech',
            ToeflSkill::ListeningExtendedMonologicSpeech->officialText()
        );
        $this->assertSame(
            'Understand classroom or campus-related announcements',
            ToeflSkill::ListeningAnnouncements->officialText()
        );
        $this->assertSame(
            'Understand academic talks, including identifying main and supporting ideas, making inferences, and sometimes interpreting less common or idiomatic vocabulary.',
            ToeflSkill::ListeningAcademicTalks->officialText()
        );
    }

    /**
     * TEST K: Writing skills official text exact match.
     */
    public function test_k_writing_skills_official_text_exact_match(): void
    {
        $this->assertSame(
            'Reconstruct a range of sentence structures',
            ToeflSkill::WritingReconstructSentencesGrammar->officialText()
        );
        $this->assertSame(
            'Write appropriate multi-sentence text',
            ToeflSkill::WritingEffectiveResponsesAcademicContext->officialText()
        );
        $this->assertSame(
            'Write academic paragraph text that present a clear, well-supported argument using varied grammar and vocabulary',
            ToeflSkill::WritingAcademicDiscussion->officialText()
        );
    }

    /**
     * TEST L: Speaking skills official text exact match.
     */
    public function test_l_speaking_skills_official_text_exact_match(): void
    {
        $this->assertSame(
            'Repeat spoken sentences with accuracy and intelligibility',
            ToeflSkill::SpeakingRepeatSpokenSentences->officialText()
        );
        $this->assertSame(
            'Respond to questions with clear, coherent elaboration using accurate grammar, varied vocabulary, and intelligible prosody',
            ToeflSkill::SpeakingSpontaneousInterview->officialText()
        );
        $this->assertSame(
            'Speak in a way that is intelligible to proficient speakers of English',
            ToeflSkill::SpeakingIntelligibly->officialText()
        );
    }

    /**
     * TEST M: Academic Passage skill exact statements.
     */
    public function test_m_academic_passage_skill_exact_statements(): void
    {
        $skill = ToeflSkill::ReadingAcademicTexts;
        $this->assertSame(
            'Understand academic text by identifying main ideas, key details, inferred meanings, idea relationships, and rhetorical structures',
            $skill->officialText()
        );
    }

    /**
     * TEST N: Conversational dialogue skills exact statements.
     */
    public function test_n_conversational_dialogue_skills_exact_statements(): void
    {
        $skill1 = ToeflSkill::ListeningSingleExchangeDialogue;
        $this->assertSame('Understand a single-exchange dialogue between two people', $skill1->officialText());

        $skill2 = ToeflSkill::ListeningShortConversations;
        $this->assertSame('Understand short conversations between two people', $skill2->officialText());
    }

    /**
     * TEST O: Announcement and academic talk skills exact statements.
     */
    public function test_o_announcement_and_talk_skills_exact_statements(): void
    {
        $announcement = ToeflSkill::ListeningAnnouncements;
        $this->assertSame('Understand classroom or campus-related announcements', $announcement->officialText());

        $talk = ToeflSkill::ListeningAcademicTalks;
        $this->assertSame(
            'Understand academic talks, including identifying main and supporting ideas, making inferences, and sometimes interpreting less common or idiomatic vocabulary.',
            $talk->officialText()
        );
    }

    /**
     * TEST P: Sentence reconstruction and multi-sentence writing skills exact statements.
     */
    public function test_p_sentence_reconstruction_and_multi_sentence_writing_skills_exact_statements(): void
    {
        $reconstruct = ToeflSkill::WritingReconstructSentencesGrammar;
        $this->assertSame('Reconstruct a range of sentence structures', $reconstruct->officialText());

        $email = ToeflSkill::WritingEffectiveResponsesAcademicContext;
        $this->assertSame('Write appropriate multi-sentence text', $email->officialText());

        $discussion = ToeflSkill::WritingAcademicDiscussion;
        $this->assertSame(
            'Write academic paragraph text that present a clear, well-supported argument using varied grammar and vocabulary',
            $discussion->officialText()
        );
    }

    /**
     * TEST Q: Speaking skills exact statements.
     */
    public function test_q_speaking_skills_exact_statements(): void
    {
        $repeat = ToeflSkill::SpeakingRepeatSpokenSentences;
        $this->assertSame('Repeat spoken sentences with accuracy and intelligibility', $repeat->officialText());

        $interview = ToeflSkill::SpeakingSpontaneousInterview;
        $this->assertSame(
            'Respond to questions with clear, coherent elaboration using accurate grammar, varied vocabulary, and intelligible prosody',
            $interview->officialText()
        );

        $intelligibly = ToeflSkill::SpeakingIntelligibly;
        $this->assertSame('Speak in a way that is intelligible to proficient speakers of English', $intelligibly->officialText());
    }

    /**
     * TEST R: Missing expected skill fails validator.
     */
    public function test_r_missing_expected_skill_fails_validator(): void
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
     * TEST S: Extra unexpected skill fails validator.
     */
    public function test_s_extra_unexpected_skill_fails_validator(): void
    {
        $def = ToeflIbt2026StandardDefinition::getDefinition();
        // Add an extra skill to complete_the_words
        $def['validation_definition']['skill_task_compatibility']['complete_the_words'][] = ToeflSkill::WritingAcademicDiscussion->value;

        $res = $this->validator->validate($def);
        $this->assertFalse($res['is_valid']);
        $this->assertStringContainsString('unexpected extra: writing_academic_discussion', $res['errors'][0]);
    }

    /**
     * TEST T: Wrong-task skill fails validator.
     */
    public function test_t_wrong_task_skill_fails_validator(): void
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
     * TEST U: Exact canonical skill set passes validator.
     */
    public function test_u_exact_canonical_skill_set_passes_validator(): void
    {
        $def = ToeflIbt2026StandardDefinition::getDefinition();
        $res = $this->validator->validate($def);
        $this->assertTrue($res['is_valid']);
        $this->assertEmpty($res['errors']);
    }

    /**
     * TEST V: source_checked_at differs semantically from effective_from.
     */
    public function test_v_source_checked_at_differs_from_effective_from(): void
    {
        $def = ToeflIbt2026StandardDefinition::getDefinition();
        $this->assertNotSame($def['effective_from'], $def['source_checked_at']);
        $this->assertSame('2026-01-21 00:00:00', $def['effective_from']);
        $this->assertSame('2026-09-28 16:30:00', $def['source_checked_at']);
        $this->assertArrayHasKey('verification_metadata', $def['metadata']);
        $this->assertSame('UTC+07:00 (Asia/Jakarta)', $def['metadata']['verification_metadata']['timezone']);
    }

    /**
     * TEST W: TOEFL four-claim hierarchy regression PASS.
     */
    public function test_w_toefl_four_claim_hierarchy_regression_pass(): void
    {
        $this->assertSame(4, count(ToeflClaim::cases()));
        $this->assertSame(ToeflClaim::Claim1Reading, ToeflTaskType::CompleteTheWords->claim());
        $this->assertSame(ToeflClaim::Claim2Listening, ToeflTaskType::ListenToAnAcademicTalk->claim());
        $this->assertSame(ToeflClaim::Claim3Writing, ToeflTaskType::BuildASentence->claim());
        $this->assertSame(ToeflClaim::Claim4Speaking, ToeflTaskType::TakeAnInterview->claim());
    }

    /**
     * TEST X: Reading/Listening adaptive range regression PASS.
     */
    public function test_x_reading_listening_adaptive_range_regression_pass(): void
    {
        $rSpec = $this->toeflBehaviour->getSectionSpecification('reading');
        $this->assertNull($rSpec->totalItemsMin);
        $this->assertSame(50, $rSpec->totalItemsMax);

        $lSpec = $this->toeflBehaviour->getSectionSpecification('listening');
        $this->assertNull($lSpec->totalItemsMin);
        $this->assertSame(47, $lSpec->totalItemsMax);
    }

    /**
     * TEST Y: Writing/Speaking fixed-count regression PASS.
     */
    public function test_y_writing_speaking_fixed_count_regression_pass(): void
    {
        $wSpec = $this->toeflBehaviour->getSectionSpecification('writing');
        $this->assertSame(12, $wSpec->fixedTotalItems);

        $sSpec = $this->toeflBehaviour->getSectionSpecification('speaking');
        $this->assertSame(11, $sSpec->fixedTotalItems);
    }

    /**
     * TEST Z: All sixteen skills mapped to proper claims and sections.
     */
    public function test_z_all_sixteen_skills_mapped_to_proper_claims_and_sections(): void
    {
        $readingSkills = ToeflSkill::forSection('reading');
        $this->assertCount(4, $readingSkills);
        foreach ($readingSkills as $s) {
            $this->assertSame('reading', $s->section());
            $this->assertSame(ToeflClaim::Claim1Reading, $s->claim());
        }

        $listeningSkills = ToeflSkill::forSection('listening');
        $this->assertCount(6, $listeningSkills);
        foreach ($listeningSkills as $s) {
            $this->assertSame('listening', $s->section());
            $this->assertSame(ToeflClaim::Claim2Listening, $s->claim());
        }

        $writingSkills = ToeflSkill::forSection('writing');
        $this->assertCount(3, $writingSkills);
        foreach ($writingSkills as $s) {
            $this->assertSame('writing', $s->section());
            $this->assertSame(ToeflClaim::Claim3Writing, $s->claim());
        }

        $speakingSkills = ToeflSkill::forSection('speaking');
        $this->assertCount(3, $speakingSkills);
        foreach ($speakingSkills as $s) {
            $this->assertSame('speaking', $s->section());
            $this->assertSame(ToeflClaim::Claim4Speaking, $s->claim());
        }
    }

    /**
     * TEST AA: Task type to skill mappings integrity.
     */
    public function test_aa_task_type_to_skill_mappings_integrity(): void
    {
        foreach (ToeflTaskType::cases() as $task) {
            $skills = $task->skills();
            $this->assertNotEmpty($skills, "Task {$task->value} must have associated skills");
            foreach ($skills as $skill) {
                $this->assertSame(
                    $task->section(),
                    $skill->section(),
                    "Task {$task->value} section must match skill {$skill->value} section"
                );
            }
        }
    }

    /**
     * TEST AB: TOEFL standard definition claims and skills metadata.
     */
    public function test_ab_toefl_standard_definition_claims_and_skills_metadata(): void
    {
        $def = ToeflIbt2026StandardDefinition::getDefinition();
        $claimsMeta = $def['structure_definition']['claims_metadata'];
        $skillsMeta = $def['structure_definition']['skills_metadata'];

        $this->assertCount(4, $claimsMeta);
        $this->assertCount(16, $skillsMeta);

        foreach (ToeflClaim::cases() as $claim) {
            $this->assertArrayHasKey($claim->value, $claimsMeta);
            $this->assertSame($claim->officialText(), $claimsMeta[$claim->value]['official_text']);
            $this->assertSame('official_ets', $claimsMeta[$claim->value]['provenance']);
        }

        foreach (ToeflSkill::cases() as $skill) {
            $this->assertArrayHasKey($skill->value, $skillsMeta);
            $this->assertSame($skill->officialText(), $skillsMeta[$skill->value]['official_text']);
            $this->assertSame('official_ets', $skillsMeta[$skill->value]['provenance']);
        }
    }

    /**
     * TEST AC: TOEIC Sprint 1 to 3 regression PASS.
     */
    public function test_ac_toeic_sprint_1_to_3_regression_pass(): void
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
     * TEST AD: IELTS and General English behaviours intact.
     */
    public function test_ad_ielts_and_general_english_behaviours_intact(): void
    {
        $ieltsBehaviour = $this->resolver->resolve(AssessmentFamily::Ielts);
        $this->assertSame(AssessmentFamily::Ielts, $ieltsBehaviour->family());

        $geBehaviour = $this->resolver->resolve(AssessmentFamily::GeneralEnglish);
        $this->assertSame(AssessmentFamily::GeneralEnglish, $geBehaviour->family());
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

    /**
     * TEST AF: ToeflClaim resolve helper.
     */
    public function test_af_toefl_claim_resolve_helper(): void
    {
        $this->assertSame(ToeflClaim::Claim1Reading, ToeflClaim::resolve('reading'));
        $this->assertSame(ToeflClaim::Claim1Reading, ToeflClaim::resolve('claim_1_reading'));
        $this->assertSame(ToeflClaim::Claim2Listening, ToeflClaim::resolve('listening'));
        $this->assertSame(ToeflClaim::Claim3Writing, ToeflClaim::resolve('writing'));
        $this->assertSame(ToeflClaim::Claim4Speaking, ToeflClaim::resolve('speaking'));
        $this->assertNull(ToeflClaim::resolve('invalid'));
    }

    /**
     * TEST AG: ToeflSkill forSection and forClaim helpers.
     */
    public function test_ag_toefl_skill_for_section_and_claim_helpers(): void
    {
        $this->assertSame(
            ToeflSkill::forSection('reading'),
            ToeflSkill::forClaim(ToeflClaim::Claim1Reading)
        );
        $this->assertSame(
            ToeflSkill::forSection('listening'),
            ToeflSkill::forClaim(ToeflClaim::Claim2Listening)
        );
        $this->assertSame(
            ToeflSkill::forSection('writing'),
            ToeflSkill::forClaim(ToeflClaim::Claim3Writing)
        );
        $this->assertSame(
            ToeflSkill::forSection('speaking'),
            ToeflSkill::forClaim(ToeflClaim::Claim4Speaking)
        );
    }
}
