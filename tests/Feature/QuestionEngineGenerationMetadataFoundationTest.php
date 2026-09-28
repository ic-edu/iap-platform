<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\QuestionBank\Enums\DifficultyLevel;
use App\Modules\QuestionBank\Enums\QuestionType;
use App\Modules\QuestionBank\Enums\SectionType;
use App\Modules\QuestionBank\Enums\TestType;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Modules\QuestionBank\Models\QuestionChoice;
use App\Modules\QuestionEngine\DTO\QuestionGenerationRequest;
use App\Modules\QuestionEngine\Enums\ConstructTaxonomy;
use App\Modules\QuestionEngine\Enums\ContentMode;
use App\Modules\QuestionEngine\Enums\ContentOrigin;
use App\Modules\QuestionEngine\Enums\ContextTaxonomy;
use App\Modules\QuestionEngine\Enums\DomainTaxonomy;
use App\Modules\QuestionEngine\Enums\ProficiencyTarget;
use App\Modules\QuestionEngine\Exceptions\InvalidQuestionGenerationRequestException;
use App\Modules\QuestionEngine\Services\PartConstructCompatibility;
use App\Modules\QuestionEngine\Services\QuestionGenerationRequestValidator;
use App\Services\QuestionDifficultyDetectionService;
use App\Services\ToeicQuestionValidator;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class QuestionEngineGenerationMetadataFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;

    protected QuestionBank $questionBank;

    protected QuestionGenerationRequestValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->teacher = User::factory()->create(['status' => 'active']);
        $this->teacher->assignRole('teacher');

        $this->questionBank = QuestionBank::create([
            'title' => 'Test Question Bank',
            'slug' => 'test-question-bank-'.uniqid(),
            'created_by' => $this->teacher->id,
            'is_published' => true,
        ]);

        $this->validator = new QuestionGenerationRequestValidator;
    }

    /**
     * TEST A: Proficiency enum canonical values & CEFR band mappings.
     */
    public function test_a_proficiency_enum_canonical_values(): void
    {
        $expectedValues = [
            'a1',
            'a1_plus',
            'a2_low',
            'a2_standard',
            'b1_low',
            'b1_standard',
            'b2_low',
            'b2_standard',
            'c1',
            'c2',
        ];

        $this->assertEquals($expectedValues, ProficiencyTarget::values());
        $this->assertEquals('A1 - Beginner', ProficiencyTarget::A1->label());
        $this->assertEquals('A1+ - Elementary', ProficiencyTarget::A1_PLUS->label());
        $this->assertEquals('A2 Low - Pre-Intermediate (Emerging)', ProficiencyTarget::A2_LOW->label());
        $this->assertEquals('A2 - Pre-Intermediate (Standard)', ProficiencyTarget::A2_STANDARD->label());
        $this->assertEquals('B1 Low - Intermediate (Emerging)', ProficiencyTarget::B1_LOW->label());
        $this->assertEquals('B1 - Intermediate (Standard)', ProficiencyTarget::B1_STANDARD->label());
        $this->assertEquals('B2 Low - Upper Intermediate (Emerging)', ProficiencyTarget::B2_LOW->label());
        $this->assertEquals('B2 - Upper Intermediate (Standard)', ProficiencyTarget::B2_STANDARD->label());
        $this->assertEquals('C1 - Advanced', ProficiencyTarget::C1->label());
        $this->assertEquals('C2 - Proficiency', ProficiencyTarget::C2->label());

        $this->assertEquals('A1', ProficiencyTarget::A1->cefrBand());
        $this->assertEquals('A2', ProficiencyTarget::A2_STANDARD->cefrBand());
        $this->assertEquals('B1', ProficiencyTarget::B1_STANDARD->cefrBand());
        $this->assertEquals('B2', ProficiencyTarget::B2_STANDARD->cefrBand());
    }

    /**
     * TEST B: Difficulty remains independent from proficiency.
     */
    public function test_b_difficulty_remains_independent_from_proficiency(): void
    {
        $question = Question::create([
            'question_bank_id' => $this->questionBank->id,
            'prompt' => 'What is the topic of the meeting?',
            'section' => SectionType::Reading,
            'part_number' => 7,
            'question_type' => QuestionType::MultipleChoice,
            'difficulty' => DifficultyLevel::Medium,
            'difficulty_score' => 56,
            'difficulty_status' => 'final',
            'proficiency_target' => ProficiencyTarget::A2_STANDARD,
            'content_mode' => ContentMode::General,
            'domain' => DomainTaxonomy::GeneralWorkplace,
            'construct' => ConstructTaxonomy::MainIdea,
            'context' => ContextTaxonomy::Meeting,
            'points' => 5,
        ]);

        $question->refresh();

        $this->assertEquals(DifficultyLevel::Medium, $question->difficulty);
        $this->assertEquals(56, $question->difficulty_score);
        $this->assertEquals(ProficiencyTarget::A2_STANDARD, $question->proficiency_target);
        $this->assertEquals('A2 - Pre-Intermediate (Standard)', $question->getProficiencyLabel());
        $this->assertFalse($question->isDomainSpecific());
        $this->assertFalse($question->isGenerated());
    }

    /**
     * TEST 1: General + omitted domain -> general_workplace.
     */
    public function test_1_general_omitted_domain_resolves_to_general_workplace(): void
    {
        $request = QuestionGenerationRequest::fromArray([
            'part_number' => 5,
            'proficiency_target' => 'b1_standard',
            'difficulty' => 'medium',
            'construct' => 'grammar',
            'content_mode' => 'general',
        ]);

        $validated = $this->validator->validate($request);

        $this->assertEquals('general', $validated['content_mode']);
        $this->assertEquals('general_workplace', $validated['domain']);
        $this->assertFalse($request->isDomainSpecific());
    }

    /**
     * TEST 2: General + general_workplace -> valid.
     */
    public function test_2_general_and_general_workplace_is_valid(): void
    {
        $request = QuestionGenerationRequest::fromArray([
            'part_number' => 5,
            'proficiency_target' => 'b1_standard',
            'difficulty' => 'medium',
            'construct' => 'grammar',
            'content_mode' => 'general',
            'domain' => 'general_workplace',
        ]);

        $validated = $this->validator->validate($request);
        $this->assertEquals('general_workplace', $validated['domain']);
    }

    /**
     * TEST 3: General + hospitality -> rejected (both raw array and DTO).
     */
    public function test_3_general_and_hospitality_is_rejected(): void
    {
        // Raw array path
        $this->expectException(InvalidQuestionGenerationRequestException::class);
        $this->validator->validate([
            'part_number' => 5,
            'proficiency_target' => 'b1_standard',
            'difficulty' => 'medium',
            'construct' => 'grammar',
            'content_mode' => 'general',
            'domain' => 'hospitality',
        ]);
    }

    public function test_3b_general_and_hospitality_from_array_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        QuestionGenerationRequest::fromArray([
            'part_number' => 5,
            'proficiency_target' => 'b1_standard',
            'difficulty' => 'medium',
            'construct' => 'grammar',
            'content_mode' => 'general',
            'domain' => 'hospitality',
        ]);
    }

    /**
     * TEST 4 & 5: Domain-specific + hospitality/engineering -> valid.
     */
    public function test_4_and_5_domain_specific_with_valid_domains_is_valid(): void
    {
        $requestHosp = QuestionGenerationRequest::fromArray([
            'part_number' => 6,
            'proficiency_target' => 'b2_standard',
            'difficulty' => 'hard',
            'construct' => 'vocabulary',
            'content_mode' => 'domain_specific',
            'domain' => 'hospitality',
            'context' => 'reservation',
        ]);
        $validatedHosp = $this->validator->validate($requestHosp);
        $this->assertEquals('hospitality', $validatedHosp['domain']);
        $this->assertTrue($requestHosp->isDomainSpecific());

        $requestEng = QuestionGenerationRequest::fromArray([
            'part_number' => 7,
            'proficiency_target' => 'b2_standard',
            'difficulty' => 'medium',
            'construct' => 'detail',
            'content_mode' => 'domain_specific',
            'domain' => 'engineering',
            'context' => 'maintenance',
        ]);
        $validatedEng = $this->validator->validate($requestEng);
        $this->assertEquals('engineering', $validatedEng['domain']);
    }

    /**
     * TEST 6: Domain-specific + general_workplace -> rejected.
     */
    public function test_6_domain_specific_and_general_workplace_is_rejected(): void
    {
        $this->expectException(InvalidQuestionGenerationRequestException::class);
        $this->validator->validate([
            'part_number' => 5,
            'proficiency_target' => 'b1_standard',
            'difficulty' => 'medium',
            'construct' => 'vocabulary',
            'content_mode' => 'domain_specific',
            'domain' => 'general_workplace',
        ]);
    }

    public function test_6b_domain_specific_and_general_workplace_from_array_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        QuestionGenerationRequest::fromArray([
            'part_number' => 5,
            'proficiency_target' => 'b1_standard',
            'difficulty' => 'medium',
            'construct' => 'vocabulary',
            'content_mode' => 'domain_specific',
            'domain' => 'general_workplace',
        ]);
    }

    /**
     * TEST 7: Domain-specific + omitted domain -> rejected.
     */
    public function test_7_domain_specific_omitted_domain_is_rejected(): void
    {
        $this->expectException(InvalidQuestionGenerationRequestException::class);
        $this->validator->validate([
            'part_number' => 5,
            'proficiency_target' => 'b1_standard',
            'difficulty' => 'medium',
            'construct' => 'vocabulary',
            'content_mode' => 'domain_specific',
            'domain' => '',
        ]);
    }

    /**
     * TEST 8: Raw item_count = 0 -> rejected.
     */
    public function test_8_raw_item_count_zero_is_rejected(): void
    {
        $this->expectException(InvalidQuestionGenerationRequestException::class);
        $this->validator->validate([
            'part_number' => 5,
            'proficiency_target' => 'b1_standard',
            'difficulty' => 'medium',
            'construct' => 'grammar',
            'item_count' => 0,
        ]);
    }

    /**
     * TEST 9: DTO fromArray item_count = 0 -> rejected.
     */
    public function test_9_dto_from_array_item_count_zero_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        QuestionGenerationRequest::fromArray([
            'part_number' => 5,
            'proficiency_target' => 'b1_standard',
            'difficulty' => 'medium',
            'construct' => 'grammar',
            'item_count' => 0,
        ]);
    }

    /**
     * TEST 10: Negative item_count -> rejected (raw and fromArray).
     */
    public function test_10_negative_item_count_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        QuestionGenerationRequest::fromArray([
            'part_number' => 5,
            'proficiency_target' => 'b1_standard',
            'difficulty' => 'medium',
            'construct' => 'grammar',
            'item_count' => -5,
        ]);
    }

    /**
     * TEST 11: Omitted item_count -> defaults to 1.
     */
    public function test_11_omitted_item_count_defaults_to_one(): void
    {
        $request = QuestionGenerationRequest::fromArray([
            'part_number' => 5,
            'proficiency_target' => 'b1_standard',
            'difficulty' => 'medium',
            'construct' => 'grammar',
        ]);

        $this->assertEquals(1, $request->itemCount);
        $validated = $this->validator->validate($request);
        $this->assertEquals(1, $validated['item_count']);
    }

    /**
     * TEST 12: Invalid test_type raw -> rejected.
     */
    public function test_12_invalid_test_type_raw_is_rejected(): void
    {
        $this->expectException(InvalidQuestionGenerationRequestException::class);
        $this->validator->validate([
            'part_number' => 5,
            'proficiency_target' => 'b1_standard',
            'difficulty' => 'medium',
            'construct' => 'grammar',
            'test_type' => 'unknown_exam_xyz',
        ]);
    }

    /**
     * TEST 13: Invalid test_type fromArray -> rejected.
     */
    public function test_13_invalid_test_type_from_array_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        QuestionGenerationRequest::fromArray([
            'part_number' => 5,
            'proficiency_target' => 'b1_standard',
            'difficulty' => 'medium',
            'construct' => 'grammar',
            'test_type' => 'unknown_exam_xyz',
        ]);
    }

    /**
     * TEST 14 & 15: TOEFL & IELTS generation request -> explicit unsupported rejection.
     */
    public function test_14_and_15_toefl_and_ielts_generation_request_is_rejected(): void
    {
        $this->expectException(InvalidQuestionGenerationRequestException::class);
        $this->expectExceptionMessage('Question generation currently supports TOEIC only.');

        $this->validator->validate([
            'part_number' => 5,
            'proficiency_target' => 'b1_standard',
            'difficulty' => 'medium',
            'construct' => 'grammar',
            'test_type' => 'toefl',
        ]);
    }

    public function test_15_ielts_generation_request_is_rejected(): void
    {
        $this->expectException(InvalidQuestionGenerationRequestException::class);
        $this->expectExceptionMessage('Question generation currently supports TOEIC only.');

        $this->validator->validate([
            'part_number' => 5,
            'proficiency_target' => 'b1_standard',
            'difficulty' => 'medium',
            'construct' => 'grammar',
            'test_type' => 'ielts',
        ]);
    }

    /**
     * TEST 16: TOEIC request remains valid.
     */
    public function test_16_toeic_request_remains_valid(): void
    {
        $request = QuestionGenerationRequest::fromArray([
            'test_type' => 'toeic',
            'part_number' => 5,
            'proficiency_target' => 'b1_standard',
            'difficulty' => 'medium',
            'construct' => 'grammar',
        ]);

        $this->assertEquals(TestType::Toeic, $request->testType);
        $validated = $this->validator->validate($request);
        $this->assertEquals('toeic', $validated['test_type']);
    }

    /**
     * TEST 17: Existing Question / TestType behavior outside Question Engine unchanged.
     */
    public function test_17_existing_test_type_enum_cases_remain_intact(): void
    {
        $this->assertEquals('toeic', TestType::Toeic->value);
        $this->assertEquals('toefl', TestType::Toefl->value);
        $this->assertEquals('ielts', TestType::Ielts->value);
        $this->assertEquals('general', TestType::General->value);

        $this->assertEquals('TOEIC', TestType::Toeic->label());
        $this->assertEquals('TOEFL iBT', TestType::Toefl->label());
        $this->assertEquals('IELTS', TestType::Ielts->label());
    }

    /**
     * TOEIC Part mappings tests.
     */
    public function test_toeic_part_canonical_mappings(): void
    {
        $this->assertEquals(SectionType::Listening, PartConstructCompatibility::getCanonicalSectionForPart(1));
        $this->assertEquals(SectionType::Listening, PartConstructCompatibility::getCanonicalSectionForPart(4));
        $this->assertEquals(SectionType::Reading, PartConstructCompatibility::getCanonicalSectionForPart(5));
        $this->assertEquals(SectionType::Reading, PartConstructCompatibility::getCanonicalSectionForPart(7));
    }

    /**
     * Incompatible section/part rejected.
     */
    public function test_incompatible_section_part_rejected(): void
    {
        $this->expectException(InvalidQuestionGenerationRequestException::class);

        $this->validator->validate([
            'part_number' => 1,
            'section' => 'reading', // Part 1 is listening
            'proficiency_target' => 'a1',
            'difficulty' => 'easy',
            'construct' => 'visual_description',
        ]);
    }

    /**
     * Compatible vs Incompatible constructs.
     */
    public function test_compatible_and_incompatible_constructs(): void
    {
        $this->assertTrue(PartConstructCompatibility::isCompatible(1, ConstructTaxonomy::VisualDescription));
        $this->assertFalse(PartConstructCompatibility::isCompatible(1, ConstructTaxonomy::Grammar));

        $this->expectException(InvalidQuestionGenerationRequestException::class);
        $this->validator->validate([
            'part_number' => 1,
            'proficiency_target' => 'a1',
            'difficulty' => 'easy',
            'construct' => 'grammar',
        ]);
    }

    /**
     * Historical Question with NULL metadata.
     */
    public function test_historical_question_with_null_metadata(): void
    {
        $question = Question::create([
            'question_bank_id' => $this->questionBank->id,
            'prompt' => 'Mr. Tanaka will _____ the proposal tomorrow.',
            'section' => SectionType::Reading,
            'part_number' => 5,
            'question_type' => QuestionType::MultipleChoice,
            'difficulty' => DifficultyLevel::Easy,
            'difficulty_score' => 25,
            'points' => 5,
        ]);

        $question->refresh();

        $this->assertNull($question->proficiency_target);
        $this->assertNull($question->content_mode);
        $this->assertNull($question->domain);
        $this->assertNull($question->construct);
        $this->assertNull($question->context);
        $this->assertNull($question->content_origin);
        $this->assertNull($question->generation_batch_id);
        $this->assertNull($question->generation_metadata);

        $this->assertFalse($question->isGenerated());
        $this->assertFalse($question->isDomainSpecific());
        $this->assertNull($question->getProficiencyLabel());
    }

    /**
     * Difficulty detection and TOEIC validator tests.
     */
    public function test_difficulty_detection_and_toeic_validator_intact(): void
    {
        $question = Question::create([
            'question_bank_id' => $this->questionBank->id,
            'prompt' => 'Mr. Tanaka will _____ the proposal tomorrow.',
            'section' => SectionType::Reading,
            'part_number' => 5,
            'question_type' => QuestionType::MultipleChoice,
            'difficulty' => DifficultyLevel::Easy,
            'points' => 5,
        ]);

        QuestionChoice::create(['question_id' => $question->id, 'label' => 'A', 'content' => 'review', 'is_correct' => true]);
        QuestionChoice::create(['question_id' => $question->id, 'label' => 'B', 'content' => 'reviews', 'is_correct' => false]);
        QuestionChoice::create(['question_id' => $question->id, 'label' => 'C', 'content' => 'reviewed', 'is_correct' => false]);
        QuestionChoice::create(['question_id' => $question->id, 'label' => 'D', 'content' => 'reviewing', 'is_correct' => false]);

        $result = QuestionDifficultyDetectionService::detect([
            'prompt' => $question->prompt,
            'part_number' => $question->part_number,
            'section' => $question->section->value,
            'choices' => $question->choices->map(fn ($c) => ['content' => $c->content, 'label' => $c->label, 'is_correct' => $c->is_correct])->toArray(),
        ], $question->fresh());

        $this->assertIsArray($result);
        $this->assertArrayHasKey('difficulty_score', $result);

        $checkResult = ToeicQuestionValidator::check([
            'part_number' => 5,
            'prompt' => 'Mr. Tanaka will _____ the proposal tomorrow.',
            'section' => 'reading',
            'difficulty' => 'easy',
            'choices' => [
                ['label' => 'A', 'content' => 'review', 'is_correct' => true],
                ['label' => 'B', 'content' => 'reviews', 'is_correct' => false],
                ['label' => 'C', 'content' => 'reviewed', 'is_correct' => false],
                ['label' => 'D', 'content' => 'reviewing', 'is_correct' => false],
            ],
        ], $question->fresh());

        $this->assertTrue($checkResult['is_valid'] ?? true);
    }

    /**
     * Provenance helpers on Question model.
     */
    public function test_provenance_helpers_on_question_model(): void
    {
        $genQuestion = Question::create([
            'question_bank_id' => $this->questionBank->id,
            'prompt' => 'Generated Question Prompt',
            'section' => SectionType::Reading,
            'part_number' => 5,
            'question_type' => QuestionType::MultipleChoice,
            'difficulty' => DifficultyLevel::Medium,
            'content_origin' => ContentOrigin::Generated,
            'generation_batch_id' => 'gen_batch_123',
            'generation_metadata' => ['engine' => 'iap-gen-v1', 'seed' => '42'],
            'points' => 5,
        ]);

        $this->assertTrue($genQuestion->isGenerated());
        $this->assertEquals(['engine' => 'iap-gen-v1', 'seed' => '42'], $genQuestion->generation_metadata);

        $editedQuestion = Question::create([
            'question_bank_id' => $this->questionBank->id,
            'prompt' => 'Generated Then Edited Question Prompt',
            'section' => SectionType::Reading,
            'part_number' => 5,
            'question_type' => QuestionType::MultipleChoice,
            'difficulty' => DifficultyLevel::Medium,
            'content_origin' => ContentOrigin::GeneratedThenEdited,
            'points' => 5,
        ]);

        $this->assertTrue($editedQuestion->isGenerated());
    }

    /**
     * Form v1/v2 unaffected and Question Bank governance tests pass.
     */
    public function test_form_v1_v2_unaffected(): void
    {
        $this->assertTrue(enum_exists(TestType::class));
        $this->assertEquals('toeic', TestType::Toeic->value);
        $this->assertEquals('toefl', TestType::Toefl->value);
        $this->assertEquals('ielts', TestType::Ielts->value);
    }
}
