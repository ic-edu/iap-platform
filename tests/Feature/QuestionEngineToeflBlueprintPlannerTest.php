<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Modules\QuestionEngine\DTO\QuestionGenerationRequest;
use App\Modules\QuestionEngine\DTO\ToeflBlueprintRequest;
use App\Modules\QuestionEngine\DTO\ToeflGenerationPlan;
use App\Modules\QuestionEngine\DTO\ToeicBlueprintRequest;
use App\Modules\QuestionEngine\Enums\AssessmentFamily;
use App\Modules\QuestionEngine\Enums\StandardStatus;
use App\Modules\QuestionEngine\Enums\ToeflLanguageUseContext;
use App\Modules\QuestionEngine\Enums\ToeflResponseMode;
use App\Modules\QuestionEngine\Enums\ToeflScoringMode;
use App\Modules\QuestionEngine\Enums\ToeflSkill;
use App\Modules\QuestionEngine\Enums\ToeflTaskType;
use App\Modules\QuestionEngine\Services\AssessmentStandardRegistry;
use App\Modules\QuestionEngine\Services\QuestionGenerationRequestValidator;
use App\Modules\QuestionEngine\Services\ToeflBlueprintPlanner;
use App\Modules\QuestionEngine\Services\ToeflIbt2026StandardDefinition;
use App\Modules\QuestionEngine\Services\ToeicBlueprintPlanner;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class QuestionEngineToeflBlueprintPlannerTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;

    protected QuestionBank $questionBank;

    protected AssessmentStandardRegistry $registry;

    protected ToeflBlueprintPlanner $planner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->teacher = User::factory()->create(['status' => 'active']);
        $this->teacher->assignRole('teacher');

        $this->questionBank = QuestionBank::create([
            'title' => 'TOEFL iBT Question Bank',
            'slug' => 'toefl-ibt-question-bank-'.uniqid(),
            'created_by' => $this->teacher->id,
            'is_published' => true,
        ]);

        $this->registry = new AssessmentStandardRegistry;
        $this->planner = new ToeflBlueprintPlanner($this->registry);

        // Register and activate canonical 2026.1 TOEFL iBT standard
        $toeflStd = $this->registry->registerStandard(ToeflIbt2026StandardDefinition::getDefinition());
        $this->registry->activateStandard($toeflStd);
    }

    /**
     * TEST A: Active TOEFL standard required (fails if no active standard).
     */
    public function test_a_active_toefl_standard_required(): void
    {
        $active = $this->registry->getActiveStandard(AssessmentFamily::ToeflIbt);
        $this->assertNotNull($active);
        $active->update(['status' => StandardStatus::Archived]);

        $this->assertNull($this->registry->findActiveStandard(AssessmentFamily::ToeflIbt));

        $req = ToeflBlueprintRequest::fromArray(['mode' => 'full_test']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No active assessment standard found for TOEFL iBT family');
        $this->planner->plan($req);
    }

    /**
     * TEST B: Standard ID and version bound atomically from active standard.
     */
    public function test_b_standard_id_and_version_bound_atomically(): void
    {
        $active = $this->registry->getActiveStandard(AssessmentFamily::ToeflIbt);
        $req = ToeflBlueprintRequest::fromArray(['mode' => 'full_test']);
        $plan = $this->planner->plan($req);

        $this->assertSame($active->id, $plan->assessmentStandardId);
        $this->assertSame($active->version, $plan->standardVersion);
        $this->assertSame('2026.1', $plan->standardVersion);

        foreach ($plan->slots as $slot) {
            $this->assertSame($active->id, $slot->assessmentStandardId);
            $this->assertSame($active->version, $slot->standardVersion);
        }
    }

    /**
     * TEST C: Mismatched standard ID rejected.
     */
    public function test_c_mismatched_standard_id_rejected(): void
    {
        $req = ToeflBlueprintRequest::fromArray([
            'mode' => 'full_test',
            'standard_id' => 'non_existent_standard_id_999',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not match active TOEFL iBT standard');
        $this->planner->plan($req);
    }

    /**
     * TEST D: Mismatched standard version rejected.
     */
    public function test_d_mismatched_standard_version_rejected(): void
    {
        $req = ToeflBlueprintRequest::fromArray([
            'mode' => 'full_test',
            'standard_version' => '1999.0_FAKE',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not match active TOEFL iBT standard version');
        $this->planner->plan($req);
    }

    /**
     * TEST E: full_test includes all 4 sections.
     */
    public function test_e_full_test_includes_all_four_sections(): void
    {
        $req = ToeflBlueprintRequest::fromArray(['mode' => 'full_test']);
        $plan = $this->planner->plan($req);

        $this->assertArrayHasKey('reading', $plan->sectionCounts);
        $this->assertArrayHasKey('listening', $plan->sectionCounts);
        $this->assertArrayHasKey('writing', $plan->sectionCounts);
        $this->assertArrayHasKey('speaking', $plan->sectionCounts);
        $this->assertCount(4, $plan->sectionCounts);
    }

    /**
     * TEST F: full_test order = Reading, Listening, Writing, Speaking.
     */
    public function test_f_full_test_order_reading_listening_writing_speaking(): void
    {
        $req = ToeflBlueprintRequest::fromArray(['mode' => 'full_test']);
        $plan = $this->planner->plan($req);

        $sectionsInOrder = [];
        $lastSection = null;
        foreach ($plan->slots as $slot) {
            if ($slot->section !== $lastSection) {
                $sectionsInOrder[] = $slot->section;
                $lastSection = $slot->section;
            }
        }

        $this->assertSame(['reading', 'listening', 'writing', 'speaking'], $sectionsInOrder);
    }

    /**
     * TEST G: No TOEFL part_number.
     */
    public function test_g_no_toefl_part_number(): void
    {
        $req = ToeflBlueprintRequest::fromArray(['mode' => 'full_test']);
        $plan = $this->planner->plan($req);

        foreach ($plan->slots as $slot) {
            $this->assertNull($slot->partNumber);
        }

        // Passing part_number in request payload throws InvalidArgumentException
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('TOEFL iBT does not use part_number');
        ToeflBlueprintRequest::fromArray(['part_number' => 5]);
    }

    /**
     * TEST H: Reading is adaptive.
     */
    public function test_h_reading_is_adaptive(): void
    {
        $req = ToeflBlueprintRequest::fromArray(['mode' => 'section', 'section' => 'reading']);
        $plan = $this->planner->plan($req);

        foreach ($plan->slots as $slot) {
            $this->assertTrue($slot->adaptiveSection);
            $this->assertNotNull($slot->adaptiveStage);
            $this->assertNotNull($slot->moduleRole);
        }

        $this->assertNotEmpty($plan->adaptiveModules);
        $this->assertSame('reading', $plan->adaptiveModules[0]->section);
    }

    /**
     * TEST I: Listening is adaptive.
     */
    public function test_i_listening_is_adaptive(): void
    {
        $req = ToeflBlueprintRequest::fromArray(['mode' => 'section', 'section' => 'listening']);
        $plan = $this->planner->plan($req);

        foreach ($plan->slots as $slot) {
            $this->assertTrue($slot->adaptiveSection);
            $this->assertNotNull($slot->adaptiveStage);
            $this->assertNotNull($slot->moduleRole);
        }

        $this->assertNotEmpty($plan->adaptiveModules);
        $this->assertSame('listening', $plan->adaptiveModules[0]->section);
    }

    /**
     * TEST J: Writing is linear.
     */
    public function test_j_writing_is_linear(): void
    {
        $req = ToeflBlueprintRequest::fromArray(['mode' => 'section', 'section' => 'writing']);
        $plan = $this->planner->plan($req);

        foreach ($plan->slots as $slot) {
            $this->assertFalse($slot->adaptiveSection);
            $this->assertNull($slot->adaptiveStage);
            $this->assertNull($slot->moduleRole);
        }

        $this->assertEmpty($plan->adaptiveModules);
    }

    /**
     * TEST K: Speaking is linear.
     */
    public function test_k_speaking_is_linear(): void
    {
        $req = ToeflBlueprintRequest::fromArray(['mode' => 'section', 'section' => 'speaking']);
        $plan = $this->planner->plan($req);

        foreach ($plan->slots as $slot) {
            $this->assertFalse($slot->adaptiveSection);
            $this->assertNull($slot->adaptiveStage);
            $this->assertNull($slot->moduleRole);
        }

        $this->assertEmpty($plan->adaptiveModules);
    }

    /**
     * TEST L: Reading plan <= 50.
     */
    public function test_l_reading_plan_le_50(): void
    {
        $req = ToeflBlueprintRequest::fromArray(['mode' => 'section', 'section' => 'reading']);
        $plan = $this->planner->plan($req);

        $this->assertLessThanOrEqual(50, $plan->totalSlots);
        $this->assertSame(50, $plan->sectionCounts['reading']);
    }

    /**
     * TEST M: Listening plan <= 47.
     */
    public function test_m_listening_plan_le_47(): void
    {
        $req = ToeflBlueprintRequest::fromArray(['mode' => 'section', 'section' => 'listening']);
        $plan = $this->planner->plan($req);

        $this->assertLessThanOrEqual(47, $plan->totalSlots);
        $this->assertSame(47, $plan->sectionCounts['listening']);
    }

    /**
     * TEST N: Writing total = 12.
     */
    public function test_n_writing_total_equals_12(): void
    {
        $req = ToeflBlueprintRequest::fromArray(['mode' => 'section', 'section' => 'writing']);
        $plan = $this->planner->plan($req);

        $this->assertSame(12, $plan->totalSlots);
        $this->assertSame(12, $plan->sectionCounts['writing']);
    }

    /**
     * TEST O: Speaking total = 11.
     */
    public function test_o_speaking_total_equals_11(): void
    {
        $req = ToeflBlueprintRequest::fromArray(['mode' => 'section', 'section' => 'speaking']);
        $plan = $this->planner->plan($req);

        $this->assertSame(11, $plan->totalSlots);
        $this->assertSame(11, $plan->sectionCounts['speaking']);
    }

    /**
     * TEST P: Build a Sentence = 10.
     */
    public function test_p_build_a_sentence_equals_10(): void
    {
        $req = ToeflBlueprintRequest::fromArray(['mode' => 'full_test']);
        $plan = $this->planner->plan($req);

        $this->assertSame(10, $plan->taskCounts[ToeflTaskType::BuildASentence->value]);
    }

    /**
     * TEST Q: Write an Email = 1.
     */
    public function test_q_write_an_email_equals_1(): void
    {
        $req = ToeflBlueprintRequest::fromArray(['mode' => 'full_test']);
        $plan = $this->planner->plan($req);

        $this->assertSame(1, $plan->taskCounts[ToeflTaskType::WriteAnEmail->value]);
    }

    /**
     * TEST R: Academic Discussion = 1.
     */
    public function test_r_academic_discussion_equals_1(): void
    {
        $req = ToeflBlueprintRequest::fromArray(['mode' => 'full_test']);
        $plan = $this->planner->plan($req);

        $this->assertSame(1, $plan->taskCounts[ToeflTaskType::WriteForAnAcademicDiscussion->value]);
    }

    /**
     * TEST S: Listen and Repeat = 7.
     */
    public function test_s_listen_and_repeat_equals_7(): void
    {
        $req = ToeflBlueprintRequest::fromArray(['mode' => 'full_test']);
        $plan = $this->planner->plan($req);

        $this->assertSame(7, $plan->taskCounts[ToeflTaskType::ListenAndRepeat->value]);
    }

    /**
     * TEST T: Take an Interview = 4.
     */
    public function test_t_take_an_interview_equals_4(): void
    {
        $req = ToeflBlueprintRequest::fromArray(['mode' => 'full_test']);
        $plan = $this->planner->plan($req);

        $this->assertSame(4, $plan->taskCounts[ToeflTaskType::TakeAnInterview->value]);
    }

    /**
     * TEST U: Reading canonical task set exact.
     */
    public function test_u_reading_canonical_task_set_exact(): void
    {
        $readingTasks = ToeflTaskType::forSection('reading');
        $this->assertSame([
            ToeflTaskType::CompleteTheWords,
            ToeflTaskType::ReadInDailyLife,
            ToeflTaskType::ReadAnAcademicPassage,
        ], $readingTasks);
    }

    /**
     * TEST V: Listening canonical task set exact.
     */
    public function test_v_listening_canonical_task_set_exact(): void
    {
        $listeningTasks = ToeflTaskType::forSection('listening');
        $this->assertSame([
            ToeflTaskType::ListenAndChooseAResponse,
            ToeflTaskType::ListenToAConversation,
            ToeflTaskType::ListenToAnAnnouncement,
            ToeflTaskType::ListenToAnAcademicTalk,
        ], $listeningTasks);
    }

    /**
     * TEST W: Writing canonical task set exact.
     */
    public function test_w_writing_canonical_task_set_exact(): void
    {
        $writingTasks = ToeflTaskType::forSection('writing');
        $this->assertSame([
            ToeflTaskType::BuildASentence,
            ToeflTaskType::WriteAnEmail,
            ToeflTaskType::WriteForAnAcademicDiscussion,
        ], $writingTasks);
    }

    /**
     * TEST X: Speaking canonical task set exact.
     */
    public function test_x_speaking_canonical_task_set_exact(): void
    {
        $speakingTasks = ToeflTaskType::forSection('speaking');
        $this->assertSame([
            ToeflTaskType::ListenAndRepeat,
            ToeflTaskType::TakeAnInterview,
        ], $speakingTasks);
    }

    /**
     * TEST Y: ranged Reading task below min rejected.
     */
    public function test_y_ranged_reading_task_below_min_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('outside canonical official range');
        ToeflBlueprintRequest::fromArray([
            'mode' => 'task',
            'task_type' => 'read_in_daily_life',
            'task_count' => 4, // min is 5
        ]);
    }

    /**
     * TEST Z: ranged Reading task above max rejected.
     */
    public function test_z_ranged_reading_task_above_max_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('outside canonical official range');
        ToeflBlueprintRequest::fromArray([
            'mode' => 'task',
            'task_type' => 'read_in_daily_life',
            'task_count' => 16, // max is 15
        ]);
    }

    /**
     * TEST AA: ranged Listening task below min rejected.
     */
    public function test_aa_ranged_listening_task_below_min_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('outside canonical official range');
        ToeflBlueprintRequest::fromArray([
            'mode' => 'task',
            'task_type' => 'listen_to_an_announcement',
            'task_count' => 5, // min is 6
        ]);
    }

    /**
     * TEST AB: ranged Listening task above max rejected.
     */
    public function test_ab_ranged_listening_task_above_max_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('outside canonical official range');
        ToeflBlueprintRequest::fromArray([
            'mode' => 'task',
            'task_type' => 'listen_to_an_announcement',
            'task_count' => 11, // max is 10
        ]);
    }

    /**
     * TEST AC: fixed task default resolves correctly.
     */
    public function test_ac_fixed_task_default_resolves_correctly(): void
    {
        $req = ToeflBlueprintRequest::fromArray([
            'mode' => 'task',
            'task_type' => 'complete_the_words',
        ]);
        $plan = $this->planner->plan($req);

        $this->assertSame(30, $plan->totalSlots);
        $this->assertSame(30, $plan->taskCounts[ToeflTaskType::CompleteTheWords->value]);
    }

    /**
     * TEST AD: strict integer count accepted.
     */
    public function test_ad_strict_integer_count_accepted(): void
    {
        $req = ToeflBlueprintRequest::fromArray([
            'mode' => 'task',
            'task_type' => 'read_in_daily_life',
            'task_count' => 8,
        ]);
        $plan = $this->planner->plan($req);

        $this->assertSame(8, $plan->totalSlots);
    }

    /**
     * TEST AE: integer-string count accepted.
     */
    public function test_ae_integer_string_count_accepted(): void
    {
        $req = ToeflBlueprintRequest::fromArray([
            'mode' => 'task',
            'task_type' => 'read_in_daily_life',
            'task_count' => '8',
        ]);
        $plan = $this->planner->plan($req);

        $this->assertSame(8, $plan->totalSlots);
    }

    /**
     * TEST AF: decimal count rejected.
     */
    public function test_af_decimal_count_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be a valid positive integer');
        ToeflBlueprintRequest::fromArray([
            'mode' => 'task',
            'task_type' => 'read_in_daily_life',
            'task_count' => 8.5,
        ]);
    }

    /**
     * TEST AG: decimal-string count rejected.
     */
    public function test_ag_decimal_string_count_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be a valid positive integer');
        ToeflBlueprintRequest::fromArray([
            'mode' => 'task',
            'task_type' => 'read_in_daily_life',
            'task_count' => '8.5',
        ]);
    }

    /**
     * TEST AH: invalid task type rejected.
     */
    public function test_ah_invalid_task_type_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid TOEFL task type');
        ToeflBlueprintRequest::fromArray([
            'mode' => 'task',
            'task_type' => 'invalid_task_type_name',
        ]);
    }

    /**
     * TEST AI: task cannot cross section.
     */
    public function test_ai_task_cannot_cross_section(): void
    {
        $req = ToeflBlueprintRequest::fromArray(['mode' => 'full_test']);
        $plan = $this->planner->plan($req);

        foreach ($plan->slots as $slot) {
            $this->assertSame($slot->taskType->section(), $slot->section);
        }
    }

    /**
     * TEST AJ: every slot has canonical claim.
     */
    public function test_aj_every_slot_has_canonical_claim(): void
    {
        $req = ToeflBlueprintRequest::fromArray(['mode' => 'full_test']);
        $plan = $this->planner->plan($req);

        foreach ($plan->slots as $slot) {
            $this->assertSame($slot->taskType->claim(), $slot->claim);
            $this->assertSame('official_ets', $slot->claim->provenance());
        }
    }

    /**
     * TEST AK: every slot has compatible skill.
     */
    public function test_ak_every_slot_has_compatible_skill(): void
    {
        $req = ToeflBlueprintRequest::fromArray(['mode' => 'full_test']);
        $plan = $this->planner->plan($req);

        foreach ($plan->slots as $slot) {
            $this->assertContains($slot->skill, $slot->taskType->skills());
            $this->assertSame('official_ets', $slot->skill->provenance());
        }
    }

    /**
     * TEST AL: invalid skill distribution rejected.
     */
    public function test_al_invalid_skill_distribution_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is incompatible with task');
        ToeflBlueprintRequest::fromArray([
            'mode' => 'task',
            'task_type' => 'complete_the_words',
            'skill_distribution' => [
                'complete_the_words' => [
                    ToeflSkill::SpeakingIntelligibly->value => 100,
                ],
            ],
        ]);
    }

    /**
     * TEST AM: skill distribution total != 100 rejected.
     */
    public function test_am_skill_distribution_total_not_100_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must sum to 100');
        ToeflBlueprintRequest::fromArray([
            'mode' => 'task',
            'task_type' => 'read_in_daily_life',
            'skill_distribution' => [
                'read_in_daily_life' => [
                    ToeflSkill::ReadingComprehendVariedFormats->value => 40,
                    ToeflSkill::ReadingShortNonacademicTexts->value => 50,
                ],
            ],
        ]);
    }

    /**
     * TEST AN: skill allocation deterministic.
     */
    public function test_an_skill_allocation_deterministic(): void
    {
        $req1 = ToeflBlueprintRequest::fromArray([
            'mode' => 'task',
            'task_type' => 'read_in_daily_life',
            'task_count' => 10,
            'seed' => 4242,
        ]);
        $plan1 = $this->planner->plan($req1);

        $req2 = ToeflBlueprintRequest::fromArray([
            'mode' => 'task',
            'task_type' => 'read_in_daily_life',
            'task_count' => 10,
            'seed' => 4242,
        ]);
        $plan2 = $this->planner->plan($req2);

        $skills1 = array_map(fn ($s) => $s->skill->value, $plan1->slots);
        $skills2 = array_map(fn ($s) => $s->skill->value, $plan2->slots);

        $this->assertSame($skills1, $skills2);
    }

    /**
     * TEST AO: valid context allocation.
     */
    public function test_ao_valid_context_allocation(): void
    {
        $req = ToeflBlueprintRequest::fromArray(['mode' => 'full_test']);
        $plan = $this->planner->plan($req);

        foreach ($plan->slots as $slot) {
            $this->assertContains($slot->languageUseContext, $slot->taskType->allowedLanguageUseContexts());
        }
    }

    /**
     * TEST AP: invalid context/task pairing rejected.
     */
    public function test_ap_invalid_context_task_pairing_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is not allowed for task');
        ToeflBlueprintRequest::fromArray([
            'mode' => 'task',
            'task_type' => 'complete_the_words', // only allows academic
            'language_context_distribution' => [
                'complete_the_words' => [
                    ToeflLanguageUseContext::SocialInterpersonal->value => 100,
                ],
            ],
        ]);
    }

    /**
     * TEST AQ: response mode inherited canonically.
     */
    public function test_aq_response_mode_inherited_canonically(): void
    {
        $req = ToeflBlueprintRequest::fromArray(['mode' => 'full_test']);
        $plan = $this->planner->plan($req);

        foreach ($plan->slots as $slot) {
            $this->assertSame($slot->taskType->responseMode(), $slot->responseMode);
        }
    }

    /**
     * TEST AR: scoring mode inherited canonically.
     */
    public function test_ar_scoring_mode_inherited_canonically(): void
    {
        $req = ToeflBlueprintRequest::fromArray(['mode' => 'full_test']);
        $plan = $this->planner->plan($req);

        foreach ($plan->slots as $slot) {
            $this->assertSame($slot->taskType->scoringMode(), $slot->scoringMode);
        }
    }

    /**
     * TEST AS: request cannot override scoring mode.
     */
    public function test_as_request_cannot_override_scoring_mode(): void
    {
        // Build a Sentence is Machine scored canonically
        $req = ToeflBlueprintRequest::fromArray([
            'mode' => 'task',
            'task_type' => 'build_a_sentence',
        ]);
        $plan = $this->planner->plan($req);

        foreach ($plan->slots as $slot) {
            $this->assertSame(ToeflScoringMode::Machine, $slot->scoringMode);
        }
    }

    /**
     * TEST AT: request cannot override response mode.
     */
    public function test_at_request_cannot_override_response_mode(): void
    {
        // Complete the Words is Reconstruction canonically
        $req = ToeflBlueprintRequest::fromArray([
            'mode' => 'task',
            'task_type' => 'complete_the_words',
        ]);
        $plan = $this->planner->plan($req);

        foreach ($plan->slots as $slot) {
            $this->assertSame(ToeflResponseMode::Reconstruction, $slot->responseMode);
        }
    }

    /**
     * TEST AU: Reading pretest capability represented.
     */
    public function test_au_reading_pretest_capability_represented(): void
    {
        $req = ToeflBlueprintRequest::fromArray([
            'mode' => 'section',
            'section' => 'reading',
            'item_scoring_category' => 'pretest',
        ]);
        $plan = $this->planner->plan($req);

        foreach ($plan->slots as $slot) {
            $this->assertSame('pretest', $slot->itemScoringCategory);
        }
    }

    /**
     * TEST AV: Listening pretest capability represented.
     */
    public function test_av_listening_pretest_capability_represented(): void
    {
        $req = ToeflBlueprintRequest::fromArray([
            'mode' => 'section',
            'section' => 'listening',
            'item_scoring_category' => 'scored',
        ]);
        $plan = $this->planner->plan($req);

        foreach ($plan->slots as $slot) {
            $this->assertSame('scored', $slot->itemScoringCategory);
        }
    }

    /**
     * TEST AW: default item_scoring_category does not invent scored/pretest status.
     */
    public function test_aw_default_item_scoring_category_unspecified(): void
    {
        $req = ToeflBlueprintRequest::fromArray(['mode' => 'full_test']);
        $plan = $this->planner->plan($req);

        foreach ($plan->slots as $slot) {
            $this->assertSame('unspecified', $slot->itemScoringCategory);
        }
    }

    /**
     * TEST AX: same request + seed => same plan.
     */
    public function test_ax_same_request_and_seed_produces_identical_plan(): void
    {
        $req1 = ToeflBlueprintRequest::fromArray([
            'mode' => 'full_test',
            'seed' => 12345,
        ]);
        $plan1 = $this->planner->plan($req1);

        $req2 = ToeflBlueprintRequest::fromArray([
            'mode' => 'full_test',
            'seed' => 12345,
        ]);
        $plan2 = $this->planner->plan($req2);

        $this->assertSame($plan1->toArray(), $plan2->toArray());
    }

    /**
     * TEST AY: same request + seed => same fingerprint.
     */
    public function test_ay_same_request_and_seed_produces_same_fingerprint(): void
    {
        $req1 = ToeflBlueprintRequest::fromArray([
            'mode' => 'full_test',
            'seed' => 9999,
        ]);
        $plan1 = $this->planner->plan($req1);

        $req2 = ToeflBlueprintRequest::fromArray([
            'mode' => 'full_test',
            'seed' => 9999,
        ]);
        $plan2 = $this->planner->plan($req2);

        $this->assertSame($plan1->fingerprint, $plan2->fingerprint);
    }

    /**
     * TEST AZ: different normalized request => different fingerprint.
     */
    public function test_az_different_normalized_request_produces_different_fingerprint(): void
    {
        $req1 = ToeflBlueprintRequest::fromArray([
            'mode' => 'section',
            'section' => 'reading',
            'seed' => 100,
        ]);
        $plan1 = $this->planner->plan($req1);

        $req2 = ToeflBlueprintRequest::fromArray([
            'mode' => 'section',
            'section' => 'listening',
            'seed' => 100,
        ]);
        $plan2 = $this->planner->plan($req2);

        $this->assertNotSame($plan1->fingerprint, $plan2->fingerprint);
    }

    /**
     * TEST BA: planner strategy version included in fingerprint semantics.
     */
    public function test_ba_planner_strategy_version_included_in_fingerprint(): void
    {
        $req = ToeflBlueprintRequest::fromArray(['mode' => 'full_test']);
        $plan = $this->planner->plan($req);

        $this->assertSame('toefl_blueprint_v1', $plan->plannerStrategyVersion);
        $this->assertNotEmpty($plan->fingerprint);
    }

    /**
     * TEST BB: section_counts match slots.
     */
    public function test_bb_section_counts_match_slots(): void
    {
        $req = ToeflBlueprintRequest::fromArray(['mode' => 'full_test']);
        $plan = $this->planner->plan($req);

        $computed = [];
        foreach ($plan->slots as $s) {
            $computed[$s->section] = ($computed[$s->section] ?? 0) + 1;
        }

        $this->assertSame($computed, $plan->sectionCounts);
    }

    /**
     * TEST BC: task_counts match slots.
     */
    public function test_bc_task_counts_match_slots(): void
    {
        $req = ToeflBlueprintRequest::fromArray(['mode' => 'full_test']);
        $plan = $this->planner->plan($req);

        $computed = [];
        foreach ($plan->slots as $s) {
            $computed[$s->taskType->value] = ($computed[$s->taskType->value] ?? 0) + 1;
        }

        $this->assertSame($computed, $plan->taskCounts);
    }

    /**
     * TEST BD: claim_counts match slots.
     */
    public function test_bd_claim_counts_match_slots(): void
    {
        $req = ToeflBlueprintRequest::fromArray(['mode' => 'full_test']);
        $plan = $this->planner->plan($req);

        $computed = [];
        foreach ($plan->slots as $s) {
            $computed[$s->claim->value] = ($computed[$s->claim->value] ?? 0) + 1;
        }

        $this->assertSame($computed, $plan->claimCounts);
    }

    /**
     * TEST BE: skill_counts match slots.
     */
    public function test_be_skill_counts_match_slots(): void
    {
        $req = ToeflBlueprintRequest::fromArray(['mode' => 'full_test']);
        $plan = $this->planner->plan($req);

        $computed = [];
        foreach ($plan->slots as $s) {
            $computed[$s->skill->value] = ($computed[$s->skill->value] ?? 0) + 1;
        }

        $this->assertSame($computed, $plan->skillCounts);
    }

    /**
     * TEST BF: no duplicate sequence.
     */
    public function test_bf_no_duplicate_sequence(): void
    {
        $req = ToeflBlueprintRequest::fromArray(['mode' => 'full_test']);
        $plan = $this->planner->plan($req);

        $sequences = array_map(fn ($s) => $s->sequence, $plan->slots);
        $expected = range(1, count($plan->slots));

        $this->assertSame($expected, $sequences);
    }

    /**
     * TEST BG: no DB Question creation.
     */
    public function test_bg_no_db_question_creation(): void
    {
        $initialCount = Question::count();
        $this->assertSame(0, $initialCount);

        $req = ToeflBlueprintRequest::fromArray(['mode' => 'full_test']);
        $this->planner->plan($req);

        $this->assertSame(0, Question::count());
    }

    /**
     * TEST BH: no persistent UAT mutation.
     */
    public function test_bh_no_persistent_uat_mutation(): void
    {
        $req = ToeflBlueprintRequest::fromArray(['mode' => 'full_test']);
        $plan = $this->planner->plan($req);

        $this->assertInstanceOf(ToeflGenerationPlan::class, $plan);
        $this->assertNotEmpty($plan->getFormattedSummary());
        $this->assertNotEmpty($plan->getSummary());
    }

    /**
     * TEST BI: Sprint 1 regression PASS.
     */
    public function test_bi_sprint_1_regression_pass(): void
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
    }

    /**
     * TEST BJ: Sprint 2 regression PASS.
     */
    public function test_bj_sprint_2_regression_pass(): void
    {
        $toeicStd = $this->registry->registerStandard([
            'assessment_family' => AssessmentFamily::Toeic,
            'version' => '2026.REGRESSION_S5',
            'status' => StandardStatus::Draft,
            'structure_definition' => [],
        ]);
        $this->registry->activateStandard($toeicStd);
        $this->assertSame($toeicStd->id, $this->registry->getActiveStandard(AssessmentFamily::Toeic)->id);
    }

    /**
     * TEST BK: Sprint 3 TOEIC planner regression PASS.
     */
    public function test_bk_sprint_3_toeic_planner_regression_pass(): void
    {
        if ($this->registry->findActiveStandard(AssessmentFamily::Toeic) === null) {
            $toeicStd = $this->registry->registerStandard([
                'assessment_family' => AssessmentFamily::Toeic,
                'version' => '2026.REGRESSION_BK',
                'status' => StandardStatus::Draft,
                'structure_definition' => [],
            ]);
            $this->registry->activateStandard($toeicStd);
        }

        $toeicPlanner = new ToeicBlueprintPlanner($this->registry);
        $bpReq = ToeicBlueprintRequest::fromArray(['mode' => 'custom', 'custom_parts' => [5 => 10]]);
        $plan = $toeicPlanner->plan($bpReq);
        $this->assertSame(10, $plan->totalSlots);
    }

    /**
     * TEST BL: Sprint 4 TOEFL standard regression PASS.
     */
    public function test_bl_sprint_4_toefl_standard_regression_pass(): void
    {
        $activeToefl = $this->registry->getActiveStandard(AssessmentFamily::ToeflIbt);
        $this->assertNotNull($activeToefl);
        $this->assertSame('2026.1', $activeToefl->version);
    }

    /**
     * TEST BM: Question Bank governance PASS.
     */
    public function test_bm_question_bank_governance_pass(): void
    {
        $this->assertDatabaseCount('question_banks', 1);
        $this->assertTrue($this->questionBank->is_published);
        $this->assertSame($this->teacher->id, $this->questionBank->created_by);
    }
}
