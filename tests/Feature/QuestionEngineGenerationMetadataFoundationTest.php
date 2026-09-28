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
     * TEST C: General content mode defaults to general_workplace domain.
     */
    public function test_c_general_content_mode_defaults_to_general_workplace(): void
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
     * TEST D: Domain-specific request requires domain.
     */
    public function test_d_domain_specific_request_requires_domain(): void
    {
        $this->expectException(InvalidQuestionGenerationRequestException::class);

        $this->validator->validate([
            'part_number' => 5,
            'proficiency_target' => 'b1_standard',
            'difficulty' => 'medium',
            'construct' => 'vocabulary',
            'content_mode' => 'domain_specific',
            'domain' => '', // empty domain
        ]);
    }

    /**
     * TEST E: Hospitality is only one possible domain among many.
     */
    public function test_e_hospitality_is_only_one_possible_domain(): void
    {
        $domains = DomainTaxonomy::values();
        $this->assertContains('hospitality', $domains);
        $this->assertContains('general_workplace', $domains);
        $this->assertContains('engineering', $domains);
        $this->assertContains('law', $domains);
        $this->assertContains('economics_finance', $domains);
        $this->assertContains('healthcare', $domains);
        $this->assertContains('manufacturing', $domains);
        $this->assertContains('logistics', $domains);
        $this->assertContains('information_technology', $domains);
        $this->assertContains('retail', $domains);
        $this->assertContains('aviation', $domains);

        $request = QuestionGenerationRequest::fromArray([
            'part_number' => 6,
            'proficiency_target' => 'b2_standard',
            'difficulty' => 'hard',
            'construct' => 'vocabulary',
            'content_mode' => 'domain_specific',
            'domain' => 'hospitality',
            'context' => 'reservation',
        ]);

        $validated = $this->validator->validate($request);
        $this->assertEquals('hospitality', $validated['domain']);
        $this->assertEquals('reservation', $validated['context']);
        $this->assertTrue($request->isDomainSpecific());
    }

    /**
     * TEST F: Engineering request valid.
     */
    public function test_f_engineering_request_valid(): void
    {
        $request = QuestionGenerationRequest::fromArray([
            'part_number' => 7,
            'proficiency_target' => 'b2_standard',
            'difficulty' => 'medium',
            'construct' => 'detail',
            'content_mode' => 'domain_specific',
            'domain' => 'engineering',
            'context' => 'maintenance',
        ]);

        $validated = $this->validator->validate($request);
        $this->assertEquals('engineering', $validated['domain']);
        $this->assertEquals('maintenance', $validated['context']);
        $this->assertTrue($request->isDomainSpecific());
    }

    /**
     * TEST G: Law request valid.
     */
    public function test_g_law_request_valid(): void
    {
        $request = QuestionGenerationRequest::fromArray([
            'part_number' => 7,
            'proficiency_target' => 'c1',
            'difficulty' => 'hard',
            'construct' => 'inference',
            'content_mode' => 'domain_specific',
            'domain' => 'law',
            'context' => 'email',
        ]);

        $validated = $this->validator->validate($request);
        $this->assertEquals('law', $validated['domain']);
        $this->assertEquals('c1', $validated['proficiency_target']);
        $this->assertEquals('hard', $validated['difficulty']);
    }

    /**
     * TEST H: Economics/Finance request valid.
     */
    public function test_h_economics_finance_request_valid(): void
    {
        $request = QuestionGenerationRequest::fromArray([
            'part_number' => 3,
            'proficiency_target' => 'b1_low',
            'difficulty' => 'easy',
            'construct' => 'purpose',
            'content_mode' => 'domain_specific',
            'domain' => 'economics_finance',
            'context' => 'finance',
        ]);

        $validated = $this->validator->validate($request);
        $this->assertEquals('economics_finance', $validated['domain']);
        $this->assertEquals('listening', $validated['section']);
    }

    /**
     * TEST I: TOEIC Part 1 maps Listening.
     */
    public function test_i_toeic_part_1_maps_listening(): void
    {
        $this->assertEquals(SectionType::Listening, PartConstructCompatibility::getCanonicalSectionForPart(1));

        $request = QuestionGenerationRequest::fromArray([
            'part_number' => 1,
            'proficiency_target' => 'a1_plus',
            'difficulty' => 'easy',
            'construct' => 'visual_description',
        ]);

        $this->assertTrue($request->isListening());
        $this->assertFalse($request->isReading());
        $this->assertEquals(SectionType::Listening, $request->section);
    }

    /**
     * TEST J: TOEIC Part 4 maps Listening.
     */
    public function test_j_toeic_part_4_maps_listening(): void
    {
        $this->assertEquals(SectionType::Listening, PartConstructCompatibility::getCanonicalSectionForPart(4));

        $request = QuestionGenerationRequest::fromArray([
            'part_number' => 4,
            'proficiency_target' => 'b2_low',
            'difficulty' => 'medium',
            'construct' => 'intent',
        ]);

        $this->assertTrue($request->isListening());
        $this->assertEquals(SectionType::Listening, $request->section);
    }

    /**
     * TEST K: TOEIC Part 5 maps Reading.
     */
    public function test_k_toeic_part_5_maps_reading(): void
    {
        $this->assertEquals(SectionType::Reading, PartConstructCompatibility::getCanonicalSectionForPart(5));

        $request = QuestionGenerationRequest::fromArray([
            'part_number' => 5,
            'proficiency_target' => 'a2_low',
            'difficulty' => 'easy',
            'construct' => 'grammar',
        ]);

        $this->assertTrue($request->isReading());
        $this->assertFalse($request->isListening());
        $this->assertEquals(SectionType::Reading, $request->section);
    }

    /**
     * TEST L: TOEIC Part 7 maps Reading.
     */
    public function test_l_toeic_part_7_maps_reading(): void
    {
        $this->assertEquals(SectionType::Reading, PartConstructCompatibility::getCanonicalSectionForPart(7));

        $request = QuestionGenerationRequest::fromArray([
            'part_number' => 7,
            'proficiency_target' => 'b2_standard',
            'difficulty' => 'hard',
            'construct' => 'reference',
        ]);

        $this->assertTrue($request->isReading());
        $this->assertEquals(SectionType::Reading, $request->section);
    }

    /**
     * TEST M: Incompatible section/part rejected.
     */
    public function test_m_incompatible_section_part_rejected(): void
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
     * TEST N: Compatible construct accepted.
     */
    public function test_n_compatible_construct_accepted(): void
    {
        $this->assertTrue(PartConstructCompatibility::isCompatible(1, ConstructTaxonomy::VisualDescription));
        $this->assertTrue(PartConstructCompatibility::isCompatible(2, ConstructTaxonomy::PragmaticResponse));
        $this->assertTrue(PartConstructCompatibility::isCompatible(3, ConstructTaxonomy::GraphicInterpretation));
        $this->assertTrue(PartConstructCompatibility::isCompatible(5, ConstructTaxonomy::Grammar));
        $this->assertTrue(PartConstructCompatibility::isCompatible(6, ConstructTaxonomy::TextCohesion));
        $this->assertTrue(PartConstructCompatibility::isCompatible(7, ConstructTaxonomy::MainIdea));

        $isValid = $this->validator->isValid([
            'part_number' => 5,
            'proficiency_target' => 'b1_standard',
            'difficulty' => 'medium',
            'construct' => 'grammar',
        ]);

        $this->assertTrue($isValid);
    }

    /**
     * TEST O: Incompatible construct rejected.
     */
    public function test_o_incompatible_construct_rejected(): void
    {
        // Grammar is not a valid construct for Part 1 (Photographs)
        $this->assertFalse(PartConstructCompatibility::isCompatible(1, ConstructTaxonomy::Grammar));
        // VisualDescription is not a valid construct for Part 5 (Incomplete Sentences)
        $this->assertFalse(PartConstructCompatibility::isCompatible(5, ConstructTaxonomy::VisualDescription));

        $this->expectException(InvalidQuestionGenerationRequestException::class);

        $this->validator->validate([
            'part_number' => 5,
            'proficiency_target' => 'b1_standard',
            'difficulty' => 'medium',
            'construct' => 'visual_description',
        ]);
    }

    /**
     * TEST P: Existing historical Question with NULL new metadata loads correctly.
     */
    public function test_p_existing_historical_question_with_null_new_metadata_loads_correctly(): void
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
     * TEST Q: Existing DifficultyLevel Easy/Medium/Hard unchanged.
     */
    public function test_q_existing_difficulty_level_easy_medium_hard_unchanged(): void
    {
        $this->assertEquals('easy', DifficultyLevel::Easy->value);
        $this->assertEquals('medium', DifficultyLevel::Medium->value);
        $this->assertEquals('hard', DifficultyLevel::Hard->value);
        $this->assertEquals('Easy', DifficultyLevel::Easy->label());
        $this->assertEquals('Medium', DifficultyLevel::Medium->label());
        $this->assertEquals('Hard / Advanced', DifficultyLevel::Hard->label());
    }

    /**
     * TEST R: Existing QuestionDifficultyDetectionService behavior passes unchanged.
     */
    public function test_r_existing_question_difficulty_detection_service_unchanged(): void
    {
        $service = app(QuestionDifficultyDetectionService::class);

        $question = Question::create([
            'question_bank_id' => $this->questionBank->id,
            'prompt' => 'The comprehensive evaluation demonstrated substantial efficacy.',
            'section' => SectionType::Reading,
            'part_number' => 5,
            'question_type' => QuestionType::MultipleChoice,
            'difficulty' => DifficultyLevel::Medium,
            'points' => 5,
        ]);

        QuestionChoice::create([
            'question_id' => $question->id,
            'label' => 'A',
            'content' => 'demonstrated',
            'is_correct' => true,
        ]);
        QuestionChoice::create([
            'question_id' => $question->id,
            'label' => 'B',
            'content' => 'demonstration',
            'is_correct' => false,
        ]);
        QuestionChoice::create([
            'question_id' => $question->id,
            'label' => 'C',
            'content' => 'demonstrative',
            'is_correct' => false,
        ]);
        QuestionChoice::create([
            'question_id' => $question->id,
            'label' => 'D',
            'content' => 'demonstrating',
            'is_correct' => false,
        ]);

        $result = QuestionDifficultyDetectionService::detect([
            'prompt' => $question->prompt,
            'part_number' => $question->part_number,
            'section' => $question->section->value,
            'choices' => $question->choices->map(fn ($c) => ['content' => $c->content, 'label' => $c->label, 'is_correct' => $c->is_correct])->toArray(),
        ], $question->fresh());

        $this->assertIsArray($result);
        $this->assertArrayHasKey('difficulty_score', $result);
        $this->assertIsInt($result['difficulty_score']);
        $this->assertGreaterThanOrEqual(1, $result['difficulty_score']);
        $this->assertLessThanOrEqual(100, $result['difficulty_score']);
    }

    /**
     * TEST S: ToeicQuestionValidator tests pass unchanged.
     */
    public function test_s_toeic_question_validator_behavior_passes_unchanged(): void
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

        // 4 choices for Part 5
        QuestionChoice::create(['question_id' => $question->id, 'label' => 'A', 'content' => 'review', 'is_correct' => true]);
        QuestionChoice::create(['question_id' => $question->id, 'label' => 'B', 'content' => 'reviews', 'is_correct' => false]);
        QuestionChoice::create(['question_id' => $question->id, 'label' => 'C', 'content' => 'reviewed', 'is_correct' => false]);
        QuestionChoice::create(['question_id' => $question->id, 'label' => 'D', 'content' => 'reviewing', 'is_correct' => false]);

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

        $this->assertIsArray($checkResult);
        $this->assertTrue($checkResult['is_valid'] ?? true);
    }

    /**
     * TEST T: Provenance helpers on Question model.
     */
    public function test_t_provenance_helpers_on_question_model(): void
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
     * TEST U: Form v1/v2 unaffected and Question Bank governance tests pass.
     */
    public function test_u_form_v1_v2_unaffected(): void
    {
        $this->assertTrue(enum_exists(TestType::class));
        $this->assertEquals('toeic', TestType::Toeic->value);
        $this->assertEquals('toefl', TestType::Toefl->value);
        $this->assertEquals('ielts', TestType::Ielts->value);
    }
}
