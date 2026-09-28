<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Modules\QuestionEngine\DTO\QuestionGenerationRequest;
use App\Modules\QuestionEngine\DTO\ToeflBlueprintRequest;
use App\Modules\QuestionEngine\DTO\ToeicBlueprintRequest;
use App\Modules\QuestionEngine\Enums\AssessmentFamily;
use App\Modules\QuestionEngine\Enums\ProficiencyTarget;
use App\Modules\QuestionEngine\Enums\StandardStatus;
use App\Modules\QuestionEngine\Services\AssessmentStandardRegistry;
use App\Modules\QuestionEngine\Services\QuestionGenerationRequestValidator;
use App\Modules\QuestionEngine\Services\ToeflBlueprintPlanner;
use App\Modules\QuestionEngine\Services\ToeflIbt2026StandardDefinition;
use App\Modules\QuestionEngine\Services\ToeicBlueprintPlanner;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
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
     * TEST A: Explicit compatible proficiency distribution accepted.
     */
    public function test_a_explicit_compatible_proficiency_distribution_accepted(): void
    {
        // Listen to an Academic Talk envelope is A2-C2
        $req = ToeflBlueprintRequest::fromArray([
            'mode' => 'task',
            'task_type' => 'listen_to_an_academic_talk',
            'task_count' => 10,
            'proficiency_distribution' => [
                'b1_standard' => 50,
                'c1' => 50,
            ],
        ]);
        $plan = $this->planner->plan($req);

        $this->assertSame(10, $plan->totalSlots);
        foreach ($plan->slots as $slot) {
            $this->assertContains($slot->proficiencyTarget, [ProficiencyTarget::B1_STANDARD, ProficiencyTarget::C1]);
        }
    }

    /**
     * TEST B: Explicit out-of-envelope proficiency target rejected.
     */
    public function test_b_explicit_out_of_envelope_proficiency_target_rejected(): void
    {
        // Listen to a Response envelope is A1-B2 (C1 is outside)
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Explicit proficiency target 'c1' is outside official CEFR envelope");
        ToeflBlueprintRequest::fromArray([
            'mode' => 'task',
            'task_type' => 'listen_and_choose_a_response',
            'task_count' => 15,
            'proficiency_distribution' => [
                'c1' => 100,
            ],
        ]);
    }

    /**
     * TEST C: Explicit mixture of valid + invalid proficiency targets rejected.
     */
    public function test_c_explicit_mixture_of_valid_and_invalid_proficiency_targets_rejected(): void
    {
        // Listen to a Response allows A1-B2; mixing B1 and C1 must fail closed
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Explicit proficiency target 'c1' is outside official CEFR envelope");
        ToeflBlueprintRequest::fromArray([
            'mode' => 'task',
            'task_type' => 'listen_and_choose_a_response',
            'task_count' => 15,
            'proficiency_distribution' => [
                'b1_standard' => 50,
                'c1' => 50,
            ],
        ]);
    }

    /**
     * TEST D: Planner default proficiency remains task-compatible.
     */
    public function test_d_planner_default_proficiency_remains_task_compatible(): void
    {
        // Without explicit proficiency distribution, each task uses allowed CEFR envelope
        $req = ToeflBlueprintRequest::fromArray(['mode' => 'full_test']);
        $plan = $this->planner->plan($req);

        foreach ($plan->slots as $slot) {
            $task = $slot->taskType;
            $minCefr = $task->cefrMin();
            $maxCefr = $task->cefrMax();
            $slotCefr = $slot->proficiencyTarget->cefrBand();

            // Rank comparison
            $cefrRanks = ['A1' => 1, 'A2' => 2, 'B1' => 3, 'B2' => 4, 'C1' => 5, 'C1+' => 5.5, 'C2' => 6];
            $this->assertGreaterThanOrEqual($cefrRanks[$minCefr], $cefrRanks[$slotCefr]);
            $this->assertLessThanOrEqual($cefrRanks[$maxCefr], $cefrRanks[$slotCefr]);
        }
    }

    /**
     * TEST E: Build a Sentence task_count=10 normal mode valid.
     */
    public function test_e_build_a_sentence_task_count_10_normal_mode_valid(): void
    {
        $req = ToeflBlueprintRequest::fromArray([
            'mode' => 'task',
            'task_type' => 'build_a_sentence',
            'task_count' => 10,
        ]);
        $plan = $this->planner->plan($req);

        $this->assertSame(10, $plan->totalSlots);
    }

    /**
     * TEST F: Build a Sentence task_count=9 normal mode rejected.
     */
    public function test_f_build_a_sentence_task_count_9_normal_mode_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must equal canonical fixed count of 10');
        ToeflBlueprintRequest::fromArray([
            'mode' => 'task',
            'task_type' => 'build_a_sentence',
            'task_count' => 9,
            'allow_practice_counts' => false,
        ]);
    }

    /**
     * TEST G: Build a Sentence task_count=11 rejected.
     */
    public function test_g_build_a_sentence_task_count_11_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must equal canonical fixed count of 10');
        ToeflBlueprintRequest::fromArray([
            'mode' => 'task',
            'task_type' => 'build_a_sentence',
            'task_count' => 11,
            'allow_practice_counts' => false,
        ]);
    }

    /**
     * TEST H: Build a Sentence task_count=5 practice mode valid.
     */
    public function test_h_build_a_sentence_task_count_5_practice_mode_valid(): void
    {
        $req = ToeflBlueprintRequest::fromArray([
            'mode' => 'task',
            'task_type' => 'build_a_sentence',
            'task_count' => 5,
            'allow_practice_counts' => true,
        ]);
        $plan = $this->planner->plan($req);

        $this->assertSame(5, $plan->totalSlots);
    }

    /**
     * TEST I: Build a Sentence task_count=11 practice mode rejected.
     */
    public function test_i_build_a_sentence_task_count_11_practice_mode_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot exceed canonical fixed limit of 10');
        ToeflBlueprintRequest::fromArray([
            'mode' => 'task',
            'task_type' => 'build_a_sentence',
            'task_count' => 11,
            'allow_practice_counts' => true,
        ]);
    }

    /**
     * TEST J: Custom fixed canonical count valid.
     */
    public function test_j_custom_fixed_canonical_count_valid(): void
    {
        $req = ToeflBlueprintRequest::fromArray([
            'mode' => 'custom',
            'custom_tasks' => [
                'build_a_sentence' => 10,
                'listen_and_repeat' => 7,
            ],
            'allow_practice_counts' => false,
        ]);
        $plan = $this->planner->plan($req);

        $this->assertSame(17, $plan->totalSlots);
    }

    /**
     * TEST K: Custom fixed smaller count without practice rejected.
     */
    public function test_k_custom_fixed_smaller_count_without_practice_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Custom task 'build_a_sentence' count [5] must equal canonical fixed count of 10");
        ToeflBlueprintRequest::fromArray([
            'mode' => 'custom',
            'custom_tasks' => [
                'build_a_sentence' => 5,
            ],
            'allow_practice_counts' => false,
        ]);
    }

    /**
     * TEST L: Custom fixed smaller count with practice valid.
     */
    public function test_l_custom_fixed_smaller_count_with_practice_valid(): void
    {
        $req = ToeflBlueprintRequest::fromArray([
            'mode' => 'custom',
            'custom_tasks' => [
                'build_a_sentence' => 5,
                'listen_and_repeat' => 3,
            ],
            'allow_practice_counts' => true,
        ]);
        $plan = $this->planner->plan($req);

        $this->assertSame(8, $plan->totalSlots);
    }

    /**
     * TEST M: Custom fixed count above canonical rejected even in practice.
     */
    public function test_m_custom_fixed_count_above_canonical_rejected_even_in_practice(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Custom practice count [15] exceeds canonical fixed count of 10 for task 'build_a_sentence'");
        ToeflBlueprintRequest::fromArray([
            'mode' => 'custom',
            'custom_tasks' => [
                'build_a_sentence' => 15,
            ],
            'allow_practice_counts' => true,
        ]);
    }

    /**
     * TEST N: Ranged canonical count valid.
     */
    public function test_n_ranged_canonical_count_valid(): void
    {
        $req = ToeflBlueprintRequest::fromArray([
            'mode' => 'task',
            'task_type' => 'read_in_daily_life',
            'task_count' => 10,
            'allow_practice_counts' => false,
        ]);
        $plan = $this->planner->plan($req);

        $this->assertSame(10, $plan->totalSlots);
    }

    /**
     * TEST O: Ranged below-min without practice rejected.
     */
    public function test_o_ranged_below_min_without_practice_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('outside canonical official range of [5-15]');
        ToeflBlueprintRequest::fromArray([
            'mode' => 'task',
            'task_type' => 'read_in_daily_life',
            'task_count' => 3,
            'allow_practice_counts' => false,
        ]);
    }

    /**
     * TEST P: Ranged below-min with practice valid.
     */
    public function test_p_ranged_below_min_with_practice_valid(): void
    {
        $req = ToeflBlueprintRequest::fromArray([
            'mode' => 'task',
            'task_type' => 'read_in_daily_life',
            'task_count' => 3,
            'allow_practice_counts' => true,
        ]);
        $plan = $this->planner->plan($req);

        $this->assertSame(3, $plan->totalSlots);
    }

    /**
     * TEST Q: Ranged above official max rejected even in practice.
     */
    public function test_q_ranged_above_official_max_rejected_even_in_practice(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot exceed official maximum of 15');
        ToeflBlueprintRequest::fromArray([
            'mode' => 'task',
            'task_type' => 'read_in_daily_life',
            'task_count' => 16,
            'allow_practice_counts' => true,
        ]);
    }

    /**
     * TEST R: Adaptive router module taskCounts match slotSequences.
     */
    public function test_r_adaptive_router_module_task_counts_match_slot_sequences(): void
    {
        $req = ToeflBlueprintRequest::fromArray(['mode' => 'full_test']);
        $plan = $this->planner->plan($req);

        $routerModules = array_filter($plan->adaptiveModules, fn ($m) => $m->moduleRole === 'stage_1_router');
        $this->assertCount(2, $routerModules); // Reading & Listening

        foreach ($routerModules as $mod) {
            $this->assertSame(count($mod->slotSequences), array_sum($mod->taskCounts));
            $this->assertNotEmpty($mod->slotSequences);
        }
    }

    /**
     * TEST S: Lower module placeholder contains no fake duplicated slot refs.
     */
    public function test_s_lower_module_placeholder_contains_no_fake_duplicated_slot_refs(): void
    {
        $req = ToeflBlueprintRequest::fromArray(['mode' => 'full_test']);
        $plan = $this->planner->plan($req);

        $lowerModules = array_filter($plan->adaptiveModules, fn ($m) => $m->moduleRole === 'stage_2_lower');
        $this->assertCount(2, $lowerModules);

        foreach ($lowerModules as $mod) {
            $this->assertEmpty($mod->slotSequences);
            $this->assertEmpty($mod->taskCounts);
        }
    }

    /**
     * TEST T: Upper module placeholder contains no fake duplicated slot refs.
     */
    public function test_t_upper_module_placeholder_contains_no_fake_duplicated_slot_refs(): void
    {
        $req = ToeflBlueprintRequest::fromArray(['mode' => 'full_test']);
        $plan = $this->planner->plan($req);

        $upperModules = array_filter($plan->adaptiveModules, fn ($m) => $m->moduleRole === 'stage_2_upper');
        $this->assertCount(2, $upperModules);

        foreach ($upperModules as $mod) {
            $this->assertEmpty($mod->slotSequences);
            $this->assertEmpty($mod->taskCounts);
        }
    }

    /**
     * TEST U: Lower and upper do not share falsely allocated operational slots.
     */
    public function test_u_lower_and_upper_do_not_share_falsely_allocated_operational_slots(): void
    {
        $req = ToeflBlueprintRequest::fromArray(['mode' => 'full_test']);
        $plan = $this->planner->plan($req);

        $stage2Modules = array_filter($plan->adaptiveModules, fn ($m) => in_array($m->moduleRole, ['stage_2_lower', 'stage_2_upper'], true));
        foreach ($stage2Modules as $mod) {
            $this->assertEmpty($mod->slotSequences);
        }
    }

    /**
     * TEST V: Routing threshold remains null.
     */
    public function test_v_routing_threshold_remains_null(): void
    {
        $req = ToeflBlueprintRequest::fromArray(['mode' => 'full_test']);
        $plan = $this->planner->plan($req);

        foreach ($plan->adaptiveModules as $mod) {
            $this->assertNull($mod->routingThreshold);
        }
    }

    /**
     * TEST W: Routing rule remains unspecified.
     */
    public function test_w_routing_rule_remains_unspecified(): void
    {
        $req = ToeflBlueprintRequest::fromArray(['mode' => 'full_test']);
        $plan = $this->planner->plan($req);

        foreach ($plan->adaptiveModules as $mod) {
            $this->assertSame('unspecified', $mod->routingRule);
        }
    }

    /**
     * TEST X: Adaptive provenance clearly distinguishes official structure from IAP planning.
     */
    public function test_x_adaptive_provenance_distinguishes_official_from_iap(): void
    {
        $req = ToeflBlueprintRequest::fromArray(['mode' => 'full_test']);
        $plan = $this->planner->plan($req);

        foreach ($plan->adaptiveModules as $mod) {
            $this->assertSame('official_structure_plus_iap_planning', $mod->provenance);
        }
    }

    /**
     * TEST Y: Strict boolean true accepted.
     */
    public function test_y_strict_boolean_true_accepted(): void
    {
        $req = ToeflBlueprintRequest::fromArray([
            'mode' => 'task',
            'task_type' => 'build_a_sentence',
            'task_count' => 5,
            'allow_practice_counts' => true,
        ]);
        $this->assertTrue($req->allowPracticeCounts);
    }

    /**
     * TEST Z: Strict boolean false accepted.
     */
    public function test_z_strict_boolean_false_accepted(): void
    {
        $req = ToeflBlueprintRequest::fromArray([
            'mode' => 'task',
            'task_type' => 'build_a_sentence',
            'task_count' => 10,
            'allow_practice_counts' => false,
        ]);
        $this->assertFalse($req->allowPracticeCounts);
    }

    /**
     * TEST AA: String "false" rejected.
     */
    public function test_aa_string_false_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('allow_practice_counts must be a strict boolean');
        ToeflBlueprintRequest::fromArray([
            'mode' => 'task',
            'task_type' => 'build_a_sentence',
            'task_count' => 10,
            'allow_practice_counts' => 'false',
        ]);
    }

    /**
     * TEST AB: String "true" rejected.
     */
    public function test_ab_string_true_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('allow_practice_counts must be a strict boolean');
        ToeflBlueprintRequest::fromArray([
            'mode' => 'task',
            'task_type' => 'build_a_sentence',
            'task_count' => 10,
            'allow_practice_counts' => 'true',
        ]);
    }

    /**
     * TEST AC: selected_sections rejected as unsupported.
     */
    public function test_ac_selected_sections_rejected_as_unsupported(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('selected_sections is unsupported');
        ToeflBlueprintRequest::fromArray([
            'mode' => 'full_test',
            'selected_sections' => ['reading', 'listening'],
        ]);
    }

    /**
     * TEST AD: Same request + seed deterministic regression PASS.
     */
    public function test_ad_same_request_and_seed_deterministic_regression_pass(): void
    {
        $req1 = ToeflBlueprintRequest::fromArray(['mode' => 'full_test', 'seed' => 777]);
        $plan1 = $this->planner->plan($req1);

        $req2 = ToeflBlueprintRequest::fromArray(['mode' => 'full_test', 'seed' => 777]);
        $plan2 = $this->planner->plan($req2);

        $this->assertSame($plan1->toArray(), $plan2->toArray());
    }

    /**
     * TEST AE: Fingerprint regression PASS.
     */
    public function test_ae_fingerprint_regression_pass(): void
    {
        $req = ToeflBlueprintRequest::fromArray(['mode' => 'full_test', 'seed' => 123]);
        $plan = $this->planner->plan($req);

        $this->assertSame(64, strlen($plan->fingerprint));
        $this->assertSame('toefl_blueprint_v1', $plan->plannerStrategyVersion);
    }

    /**
     * TEST AF: Full-test Reading <= 50.
     */
    public function test_af_full_test_reading_le_50(): void
    {
        $req = ToeflBlueprintRequest::fromArray(['mode' => 'full_test']);
        $plan = $this->planner->plan($req);

        $this->assertSame(50, $plan->sectionCounts['reading']);
    }

    /**
     * TEST AG: Full-test Listening <= 47.
     */
    public function test_ag_full_test_listening_le_47(): void
    {
        $req = ToeflBlueprintRequest::fromArray(['mode' => 'full_test']);
        $plan = $this->planner->plan($req);

        $this->assertSame(47, $plan->sectionCounts['listening']);
    }

    /**
     * TEST AH: Writing = 12.
     */
    public function test_ah_writing_equals_12(): void
    {
        $req = ToeflBlueprintRequest::fromArray(['mode' => 'full_test']);
        $plan = $this->planner->plan($req);

        $this->assertSame(12, $plan->sectionCounts['writing']);
    }

    /**
     * TEST AI: Speaking = 11.
     */
    public function test_ai_speaking_equals_11(): void
    {
        $req = ToeflBlueprintRequest::fromArray(['mode' => 'full_test']);
        $plan = $this->planner->plan($req);

        $this->assertSame(11, $plan->sectionCounts['speaking']);
    }

    /**
     * TEST AJ: No DB Question creation.
     */
    public function test_aj_no_db_question_creation(): void
    {
        $this->assertSame(0, Question::count());
        $req = ToeflBlueprintRequest::fromArray(['mode' => 'full_test']);
        $this->planner->plan($req);

        $this->assertSame(0, Question::count());
    }

    /**
     * TEST AK: Sprint 1 regression PASS.
     */
    public function test_ak_sprint_1_regression_pass(): void
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
     * TEST AL: Sprint 2 regression PASS.
     */
    public function test_al_sprint_2_regression_pass(): void
    {
        $toeicStd = $this->registry->registerStandard([
            'assessment_family' => AssessmentFamily::Toeic,
            'version' => '2026.REGRESSION_S5_FINAL',
            'status' => StandardStatus::Draft,
            'structure_definition' => [],
        ]);
        $this->registry->activateStandard($toeicStd);
        $this->assertSame($toeicStd->id, $this->registry->getActiveStandard(AssessmentFamily::Toeic)->id);
    }

    /**
     * TEST AM: Sprint 3 TOEIC planner regression PASS.
     */
    public function test_am_sprint_3_toeic_planner_regression_pass(): void
    {
        if ($this->registry->findActiveStandard(AssessmentFamily::Toeic) === null) {
            $toeicStd = $this->registry->registerStandard([
                'assessment_family' => AssessmentFamily::Toeic,
                'version' => '2026.REGRESSION_AM',
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
     * TEST AN: Sprint 4 TOEFL standard regression PASS.
     */
    public function test_an_sprint_4_toefl_standard_regression_pass(): void
    {
        $activeToefl = $this->registry->getActiveStandard(AssessmentFamily::ToeflIbt);
        $this->assertNotNull($activeToefl);
        $this->assertSame('2026.1', $activeToefl->version);
    }

    /**
     * TEST AO: Question Bank governance PASS.
     */
    public function test_ao_question_bank_governance_pass(): void
    {
        $this->assertDatabaseCount('question_banks', 1);
        $this->assertTrue($this->questionBank->is_published);
        $this->assertSame($this->teacher->id, $this->questionBank->created_by);
    }
}
