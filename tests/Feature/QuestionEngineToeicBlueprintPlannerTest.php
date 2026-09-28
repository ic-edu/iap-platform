<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\QuestionBank\Enums\DifficultyLevel;
use App\Modules\QuestionBank\Enums\SectionType;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Modules\QuestionEngine\DTO\QuestionGenerationRequest;
use App\Modules\QuestionEngine\DTO\ToeicBlueprintRequest;
use App\Modules\QuestionEngine\Enums\AssessmentFamily;
use App\Modules\QuestionEngine\Enums\ConstructTaxonomy;
use App\Modules\QuestionEngine\Enums\ContentMode;
use App\Modules\QuestionEngine\Enums\DomainTaxonomy;
use App\Modules\QuestionEngine\Enums\ProficiencyTarget;
use App\Modules\QuestionEngine\Enums\StandardStatus;
use App\Modules\QuestionEngine\Models\AssessmentStandard;
use App\Modules\QuestionEngine\Services\AssessmentStandardRegistry;
use App\Modules\QuestionEngine\Services\QuestionGenerationRequestValidator;
use App\Modules\QuestionEngine\Services\ToeicBlueprintPlanner;
use App\Modules\QuestionEngine\Services\WeightedSlotAllocator;
use App\Services\ToeicQuestionValidator;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class QuestionEngineToeicBlueprintPlannerTest extends TestCase
{
    use RefreshDatabase;

    protected AssessmentStandardRegistry $registry;

    protected ToeicBlueprintPlanner $planner;

    protected AssessmentStandard $activeToeicStandard;

    protected User $teacher;

    protected QuestionBank $questionBank;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->registry = app(AssessmentStandardRegistry::class);
        $this->planner = new ToeicBlueprintPlanner($this->registry);

        // Seed Active Standard for TOEIC
        $this->activeToeicStandard = $this->registry->registerStandard([
            'assessment_family' => AssessmentFamily::Toeic,
            'version' => '2026.1',
            'title' => 'TOEIC Official Standard 2026.1',
            'provider' => 'IAP Authority',
            'status' => StandardStatus::Draft,
            'structure_definition' => [],
        ]);
        $this->registry->activateStandard($this->activeToeicStandard);

        $this->teacher = User::factory()->create(['email' => 'planner_teacher@test.com']);
        $this->teacher->assignRole('teacher');

        $this->questionBank = QuestionBank::create([
            'title' => 'Planner QA Bank',
            'slug' => 'planner-qa-bank-'.uniqid(),
            'created_by' => $this->teacher->id,
            'is_published' => true,
        ]);
    }

    /**
     * TEST A: Full test mode produces exactly 200 slots.
     */
    public function test_a_full_test_produces_exactly_200_slots(): void
    {
        $request = ToeicBlueprintRequest::fromArray(['mode' => 'full_test']);
        $plan = $this->planner->plan($request);

        $this->assertSame(200, $plan->totalSlots);
        $this->assertCount(200, $plan->slots);
    }

    /**
     * TEST B & C: Listening total = 100 and Reading total = 100.
     */
    public function test_b_and_c_listening_and_reading_totals_equal_100(): void
    {
        $request = ToeicBlueprintRequest::fromArray(['mode' => 'full_test']);
        $plan = $this->planner->plan($request);

        $this->assertSame(100, $plan->sectionCounts['listening']);
        $this->assertSame(100, $plan->sectionCounts['reading']);

        $listeningSlots = array_filter($plan->slots, fn ($s) => $s->section === SectionType::Listening);
        $readingSlots = array_filter($plan->slots, fn ($s) => $s->section === SectionType::Reading);

        $this->assertCount(100, $listeningSlots);
        $this->assertCount(100, $readingSlots);
    }

    /**
     * TEST D..J: Canonical part counts for P1 to P7 (6, 25, 39, 30, 30, 16, 54).
     */
    public function test_d_to_j_canonical_part_counts(): void
    {
        $request = ToeicBlueprintRequest::fromArray(['mode' => 'full_test']);
        $plan = $this->planner->plan($request);

        $this->assertSame(6, $plan->partCounts[1]);
        $this->assertSame(25, $plan->partCounts[2]);
        $this->assertSame(39, $plan->partCounts[3]);
        $this->assertSame(30, $plan->partCounts[4]);
        $this->assertSame(30, $plan->partCounts[5]);
        $this->assertSame(16, $plan->partCounts[6]);
        $this->assertSame(54, $plan->partCounts[7]);
    }

    /**
     * TEST K: Canonical question numbering Q1 to Q200 is contiguous and unique.
     */
    public function test_k_canonical_question_numbering_q1_to_q200_is_unique_and_contiguous(): void
    {
        $request = ToeicBlueprintRequest::fromArray(['mode' => 'full_test']);
        $plan = $this->planner->plan($request);

        $qNums = array_map(fn ($s) => $s->canonicalQuestionNumber, $plan->slots);
        $this->assertSame(range(1, 200), $qNums);
    }

    /**
     * TEST L..R: Canonical numbering ranges per part.
     */
    public function test_l_to_r_canonical_numbering_ranges_per_part(): void
    {
        $request = ToeicBlueprintRequest::fromArray(['mode' => 'full_test']);
        $plan = $this->planner->plan($request);

        $partRanges = [
            1 => [1, 6],
            2 => [7, 31],
            3 => [32, 70],
            4 => [71, 100],
            5 => [101, 130],
            6 => [131, 146],
            7 => [147, 200],
        ];

        foreach ($partRanges as $part => [$start, $end]) {
            $slots = array_values(array_filter($plan->slots, fn ($s) => $s->partNumber === $part));
            $this->assertSame($start, $slots[0]->canonicalQuestionNumber);
            $this->assertSame($end, $slots[count($slots) - 1]->canonicalQuestionNumber);
            $this->assertCount($end - $start + 1, $slots);
        }
    }

    /**
     * TEST S: Part 3 = 13 AudioGroups × 3 questions = 39.
     */
    public function test_s_part_3_has_13_conversation_groups_of_3(): void
    {
        $request = ToeicBlueprintRequest::fromArray(['mode' => 'full_test']);
        $plan = $this->planner->plan($request);

        $p3Groups = array_values(array_filter($plan->groups, fn ($g) => $g->partNumber === 3));
        $this->assertCount(13, $p3Groups);

        $totalQ = 0;
        foreach ($p3Groups as $idx => $g) {
            $this->assertSame('conversation', $g->groupType);
            $this->assertSame(3, $g->questionCount);
            $this->assertCount(3, $g->questionNumbers);
            $this->assertCount(3, $g->slotSequences);
            $this->assertSame($idx + 1, $g->groupIndex);
            $totalQ += $g->questionCount;
        }
        $this->assertSame(39, $totalQ);
    }

    /**
     * TEST T: Part 4 = 10 AudioGroups × 3 questions = 30.
     */
    public function test_t_part_4_has_10_talk_groups_of_3(): void
    {
        $request = ToeicBlueprintRequest::fromArray(['mode' => 'full_test']);
        $plan = $this->planner->plan($request);

        $p4Groups = array_values(array_filter($plan->groups, fn ($g) => $g->partNumber === 4));
        $this->assertCount(10, $p4Groups);

        $totalQ = 0;
        foreach ($p4Groups as $idx => $g) {
            $this->assertSame('talk', $g->groupType);
            $this->assertSame(3, $g->questionCount);
            $this->assertCount(3, $g->questionNumbers);
            $this->assertCount(3, $g->slotSequences);
            $this->assertSame($idx + 1, $g->groupIndex);
            $totalQ += $g->questionCount;
        }
        $this->assertSame(30, $totalQ);
    }

    /**
     * TEST U..X: Part 7 = 15 groups (10 Single / 29 Q, 2 Double / 10 Q, 3 Triple / 15 Q).
     */
    public function test_u_to_x_part_7_grouping_structure(): void
    {
        $request = ToeicBlueprintRequest::fromArray(['mode' => 'full_test']);
        $plan = $this->planner->plan($request);

        $p7Groups = array_values(array_filter($plan->groups, fn ($g) => $g->partNumber === 7));
        $this->assertCount(15, $p7Groups);

        $p7Single = array_values(array_filter($p7Groups, fn ($g) => $g->groupType === 'single'));
        $p7Double = array_values(array_filter($p7Groups, fn ($g) => $g->groupType === 'double'));
        $p7Triple = array_values(array_filter($p7Groups, fn ($g) => $g->groupType === 'triple'));

        $this->assertCount(10, $p7Single);
        $this->assertSame(29, array_sum(array_map(fn ($g) => $g->questionCount, $p7Single)));
        foreach ($p7Single as $g) {
            $this->assertSame(1, $g->documentCount);
            $this->assertGreaterThanOrEqual(2, $g->questionCount);
            $this->assertLessThanOrEqual(4, $g->questionCount);
        }

        $this->assertCount(2, $p7Double);
        $this->assertSame(10, array_sum(array_map(fn ($g) => $g->questionCount, $p7Double)));
        foreach ($p7Double as $g) {
            $this->assertSame(2, $g->documentCount);
            $this->assertSame(5, $g->questionCount);
        }

        $this->assertCount(3, $p7Triple);
        $this->assertSame(15, array_sum(array_map(fn ($g) => $g->questionCount, $p7Triple)));
        foreach ($p7Triple as $g) {
            $this->assertSame(3, $g->documentCount);
            $this->assertSame(5, $g->questionCount);
        }
    }

    /**
     * TEST Y: Active TOEIC standard is bound to plan.
     */
    public function test_y_active_toeic_standard_bound_to_plan(): void
    {
        $request = ToeicBlueprintRequest::fromArray(['mode' => 'full_test']);
        $plan = $this->planner->plan($request);

        $this->assertSame(AssessmentFamily::Toeic, $plan->assessmentFamily);
        $this->assertSame($this->activeToeicStandard->id, $plan->assessmentStandardId);
        $this->assertSame('2026.1', $plan->standardVersion);

        foreach ($plan->slots as $slot) {
            $this->assertSame($this->activeToeicStandard->id, $slot->assessmentStandardId);
            $this->assertSame('2026.1', $slot->standardVersion);
        }
    }

    /**
     * TEST Z: Missing active TOEIC standard fails with RuntimeException.
     */
    public function test_z_missing_active_toeic_standard_fails(): void
    {
        // Supersede active standard so none is active
        $this->activeToeicStandard->update(['status' => StandardStatus::Superseded]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No active assessment standard found for TOEIC family');

        $request = ToeicBlueprintRequest::fromArray(['mode' => 'full_test']);
        $this->planner->plan($request);
    }

    /**
     * TEST AA: Difficulty 30/50/20 allocation is exact.
     */
    public function test_aa_difficulty_30_50_20_allocation_is_exact(): void
    {
        $request = ToeicBlueprintRequest::fromArray([
            'mode' => 'full_test',
            'difficulty_distribution' => [
                'easy' => 30,
                'medium' => 50,
                'hard' => 20,
            ],
        ]);
        $plan = $this->planner->plan($request);

        $this->assertSame(60, $plan->difficultyCounts['easy']);
        $this->assertSame(100, $plan->difficultyCounts['medium']);
        $this->assertSame(40, $plan->difficultyCounts['hard']);
        $this->assertSame(200, array_sum($plan->difficultyCounts));

        $easySlots = count(array_filter($plan->slots, fn ($s) => $s->difficulty === DifficultyLevel::Easy));
        $medSlots = count(array_filter($plan->slots, fn ($s) => $s->difficulty === DifficultyLevel::Medium));
        $hardSlots = count(array_filter($plan->slots, fn ($s) => $s->difficulty === DifficultyLevel::Hard));

        $this->assertSame(60, $easySlots);
        $this->assertSame(100, $medSlots);
        $this->assertSame(40, $hardSlots);
    }

    /**
     * TEST AB: Proficiency allocation is exact.
     */
    public function test_ab_proficiency_allocation_is_exact(): void
    {
        $request = ToeicBlueprintRequest::fromArray([
            'mode' => 'full_test',
            'proficiency_distribution' => [
                'b1_low' => 25,
                'b1_standard' => 50,
                'b2_low' => 25,
            ],
        ]);
        $plan = $this->planner->plan($request);

        $this->assertSame(50, $plan->proficiencyCounts['b1_low']);
        $this->assertSame(100, $plan->proficiencyCounts['b1_standard']);
        $this->assertSame(50, $plan->proficiencyCounts['b2_low']);
        $this->assertSame(200, array_sum($plan->proficiencyCounts));
    }

    /**
     * TEST AC: Percentages not equal to 100 are rejected.
     */
    public function test_ac_percentages_not_equal_to_100_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Difficulty distribution percentages must sum to exactly 100');

        ToeicBlueprintRequest::fromArray([
            'mode' => 'full_test',
            'difficulty_distribution' => [
                'easy' => 30,
                'medium' => 40, // Sum = 70 != 100
            ],
        ]);
    }

    /**
     * TEST AD: Incompatible construct is rejected.
     */
    public function test_ad_incompatible_construct_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Construct [visual_description] is incompatible with Part 5');

        ToeicBlueprintRequest::fromArray([
            'mode' => 'part',
            'part_number' => 5,
            'construct_distribution' => [
                'visual_description' => 100,
            ],
        ]);
    }

    /**
     * TEST AE: Part 5 grammar/vocabulary distribution is valid.
     */
    public function test_ae_part_5_grammar_vocabulary_valid(): void
    {
        $request = ToeicBlueprintRequest::fromArray([
            'mode' => 'part',
            'part_number' => 5,
            'construct_distribution' => [
                'grammar' => 60,
                'vocabulary' => 40,
            ],
        ]);
        $plan = $this->planner->plan($request);

        $this->assertSame(30, $plan->totalSlots);
        $this->assertSame(18, $plan->constructCounts[5]['grammar']);
        $this->assertSame(12, $plan->constructCounts[5]['vocabulary']);
    }

    /**
     * TEST AF: Part 1 visual_description distribution is valid.
     */
    public function test_af_part_1_visual_description_valid(): void
    {
        $request = ToeicBlueprintRequest::fromArray([
            'mode' => 'part',
            'part_number' => 1,
            'construct_distribution' => [
                'visual_description' => 50,
                'detail' => 30,
                'vocabulary' => 20,
            ],
        ]);
        $plan = $this->planner->plan($request);

        $this->assertSame(6, $plan->totalSlots);
        $this->assertSame(3, $plan->constructCounts[1]['visual_description']);
    }

    /**
     * TEST AG: General mode resolves to general_workplace domain.
     */
    public function test_ag_general_mode_resolves_general_workplace(): void
    {
        $request = ToeicBlueprintRequest::fromArray([
            'mode' => 'full_test',
            'content_mode' => 'general',
        ]);
        $this->assertSame(DomainTaxonomy::GeneralWorkplace, $request->domain);

        // Explicit contradictory domain with general mode must fail
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('General content mode requires domain general_workplace');

        ToeicBlueprintRequest::fromArray([
            'mode' => 'full_test',
            'content_mode' => 'general',
            'domain' => 'hospitality',
        ]);
    }

    /**
     * TEST AH: Domain-specific mode requires explicit non-general domain.
     */
    public function test_ah_domain_specific_mode_requires_explicit_non_general_domain(): void
    {
        // Valid domain specific
        $reqValid = ToeicBlueprintRequest::fromArray([
            'mode' => 'full_test',
            'content_mode' => 'domain_specific',
            'domain' => 'hospitality',
        ]);
        $this->assertSame(DomainTaxonomy::Hospitality, $reqValid->domain);

        // General domain with domain_specific fails
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Domain-specific content mode requires an explicit domain other than general_workplace');

        ToeicBlueprintRequest::fromArray([
            'mode' => 'full_test',
            'content_mode' => 'domain_specific',
            'domain' => 'general_workplace',
        ]);
    }

    /**
     * TEST AI & AJ: Deterministic same request and same seed produce identical plan and fingerprint.
     */
    public function test_ai_and_aj_deterministic_same_request_and_seed_produce_identical_plan(): void
    {
        $payload = [
            'mode' => 'full_test',
            'seed' => 4242,
            'difficulty_distribution' => ['easy' => 30, 'medium' => 50, 'hard' => 20],
            'proficiency_distribution' => ['b1_low' => 25, 'b1_standard' => 50, 'b2_low' => 25],
        ];

        $req1 = ToeicBlueprintRequest::fromArray($payload);
        $req2 = ToeicBlueprintRequest::fromArray($payload);

        $plan1 = $this->planner->plan($req1);
        $plan2 = $this->planner->plan($req2);

        $this->assertSame($plan1->fingerprint, $plan2->fingerprint);
        $this->assertSame($plan1->toArray(), $plan2->toArray());
    }

    /**
     * TEST AK: Invalid part is rejected.
     */
    public function test_ak_invalid_part_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid TOEIC part number [8]');

        ToeicBlueprintRequest::fromArray([
            'mode' => 'part',
            'part_number' => 8,
        ]);
    }

    /**
     * TEST AL: Part mode full P5 produces exactly 30 slots.
     */
    public function test_al_part_mode_full_p5_produces_30_slots(): void
    {
        $request = ToeicBlueprintRequest::fromArray([
            'mode' => 'part',
            'part_number' => 5,
        ]);
        $plan = $this->planner->plan($request);

        $this->assertSame(30, $plan->totalSlots);
        $this->assertCount(30, $plan->slots);
        $this->assertSame(101, $plan->slots[0]->canonicalQuestionNumber);
        $this->assertSame(130, $plan->slots[29]->canonicalQuestionNumber);
    }

    /**
     * TEST AM: Partial part batch cannot exceed canonical part size.
     */
    public function test_am_partial_part_batch_cannot_exceed_canonical_part_size(): void
    {
        // P1 has max 6 items; requesting 7 must fail closed
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('item_count [7] exceeds canonical Part 1 limit of 6 questions');

        ToeicBlueprintRequest::fromArray([
            'mode' => 'part',
            'part_number' => 1,
            'item_count' => 7,
        ]);
    }

    /**
     * TEST AN: No slot is persisted to questions table.
     */
    public function test_an_no_slot_persisted_to_questions_table(): void
    {
        $initialCount = Question::count();

        $request = ToeicBlueprintRequest::fromArray(['mode' => 'full_test']);
        $plan = $this->planner->plan($request);

        $this->assertSame(200, $plan->totalSlots);
        $this->assertSame($initialCount, Question::count());
    }

    /**
     * TEST AO: Summary method formats clean textual report.
     */
    public function test_ao_plan_summary_and_formatted_text(): void
    {
        $request = ToeicBlueprintRequest::fromArray(['mode' => 'full_test']);
        $plan = $this->planner->plan($request);

        $summary = $plan->getSummary();
        $this->assertSame(200, $summary['total_slots']);
        $this->assertSame('TOEIC-2026.1', $summary['standard']);

        $formatted = $plan->getFormattedSummary();
        $this->assertStringContainsString('TOEIC Generation Plan', $formatted);
        $this->assertStringContainsString('Standard: TOEIC-2026.1', $formatted);
        $this->assertStringContainsString('P1 6', $formatted);
        $this->assertStringContainsString('P7 54', $formatted);
        $this->assertStringContainsString('P3 conversations: 13', $formatted);
    }

    /**
     * TEST AP: WeightedSlotAllocator allocates exact remainder distribution.
     */
    public function test_ap_weighted_slot_allocator_deterministic_and_exact(): void
    {
        $allocator = new WeightedSlotAllocator;
        $weights = ['easy' => 50, 'medium' => 30, 'hard' => 20];

        // 7 slots with 50/30/20:
        // 7*0.5=3.5 (rem 0.5), 7*0.3=2.1 (rem 0.1), 7*0.2=1.4 (rem 0.4)
        // base: 3, 2, 1 = 6. shortage = 1.
        // top rem: easy (0.5) -> easy becomes 4, medium 2, hard 1 = 7!
        $alloc = $allocator->allocate(7, $weights);
        $this->assertSame(['easy' => 4, 'medium' => 2, 'hard' => 1], $alloc);
        $this->assertSame(7, array_sum($alloc));
    }

    /**
     * TEST Section 21: Blueprint Duplication Guard - planner values originate from ToeicQuestionValidator.
     */
    public function test_blueprint_duplication_guard_matches_toeic_question_validator(): void
    {
        $request = ToeicBlueprintRequest::fromArray(['mode' => 'full_test']);
        $plan = $this->planner->plan($request);

        $validatorBlueprints = ToeicQuestionValidator::getAllPartBlueprints();
        foreach ($validatorBlueprints as $part => $bp) {
            $this->assertSame($bp['target_count'], $plan->partCounts[$part]);
        }
        $this->assertSame(ToeicQuestionValidator::getTotalCanonicalTargetCount(), $plan->totalSlots);
    }

    /**
     * TEST AQ: Sprint 1 generation contract regression PASS.
     */
    public function test_aq_sprint1_generation_contract_regression_pass(): void
    {
        $validator = new QuestionGenerationRequestValidator;

        $validRequest = QuestionGenerationRequest::fromArray([
            'part_number' => 5,
            'item_count' => 10,
            'proficiency_target' => ProficiencyTarget::B1_STANDARD->value,
            'difficulty' => DifficultyLevel::Medium->value,
            'construct' => ConstructTaxonomy::Grammar->value,
            'content_mode' => ContentMode::General->value,
        ]);

        $this->assertInstanceOf(QuestionGenerationRequest::class, $validRequest);
        $validated = $validator->validate($validRequest);
        $this->assertIsArray($validated);
        $this->assertSame(5, $validated['part_number']);
    }

    /**
     * TEST AR: Sprint 2 standards registry regression PASS.
     */
    public function test_ar_sprint2_standards_registry_regression_pass(): void
    {
        $active = $this->registry->getActiveStandard(AssessmentFamily::Toeic);
        $this->assertNotNull($active);
        $this->assertSame(StandardStatus::Active, $active->status);
        $this->assertSame('2026.1', $active->version);
    }

    /**
     * TEST AS: Question Bank governance regression PASS.
     */
    public function test_as_question_bank_governance_regression_pass(): void
    {
        $this->assertDatabaseCount('question_banks', 1);
        $this->assertTrue($this->questionBank->is_published);
        $this->assertSame($this->teacher->id, $this->questionBank->created_by);
    }
}
