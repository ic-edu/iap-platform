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
use App\Modules\QuestionEngine\Enums\ProficiencyTarget;
use App\Modules\QuestionEngine\Enums\StandardStatus;
use App\Modules\QuestionEngine\Models\AssessmentStandard;
use App\Modules\QuestionEngine\Services\AssessmentStandardRegistry;
use App\Modules\QuestionEngine\Services\QuestionGenerationRequestValidator;
use App\Modules\QuestionEngine\Services\ToeicBlueprintPlanner;
use App\Services\ToeicQuestionValidator;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
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
     * TEST A: Active standard ID+version both resolved from same record.
     */
    public function test_a_active_standard_id_and_version_both_resolved_from_same_record(): void
    {
        $request = ToeicBlueprintRequest::fromArray(['mode' => 'full_test']);
        $plan = $this->planner->plan($request);

        $this->assertSame($this->activeToeicStandard->id, $plan->assessmentStandardId);
        $this->assertSame($this->activeToeicStandard->version, $plan->standardVersion);

        foreach ($plan->slots as $slot) {
            $this->assertSame($this->activeToeicStandard->id, $slot->assessmentStandardId);
            $this->assertSame($this->activeToeicStandard->version, $slot->standardVersion);
        }
    }

    /**
     * TEST B & C: Matching explicit standard_id and standard_version are accepted.
     */
    public function test_b_and_c_matching_explicit_standard_id_and_version_accepted(): void
    {
        $request = ToeicBlueprintRequest::fromArray([
            'mode' => 'full_test',
            'standard_id' => $this->activeToeicStandard->id,
            'standard_version' => $this->activeToeicStandard->version,
        ]);
        $plan = $this->planner->plan($request);

        $this->assertSame($this->activeToeicStandard->id, $plan->assessmentStandardId);
        $this->assertSame($this->activeToeicStandard->version, $plan->standardVersion);
    }

    /**
     * TEST D: Mismatched standard_version is rejected.
     */
    public function test_d_mismatched_standard_version_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Requested standard_version [2099.9] does not match active TOEIC standard version [{$this->activeToeicStandard->version}].");

        $request = ToeicBlueprintRequest::fromArray([
            'mode' => 'full_test',
            'standard_version' => '2099.9',
        ]);
        $this->planner->plan($request);
    }

    /**
     * TEST E: Mismatched standard_id is rejected.
     */
    public function test_e_mismatched_standard_id_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Requested standard_id [01fakestandardid000000000000] does not match active TOEIC standard [{$this->activeToeicStandard->id}].");

        $request = ToeicBlueprintRequest::fromArray([
            'mode' => 'full_test',
            'standard_id' => '01fakestandardid000000000000',
        ]);
        $this->planner->plan($request);
    }

    /**
     * TEST F & G: Planner and validator derive canonical structural values from ToeicQuestionValidator.
     */
    public function test_f_and_g_planner_and_validator_derive_from_toeic_question_validator(): void
    {
        $request = ToeicBlueprintRequest::fromArray(['mode' => 'full_test']);
        $plan = $this->planner->plan($request);

        $validatorBlueprints = ToeicQuestionValidator::getAllPartBlueprints();
        foreach ($validatorBlueprints as $part => $bp) {
            $this->assertSame($bp['target_count'], $plan->partCounts[$part]);
            $range = ToeicQuestionValidator::getPartQuestionRange($part);
            $partSlots = array_values(array_filter($plan->slots, fn ($s) => $s->partNumber === $part));
            $this->assertSame($range['start'], $partSlots[0]->canonicalQuestionNumber);
            $this->assertSame($range['end'], $partSlots[count($partSlots) - 1]->canonicalQuestionNumber);
        }

        $this->assertSame(ToeicQuestionValidator::getTotalCanonicalTargetCount(), $plan->totalSlots);
        $this->assertSame(ToeicQuestionValidator::getSectionTargetCount('listening'), $plan->sectionCounts['listening']);
        $this->assertSame(ToeicQuestionValidator::getSectionTargetCount('reading'), $plan->sectionCounts['reading']);
    }

    /**
     * TEST H & I: Part 3 complete audio group item_counts (3 and 6) are valid.
     */
    public function test_h_and_i_part_3_complete_audio_groups_valid(): void
    {
        $req3 = ToeicBlueprintRequest::fromArray(['mode' => 'part', 'part_number' => 3, 'item_count' => 3]);
        $plan3 = $this->planner->plan($req3);
        $this->assertSame(3, $plan3->totalSlots);
        $this->assertCount(1, $plan3->groups);
        $this->assertSame('conversation', $plan3->groups[0]->groupType);

        $req6 = ToeicBlueprintRequest::fromArray(['mode' => 'part', 'part_number' => 3, 'item_count' => 6]);
        $plan6 = $this->planner->plan($req6);
        $this->assertSame(6, $plan6->totalSlots);
        $this->assertCount(2, $plan6->groups);
    }

    /**
     * TEST J & K: Part 3 incomplete group item_counts (1 and 4) are rejected.
     */
    public function test_j_and_k_part_3_incomplete_groups_rejected(): void
    {
        try {
            ToeicBlueprintRequest::fromArray(['mode' => 'part', 'part_number' => 3, 'item_count' => 1]);
            $this->fail('Part 3 item_count=1 should fail closed');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('multiple of 3', $e->getMessage());
        }

        try {
            ToeicBlueprintRequest::fromArray(['mode' => 'part', 'part_number' => 3, 'item_count' => 4]);
            $this->fail('Part 3 item_count=4 should fail closed');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('multiple of 3', $e->getMessage());
        }
    }

    /**
     * TEST L: Part 4 incomplete audio group item_count (2) is rejected.
     */
    public function test_l_part_4_incomplete_group_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('multiple of 3');

        ToeicBlueprintRequest::fromArray(['mode' => 'part', 'part_number' => 4, 'item_count' => 2]);
    }

    /**
     * TEST M & N: Part 6 complete passage group item_counts (4 and 8) are valid.
     */
    public function test_m_and_n_part_6_complete_passage_groups_valid(): void
    {
        $req4 = ToeicBlueprintRequest::fromArray(['mode' => 'part', 'part_number' => 6, 'item_count' => 4]);
        $plan4 = $this->planner->plan($req4);
        $this->assertSame(4, $plan4->totalSlots);
        $this->assertCount(1, $plan4->groups);
        $this->assertSame('passage', $plan4->groups[0]->groupType);

        $req8 = ToeicBlueprintRequest::fromArray(['mode' => 'part', 'part_number' => 6, 'item_count' => 8]);
        $plan8 = $this->planner->plan($req8);
        $this->assertSame(8, $plan8->totalSlots);
        $this->assertCount(2, $plan8->groups);
    }

    /**
     * TEST O: Part 6 incomplete passage group item_count (1) is rejected.
     */
    public function test_o_part_6_incomplete_group_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('multiple of 4');

        ToeicBlueprintRequest::fromArray(['mode' => 'part', 'part_number' => 6, 'item_count' => 1]);
    }

    /**
     * TEST P: Part 7 partial plan never ends with incomplete passage group.
     */
    public function test_p_part_7_partial_plan_never_ends_with_incomplete_passage_group(): void
    {
        // 29 is a complete Single passage block (10 groups)
        $req29 = ToeicBlueprintRequest::fromArray(['mode' => 'part', 'part_number' => 7, 'item_count' => 29]);
        $plan29 = $this->planner->plan($req29);
        $this->assertSame(29, $plan29->totalSlots);
        $this->assertCount(10, $plan29->groups);
        foreach ($plan29->groups as $g) {
            $this->assertSame('single', $g->groupType);
            $this->assertCount($g->questionCount, $g->slotSequences);
            $this->assertCount($g->questionCount, $g->questionNumbers);
        }

        // Arbitrary item_count (e.g. 7) that does not match complete group prefix must fail closed
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not align with complete canonical passage groups');

        $req7 = ToeicBlueprintRequest::fromArray(['mode' => 'part', 'part_number' => 7, 'item_count' => 7]);
        $this->planner->plan($req7);
    }

    /**
     * TEST Q..T: Custom mode group-awareness preserves canonical group semantics.
     */
    public function test_q_to_t_custom_mode_preserves_canonical_groups(): void
    {
        $request = ToeicBlueprintRequest::fromArray([
            'mode' => 'custom',
            'custom_parts' => [
                3 => 6, // 2 conversation groups
                4 => 6, // 2 talk groups
                5 => 10, // 10 standalone
                6 => 8, // 2 passage groups
            ],
        ]);
        $plan = $this->planner->plan($request);

        $this->assertSame(30, $plan->totalSlots);

        $p3Groups = array_values(array_filter($plan->groups, fn ($g) => $g->partNumber === 3));
        $this->assertCount(2, $p3Groups);
        $this->assertSame('conversation', $p3Groups[0]->groupType);
        $this->assertSame(3, $p3Groups[0]->questionCount);

        $p4Groups = array_values(array_filter($plan->groups, fn ($g) => $g->partNumber === 4));
        $this->assertCount(2, $p4Groups);
        $this->assertSame('talk', $p4Groups[0]->groupType);
        $this->assertSame(3, $p4Groups[0]->questionCount);

        $p6Groups = array_values(array_filter($plan->groups, fn ($g) => $g->partNumber === 6));
        $this->assertCount(2, $p6Groups);
        $this->assertSame('passage', $p6Groups[0]->groupType);
        $this->assertSame(4, $p6Groups[0]->questionCount);

        // Group type must NEVER be generic 'custom_part'
        foreach ($plan->groups as $g) {
            $this->assertNotSame('custom_part', $g->groupType);
        }
    }

    /**
     * TEST U: Part 7 deterministic Single allocation satisfies 10 groups, 29 total, each 2..4.
     */
    public function test_u_part_7_single_allocation_satisfies_invariants(): void
    {
        $allocations = $this->planner->derivePart7SingleGroupAllocations(42);

        $this->assertCount(10, $allocations);
        $this->assertSame(29, array_sum($allocations));

        foreach ($allocations as $count) {
            $this->assertGreaterThanOrEqual(2, $count);
            $this->assertLessThanOrEqual(4, $count);
        }
    }

    /**
     * TEST V: Part 7 planner strategy is deterministic for same seed.
     */
    public function test_v_part_7_planner_strategy_is_deterministic_for_same_seed(): void
    {
        $alloc1 = $this->planner->derivePart7SingleGroupAllocations(12345);
        $alloc2 = $this->planner->derivePart7SingleGroupAllocations(12345);

        $this->assertSame($alloc1, $alloc2);
    }

    /**
     * TEST W..Z: Strict seed integer parsing.
     */
    public function test_w_to_z_strict_seed_parsing(): void
    {
        // Strict integer accepted
        $req1 = ToeicBlueprintRequest::fromArray(['mode' => 'full_test', 'seed' => 42]);
        $this->assertSame(42, $req1->seed);

        // Integer-string accepted
        $req2 = ToeicBlueprintRequest::fromArray(['mode' => 'full_test', 'seed' => '42']);
        $this->assertSame(42, $req2->seed);

        // Decimal seed rejected
        try {
            ToeicBlueprintRequest::fromArray(['mode' => 'full_test', 'seed' => 42.5]);
            $this->fail('42.5 seed should throw InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Seed must be a valid integer', $e->getMessage());
        }

        // Decimal string seed rejected
        try {
            ToeicBlueprintRequest::fromArray(['mode' => 'full_test', 'seed' => '42.5']);
            $this->fail('"42.5" seed should throw InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Seed must be a valid integer', $e->getMessage());
        }

        // Non-numeric string rejected
        try {
            ToeicBlueprintRequest::fromArray(['mode' => 'full_test', 'seed' => 'invalid_seed']);
            $this->fail('"invalid_seed" should throw InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Seed must be a valid integer', $e->getMessage());
        }
    }

    /**
     * TEST AA..AD: Plan Validator asserts slot standard binding, counts, and ranges.
     */
    public function test_aa_to_ad_plan_validator_invariants(): void
    {
        $request = ToeicBlueprintRequest::fromArray(['mode' => 'full_test']);
        $plan = $this->planner->plan($request);

        // AA: Slot standard binding equals plan standard binding
        foreach ($plan->slots as $slot) {
            $this->assertSame($plan->assessmentStandardId, $slot->assessmentStandardId);
            $this->assertSame($plan->standardVersion, $slot->standardVersion);
        }

        // AB: sectionCounts equal actual slots
        $this->assertSame(100, count(array_filter($plan->slots, fn ($s) => $s->section === SectionType::Listening)));
        $this->assertSame(100, count(array_filter($plan->slots, fn ($s) => $s->section === SectionType::Reading)));

        // AC: partCounts equal actual slots
        $expectedParts = [1 => 6, 2 => 25, 3 => 39, 4 => 30, 5 => 30, 6 => 16, 7 => 54];
        foreach ($expectedParts as $p => $exp) {
            $this->assertSame($exp, count(array_filter($plan->slots, fn ($s) => $s->partNumber === $p)));
        }

        // AD: canonical question numbers remain within part ranges
        foreach ($expectedParts as $p => $exp) {
            $range = ToeicQuestionValidator::getPartQuestionRange($p);
            $partSlots = array_values(array_filter($plan->slots, fn ($s) => $s->partNumber === $p));
            $this->assertGreaterThanOrEqual($range['start'], $partSlots[0]->canonicalQuestionNumber);
            $this->assertLessThanOrEqual($range['end'], $partSlots[count($partSlots) - 1]->canonicalQuestionNumber);
        }
    }

    /**
     * TEST AE..AK: Full test totals and complete group structures.
     */
    public function test_ae_to_ak_full_test_canonical_aggregates(): void
    {
        $request = ToeicBlueprintRequest::fromArray(['mode' => 'full_test']);
        $plan = $this->planner->plan($request);

        $this->assertSame(200, $plan->totalSlots);
        $this->assertSame(100, $plan->sectionCounts['listening']);
        $this->assertSame(100, $plan->sectionCounts['reading']);

        // P3 = 13 complete groups
        $p3Groups = array_values(array_filter($plan->groups, fn ($g) => $g->partNumber === 3));
        $this->assertCount(13, $p3Groups);

        // P4 = 10 complete groups
        $p4Groups = array_values(array_filter($plan->groups, fn ($g) => $g->partNumber === 4));
        $this->assertCount(10, $p4Groups);

        // P6 = 4 complete groups
        $p6Groups = array_values(array_filter($plan->groups, fn ($g) => $g->partNumber === 6));
        $this->assertCount(4, $p6Groups);

        // P7 = 15 complete groups (10 Single / 29 Q, 2 Double / 10 Q, 3 Triple / 15 Q)
        $p7Groups = array_values(array_filter($plan->groups, fn ($g) => $g->partNumber === 7));
        $this->assertCount(15, $p7Groups);
        $this->assertSame(54, array_sum(array_map(fn ($g) => $g->questionCount, $p7Groups)));
    }

    /**
     * TEST AL: Sprint 1 regression PASS.
     */
    public function test_al_sprint1_generation_contract_regression_pass(): void
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
     * TEST AM: Sprint 2 regression PASS.
     */
    public function test_am_sprint2_standards_registry_regression_pass(): void
    {
        $active = $this->registry->getActiveStandard(AssessmentFamily::Toeic);
        $this->assertNotNull($active);
        $this->assertSame(StandardStatus::Active, $active->status);
        $this->assertSame('2026.1', $active->version);
    }

    /**
     * TEST AN: TOEIC validator regression PASS.
     */
    public function test_an_toeic_validator_regression_pass(): void
    {
        $validData = [
            'part_number' => 5,
            'prompt' => 'Please review the attached contract carefully.',
            'choices' => [
                ['label' => 'A', 'content' => 'Choice A', 'is_correct' => true],
                ['label' => 'B', 'content' => 'Choice B', 'is_correct' => false],
                ['label' => 'C', 'content' => 'Choice C', 'is_correct' => false],
                ['label' => 'D', 'content' => 'Choice D', 'is_correct' => false],
            ],
        ];

        $check = ToeicQuestionValidator::check($validData);
        $this->assertTrue($check['is_valid']);
    }

    /**
     * CONTRACT CLEANUP TEST A & B: Custom [3 => 6] and ["3" => "6"] are valid.
     */
    public function test_contract_cleanup_a_and_b_custom_associative_forms(): void
    {
        $req1 = ToeicBlueprintRequest::fromArray(['mode' => 'custom', 'custom_parts' => [3 => 6]]);
        $this->assertSame([3 => 6], $req1->customParts);

        $req2 = ToeicBlueprintRequest::fromArray(['mode' => 'custom', 'custom_parts' => ['3' => '6']]);
        $this->assertSame([3 => 6], $req2->customParts);
    }

    /**
     * CONTRACT CLEANUP TEST C: List [3, 5] normalizes to canonical full counts.
     */
    public function test_contract_cleanup_c_list_form_normalizes_to_canonical_counts(): void
    {
        $req = ToeicBlueprintRequest::fromArray(['mode' => 'custom', 'custom_parts' => [3, 5]]);
        $this->assertSame([
            3 => ToeicQuestionValidator::getPartTargetQuestionCount(3),
            5 => ToeicQuestionValidator::getPartTargetQuestionCount(5),
        ], $req->customParts);
    }

    /**
     * CONTRACT CLEANUP TEST D..G: Custom count 6.8, "6.8", 0, and negative are rejected.
     */
    public function test_contract_cleanup_d_to_g_custom_count_strict_rejections(): void
    {
        // 6.8 rejected
        try {
            ToeicBlueprintRequest::fromArray(['mode' => 'custom', 'custom_parts' => [3 => 6.8]]);
            $this->fail('6.8 count should throw InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('positive', $e->getMessage());
        }

        // "6.8" rejected
        try {
            ToeicBlueprintRequest::fromArray(['mode' => 'custom', 'custom_parts' => [3 => '6.8']]);
            $this->fail('"6.8" count should throw InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('positive', $e->getMessage());
        }

        // 0 rejected
        try {
            ToeicBlueprintRequest::fromArray(['mode' => 'custom', 'custom_parts' => [3 => 0]]);
            $this->fail('0 count should throw InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('positive', $e->getMessage());
        }

        // -1 rejected
        try {
            ToeicBlueprintRequest::fromArray(['mode' => 'custom', 'custom_parts' => [3 => -1]]);
            $this->fail('-1 count should throw InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('positive', $e->getMessage());
        }
    }

    /**
     * CONTRACT CLEANUP TEST H & I: Custom invalid part key "3.5" and part 8 are rejected.
     */
    public function test_contract_cleanup_h_and_i_custom_part_key_strict_rejections(): void
    {
        // "3.5" rejected
        try {
            ToeicBlueprintRequest::fromArray(['mode' => 'custom', 'custom_parts' => ['3.5' => 6]]);
            $this->fail('"3.5" part key should throw InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Invalid TOEIC part number', $e->getMessage());
        }

        // 8 rejected
        try {
            ToeicBlueprintRequest::fromArray(['mode' => 'custom', 'custom_parts' => [8 => 10]]);
            $this->fail('Part 8 should throw InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Invalid TOEIC part number', $e->getMessage());
        }
    }

    /**
     * CONTRACT CLEANUP TEST J & K: Custom Part 3 count 6 is group-safe, count 4 is rejected.
     */
    public function test_contract_cleanup_j_and_k_custom_group_safety(): void
    {
        $req6 = ToeicBlueprintRequest::fromArray(['mode' => 'custom', 'custom_parts' => [3 => 6]]);
        $plan6 = $this->planner->plan($req6);
        $this->assertCount(2, $plan6->groups);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('multiple of 3');

        ToeicBlueprintRequest::fromArray(['mode' => 'custom', 'custom_parts' => [3 => 4]]);
    }

    /**
     * CONTRACT CLEANUP TEST L..O: Construct distribution part keys strictness.
     */
    public function test_contract_cleanup_l_to_o_construct_distribution_part_keys_strictness(): void
    {
        // 5 valid
        $req5 = ToeicBlueprintRequest::fromArray([
            'mode' => 'full_test',
            'construct_distribution' => [
                5 => ['grammar' => 60, 'vocabulary' => 40],
            ],
        ]);
        $this->assertArrayHasKey(5, $req5->constructDistribution);

        // "5" valid
        $reqStr5 = ToeicBlueprintRequest::fromArray([
            'mode' => 'full_test',
            'construct_distribution' => [
                '5' => ['grammar' => 60, 'vocabulary' => 40],
            ],
        ]);
        $this->assertArrayHasKey(5, $reqStr5->constructDistribution);

        // "5.5" rejected
        try {
            ToeicBlueprintRequest::fromArray([
                'mode' => 'full_test',
                'construct_distribution' => [
                    '5.5' => ['grammar' => 60, 'vocabulary' => 40],
                ],
            ]);
            $this->fail('"5.5" construct part key should throw InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Invalid TOEIC part number', $e->getMessage());
        }

        // Part 8 rejected
        try {
            ToeicBlueprintRequest::fromArray([
                'mode' => 'full_test',
                'construct_distribution' => [
                    8 => ['grammar' => 60, 'vocabulary' => 40],
                ],
            ]);
            $this->fail('Part 8 construct key should throw InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Invalid TOEIC part number', $e->getMessage());
        }
    }

    /**
     * CONTRACT CLEANUP TEST P..R: Canonical total source reuse and full test = 200 today.
     */
    public function test_contract_cleanup_p_to_r_canonical_total_source_reuse(): void
    {
        $this->assertSame(200, ToeicQuestionValidator::getTotalCanonicalTargetCount());

        $request = ToeicBlueprintRequest::fromArray(['mode' => 'full_test']);
        $plan = $this->planner->plan($request);

        $this->assertSame(ToeicQuestionValidator::getTotalCanonicalTargetCount(), $plan->totalSlots);
        $this->assertSame(200, $plan->totalSlots);
    }
}
