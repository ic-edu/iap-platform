<?php

use App\Models\User;
use App\Modules\Assessment\Enums\AssessmentMode;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestQuestion;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\Assessment\Services\DeliveryUnitBuilder;
use App\Modules\Assessment\Services\TestBuilderService;
use App\Modules\QuestionBank\Models\Passage;
use App\Modules\QuestionBank\Models\PassageGroup;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionChoice;
use App\Services\ToeicQuestionValidator;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->teacher = User::factory()->create(['email' => 'teacher_p7_bp@test.com']);
    $this->teacher->assignRole('teacher');

    $this->toeicTest = Test::create([
        'title'           => 'TOEIC Part 7 Blueprint Test',
        'slug'            => 'toeic-p7-bp-' . uniqid(),
        'type'            => 'simulator',
        'assessment_mode' => AssessmentMode::Simulator,
        'test_type'       => 'toeic',
        'status'          => 'draft',
        'is_published'    => false,
        'is_active'       => true,
        'created_by'      => $this->teacher->id,
    ]);

    $this->builderService = app(TestBuilderService::class);
    $this->p7Section = TestSection::create([
        'test_id'      => $this->toeicTest->id,
        'title'        => 'Part 7: Reading Comprehension',
        'section_type' => 'reading',
        'order'        => 7,
    ]);
});

/**
 * Helper to build valid payload for creating a passage group via TestBuilderService.
 */
function makePart7Payload(int|string $sectionId, string $type, int $qCount, array $custom = []): array
{
    $numDocs = match ($type) {
        'triple' => 3,
        'double' => 2,
        default  => 1,
    };

    $passages = [];
    for ($d = 1; $d <= $numDocs; $d++) {
        $passages[] = [
            'document_type' => 'article',
            'title'         => "Document {$d}",
            'content'       => "Reading stimulus document {$d} text content.",
            'order_in_group'=> $d,
        ];
    }

    $questions = [];
    for ($i = 1; $i <= $qCount; $i++) {
        $questions[] = [
            'prompt'         => "Question {$i} stem prompt",
            'difficulty'     => 'medium',
            'choices'        => [
                ['label' => 'A', 'content' => 'Choice A text', 'is_correct' => true],
                ['label' => 'B', 'content' => 'Choice B text', 'is_correct' => false],
                ['label' => 'C', 'content' => 'Choice C text', 'is_correct' => false],
                ['label' => 'D', 'content' => 'Choice D text', 'is_correct' => false],
            ],
            'correct_choice' => 0,
        ];
    }

    return array_merge([
        'test_section_id' => $sectionId,
        'part_number'     => 7,
        'passage_type'    => $type,
        'title'           => ucfirst($type) . " Passage Group",
        'passages'        => $passages,
        'questions'       => $questions,
    ], $custom);
}

/**
 * Helper to construct a canonical 15-group Part 7 in DB.
 */
function seedCanonicalPart7(TestSection $section, TestBuilderService $service): array
{
    $groups = [];
    // 10 Single groups: 9 of 3 questions (27) + 1 of 2 questions (2) = 29 questions
    for ($g = 1; $g <= 9; $g++) {
        $groups[] = $service->createPassageGroup($section, makePart7Payload($section->id, 'single', 3, ['title' => "Single Group {$g}"]));
    }
    $groups[] = $service->createPassageGroup($section, makePart7Payload($section->id, 'single', 2, ['title' => "Single Group 10"]));

    // 2 Double groups: 2 of 5 questions = 10 questions
    for ($g = 1; $g <= 2; $g++) {
        $groups[] = $service->createPassageGroup($section, makePart7Payload($section->id, 'double', 5, ['title' => "Double Group {$g}"]));
    }

    // 3 Triple groups: 3 of 5 questions = 15 questions
    for ($g = 1; $g <= 3; $g++) {
        $groups[] = $service->createPassageGroup($section, makePart7Payload($section->id, 'triple', 5, ['title' => "Triple Group {$g}"]));
    }

    return $groups;
}

/*
|--------------------------------------------------------------------------
| P7-BP-01: Exact Canonical Blueprint
|--------------------------------------------------------------------------
*/
test('P7-BP-01: Exact canonical Part 7 blueprint produces 15 groups, 54 questions, READY state', function () {
    $groups = seedCanonicalPart7($this->p7Section, $this->builderService);

    expect(count($groups))->toBe(15);

    $eval = ToeicQuestionValidator::evaluatePart7Blueprint($this->toeicTest);
    expect($eval['is_ready'])->toBeTrue()
        ->and($eval['total']['groups'])->toBe(15)
        ->and($eval['total']['questions'])->toBe(54)
        ->and($eval['total']['status'])->toBe('ready')
        ->and($eval['single']['is_exact'])->toBeTrue()
        ->and($eval['single']['groups'])->toBe(10)
        ->and($eval['single']['questions'])->toBe(29)
        ->and($eval['double']['is_exact'])->toBeTrue()
        ->and($eval['double']['groups'])->toBe(2)
        ->and($eval['double']['questions'])->toBe(10)
        ->and($eval['triple']['is_exact'])->toBeTrue()
        ->and($eval['triple']['groups'])->toBe(3)
        ->and($eval['triple']['questions'])->toBe(15)
        ->and($eval['ordering']['valid'])->toBeTrue()
        ->and($eval['findings'])->toBeEmpty();
});

/*
|--------------------------------------------------------------------------
| P7-BP-02: 54 Questions but Wrong Composition
|--------------------------------------------------------------------------
*/
test('P7-BP-02: 54 questions with invalid composition (11 single of 4 = 44 + 2 double of 5 = 10) is NOT READY', function () {
    // 11 Single groups (11 x 4 = 44 Qs) + 2 Double groups (2 x 5 = 10 Qs) -> 54 questions total, but no Triple groups
    $groups = [];
    for ($g = 1; $g <= 11; $g++) {
        $pg = PassageGroup::create([
            'test_id'      => $this->toeicTest->id,
            'part_number'  => 7,
            'passage_type' => 'single',
            'title'        => "Single {$g}",
            'order'        => $g,
        ]);
        Passage::create(['passage_group_id' => $pg->id, 'title' => 'Doc', 'content' => 'Content', 'order_in_group' => 1]);
        for ($q = 1; $q <= 4; $q++) {
            $cq = Question::create(['passage_group_id' => $pg->id, 'part_number' => 7, 'section' => 'reading', 'prompt' => "Prompt {$q}", 'question_type' => 'multiple_choice']);
            foreach (['A', 'B', 'C', 'D'] as $ci => $cl) {
                QuestionChoice::create(['question_id' => $cq->id, 'label' => $cl, 'content' => "Option {$cl}", 'is_correct' => $ci === 0, 'order' => $ci + 1]);
            }
        }
        $groups[] = $pg;
    }

    for ($g = 12; $g <= 13; $g++) {
        $pg = PassageGroup::create([
            'test_id'      => $this->toeicTest->id,
            'part_number'  => 7,
            'passage_type' => 'double',
            'title'        => "Double {$g}",
            'order'        => $g,
        ]);
        Passage::create(['passage_group_id' => $pg->id, 'title' => 'Doc 1', 'content' => 'Content', 'order_in_group' => 1]);
        Passage::create(['passage_group_id' => $pg->id, 'title' => 'Doc 2', 'content' => 'Content', 'order_in_group' => 2]);
        for ($q = 1; $q <= 5; $q++) {
            $cq = Question::create(['passage_group_id' => $pg->id, 'part_number' => 7, 'section' => 'reading', 'prompt' => "Prompt {$q}", 'question_type' => 'multiple_choice']);
            foreach (['A', 'B', 'C', 'D'] as $ci => $cl) {
                QuestionChoice::create(['question_id' => $cq->id, 'label' => $cl, 'content' => "Option {$cl}", 'is_correct' => $ci === 0, 'order' => $ci + 1]);
            }
        }
        $groups[] = $pg;
    }

    $eval = ToeicQuestionValidator::evaluatePart7Blueprint($this->toeicTest);
    expect($eval['total']['questions'])->toBe(54)
        ->and($eval['is_ready'])->toBeFalse()
        ->and($eval['total']['status'])->toBe('needs_attention')
        ->and(implode(' ', $eval['findings']))->toContain('does not match the canonical TOEIC blueprint');
});

/*
|--------------------------------------------------------------------------
| P7-BP-03: Single Group Limit
|--------------------------------------------------------------------------
*/
test('P7-BP-03: 11th Single Passage creation attempt is rejected by backend validation', function () {
    // Create 10 single groups
    for ($g = 1; $g <= 9; $g++) {
        $this->builderService->createPassageGroup($this->p7Section, makePart7Payload($this->p7Section->id, 'single', 3));
    }
    $this->builderService->createPassageGroup($this->p7Section, makePart7Payload($this->p7Section->id, 'single', 2));

    // Attempt 11th single group
    expect(fn () => $this->builderService->createPassageGroup($this->p7Section, makePart7Payload($this->p7Section->id, 'single', 2)))
        ->toThrow(ValidationException::class);
});

/*
|--------------------------------------------------------------------------
| P7-BP-04: Single Total Question Limit
|--------------------------------------------------------------------------
*/
test('P7-BP-04: Attempting to exceed 29 Single questions is rejected', function () {
    // Create 9 single groups of 3 questions = 27 questions (valid distribution)
    for ($g = 1; $g <= 9; $g++) {
        $this->builderService->createPassageGroup($this->p7Section, makePart7Payload($this->p7Section->id, 'single', 3));
    }
    // Attempt to add 10th group with 3 questions (27 + 3 = 30 > 29)
    expect(fn () => $this->builderService->createPassageGroup($this->p7Section, makePart7Payload($this->p7Section->id, 'single', 3)))
        ->toThrow(ValidationException::class);
});

/*
|--------------------------------------------------------------------------
| P7-BP-05: Single Passage Feasibility Logic
|--------------------------------------------------------------------------
*/
test('P7-BP-05: Single Passage mathematical feasibility checks', function () {
    // 9 groups / 27 questions -> rem 1 / 2 -> valid
    $f1 = ToeicQuestionValidator::checkSinglePassageFeasibility(9, 27);
    expect($f1['is_feasible'])->toBeTrue()
        ->and($f1['message'])->toContain('Final Single Passage must contain exactly 2 questions.');

    // 9 groups / 25 questions -> rem 1 / 4 -> valid
    $f2 = ToeicQuestionValidator::checkSinglePassageFeasibility(9, 25);
    expect($f2['is_feasible'])->toBeTrue()
        ->and($f2['message'])->toContain('Final Single Passage must contain exactly 4 questions.');

    // 9 groups / 24 questions -> rem 1 / 5 -> impossible (5 > 4)
    $f3 = ToeicQuestionValidator::checkSinglePassageFeasibility(9, 24);
    expect($f3['is_feasible'])->toBeFalse()
        ->and($f3['reason'])->toBe('impossible');

    // 10 groups / 28 questions -> impossible (rem 0 / 1)
    $f4 = ToeicQuestionValidator::checkSinglePassageFeasibility(10, 28);
    expect($f4['is_feasible'])->toBeFalse()
        ->and($f4['reason'])->toBe('mismatch');

    // 8 groups / 23 questions -> rem 2 / 6 -> valid
    $f5 = ToeicQuestionValidator::checkSinglePassageFeasibility(8, 23);
    expect($f5['is_feasible'])->toBeTrue()
        ->and($f5['message'])->toContain('6 questions remaining across 2 Single Passage groups.');
});

/*
|--------------------------------------------------------------------------
| P7-BP-06: Double Lock & Unlock
|--------------------------------------------------------------------------
*/
test('P7-BP-06: Double Passage creation is locked until Single block is complete', function () {
    // With only 5 single groups (incomplete)
    for ($g = 1; $g <= 5; $g++) {
        $this->builderService->createPassageGroup($this->p7Section, makePart7Payload($this->p7Section->id, 'single', 3));
    }

    // Attempt Double creation -> rejected with lock message
    try {
        $this->builderService->createPassageGroup($this->p7Section, makePart7Payload($this->p7Section->id, 'double', 5));
        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->validator->errors()->first('passage_type'))->toContain('locked until the Single Passage block is complete');
    }

    // Complete Single block (4 more groups of 3 = 12 Qs, plus 1 group of 2 = 2 Qs -> 15 + 12 + 2 = 29 Qs across 10 groups)
    for ($g = 6; $g <= 9; $g++) {
        $this->builderService->createPassageGroup($this->p7Section, makePart7Payload($this->p7Section->id, 'single', 3));
    }
    $this->builderService->createPassageGroup($this->p7Section, makePart7Payload($this->p7Section->id, 'single', 2));

    // Now Double creation is allowed!
    $doublePg = $this->builderService->createPassageGroup($this->p7Section, makePart7Payload($this->p7Section->id, 'double', 5));
    expect($doublePg)->not->toBeNull()
        ->and($doublePg->passage_type)->toBe('double');
});

/*
|--------------------------------------------------------------------------
| P7-BP-07: Triple Lock & Unlock
|--------------------------------------------------------------------------
*/
test('P7-BP-07: Triple Passage creation is locked until Double block is complete', function () {
    // Single complete (10 groups / 29 questions)
    for ($g = 1; $g <= 9; $g++) {
        $this->builderService->createPassageGroup($this->p7Section, makePart7Payload($this->p7Section->id, 'single', 3));
    }
    $this->builderService->createPassageGroup($this->p7Section, makePart7Payload($this->p7Section->id, 'single', 2));

    // Only 1 Double group created (Double block incomplete)
    $this->builderService->createPassageGroup($this->p7Section, makePart7Payload($this->p7Section->id, 'double', 5));

    // Attempt Triple creation -> rejected with lock message
    try {
        $this->builderService->createPassageGroup($this->p7Section, makePart7Payload($this->p7Section->id, 'triple', 5));
        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->validator->errors()->first('passage_type'))->toContain('locked until the Double Passage block is complete');
    }

    // Complete Double block (2nd Double group)
    $this->builderService->createPassageGroup($this->p7Section, makePart7Payload($this->p7Section->id, 'double', 5));

    // Now Triple creation is allowed!
    $triplePg = $this->builderService->createPassageGroup($this->p7Section, makePart7Payload($this->p7Section->id, 'triple', 5));
    expect($triplePg)->not->toBeNull()
        ->and($triplePg->passage_type)->toBe('triple');
});

/*
|--------------------------------------------------------------------------
| P7-BP-08: Double Limit (Max 2 Groups)
|--------------------------------------------------------------------------
*/
test('P7-BP-08: 3rd Double Passage group creation attempt is rejected', function () {
    // Single complete + 2 Double groups
    for ($g = 1; $g <= 9; $g++) {
        $this->builderService->createPassageGroup($this->p7Section, makePart7Payload($this->p7Section->id, 'single', 3));
    }
    $this->builderService->createPassageGroup($this->p7Section, makePart7Payload($this->p7Section->id, 'single', 2));
    $this->builderService->createPassageGroup($this->p7Section, makePart7Payload($this->p7Section->id, 'double', 5));
    $this->builderService->createPassageGroup($this->p7Section, makePart7Payload($this->p7Section->id, 'double', 5));

    // Attempt 3rd Double group
    expect(fn () => $this->builderService->createPassageGroup($this->p7Section, makePart7Payload($this->p7Section->id, 'double', 5)))
        ->toThrow(ValidationException::class);
});

/*
|--------------------------------------------------------------------------
| P7-BP-09: Triple Limit (Max 3 Groups)
|--------------------------------------------------------------------------
*/
test('P7-BP-09: 4th Triple Passage group creation attempt is rejected', function () {
    seedCanonicalPart7($this->p7Section, $this->builderService);

    // Attempt 4th Triple group
    expect(fn () => $this->builderService->createPassageGroup($this->p7Section, makePart7Payload($this->p7Section->id, 'triple', 5)))
        ->toThrow(ValidationException::class);
});

/*
|--------------------------------------------------------------------------
| P7-BP-10: Ordering Validation (Single -> Double -> Triple)
|--------------------------------------------------------------------------
*/
test('P7-BP-10: Out-of-order passage groups (Single, Double, Single, Triple) fail ordering check', function () {
    $g1 = PassageGroup::create(['test_id' => $this->toeicTest->id, 'part_number' => 7, 'passage_type' => 'single', 'order' => 1]);
    Passage::create(['passage_group_id' => $g1->id, 'title' => 'Doc', 'content' => 'Text', 'order_in_group' => 1]);

    $g2 = PassageGroup::create(['test_id' => $this->toeicTest->id, 'part_number' => 7, 'passage_type' => 'double', 'order' => 2]);
    Passage::create(['passage_group_id' => $g2->id, 'title' => 'Doc 1', 'content' => 'Text', 'order_in_group' => 1]);
    Passage::create(['passage_group_id' => $g2->id, 'title' => 'Doc 2', 'content' => 'Text', 'order_in_group' => 2]);

    $g3 = PassageGroup::create(['test_id' => $this->toeicTest->id, 'part_number' => 7, 'passage_type' => 'single', 'order' => 3]);
    Passage::create(['passage_group_id' => $g3->id, 'title' => 'Doc', 'content' => 'Text', 'order_in_group' => 1]);

    $g4 = PassageGroup::create(['test_id' => $this->toeicTest->id, 'part_number' => 7, 'passage_type' => 'triple', 'order' => 4]);
    Passage::create(['passage_group_id' => $g4->id, 'title' => 'Doc 1', 'content' => 'Text', 'order_in_group' => 1]);
    Passage::create(['passage_group_id' => $g4->id, 'title' => 'Doc 2', 'content' => 'Text', 'order_in_group' => 2]);
    Passage::create(['passage_group_id' => $g4->id, 'title' => 'Doc 3', 'content' => 'Text', 'order_in_group' => 3]);

    $eval = ToeicQuestionValidator::evaluatePart7Blueprint($this->toeicTest);
    expect($eval['ordering']['valid'])->toBeFalse()
        ->and($eval['ordering']['message'])->toBe('Part 7 passage groups are out of canonical order. Expected Single → Double → Triple.')
        ->and(PassageGroup::where('test_id', $this->toeicTest->id)->pluck('order')->all())->toBe([1, 2, 3, 4]);
});

/*
|--------------------------------------------------------------------------
| P7-BP-11: Canonical Question Range by Type
|--------------------------------------------------------------------------
*/
test('P7-BP-11: Real persisted canonical Part 7 maps direct runtime numbers: Single (Q147-Q175), Double (Q176-Q185), Triple (Q186-Q200)', function () {
    // 1. Seed real persisted canonical fixture in DB
    $groups = seedCanonicalPart7($this->p7Section, $this->builderService);
    expect(count($groups))->toBe(15);

    // 2. Authoritative IAP runtime validation & numbering mapping
    $eval = ToeicQuestionValidator::evaluatePart7Blueprint($this->toeicTest);
    expect($eval['is_ready'])->toBeTrue()
        ->and($eval['findings'])->toBeEmpty();

    $validation = $this->builderService->validateAssessment($this->toeicTest);
    $part7Questions = collect($validation['questions'])->filter(function ($item) {
        $q = $item['question'];
        return (int) ($q->part_number ?? $item['section']->order ?? 0) === 7;
    })->values();

    // Verify all Part 7 questions have no validation warnings
    foreach ($part7Questions as $item) {
        expect($item['warnings'])->toBeEmpty();
    }

    // Total Part 7 count = 54
    expect($part7Questions->count())->toBe(54);

    // Collect ordered child questions by passage type
    $singleItems = $part7Questions->filter(fn ($it) => $it['question']->passageGroup?->passage_type === 'single')->values();
    $doubleItems = $part7Questions->filter(fn ($it) => $it['question']->passageGroup?->passage_type === 'double')->values();
    $tripleItems = $part7Questions->filter(fn ($it) => $it['question']->passageGroup?->passage_type === 'triple')->values();

    // 3. SINGLE PASSAGE BLOCK ASSERTIONS (Q147 - Q175)
    expect($singleItems->count())->toBe(29);
    $singleNumbers = $singleItems->map(fn ($it) => $it['canonical_number'])->all();
    expect($singleNumbers[0])->toBe(147)
        ->and(end($singleNumbers))->toBe(175)
        ->and($singleNumbers)->toBe(range(147, 175))
        ->and(count(array_unique($singleNumbers)))->toBe(29);
    // Ensure no Double or Triple questions inside Single range
    foreach ($singleItems as $it) {
        expect($it['question']->passageGroup->passage_type)->toBe('single');
    }

    // 4. DOUBLE PASSAGE BLOCK ASSERTIONS (Q176 - Q185)
    expect($doubleItems->count())->toBe(10);
    $doubleNumbers = $doubleItems->map(fn ($it) => $it['canonical_number'])->all();
    expect($doubleNumbers[0])->toBe(176)
        ->and(end($doubleNumbers))->toBe(185)
        ->and($doubleNumbers)->toBe(range(176, 185))
        ->and(count(array_unique($doubleNumbers)))->toBe(10);
    // Ensure no Single or Triple questions inside Double range
    foreach ($doubleItems as $it) {
        expect($it['question']->passageGroup->passage_type)->toBe('double');
    }

    // 5. TRIPLE PASSAGE BLOCK ASSERTIONS (Q186 - Q200)
    expect($tripleItems->count())->toBe(15);
    $tripleNumbers = $tripleItems->map(fn ($it) => $it['canonical_number'])->all();
    expect($tripleNumbers[0])->toBe(186)
        ->and(end($tripleNumbers))->toBe(200)
        ->and($tripleNumbers)->toBe(range(186, 200))
        ->and(count(array_unique($tripleNumbers)))->toBe(15);
    // Ensure no Single or Double questions inside Triple range
    foreach ($tripleItems as $it) {
        expect($it['question']->passageGroup->passage_type)->toBe('triple');
    }

    // 6. WHOLE-PART DIRECT ORDERED SEQUENCE ASSERTIONS (Q147 - Q200)
    $allNumbers = $part7Questions->map(fn ($it) => $it['canonical_number'])->all();
    expect($allNumbers)->toBe(range(147, 200))
        ->and(count($allNumbers))->toBe(54)
        ->and(count(array_unique($allNumbers)))->toBe(54);

    // 7. EXACT BLOCK BOUNDARY ASSERTIONS
    expect(end($singleNumbers))->toBe(175)
        ->and($doubleNumbers[0])->toBe(176)
        ->and(end($doubleNumbers))->toBe(185)
        ->and($tripleNumbers[0])->toBe(186)
        ->and(end($tripleNumbers))->toBe(200);

    // 8. Helper verification
    expect(ToeicQuestionValidator::getPart7PassageTypeQuestionRange('single'))
        ->toBe(['start' => 147, 'end' => 175, 'count' => 29])
        ->and(ToeicQuestionValidator::getPart7PassageTypeQuestionRange('double'))
        ->toBe(['start' => 176, 'end' => 185, 'count' => 10])
        ->and(ToeicQuestionValidator::getPart7PassageTypeQuestionRange('triple'))
        ->toBe(['start' => 186, 'end' => 200, 'count' => 15]);
});

/*
|--------------------------------------------------------------------------
| P7-BP-12: Edit Existing Non-Canonical Data
|--------------------------------------------------------------------------
*/
test('P7-BP-12: Existing non-canonical Double group remains editable without blocking repair', function () {
    // Non-canonical setup: 2 single groups and 1 double group
    $s1 = $this->builderService->createPassageGroup($this->p7Section, makePart7Payload($this->p7Section->id, 'single', 3));
    $s2 = $this->builderService->createPassageGroup($this->p7Section, makePart7Payload($this->p7Section->id, 'single', 3));

    // Manually created legacy Double group
    $existingDouble = PassageGroup::create([
        'test_id'      => $this->toeicTest->id,
        'part_number'  => 7,
        'passage_type' => 'double',
        'title'        => 'Legacy Double Group',
        'order'        => 3,
    ]);
    Passage::create(['passage_group_id' => $existingDouble->id, 'title' => 'Doc 1', 'content' => 'Old content 1', 'order_in_group' => 1]);
    Passage::create(['passage_group_id' => $existingDouble->id, 'title' => 'Doc 2', 'content' => 'Old content 2', 'order_in_group' => 2]);
    for ($i = 1; $i <= 5; $i++) {
        $cq = Question::create(['passage_group_id' => $existingDouble->id, 'part_number' => 7, 'section' => 'reading', 'prompt' => "Q{$i}", 'question_type' => 'multiple_choice']);
        foreach (['A', 'B', 'C', 'D'] as $ci => $cl) {
            QuestionChoice::create(['question_id' => $cq->id, 'label' => $cl, 'content' => "Option {$cl}", 'is_correct' => $ci === 0, 'order' => $ci + 1]);
        }
    }

    // Teacher updates the existing Double group title & passage content -> allowed (no deadlock!)
    $updated = $this->builderService->updatePassageGroup($existingDouble, [
        'title'        => 'Updated Double Group Title',
        'part_number'  => 7,
        'passage_type' => 'double',
        'passages'     => [
            ['title' => 'Doc 1', 'content' => 'Updated content 1', 'document_type' => 'article', 'order_in_group' => 1],
            ['title' => 'Doc 2', 'content' => 'Updated content 2', 'document_type' => 'article', 'order_in_group' => 2],
        ],
        'questions'    => $existingDouble->questions->map(fn($q) => [
            'id'             => $q->id,
            'prompt'         => $q->prompt . ' (edited)',
            'difficulty'     => 'medium',
            'choices'        => ['A', 'B', 'C', 'D'],
            'correct_choice' => 0,
        ])->toArray(),
    ]);

    expect($updated->title)->toBe('Updated Double Group Title');

    // But creating a NEW double or triple group remains governed and rejected
    expect(fn () => $this->builderService->createPassageGroup($this->p7Section, makePart7Payload($this->p7Section->id, 'double', 5)))
        ->toThrow(ValidationException::class);
});

/*
|--------------------------------------------------------------------------
| P7-BP-13: Delete Regression
|--------------------------------------------------------------------------
*/
test('P7-BP-13: Deleting a Single group marks Part 7 NEEDS ATTENTION but preserves later groups', function () {
    $groups = seedCanonicalPart7($this->p7Section, $this->builderService);

    // Initial state is READY
    $evalBefore = ToeicQuestionValidator::evaluatePart7Blueprint($this->toeicTest);
    expect($evalBefore['is_ready'])->toBeTrue();

    // Delete one Single group (group 1)
    $this->builderService->deletePassageGroup($this->toeicTest, $groups[0]);

    // Later groups (Double & Triple) remain intact
    $remainingGroups = PassageGroup::where('test_id', $this->toeicTest->id)->get();
    expect($remainingGroups->count())->toBe(14)
        ->and($remainingGroups->where('passage_type', 'double')->count())->toBe(2)
        ->and($remainingGroups->where('passage_type', 'triple')->count())->toBe(3);

    // Part 7 becomes NEEDS ATTENTION
    $evalAfter = ToeicQuestionValidator::evaluatePart7Blueprint($this->toeicTest);
    expect($evalAfter['is_ready'])->toBeFalse()
        ->and($evalAfter['total']['status'])->toBe('needs_attention')
        ->and($evalAfter['single']['groups'])->toBe(9);
});

/*
|--------------------------------------------------------------------------
| P7-BP-14: Part 6 Regression (4 groups x 4 Qs = 16, Q131-Q146)
|--------------------------------------------------------------------------
*/
test('P7-BP-14: Part 6 Text Completion remains completely untouched and functional', function () {
    $p6Section = TestSection::create([
        'test_id'      => $this->toeicTest->id,
        'title'        => 'Part 6: Text Completion',
        'section_type' => 'reading',
        'order'        => 6,
    ]);

    $p6Group = $this->builderService->createPassageGroup($p6Section, [
        'test_section_id' => $p6Section->id,
        'part_number'     => 6,
        'passage_type'    => 'single',
        'title'           => 'Part 6 Group 1',
        'passages'        => [
            ['title' => 'Memo', 'content' => 'Passage with blanks [1] [2] [3] [4]', 'document_type' => 'memo'],
        ],
        'questions'       => [
            ['prompt' => 'Note 1', 'difficulty' => 'easy', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0],
            ['prompt' => 'Note 2', 'difficulty' => 'easy', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 1],
            ['prompt' => 'Note 3', 'difficulty' => 'easy', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 2],
            ['prompt' => 'Note 4', 'difficulty' => 'easy', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 3],
        ],
    ]);

    expect($p6Group->part_number)->toBe(6)
        ->and($p6Group->questions()->count())->toBe(4);

    expect(ToeicQuestionValidator::getPartBlueprint(6)['target_count'])->toBe(16)
        ->and(ToeicQuestionValidator::getPartQuestionRange(6))->toBe(['start' => 131, 'end' => 146]);
});

/*
|--------------------------------------------------------------------------
| P7-BP-15: Transition Boundary Regression
|--------------------------------------------------------------------------
*/
test('P7-BP-15: Canonical Part 7 renders expected transition boundaries on candidate exam and preview', function () {
    seedCanonicalPart7($this->p7Section, $this->builderService);

    // 1. Verify Delivery Units structure from DeliveryUnitBuilder
    $deliveryData = DeliveryUnitBuilder::build($this->toeicTest);
    $units = $deliveryData['deliveryUnits'];

    expect($units->count())->toBe(15);

    // Units 0 to 9 are Single Passage groups
    for ($u = 0; $u <= 9; $u++) {
        expect($units[$u]['type'])->toBe('passage_group')
            ->and($units[$u]['part_number'])->toBe(7)
            ->and($units[$u]['passage_type'])->toBe('single');
    }

    // Units 10 and 11 are Double Passage groups
    for ($u = 10; $u <= 11; $u++) {
        expect($units[$u]['type'])->toBe('passage_group')
            ->and($units[$u]['part_number'])->toBe(7)
            ->and($units[$u]['passage_type'])->toBe('double');
    }

    // Units 12 to 14 are Triple Passage groups
    for ($u = 12; $u <= 14; $u++) {
        expect($units[$u]['type'])->toBe('passage_group')
            ->and($units[$u]['part_number'])->toBe(7)
            ->and($units[$u]['passage_type'])->toBe('triple');
    }

    // 2. Validate Transition Rule Logic at adjacent boundaries
    // Single -> Double boundary: Unit 9 (ends Q175) to Unit 10 (begins Q176)
    $transitionSingleToDouble = ($units[9]['passage_type'] !== $units[10]['passage_type'] && $units[9]['passage_type'] === 'single' && $units[10]['passage_type'] === 'double');
    expect($transitionSingleToDouble)->toBeTrue();

    // Double -> Triple boundary: Unit 11 (ends Q185) to Unit 12 (begins Q186)
    $transitionDoubleToTriple = ($units[11]['passage_type'] !== $units[12]['passage_type'] && $units[11]['passage_type'] === 'double' && $units[12]['passage_type'] === 'triple');
    expect($transitionDoubleToTriple)->toBeTrue();

    // Within-block transitions must NOT trigger passage type transition
    expect($units[0]['passage_type'] === $units[1]['passage_type'])->toBeTrue()
        ->and($units[10]['passage_type'] === $units[11]['passage_type'])->toBeTrue()
        ->and($units[12]['passage_type'] === $units[13]['passage_type'])->toBeTrue();

    // 3. Verify HTTP Candidate Preview rendering
    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.preview', $this->toeicTest->id));
    $response->assertOk();

    // Verify presence of transition banner markup and JS transition triggers
    $response->assertSee('Passage Format Change');
    $response->assertSee('Double Passage');
    $response->assertSee('Triple Passage');
    $response->assertSee('shouldShowPassageTypeTransition');
});

/*
|--------------------------------------------------------------------------
| P7-EDGE-01: Triple Lock Semantics (Single Incomplete + Double Exact)
|--------------------------------------------------------------------------
*/
test('P7-EDGE-01: Triple Passage is locked when Single is incomplete even if Double block is exact', function () {
    // 1. Seed legacy state: Single incomplete (5 groups of 3 = 15 Qs), Double exact (2 groups of 5 = 10 Qs)
    for ($g = 1; $g <= 5; $g++) {
        $this->builderService->createPassageGroup($this->p7Section, makePart7Payload($this->p7Section->id, 'single', 3, ['title' => "Single {$g}"]));
    }
    // Direct DB creation for Double groups since normal builder service would reject it during creation
    for ($g = 1; $g <= 2; $g++) {
        $pg = PassageGroup::create([
            'test_id'      => $this->toeicTest->id,
            'part_number'  => 7,
            'passage_type' => 'double',
            'title'        => "Legacy Double {$g}",
            'order'        => 5 + $g,
        ]);
        Passage::create(['passage_group_id' => $pg->id, 'title' => 'Doc 1', 'content' => 'Content', 'order_in_group' => 1]);
        Passage::create(['passage_group_id' => $pg->id, 'title' => 'Doc 2', 'content' => 'Content', 'order_in_group' => 2]);
        for ($q = 1; $q <= 5; $q++) {
            $cq = Question::create(['passage_group_id' => $pg->id, 'part_number' => 7, 'section' => 'reading', 'prompt' => "Prompt {$q}", 'question_type' => 'multiple_choice']);
            foreach (['A', 'B', 'C', 'D'] as $ci => $cl) {
                QuestionChoice::create(['question_id' => $cq->id, 'label' => $cl, 'content' => "Option {$cl}", 'is_correct' => $ci === 0, 'order' => $ci + 1]);
            }
        }
    }

    // 2. Evaluator check
    $eval = ToeicQuestionValidator::evaluatePart7Blueprint($this->toeicTest);
    expect($eval['single']['is_exact'])->toBeFalse()
        ->and($eval['double']['is_exact'])->toBeTrue()
        ->and($eval['triple']['is_locked'])->toBeTrue()
        ->and($eval['triple']['status'])->toBe('locked');

    // 3. Teacher UI check
    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $response->assertOk();
    $response->assertDontSee('+ Add Triple Passage');

    // 4. Backend enforcement check
    try {
        $this->builderService->createPassageGroup($this->p7Section, makePart7Payload($this->p7Section->id, 'triple', 5));
        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->validator->errors()->first('passage_type'))->toContain('Triple Passage creation is locked until Single and Double Passage blocks are complete');
    }
});

/*
|--------------------------------------------------------------------------
| P7-EDGE-02: Legacy 10 Single / 28 Questions Repair State
|--------------------------------------------------------------------------
*/
test('P7-EDGE-02: Legacy 10 Single groups with 28 questions renders repair guidance and no generic create button', function () {
    // 10 Single groups: 8 of 3 questions (24) + 2 of 2 questions (4) = 28 questions
    for ($g = 1; $g <= 8; $g++) {
        $this->builderService->createPassageGroup($this->p7Section, makePart7Payload($this->p7Section->id, 'single', 3, ['title' => "Single {$g}"]));
    }
    for ($g = 9; $g <= 10; $g++) {
        $pg = PassageGroup::create([
            'test_id'      => $this->toeicTest->id,
            'part_number'  => 7,
            'passage_type' => 'single',
            'title'        => "Single {$g}",
            'order'        => $g,
        ]);
        Passage::create(['passage_group_id' => $pg->id, 'title' => 'Doc', 'content' => 'Content', 'order_in_group' => 1]);
        for ($q = 1; $q <= 2; $q++) {
            $cq = Question::create(['passage_group_id' => $pg->id, 'part_number' => 7, 'section' => 'reading', 'prompt' => "Prompt {$q}", 'question_type' => 'multiple_choice']);
            foreach (['A', 'B', 'C', 'D'] as $ci => $cl) {
                QuestionChoice::create(['question_id' => $cq->id, 'label' => $cl, 'content' => "Option {$cl}", 'is_correct' => $ci === 0, 'order' => $ci + 1]);
            }
        }
    }

    $eval = ToeicQuestionValidator::evaluatePart7Blueprint($this->toeicTest);
    expect($eval['single']['is_exact'])->toBeFalse()
        ->and($eval['single']['is_feasible'])->toBeFalse()
        ->and($eval['single']['groups'])->toBe(10)
        ->and($eval['single']['questions'])->toBe(28);

    // Teacher UI assertions
    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $response->assertOk();
    $response->assertDontSee('+ Add Single Passage');
    $response->assertDontSee('+ Add Passage Group');
    $response->assertSee('Needs Attention:');
    $response->assertSee('Single Passage has 10 / 10 groups but 28 / 29 questions. Edit an existing Single Passage group to reach exactly 29 questions.');
});

/*
|--------------------------------------------------------------------------
| P7-EDGE-03: Infeasible 9 Single / 24 Questions State
|--------------------------------------------------------------------------
*/
test('P7-EDGE-03: Infeasible 9 Single groups with 24 questions suppresses Add Single and shows repair notice', function () {
    for ($g = 1; $g <= 6; $g++) {
        $this->builderService->createPassageGroup($this->p7Section, makePart7Payload($this->p7Section->id, 'single', 3, ['title' => "Single {$g}"]));
    }
    for ($g = 7; $g <= 9; $g++) {
        $pg = PassageGroup::create([
            'test_id'      => $this->toeicTest->id,
            'part_number'  => 7,
            'passage_type' => 'single',
            'title'        => "Single {$g}",
            'order'        => $g,
        ]);
        Passage::create(['passage_group_id' => $pg->id, 'title' => 'Doc', 'content' => 'Content', 'order_in_group' => 1]);
        for ($q = 1; $q <= 2; $q++) {
            $cq = Question::create(['passage_group_id' => $pg->id, 'part_number' => 7, 'section' => 'reading', 'prompt' => "Prompt {$q}", 'question_type' => 'multiple_choice']);
            foreach (['A', 'B', 'C', 'D'] as $ci => $cl) {
                QuestionChoice::create(['question_id' => $cq->id, 'label' => $cl, 'content' => "Option {$cl}", 'is_correct' => $ci === 0, 'order' => $ci + 1]);
            }
        }
    }

    $eval = ToeicQuestionValidator::evaluatePart7Blueprint($this->toeicTest);
    expect($eval['single']['is_feasible'])->toBeFalse();

    // Teacher UI assertions
    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $response->assertOk();
    $response->assertDontSee('+ Add Single Passage');
    $response->assertDontSee('+ Add Passage Group');
    $response->assertSee('Needs Attention:');
    $response->assertSee('Single Passage distribution needs repair before authoring can continue.');
});

/*
|--------------------------------------------------------------------------
| P7-EDGE-04: Valid Progressive Authoring Flow
|--------------------------------------------------------------------------
*/
test('P7-EDGE-04: Progressive UI correctly advances from Single -> Double -> Triple -> Complete', function () {
    // 1. Initial State: Empty Part 7 -> shows + Add Single Passage (0/10)
    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $response->assertOk();
    $response->assertSee('+ Add Single Passage');

    // 2. Author 10 Single groups (29 questions) -> shows + Add Double Passage (0/2)
    for ($g = 1; $g <= 9; $g++) {
        $this->builderService->createPassageGroup($this->p7Section, makePart7Payload($this->p7Section->id, 'single', 3, ['title' => "Single {$g}"]));
    }
    $this->builderService->createPassageGroup($this->p7Section, makePart7Payload($this->p7Section->id, 'single', 2, ['title' => "Single 10"]));

    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $response->assertOk();
    $response->assertSee('+ Add Double Passage (0/2)');
    $response->assertDontSee('+ Add Single Passage');
    $response->assertDontSee('+ Add Triple Passage');

    // 3. Author 2 Double groups (10 questions) -> shows + Add Triple Passage (0/3)
    for ($g = 1; $g <= 2; $g++) {
        $this->builderService->createPassageGroup($this->p7Section, makePart7Payload($this->p7Section->id, 'double', 5, ['title' => "Double {$g}"]));
    }

    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $response->assertOk();
    $response->assertSee('+ Add Triple Passage (0/3)');
    $response->assertDontSee('+ Add Double Passage');
    $response->assertDontSee('+ Add Single Passage');

    // 4. Author 3 Triple groups (15 questions) -> shows All Part 7 Blocks Complete (15/15 Groups)
    for ($g = 1; $g <= 3; $g++) {
        $this->builderService->createPassageGroup($this->p7Section, makePart7Payload($this->p7Section->id, 'triple', 5, ['title' => "Triple {$g}"]));
    }

    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $response->assertOk();
    $response->assertSee('All Part 7 Blocks Complete (15/15 Groups)');
    $response->assertDontSee('+ Add Triple Passage');
    $response->assertDontSee('+ Add Double Passage');
    $response->assertDontSee('+ Add Single Passage');
});

/*
|--------------------------------------------------------------------------
| P7-LEGACY-01: Single Incomplete with Existing Later-Phase Double Groups
|--------------------------------------------------------------------------
*/
test('P7-LEGACY-01: Single incomplete with existing Double groups suppresses Add Single CTA and shows repair guidance', function () {
    // 8 Single groups (24 questions)
    for ($g = 1; $g <= 8; $g++) {
        $this->builderService->createPassageGroup($this->p7Section, makePart7Payload($this->p7Section->id, 'single', 3, ['title' => "Single {$g}"]));
    }
    // Direct DB creation of 2 Double groups (simulating legacy data)
    for ($g = 1; $g <= 2; $g++) {
        $pg = PassageGroup::create([
            'test_id'      => $this->toeicTest->id,
            'part_number'  => 7,
            'passage_type' => 'double',
            'title'        => "Legacy Double {$g}",
            'order'        => 8 + $g,
        ]);
        Passage::create(['passage_group_id' => $pg->id, 'title' => 'Doc 1', 'content' => 'Content', 'order_in_group' => 1]);
        Passage::create(['passage_group_id' => $pg->id, 'title' => 'Doc 2', 'content' => 'Content', 'order_in_group' => 2]);
        for ($q = 1; $q <= 5; $q++) {
            $cq = Question::create(['passage_group_id' => $pg->id, 'part_number' => 7, 'section' => 'reading', 'prompt' => "Prompt {$q}", 'question_type' => 'multiple_choice']);
            foreach (['A', 'B', 'C', 'D'] as $ci => $cl) {
                QuestionChoice::create(['question_id' => $cq->id, 'label' => $cl, 'content' => "Option {$cl}", 'is_correct' => $ci === 0, 'order' => $ci + 1]);
            }
        }
    }

    $eval = ToeicQuestionValidator::evaluatePart7Blueprint($this->toeicTest);
    expect($eval['single']['is_exact'])->toBeFalse()
        ->and($eval['single']['has_later_phase_groups'])->toBeTrue()
        ->and($eval['single']['can_create_new_group'])->toBeFalse();

    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $response->assertOk();
    $response->assertDontSee('+ Add Single Passage');
    $response->assertDontSee('+ Add Double Passage');
    $response->assertDontSee('+ Add Triple Passage');
    $response->assertDontSee('+ Add Passage Group');
    $response->assertSee('Needs Attention:');
    $response->assertSee('Legacy Part 7 structure detected. Later-phase passage groups already exist while the Single Passage block is incomplete. Edit or remove existing passage groups before adding new groups.');
});

/*
|--------------------------------------------------------------------------
| P7-LEGACY-02: Real UAT Shape (8 Single / 24Q + 6 Double / 30Q)
|--------------------------------------------------------------------------
*/
test('P7-LEGACY-02: Real UAT legacy shape (8 Single / 24Q + 6 Double / 30Q) displays Single in progress, Double needs attention, Triple locked', function () {
    // 8 Single groups (24 complete questions)
    for ($g = 1; $g <= 8; $g++) {
        $this->builderService->createPassageGroup($this->p7Section, makePart7Payload($this->p7Section->id, 'single', 3, ['title' => "Single {$g}"]));
    }
    // 6 Double groups (30 complete questions)
    for ($g = 1; $g <= 6; $g++) {
        $pg = PassageGroup::create([
            'test_id'      => $this->toeicTest->id,
            'part_number'  => 7,
            'passage_type' => 'double',
            'title'        => "Legacy Double {$g}",
            'order'        => 8 + $g,
        ]);
        Passage::create(['passage_group_id' => $pg->id, 'title' => 'Doc 1', 'content' => 'Content', 'order_in_group' => 1]);
        Passage::create(['passage_group_id' => $pg->id, 'title' => 'Doc 2', 'content' => 'Content', 'order_in_group' => 2]);
        for ($q = 1; $q <= 5; $q++) {
            $cq = Question::create(['passage_group_id' => $pg->id, 'part_number' => 7, 'section' => 'reading', 'prompt' => "Prompt {$q}", 'question_type' => 'multiple_choice']);
            foreach (['A', 'B', 'C', 'D'] as $ci => $cl) {
                QuestionChoice::create(['question_id' => $cq->id, 'label' => $cl, 'content' => "Option {$cl}", 'is_correct' => $ci === 0, 'order' => $ci + 1]);
            }
        }
    }

    $eval = ToeicQuestionValidator::evaluatePart7Blueprint($this->toeicTest);
    expect($eval['total']['groups'])->toBe(14)
        ->and($eval['total']['questions'])->toBe(54)
        ->and($eval['is_ready'])->toBeFalse()
        ->and($eval['single']['status'])->toBe('in_progress')
        ->and($eval['double']['status'])->toBe('invalid')
        ->and($eval['triple']['status'])->toBe('locked')
        ->and($eval['single']['can_create_new_group'])->toBeFalse()
        ->and($eval['double']['can_create_new_group'])->toBeFalse()
        ->and($eval['triple']['can_create_new_group'])->toBeFalse();

    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $response->assertOk();

    // Verify badges and absence of create actions
    $response->assertSee('IN PROGRESS');
    $response->assertSee('NEEDS ATTENTION');
    $response->assertSee('LOCKED');
    $response->assertDontSee('+ Add Single Passage');
    $response->assertDontSee('+ Add Double Passage');
    $response->assertDontSee('+ Add Triple Passage');
    $response->assertDontSee('+ Add Passage Group');
});

/*
|--------------------------------------------------------------------------
| P7-LEGACY-03: Invalid Double Priority over Locked
|--------------------------------------------------------------------------
*/
test('P7-LEGACY-03: Over-limit Double block displays NEEDS ATTENTION even when Single is incomplete', function () {
    // Single incomplete: 5 Single groups (15 questions)
    for ($g = 1; $g <= 5; $g++) {
        $this->builderService->createPassageGroup($this->p7Section, makePart7Payload($this->p7Section->id, 'single', 3, ['title' => "Single {$g}"]));
    }
    // Double over-limit: 6 groups (30 questions)
    for ($g = 1; $g <= 6; $g++) {
        $pg = PassageGroup::create([
            'test_id'      => $this->toeicTest->id,
            'part_number'  => 7,
            'passage_type' => 'double',
            'title'        => "Legacy Double {$g}",
            'order'        => 5 + $g,
        ]);
        Passage::create(['passage_group_id' => $pg->id, 'title' => 'Doc 1', 'content' => 'Content', 'order_in_group' => 1]);
        Passage::create(['passage_group_id' => $pg->id, 'title' => 'Doc 2', 'content' => 'Content', 'order_in_group' => 2]);
        for ($q = 1; $q <= 5; $q++) {
            $cq = Question::create(['passage_group_id' => $pg->id, 'part_number' => 7, 'section' => 'reading', 'prompt' => "Prompt {$q}", 'question_type' => 'multiple_choice']);
            foreach (['A', 'B', 'C', 'D'] as $ci => $cl) {
                QuestionChoice::create(['question_id' => $cq->id, 'label' => $cl, 'content' => "Option {$cl}", 'is_correct' => $ci === 0, 'order' => $ci + 1]);
            }
        }
    }

    $eval = ToeicQuestionValidator::evaluatePart7Blueprint($this->toeicTest);
    expect($eval['double']['is_locked'])->toBeTrue()
        ->and($eval['double']['status'])->toBe('invalid');

    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $response->assertOk();
    $response->assertSee('NEEDS ATTENTION');
});

/*
|--------------------------------------------------------------------------
| P7-LEGACY-04: Invalid Triple Priority over Locked
|--------------------------------------------------------------------------
*/
test('P7-LEGACY-04: Over-limit Triple block displays NEEDS ATTENTION even when Double is incomplete', function () {
    // Single exact: 10 groups (29 questions)
    for ($g = 1; $g <= 9; $g++) {
        $this->builderService->createPassageGroup($this->p7Section, makePart7Payload($this->p7Section->id, 'single', 3, ['title' => "Single {$g}"]));
    }
    $this->builderService->createPassageGroup($this->p7Section, makePart7Payload($this->p7Section->id, 'single', 2, ['title' => "Single 10"]));

    // Double incomplete: 1 group (5 questions)
    $this->builderService->createPassageGroup($this->p7Section, makePart7Payload($this->p7Section->id, 'double', 5, ['title' => "Double 1"]));

    // Triple over-limit: 4 groups (20 questions) created directly
    for ($g = 1; $g <= 4; $g++) {
        $pg = PassageGroup::create([
            'test_id'      => $this->toeicTest->id,
            'part_number'  => 7,
            'passage_type' => 'triple',
            'title'        => "Legacy Triple {$g}",
            'order'        => 11 + $g,
        ]);
        Passage::create(['passage_group_id' => $pg->id, 'title' => 'Doc 1', 'content' => 'Content', 'order_in_group' => 1]);
        Passage::create(['passage_group_id' => $pg->id, 'title' => 'Doc 2', 'content' => 'Content', 'order_in_group' => 2]);
        Passage::create(['passage_group_id' => $pg->id, 'title' => 'Doc 3', 'content' => 'Content', 'order_in_group' => 3]);
        for ($q = 1; $q <= 5; $q++) {
            $cq = Question::create(['passage_group_id' => $pg->id, 'part_number' => 7, 'section' => 'reading', 'prompt' => "Prompt {$q}", 'question_type' => 'multiple_choice']);
            foreach (['A', 'B', 'C', 'D'] as $ci => $cl) {
                QuestionChoice::create(['question_id' => $cq->id, 'label' => $cl, 'content' => "Option {$cl}", 'is_correct' => $ci === 0, 'order' => $ci + 1]);
            }
        }
    }

    $eval = ToeicQuestionValidator::evaluatePart7Blueprint($this->toeicTest);
    expect($eval['triple']['is_locked'])->toBeTrue()
        ->and($eval['triple']['status'])->toBe('invalid')
        ->and($eval['triple']['can_create_new_group'])->toBeFalse();

    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $response->assertOk();
    $response->assertSee('NEEDS ATTENTION');
});

/*
|--------------------------------------------------------------------------
| P7-LEGACY-05: Canonical Clean Authoring Flow Regression
|--------------------------------------------------------------------------
*/
test('P7-LEGACY-05: Canonical authoring flow cleanly progresses through can_create_new_group states', function () {
    // 1. Empty state
    $eval0 = ToeicQuestionValidator::evaluatePart7Blueprint($this->toeicTest);
    expect($eval0['single']['can_create_new_group'])->toBeTrue()
        ->and($eval0['double']['can_create_new_group'])->toBeFalse()
        ->and($eval0['triple']['can_create_new_group'])->toBeFalse();

    // 2. Single incomplete (5 groups of 3)
    for ($g = 1; $g <= 5; $g++) {
        $this->builderService->createPassageGroup($this->p7Section, makePart7Payload($this->p7Section->id, 'single', 3, ['title' => "Single {$g}"]));
    }
    $eval1 = ToeicQuestionValidator::evaluatePart7Blueprint($this->toeicTest);
    expect($eval1['single']['can_create_new_group'])->toBeTrue()
        ->and($eval1['double']['can_create_new_group'])->toBeFalse()
        ->and($eval1['triple']['can_create_new_group'])->toBeFalse();

    // 3. Complete Single (10 groups / 29 questions)
    for ($g = 6; $g <= 9; $g++) {
        $this->builderService->createPassageGroup($this->p7Section, makePart7Payload($this->p7Section->id, 'single', 3, ['title' => "Single {$g}"]));
    }
    $this->builderService->createPassageGroup($this->p7Section, makePart7Payload($this->p7Section->id, 'single', 2, ['title' => "Single 10"]));

    $eval2 = ToeicQuestionValidator::evaluatePart7Blueprint($this->toeicTest);
    expect($eval2['single']['can_create_new_group'])->toBeFalse()
        ->and($eval2['double']['can_create_new_group'])->toBeTrue()
        ->and($eval2['triple']['can_create_new_group'])->toBeFalse();

    // 4. Double incomplete (1 group of 5)
    $this->builderService->createPassageGroup($this->p7Section, makePart7Payload($this->p7Section->id, 'double', 5, ['title' => "Double 1"]));
    $eval3 = ToeicQuestionValidator::evaluatePart7Blueprint($this->toeicTest);
    expect($eval3['single']['can_create_new_group'])->toBeFalse()
        ->and($eval3['double']['can_create_new_group'])->toBeTrue()
        ->and($eval3['triple']['can_create_new_group'])->toBeFalse();

    // 5. Complete Double (2 groups of 5)
    $this->builderService->createPassageGroup($this->p7Section, makePart7Payload($this->p7Section->id, 'double', 5, ['title' => "Double 2"]));
    $eval4 = ToeicQuestionValidator::evaluatePart7Blueprint($this->toeicTest);
    expect($eval4['single']['can_create_new_group'])->toBeFalse()
        ->and($eval4['double']['can_create_new_group'])->toBeFalse()
        ->and($eval4['triple']['can_create_new_group'])->toBeTrue();

    // 6. Complete Triple (3 groups of 5) -> All complete
    for ($g = 1; $g <= 3; $g++) {
        $this->builderService->createPassageGroup($this->p7Section, makePart7Payload($this->p7Section->id, 'triple', 5, ['title' => "Triple {$g}"]));
    }
    $eval5 = ToeicQuestionValidator::evaluatePart7Blueprint($this->toeicTest);
    expect($eval5['single']['can_create_new_group'])->toBeFalse()
        ->and($eval5['double']['can_create_new_group'])->toBeFalse()
        ->and($eval5['triple']['can_create_new_group'])->toBeFalse()
        ->and($eval5['is_ready'])->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| P7-LEGACY-06: Existing Group Repairability in Legacy State
|--------------------------------------------------------------------------
*/
test('P7-LEGACY-06: Existing passage groups in legacy state retain edit and delete repairability', function () {
    // 8 Single groups + 6 Double groups
    $singleGroups = [];
    for ($g = 1; $g <= 8; $g++) {
        $singleGroups[] = $this->builderService->createPassageGroup($this->p7Section, makePart7Payload($this->p7Section->id, 'single', 3, ['title' => "Single {$g}"]));
    }
    $doubleGroups = [];
    for ($g = 1; $g <= 6; $g++) {
        $pg = PassageGroup::create([
            'test_id'      => $this->toeicTest->id,
            'part_number'  => 7,
            'passage_type' => 'double',
            'title'        => "Legacy Double {$g}",
            'order'        => 8 + $g,
        ]);
        Passage::create(['passage_group_id' => $pg->id, 'title' => 'Doc 1', 'content' => 'Content', 'order_in_group' => 1]);
        Passage::create(['passage_group_id' => $pg->id, 'title' => 'Doc 2', 'content' => 'Content', 'order_in_group' => 2]);
        for ($q = 1; $q <= 5; $q++) {
            $cq = Question::create(['passage_group_id' => $pg->id, 'part_number' => 7, 'section' => 'reading', 'prompt' => "Prompt {$q}", 'question_type' => 'multiple_choice']);
            foreach (['A', 'B', 'C', 'D'] as $ci => $cl) {
                QuestionChoice::create(['question_id' => $cq->id, 'label' => $cl, 'content' => "Option {$cl}", 'is_correct' => $ci === 0, 'order' => $ci + 1]);
            }
        }
        $doubleGroups[] = $pg;
    }

    // 1. Verify Teacher can view assessment detail with edit/delete buttons for each group
    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $response->assertOk();
    $response->assertSee('Edit Group');
    $response->assertSee('Remove Group');

    // 2. Teacher can delete one of the over-limit Double groups to repair data
    $doubleToDelete = end($doubleGroups);
    $delResponse = $this->actingAs($this->teacher)->delete(route('teacher.tests.destroy-passage-group', [
        'test' => $this->toeicTest->id,
        'passageGroup' => $doubleToDelete->id,
    ]));
    $delResponse->assertRedirect();
    expect(PassageGroup::find($doubleToDelete->id))->toBeNull()
        ->and(PassageGroup::where('test_id', $this->toeicTest->id)->where('passage_type', 'double')->count())->toBe(5);

    // 3. Teacher can edit a Single group
    $singleToEdit = $singleGroups[0];
    $updatePayload = makePart7Payload($this->p7Section->id, 'single', 4, ['title' => 'Single 1 Updated to 4 Questions']);
    $updatedGroup = $this->builderService->updatePassageGroup($singleToEdit, $updatePayload);
    expect($updatedGroup->fresh()->questions()->count())->toBe(4);
});

/*
|--------------------------------------------------------------------------
| P7-STRUCT-01: Single Block Count Exact But Structurally Invalid Group
|--------------------------------------------------------------------------
*/
test('P7-STRUCT-01: Single block with 10 groups / 29 questions but an invalid group is invalid and locks Double creation', function () {
    // 9 valid single groups of 3 = 27 Qs
    for ($g = 1; $g <= 9; $g++) {
        $this->builderService->createPassageGroup($this->p7Section, makePart7Payload($this->p7Section->id, 'single', 3, ['title' => "Single {$g}"]));
    }

    // 10th single group with 2 questions, but 2 documents instead of 1 (structurally invalid for single)
    $invalidPg = PassageGroup::create([
        'test_id'      => $this->toeicTest->id,
        'part_number'  => 7,
        'passage_type' => 'single',
        'title'        => 'Single 10 Invalid (2 docs)',
        'order'        => 10,
    ]);
    Passage::create(['passage_group_id' => $invalidPg->id, 'title' => 'Doc 1', 'content' => 'Content 1', 'order_in_group' => 1]);
    Passage::create(['passage_group_id' => $invalidPg->id, 'title' => 'Doc 2', 'content' => 'Content 2', 'order_in_group' => 2]);
    for ($q = 1; $q <= 2; $q++) {
        $cq = Question::create(['passage_group_id' => $invalidPg->id, 'part_number' => 7, 'section' => 'reading', 'prompt' => "Prompt {$q}", 'question_type' => 'multiple_choice']);
        foreach (['A', 'B', 'C', 'D'] as $ci => $cl) {
            QuestionChoice::create(['question_id' => $cq->id, 'label' => $cl, 'content' => "Choice {$cl}", 'is_correct' => $ci === 0, 'order' => $ci + 1]);
        }
    }

    $eval = ToeicQuestionValidator::evaluatePart7Blueprint($this->toeicTest);
    expect($eval['single']['count_exact'])->toBeTrue()
        ->and($eval['single']['groups_valid'])->toBeFalse()
        ->and($eval['single']['is_exact'])->toBeFalse()
        ->and($eval['single']['status'])->toBe('invalid')
        ->and($eval['double']['can_create_new_group'])->toBeFalse();

    // Teacher UI shows NEEDS ATTENTION on Single card and no + Add Double Passage CTA
    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $response->assertOk();
    $response->assertSee('NEEDS ATTENTION');
    $response->assertDontSee('+ Add Double Passage');

    // Backend attempt to create Double is rejected
    expect(fn () => $this->builderService->createPassageGroup($this->p7Section, makePart7Payload($this->p7Section->id, 'double', 5)))
        ->toThrow(ValidationException::class);
});

/*
|--------------------------------------------------------------------------
| P7-STRUCT-02: Double Block Count Exact But Structurally Invalid Group
|--------------------------------------------------------------------------
*/
test('P7-STRUCT-02: Double block with 2 groups / 10 questions but an invalid group is invalid and locks Triple creation', function () {
    // 10 valid single groups = 29 questions
    for ($g = 1; $g <= 9; $g++) {
        $this->builderService->createPassageGroup($this->p7Section, makePart7Payload($this->p7Section->id, 'single', 3, ['title' => "Single {$g}"]));
    }
    $this->builderService->createPassageGroup($this->p7Section, makePart7Payload($this->p7Section->id, 'single', 2, ['title' => 'Single 10']));

    // 1st double group valid (2 docs, 5 questions)
    $this->builderService->createPassageGroup($this->p7Section, makePart7Payload($this->p7Section->id, 'double', 5, ['title' => 'Double 1']));

    // 2nd double group invalid: only 1 doc instead of 2 docs
    $invalidDouble = PassageGroup::create([
        'test_id'      => $this->toeicTest->id,
        'part_number'  => 7,
        'passage_type' => 'double',
        'title'        => 'Double 2 Invalid (1 doc)',
        'order'        => 12,
    ]);
    Passage::create(['passage_group_id' => $invalidDouble->id, 'title' => 'Doc 1', 'content' => 'Content 1', 'order_in_group' => 1]);
    for ($q = 1; $q <= 5; $q++) {
        $cq = Question::create(['passage_group_id' => $invalidDouble->id, 'part_number' => 7, 'section' => 'reading', 'prompt' => "Prompt {$q}", 'question_type' => 'multiple_choice']);
        foreach (['A', 'B', 'C', 'D'] as $ci => $cl) {
            QuestionChoice::create(['question_id' => $cq->id, 'label' => $cl, 'content' => "Choice {$cl}", 'is_correct' => $ci === 0, 'order' => $ci + 1]);
        }
    }

    $eval = ToeicQuestionValidator::evaluatePart7Blueprint($this->toeicTest);
    expect($eval['double']['count_exact'])->toBeTrue()
        ->and($eval['double']['groups_valid'])->toBeFalse()
        ->and($eval['double']['is_exact'])->toBeFalse()
        ->and($eval['double']['status'])->toBe('invalid')
        ->and($eval['triple']['can_create_new_group'])->toBeFalse();

    // Teacher UI shows NEEDS ATTENTION on Double card and no + Add Triple Passage CTA
    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $response->assertOk();
    $response->assertSee('NEEDS ATTENTION');
    $response->assertDontSee('+ Add Triple Passage');

    // Backend attempt to create Triple is rejected
    expect(fn () => $this->builderService->createPassageGroup($this->p7Section, makePart7Payload($this->p7Section->id, 'triple', 5)))
        ->toThrow(ValidationException::class);
});

/*
|--------------------------------------------------------------------------
| P7-STRUCT-03: Triple Block Count Exact But Structurally Invalid Group
|--------------------------------------------------------------------------
*/
test('P7-STRUCT-03: Triple block with 3 groups / 15 questions but an invalid group is invalid and Part 7 is not ready', function () {
    // Single & Double valid exact
    for ($g = 1; $g <= 9; $g++) {
        $this->builderService->createPassageGroup($this->p7Section, makePart7Payload($this->p7Section->id, 'single', 3, ['title' => "Single {$g}"]));
    }
    $this->builderService->createPassageGroup($this->p7Section, makePart7Payload($this->p7Section->id, 'single', 2, ['title' => 'Single 10']));
    $this->builderService->createPassageGroup($this->p7Section, makePart7Payload($this->p7Section->id, 'double', 5, ['title' => 'Double 1']));
    $this->builderService->createPassageGroup($this->p7Section, makePart7Payload($this->p7Section->id, 'double', 5, ['title' => 'Double 2']));

    // 2 valid Triple groups
    $this->builderService->createPassageGroup($this->p7Section, makePart7Payload($this->p7Section->id, 'triple', 5, ['title' => 'Triple 1']));
    $this->builderService->createPassageGroup($this->p7Section, makePart7Payload($this->p7Section->id, 'triple', 5, ['title' => 'Triple 2']));

    // 3rd Triple group invalid: only 2 docs instead of 3 docs
    $invalidTriple = PassageGroup::create([
        'test_id'      => $this->toeicTest->id,
        'part_number'  => 7,
        'passage_type' => 'triple',
        'title'        => 'Triple 3 Invalid (2 docs)',
        'order'        => 15,
    ]);
    Passage::create(['passage_group_id' => $invalidTriple->id, 'title' => 'Doc 1', 'content' => 'Content 1', 'order_in_group' => 1]);
    Passage::create(['passage_group_id' => $invalidTriple->id, 'title' => 'Doc 2', 'content' => 'Content 2', 'order_in_group' => 2]);
    for ($q = 1; $q <= 5; $q++) {
        $cq = Question::create(['passage_group_id' => $invalidTriple->id, 'part_number' => 7, 'section' => 'reading', 'prompt' => "Prompt {$q}", 'question_type' => 'multiple_choice']);
        foreach (['A', 'B', 'C', 'D'] as $ci => $cl) {
            QuestionChoice::create(['question_id' => $cq->id, 'label' => $cl, 'content' => "Choice {$cl}", 'is_correct' => $ci === 0, 'order' => $ci + 1]);
        }
    }

    $eval = ToeicQuestionValidator::evaluatePart7Blueprint($this->toeicTest);
    expect($eval['triple']['count_exact'])->toBeTrue()
        ->and($eval['triple']['groups_valid'])->toBeFalse()
        ->and($eval['triple']['is_exact'])->toBeFalse()
        ->and($eval['triple']['status'])->toBe('invalid')
        ->and($eval['is_ready'])->toBeFalse();

    // Teacher UI shows NEEDS ATTENTION for Triple, NOT COMPLETE
    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $response->assertOk();
    $response->assertSee('NEEDS ATTENTION');
});

/*
|--------------------------------------------------------------------------
| P7-STRUCT-04: Partial In-Progress Block With Invalid Group
|--------------------------------------------------------------------------
*/
test('P7-STRUCT-04: Partial block with an invalid group becomes invalid and disables create CTA while keeping repair controls', function () {
    // 4 valid single groups
    for ($g = 1; $g <= 4; $g++) {
        $this->builderService->createPassageGroup($this->p7Section, makePart7Payload($this->p7Section->id, 'single', 3, ['title' => "Single {$g}"]));
    }

    // 5th single group invalid (2 docs)
    $invalidPg = PassageGroup::create([
        'test_id'      => $this->toeicTest->id,
        'part_number'  => 7,
        'passage_type' => 'single',
        'title'        => 'Single 5 Invalid',
        'order'        => 5,
    ]);
    Passage::create(['passage_group_id' => $invalidPg->id, 'title' => 'Doc 1', 'content' => 'Content 1', 'order_in_group' => 1]);
    Passage::create(['passage_group_id' => $invalidPg->id, 'title' => 'Doc 2', 'content' => 'Content 2', 'order_in_group' => 2]);
    for ($q = 1; $q <= 3; $q++) {
        $cq = Question::create(['passage_group_id' => $invalidPg->id, 'part_number' => 7, 'section' => 'reading', 'prompt' => "Prompt {$q}", 'question_type' => 'multiple_choice']);
        foreach (['A', 'B', 'C', 'D'] as $ci => $cl) {
            QuestionChoice::create(['question_id' => $cq->id, 'label' => $cl, 'content' => "Choice {$cl}", 'is_correct' => $ci === 0, 'order' => $ci + 1]);
        }
    }

    $eval = ToeicQuestionValidator::evaluatePart7Blueprint($this->toeicTest);
    expect($eval['single']['groups_valid'])->toBeFalse()
        ->and($eval['single']['status'])->toBe('invalid')
        ->and($eval['single']['can_create_new_group'])->toBeFalse();

    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $response->assertOk();
    $response->assertSee('NEEDS ATTENTION');
    $response->assertSee('Edit Group');
    $response->assertSee('Remove Group');
    $response->assertDontSee('+ Add Single Passage');
});

/*
|--------------------------------------------------------------------------
| P7-STRUCT-05: Valid Canonical Regression (15 Groups / 54 Questions)
|--------------------------------------------------------------------------
*/
test('P7-STRUCT-05: Full canonical Part 7 structure satisfies per-block validity and is ready', function () {
    seedCanonicalPart7($this->p7Section, $this->builderService);

    $eval = ToeicQuestionValidator::evaluatePart7Blueprint($this->toeicTest);
    expect($eval['single']['groups_valid'])->toBeTrue()
        ->and($eval['single']['is_exact'])->toBeTrue()
        ->and($eval['double']['groups_valid'])->toBeTrue()
        ->and($eval['double']['is_exact'])->toBeTrue()
        ->and($eval['triple']['groups_valid'])->toBeTrue()
        ->and($eval['triple']['is_exact'])->toBeTrue()
        ->and($eval['is_ready'])->toBeTrue()
        ->and($eval['total']['groups'])->toBe(15)
        ->and($eval['total']['questions'])->toBe(54);
});

/*
|--------------------------------------------------------------------------
| P7-STRUCT-06: Real UAT Legacy Shape Structural Regression
|--------------------------------------------------------------------------
*/
test('P7-STRUCT-06: Real UAT legacy shape (8 Single / 6 Double) preserves expected statuses and blocks CTA', function () {
    for ($g = 1; $g <= 8; $g++) {
        $this->builderService->createPassageGroup($this->p7Section, makePart7Payload($this->p7Section->id, 'single', 3, ['title' => "Single {$g}"]));
    }
    for ($g = 1; $g <= 6; $g++) {
        $pg = PassageGroup::create([
            'test_id'      => $this->toeicTest->id,
            'part_number'  => 7,
            'passage_type' => 'double',
            'title'        => "Legacy Double {$g}",
            'order'        => 8 + $g,
        ]);
        Passage::create(['passage_group_id' => $pg->id, 'title' => 'Doc 1', 'content' => 'Content', 'order_in_group' => 1]);
        Passage::create(['passage_group_id' => $pg->id, 'title' => 'Doc 2', 'content' => 'Content', 'order_in_group' => 2]);
        for ($q = 1; $q <= 5; $q++) {
            $cq = Question::create(['passage_group_id' => $pg->id, 'part_number' => 7, 'section' => 'reading', 'prompt' => "Prompt {$q}", 'question_type' => 'multiple_choice']);
            foreach (['A', 'B', 'C', 'D'] as $ci => $cl) {
                QuestionChoice::create(['question_id' => $cq->id, 'label' => $cl, 'content' => "Option {$cl}", 'is_correct' => $ci === 0, 'order' => $ci + 1]);
            }
        }
    }

    $eval = ToeicQuestionValidator::evaluatePart7Blueprint($this->toeicTest);
    expect($eval['single']['status'])->toBe('in_progress')
        ->and($eval['double']['status'])->toBe('invalid')
        ->and($eval['triple']['status'])->toBe('locked')
        ->and($eval['single']['can_create_new_group'])->toBeFalse()
        ->and($eval['double']['can_create_new_group'])->toBeFalse()
        ->and($eval['triple']['can_create_new_group'])->toBeFalse();
});
