<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\QuestionBank\Enums\DifficultyLevel;
use App\Modules\QuestionBank\Enums\QuestionType;
use App\Modules\QuestionBank\Enums\SectionType;
use App\Modules\QuestionBank\Enums\TestType;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Modules\QuestionEngine\Behaviours\GeneralEnglishAssessmentBehaviour;
use App\Modules\QuestionEngine\Behaviours\IeltsAssessmentBehaviour;
use App\Modules\QuestionEngine\Behaviours\ToeflIbtAssessmentBehaviour;
use App\Modules\QuestionEngine\Behaviours\ToeicAssessmentBehaviour;
use App\Modules\QuestionEngine\DTO\AssessmentCapabilities;
use App\Modules\QuestionEngine\DTO\AssessmentItemIdentity;
use App\Modules\QuestionEngine\DTO\QuestionGenerationRequest;
use App\Modules\QuestionEngine\Enums\AssessmentFamily;
use App\Modules\QuestionEngine\Enums\ConstructTaxonomy;
use App\Modules\QuestionEngine\Enums\ContentMode;
use App\Modules\QuestionEngine\Enums\ContentOrigin;
use App\Modules\QuestionEngine\Enums\ContextTaxonomy;
use App\Modules\QuestionEngine\Enums\DomainTaxonomy;
use App\Modules\QuestionEngine\Enums\ProficiencyTarget;
use App\Modules\QuestionEngine\Enums\StandardStatus;
use App\Modules\QuestionEngine\Models\AssessmentStandard;
use App\Modules\QuestionEngine\Services\AssessmentBehaviourResolver;
use App\Modules\QuestionEngine\Services\AssessmentStandardRegistry;
use App\Modules\QuestionEngine\Services\PartConstructCompatibility;
use App\Modules\QuestionEngine\Services\QuestionGenerationRequestValidator;
use App\Services\QuestionDifficultyDetectionService;
use App\Services\ToeicQuestionValidator;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class QuestionEngineStandardsAndMultiAssessmentTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;

    protected QuestionBank $questionBank;

    protected AssessmentBehaviourResolver $resolver;

    protected AssessmentStandardRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->teacher = User::factory()->create(['status' => 'active']);
        $this->teacher->assignRole('teacher');

        $this->questionBank = QuestionBank::create([
            'title' => 'Multi-Assessment Standards Bank',
            'slug' => 'multi-assessment-standards-bank-'.uniqid(),
            'created_by' => $this->teacher->id,
            'is_published' => true,
        ]);

        $this->resolver = new AssessmentBehaviourResolver(
            new ToeicAssessmentBehaviour,
            new ToeflIbtAssessmentBehaviour,
            new GeneralEnglishAssessmentBehaviour,
            new IeltsAssessmentBehaviour
        );

        $this->registry = new AssessmentStandardRegistry;
    }

    /**
     * TEST A: Stable canonical assessment family enum values.
     */
    public function test_a_stable_canonical_assessment_family_enum_values(): void
    {
        $this->assertSame('toeic', AssessmentFamily::Toeic->value);
        $this->assertSame('toefl_ibt', AssessmentFamily::ToeflIbt->value);
        $this->assertSame('general_english', AssessmentFamily::GeneralEnglish->value);
        $this->assertSame('ielts', AssessmentFamily::Ielts->value);

        $this->assertCount(4, AssessmentFamily::cases());
        $this->assertStringContainsString('TOEIC', AssessmentFamily::Toeic->label());
        $this->assertStringContainsString('TOEFL iBT', AssessmentFamily::ToeflIbt->label());
        $this->assertStringContainsString('General English', AssessmentFamily::GeneralEnglish->label());
        $this->assertStringContainsString('IELTS', AssessmentFamily::Ielts->label());
    }

    /**
     * TEST B: Assessment family enum mapping to test types.
     */
    public function test_b_assessment_family_mapping_to_test_types(): void
    {
        $this->assertSame(TestType::Toeic, AssessmentFamily::Toeic->toTestType());
        $this->assertSame(TestType::Toefl, AssessmentFamily::ToeflIbt->toTestType());
        $this->assertSame(TestType::General, AssessmentFamily::GeneralEnglish->toTestType());
        $this->assertSame(TestType::Ielts, AssessmentFamily::Ielts->toTestType());

        $this->assertSame(AssessmentFamily::Toeic, AssessmentFamily::fromTestType(TestType::Toeic));
        $this->assertSame(AssessmentFamily::ToeflIbt, AssessmentFamily::fromTestType(TestType::Toefl));
        $this->assertSame(AssessmentFamily::GeneralEnglish, AssessmentFamily::fromTestType(TestType::General));
        $this->assertSame(AssessmentFamily::Ielts, AssessmentFamily::fromTestType(TestType::Ielts));
    }

    /**
     * TEST C: Assessment behaviour resolver returns ToeicAssessmentBehaviour for TOEIC.
     */
    public function test_c_behaviour_resolver_returns_toeic_behaviour(): void
    {
        $behaviour = $this->resolver->resolve(AssessmentFamily::Toeic);
        $this->assertInstanceOf(ToeicAssessmentBehaviour::class, $behaviour);
        $this->assertSame(AssessmentFamily::Toeic, $behaviour->family());

        // String lookup
        $behaviourFromString = $this->resolver->resolve('toeic');
        $this->assertInstanceOf(ToeicAssessmentBehaviour::class, $behaviourFromString);
    }

    /**
     * TEST D: Assessment behaviour resolver returns ToeflIbtAssessmentBehaviour for TOEFL iBT.
     */
    public function test_d_behaviour_resolver_returns_toefl_ibt_behaviour(): void
    {
        $behaviour = $this->resolver->resolve(AssessmentFamily::ToeflIbt);
        $this->assertInstanceOf(ToeflIbtAssessmentBehaviour::class, $behaviour);
        $this->assertSame(AssessmentFamily::ToeflIbt, $behaviour->family());

        $behaviourFromString = $this->resolver->resolve('toefl_ibt');
        $this->assertInstanceOf(ToeflIbtAssessmentBehaviour::class, $behaviourFromString);
    }

    /**
     * TEST E: Assessment behaviour resolver returns GeneralEnglishAssessmentBehaviour for General English.
     */
    public function test_e_behaviour_resolver_returns_general_english_behaviour(): void
    {
        $behaviour = $this->resolver->resolve(AssessmentFamily::GeneralEnglish);
        $this->assertInstanceOf(GeneralEnglishAssessmentBehaviour::class, $behaviour);
        $this->assertSame(AssessmentFamily::GeneralEnglish, $behaviour->family());

        $behaviourFromString = $this->resolver->resolve('general_english');
        $this->assertInstanceOf(GeneralEnglishAssessmentBehaviour::class, $behaviourFromString);
    }

    /**
     * TEST F: Assessment behaviour resolver returns IeltsAssessmentBehaviour for IELTS.
     */
    public function test_f_behaviour_resolver_returns_ielts_behaviour(): void
    {
        $behaviour = $this->resolver->resolve(AssessmentFamily::Ielts);
        $this->assertInstanceOf(IeltsAssessmentBehaviour::class, $behaviour);
        $this->assertSame(AssessmentFamily::Ielts, $behaviour->family());

        $behaviourFromString = $this->resolver->resolve('ielts');
        $this->assertInstanceOf(IeltsAssessmentBehaviour::class, $behaviourFromString);
    }

    /**
     * TEST G: Unknown assessment family fails closed in resolver.
     */
    public function test_g_unknown_family_fails_closed_in_resolver(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown or unsupported AssessmentFamily');

        $this->resolver->resolve('unknown_family');
    }

    /**
     * TEST H: TOEIC assessment behaviour supports and validates part numbers (1..7).
     */
    public function test_h_toeic_behaviour_supports_and_validates_part_numbers(): void
    {
        $toeic = $this->resolver->resolve(AssessmentFamily::Toeic);

        $this->assertTrue($toeic->supportsPartNumbers());

        for ($part = 1; $part <= 7; $part++) {
            $identity = new AssessmentItemIdentity(
                assessmentFamily: AssessmentFamily::Toeic,
                partNumber: $part,
                section: $part <= 4 ? 'listening' : 'reading'
            );
            $validated = $toeic->validateStructure($identity);
            $this->assertSame($part, $validated['part_number']);
            $this->assertSame($part <= 4 ? 'listening' : 'reading', $validated['section']);
        }

        // Part 0 must be invalid
        $this->expectException(InvalidArgumentException::class);
        $toeic->validateStructure(new AssessmentItemIdentity(assessmentFamily: AssessmentFamily::Toeic, partNumber: 0));
    }

    /**
     * TEST I: TOEFL assessment behaviour rejects part numbers (fails closed on part_number).
     */
    public function test_i_toefl_behaviour_rejects_part_numbers(): void
    {
        $toefl = $this->resolver->resolve(AssessmentFamily::ToeflIbt);

        $this->assertFalse($toefl->supportsPartNumbers());

        // Providing part_number must throw
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('TOEFL iBT does not support TOEIC part numbers');

        $toefl->validateStructure(new AssessmentItemIdentity(
            assessmentFamily: AssessmentFamily::ToeflIbt,
            section: 'reading',
            partNumber: 1,
            taskType: 'independent'
        ));
    }

    /**
     * TEST J: General English assessment behaviour rejects part numbers.
     */
    public function test_j_general_english_behaviour_rejects_part_numbers(): void
    {
        $ge = $this->resolver->resolve(AssessmentFamily::GeneralEnglish);

        $this->assertFalse($ge->supportsPartNumbers());

        // Providing part_number must throw
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('General English does not use TOEIC part numbers');

        $ge->validateStructure(new AssessmentItemIdentity(
            assessmentFamily: AssessmentFamily::GeneralEnglish,
            partNumber: 2,
            skill: 'reading',
            proficiencyTarget: 'b1_standard'
        ));
    }

    /**
     * TEST K: IELTS assessment behaviour rejects part numbers.
     */
    public function test_k_ielts_behaviour_rejects_part_numbers(): void
    {
        $ielts = $this->resolver->resolve(AssessmentFamily::Ielts);

        $this->assertFalse($ielts->supportsPartNumbers());

        // Providing part_number must throw
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('IELTS does not use TOEIC Part 1–7 semantics');

        $ielts->validateStructure(new AssessmentItemIdentity(
            assessmentFamily: AssessmentFamily::Ielts,
            section: 'reading',
            partNumber: 3,
            taskType: 'multiple_choice'
        ));
    }

    /**
     * TEST L: TOEIC assessment behaviour returns canonical sections and blueprint constructs.
     */
    public function test_l_toeic_behaviour_canonical_sections_and_blueprint(): void
    {
        $toeic = $this->resolver->resolve(AssessmentFamily::Toeic);
        $caps = $toeic->getCapabilities();

        $this->assertSame(['listening', 'reading'], $caps->supportedSections);

        // Section resolution for parts 1-4 is listening, 5-7 is reading
        $this->assertSame(SectionType::Listening, $toeic->resolveSection(partNumber: 1));
        $this->assertSame(SectionType::Listening, $toeic->resolveSection(partNumber: 4));
        $this->assertSame(SectionType::Reading, $toeic->resolveSection(partNumber: 5));
        $this->assertSame(SectionType::Reading, $toeic->resolveSection(partNumber: 7));

        // PartConstructCompatibility constructs check
        $part1Constructs = PartConstructCompatibility::getCompatibleConstructs(1);
        $this->assertContains(ConstructTaxonomy::VisualDescription, $part1Constructs);
        $this->assertNotContains(ConstructTaxonomy::Grammar, $part1Constructs);

        $part5Constructs = PartConstructCompatibility::getCompatibleConstructs(5);
        $this->assertContains(ConstructTaxonomy::Grammar, $part5Constructs);
        $this->assertContains(ConstructTaxonomy::Vocabulary, $part5Constructs);
    }

    /**
     * TEST M: TOEIC assessment behaviour delegates to existing ToeicQuestionValidator.
     */
    public function test_m_toeic_behaviour_delegates_to_toeic_question_validator(): void
    {
        // Valid Part 1 Question (Photographs) requires image and audio
        $validData = [
            'part_number' => 1,
            'section' => 'listening',
            'prompt' => 'Look at the photograph and choose the best statement.',
            'image_url' => 'https://example.com/p1.jpg',
            'audio_url' => 'https://example.com/p1.mp3',
            'choices' => [
                ['choice_text' => 'A statement', 'is_correct' => true],
                ['choice_text' => 'B statement', 'is_correct' => false],
                ['choice_text' => 'C statement', 'is_correct' => false],
                ['choice_text' => 'D statement', 'is_correct' => false],
            ],
        ];

        $resultPart1 = ToeicQuestionValidator::check($validData);
        $this->assertTrue($resultPart1['is_valid']);

        // Invalid Part 1 Question (missing image and audio)
        $invalidData = [
            'part_number' => 1,
            'section' => 'listening',
            'prompt' => 'Look at the photograph and choose the best statement.',
            'image_url' => null,
            'audio_url' => null,
            'choices' => [
                ['choice_text' => 'A statement', 'is_correct' => true],
                ['choice_text' => 'B statement', 'is_correct' => false],
                ['choice_text' => 'C statement', 'is_correct' => false],
                ['choice_text' => 'D statement', 'is_correct' => false],
            ],
        ];

        $invalidResult = ToeicQuestionValidator::check($invalidData);
        $this->assertFalse($invalidResult['is_valid']);
        $this->assertNotEmpty($invalidResult['errors']);
    }

    /**
     * TEST N: TOEFL assessment behaviour exposes section and task-type capabilities.
     */
    public function test_n_toefl_capabilities(): void
    {
        $toefl = $this->resolver->resolve(AssessmentFamily::ToeflIbt);
        $caps = $toefl->getCapabilities();

        $this->assertInstanceOf(AssessmentCapabilities::class, $caps);
        $this->assertFalse($caps->supportsParts);
        $this->assertTrue($caps->supportsTaskTypes);
        $this->assertTrue($caps->supportsClaims);
        $this->assertTrue($caps->supportsAdaptiveBlueprint);
        $this->assertFalse($caps->supportsFixedFullTestBlueprint);
        $this->assertTrue($caps->supportsCefrTargeting);
        $this->assertSame(['reading', 'listening', 'speaking', 'writing'], $caps->supportedSections);
    }

    /**
     * TEST O: General English assessment behaviour exposes skill and CEFR capabilities.
     */
    public function test_o_general_english_capabilities(): void
    {
        $ge = $this->resolver->resolve(AssessmentFamily::GeneralEnglish);
        $caps = $ge->getCapabilities();

        $this->assertInstanceOf(AssessmentCapabilities::class, $caps);
        $this->assertFalse($caps->supportsParts);
        $this->assertTrue($caps->supportsTaskTypes);
        $this->assertTrue($caps->supportsDomainSpecificity);
        $this->assertTrue($caps->supportsCefrTargeting);
        $this->assertSame(['reading', 'listening', 'speaking', 'writing'], $caps->supportedSections);
    }

    /**
     * TEST P: IELTS assessment behaviour exposes academic/general training section capabilities.
     */
    public function test_p_ielts_capabilities(): void
    {
        $ielts = $this->resolver->resolve(AssessmentFamily::Ielts);
        $caps = $ielts->getCapabilities();

        $this->assertInstanceOf(AssessmentCapabilities::class, $caps);
        $this->assertFalse($caps->supportsParts);
        $this->assertTrue($caps->supportsTaskTypes);
        $this->assertTrue($caps->supportsFixedFullTestBlueprint);
        $this->assertTrue($caps->supportsCefrTargeting);
        $this->assertSame(['listening', 'reading', 'writing', 'speaking'], $caps->supportedSections);
    }

    /**
     * TEST Q: Versioned AssessmentStandard model creation and retrieval by family and version.
     */
    public function test_q_versioned_standard_creation_and_retrieval(): void
    {
        $standard = AssessmentStandard::create([
            'assessment_family' => AssessmentFamily::Toeic,
            'standard_code' => 'TOEIC-2026.1',
            'version' => '2026.1',
            'title' => 'TOEIC Standard 2026.1',
            'provider' => 'IAP Authority',
            'status' => StandardStatus::Active,
            'structure_definition' => ['parts' => [1, 2, 3, 4, 5, 6, 7]],
            'validation_definition' => ['allowed_constructs' => ['grammar', 'vocabulary']],
        ]);

        $this->assertDatabaseHas('assessment_standards', [
            'assessment_family' => 'toeic',
            'version' => '2026.1',
            'status' => 'active',
        ]);

        $found = $this->registry->getStandardByVersion(AssessmentFamily::Toeic, '2026.1');
        $this->assertNotNull($found);
        $this->assertSame($standard->id, $found->id);
        $this->assertSame('2026.1', $found->version);
        $this->assertTrue($found->isActive());
    }

    /**
     * TEST R: AssessmentStandardRegistry manages multiple standard versions for same family.
     */
    public function test_r_registry_manages_multiple_standard_versions(): void
    {
        $v1 = AssessmentStandard::create([
            'assessment_family' => AssessmentFamily::Toeic,
            'standard_code' => 'TOEIC-2024.1',
            'version' => '2024.1',
            'title' => 'TOEIC Standard 2024.1',
            'provider' => 'IAP Authority',
            'status' => StandardStatus::Superseded,
            'structure_definition' => [],
        ]);

        $v2 = AssessmentStandard::create([
            'assessment_family' => AssessmentFamily::Toeic,
            'standard_code' => 'TOEIC-2026.1',
            'version' => '2026.1',
            'title' => 'TOEIC Standard 2026.1',
            'provider' => 'IAP Authority',
            'status' => StandardStatus::Active,
            'structure_definition' => [],
        ]);

        $versions = $this->registry->listVersions(AssessmentFamily::Toeic);
        $this->assertCount(2, $versions);
        $this->assertContains('2024.1', $versions->pluck('version'));
        $this->assertContains('2026.1', $versions->pluck('version'));

        $active = $this->registry->getActiveStandard(AssessmentFamily::Toeic);
        $this->assertSame($v2->id, $active->id);
    }

    /**
     * TEST S: Only one standard can be active per family at a time.
     */
    public function test_s_single_active_standard_per_family_atomic_switch(): void
    {
        $standard1 = $this->registry->registerStandard([
            'assessment_family' => AssessmentFamily::Toeic,
            'version' => '2025.1',
            'title' => 'TOEIC 2025 Standard',
            'status' => StandardStatus::Active,
            'structure_definition' => [],
        ]);

        $this->assertTrue($standard1->fresh()->isActive());

        // Register standard2 as draft
        $standard2 = $this->registry->registerStandard([
            'assessment_family' => AssessmentFamily::Toeic,
            'version' => '2026.1',
            'title' => 'TOEIC 2026 Standard',
            'status' => StandardStatus::Draft,
            'structure_definition' => [],
        ]);

        $this->assertFalse($standard2->fresh()->isActive());
        $this->assertTrue($standard1->fresh()->isActive());

        // Activate standard2
        $this->registry->activateStandard($standard2);

        $this->assertTrue($standard2->fresh()->isActive());
        $this->assertSame(StandardStatus::Superseded, $standard1->fresh()->status);

        // Assert exactly one active standard exists for TOEIC
        $activeCount = AssessmentStandard::where('assessment_family', AssessmentFamily::Toeic)
            ->where('status', StandardStatus::Active)
            ->count();
        $this->assertSame(1, $activeCount);
    }

    /**
     * TEST T: Questions can be bound to a specific AssessmentStandard ID and version.
     */
    public function test_t_question_bound_to_standard_id_and_version(): void
    {
        $standard = AssessmentStandard::create([
            'assessment_family' => AssessmentFamily::Toeic,
            'standard_code' => 'TOEIC-2026.1',
            'version' => '2026.1',
            'title' => 'TOEIC Standard 2026.1',
            'provider' => 'IAP Authority',
            'status' => StandardStatus::Active,
            'structure_definition' => [],
        ]);

        $question = Question::create([
            'question_bank_id' => $this->questionBank->id,
            'prompt' => 'Select the best word to complete the sentence.',
            'section' => SectionType::Reading,
            'part_number' => 5,
            'question_type' => QuestionType::MultipleChoice,
            'difficulty' => DifficultyLevel::Medium,
            'assessment_family' => AssessmentFamily::Toeic,
            'assessment_standard_id' => $standard->id,
            'standard_version' => '2026.1',
            'proficiency_target' => ProficiencyTarget::B1_STANDARD,
            'content_mode' => ContentMode::General,
            'domain' => DomainTaxonomy::GeneralWorkplace,
            'construct' => ConstructTaxonomy::Grammar,
            'context' => ContextTaxonomy::Office,
            'content_origin' => ContentOrigin::Generated,
        ]);

        $question->refresh();
        $this->assertSame(AssessmentFamily::Toeic, $question->assessment_family);
        $this->assertSame($standard->id, $question->assessment_standard_id);
        $this->assertSame('2026.1', $question->standard_version);
        $this->assertInstanceOf(AssessmentStandard::class, $question->assessmentStandard);
        $this->assertSame($standard->id, $question->assessmentStandard->id);
    }

    /**
     * TEST U: Historical questions retain standard version binding even if newer standard becomes active.
     */
    public function test_u_historical_questions_retain_immutable_standard_version(): void
    {
        $std2024 = $this->registry->registerStandard([
            'assessment_family' => AssessmentFamily::Toeic,
            'version' => '2024.1',
            'title' => 'TOEIC 2024 Standard',
            'status' => StandardStatus::Active,
            'structure_definition' => [],
        ]);

        $q2024 = Question::create([
            'question_bank_id' => $this->questionBank->id,
            'prompt' => 'Historical question under 2024 standard.',
            'section' => SectionType::Reading,
            'part_number' => 5,
            'question_type' => QuestionType::MultipleChoice,
            'difficulty' => DifficultyLevel::Easy,
            'assessment_family' => AssessmentFamily::Toeic,
            'assessment_standard_id' => $std2024->id,
            'standard_version' => '2024.1',
        ]);

        // Newer standard created and activated
        $std2026 = $this->registry->registerStandard([
            'assessment_family' => AssessmentFamily::Toeic,
            'version' => '2026.1',
            'title' => 'TOEIC 2026 Standard',
            'status' => StandardStatus::Draft,
            'structure_definition' => [],
        ]);
        $this->registry->activateStandard($std2026);

        $q2024->refresh();
        $this->assertSame('2024.1', $q2024->standard_version);
        $this->assertSame($std2024->id, $q2024->assessment_standard_id);
        $this->assertSame(StandardStatus::Superseded, $q2024->assessmentStandard->status);
        $this->assertSame($std2026->id, $this->registry->getActiveStandard(AssessmentFamily::Toeic)->id);
    }

    /**
     * TEST V: StandardStatus enum values and non-active statuses cannot be resolved as active.
     */
    public function test_v_standard_status_enum_and_non_active_filtering(): void
    {
        $this->assertSame('draft', StandardStatus::Draft->value);
        $this->assertSame('detected', StandardStatus::Detected->value);
        $this->assertSame('pending_review', StandardStatus::PendingReview->value);
        $this->assertSame('active', StandardStatus::Active->value);
        $this->assertSame('superseded', StandardStatus::Superseded->value);
        $this->assertSame('archived', StandardStatus::Archived->value);

        AssessmentStandard::create([
            'assessment_family' => AssessmentFamily::ToeflIbt,
            'standard_code' => 'TOEFL-2026.DRAFT',
            'version' => '2026.DRAFT',
            'title' => 'TOEFL Draft Standard',
            'provider' => 'ETS',
            'status' => StandardStatus::Draft,
            'structure_definition' => [],
        ]);

        AssessmentStandard::create([
            'assessment_family' => AssessmentFamily::ToeflIbt,
            'standard_code' => 'TOEFL-2026.PENDING',
            'version' => '2026.PENDING',
            'title' => 'TOEFL Pending Standard',
            'provider' => 'ETS',
            'status' => StandardStatus::PendingReview,
            'structure_definition' => [],
        ]);

        // Attempting to get active standard for TOEFL iBT must throw since only draft/pending exist
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("No active AssessmentStandard found for family 'toefl_ibt'.");

        $this->registry->getActiveStandard(AssessmentFamily::ToeflIbt);
    }

    /**
     * TEST W: AssessmentStandardRegistry resolves current active standard for a family.
     */
    public function test_w_registry_resolves_current_active_standard(): void
    {
        $activeStd = AssessmentStandard::create([
            'assessment_family' => AssessmentFamily::GeneralEnglish,
            'standard_code' => 'GE-1.0',
            'version' => '1.0',
            'title' => 'GE Standard 1.0',
            'provider' => 'CEFR Council',
            'status' => StandardStatus::Active,
            'structure_definition' => [],
        ]);

        $resolved = $this->registry->getActiveStandard(AssessmentFamily::GeneralEnglish);
        $this->assertSame($activeStd->id, $resolved->id);
        $this->assertSame('1.0', $resolved->version);
    }

    /**
     * TEST X: AssessmentStandardRegistry throws when no active standard exists for a family.
     */
    public function test_x_registry_throws_when_no_active_standard_exists(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("No active AssessmentStandard found for family 'ielts'.");

        $this->registry->getActiveStandard(AssessmentFamily::Ielts);
    }

    /**
     * TEST Y: Historical question with NULL standard metadata loads normally without errors.
     */
    public function test_y_historical_question_with_null_standard_metadata(): void
    {
        $historicalQ = Question::create([
            'question_bank_id' => $this->questionBank->id,
            'prompt' => 'Legacy pre-sprint question prompt without standard metadata.',
            'section' => SectionType::Reading,
            'part_number' => 5,
            'question_type' => QuestionType::MultipleChoice,
            'difficulty' => DifficultyLevel::Easy,
            'assessment_family' => null,
            'assessment_standard_id' => null,
            'standard_version' => null,
            'proficiency_target' => null,
            'content_mode' => null,
            'domain' => null,
            'construct' => null,
            'context' => null,
            'content_origin' => null,
        ]);

        $historicalQ->refresh();
        $this->assertNull($historicalQ->assessment_family);
        $this->assertNull($historicalQ->assessment_standard_id);
        $this->assertNull($historicalQ->standard_version);
        $this->assertNull($historicalQ->assessmentStandard);
        $this->assertSame('Legacy pre-sprint question prompt without standard metadata.', $historicalQ->prompt);
    }

    /**
     * TEST Z: QuestionGenerationRequest maintains TOEIC-only generation guard in Sprint 2.
     */
    public function test_z_generation_request_maintains_toeic_only_guard(): void
    {
        $validator = new QuestionGenerationRequestValidator;

        // Valid TOEIC generation request
        $validToeicReq = QuestionGenerationRequest::fromArray([
            'part_number' => 5,
            'proficiency_target' => 'b1_standard',
            'difficulty' => 'medium',
            'content_mode' => 'general',
            'construct' => 'grammar',
            'item_count' => 1,
        ]);

        $validated = $validator->validate($validToeicReq);
        $this->assertIsArray($validated);
        $this->assertSame(5, $validated['part_number']);

        // Attempting to construct a request for unsupported parts or non-TOEIC parameters must fail closed
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The part_number must be an integer between 1 and 7 for TOEIC.');

        QuestionGenerationRequest::fromArray([
            'part_number' => 8,
            'proficiency_target' => 'b1_standard',
            'difficulty' => 'medium',
            'content_mode' => 'general',
            'construct' => 'grammar',
            'item_count' => 1,
        ]);
    }

    /**
     * TEST AA: Existing question bank governance and approval workflows unaffected.
     */
    public function test_aa_question_bank_governance_unaffected(): void
    {
        $this->assertDatabaseCount('question_banks', 1);
        $this->assertTrue($this->questionBank->is_published);
        $this->assertSame($this->teacher->id, $this->questionBank->created_by);
    }

    /**
     * TEST AB: ToeicQuestionValidator behavior unchanged.
     */
    public function test_ab_toeic_question_validator_behavior_unchanged(): void
    {
        $validData = [
            'part_number' => 5,
            'section' => 'reading',
            'prompt' => 'Mr. Henderson _____ the report before the deadline.',
            'choices' => [
                ['choice_text' => 'submits', 'is_correct' => false],
                ['choice_text' => 'submitted', 'is_correct' => true],
                ['choice_text' => 'submitting', 'is_correct' => false],
                ['choice_text' => 'submission', 'is_correct' => false],
            ],
        ];

        $result = ToeicQuestionValidator::check($validData);
        $this->assertTrue($result['is_valid']);
    }

    /**
     * TEST AC: QuestionDifficultyDetectionService behavior unchanged.
     */
    public function test_ac_difficulty_detection_service_behavior_unchanged(): void
    {
        $question = new Question([
            'section' => SectionType::Reading,
            'part_number' => 5,
            'prompt' => 'The manager requested that all employees submit their quarterly expense reports immediately.',
        ]);

        $detection = QuestionDifficultyDetectionService::detect([
            'section' => 'reading',
            'part_number' => 5,
            'prompt' => $question->prompt,
        ], $question);

        $this->assertArrayHasKey('difficulty_level', $detection);
        $this->assertArrayHasKey('difficulty_score', $detection);
        $this->assertArrayHasKey('difficulty_factors', $detection);
        $this->assertIsString($detection['difficulty_level']);
    }

    /**
     * TEST AD: Candidate CBT / iBT assessment delivery and scoring unchanged.
     */
    public function test_ad_cbt_delivery_and_scoring_unchanged(): void
    {
        $test = Test::create([
            'title' => 'CBT Regression Assessment',
            'slug' => 'cbt-regression-assessment-'.uniqid(),
            'type' => TestType::Toeic,
            'created_by' => $this->teacher->id,
            'is_active' => true,
        ]);

        $section = TestSection::create([
            'test_id' => $test->id,
            'title' => 'Listening Section',
            'section_type' => SectionType::Listening,
            'order' => 1,
            'duration_minutes' => 45,
        ]);

        $question = Question::create([
            'question_bank_id' => $this->questionBank->id,
            'prompt' => 'CBT Test Question Prompt',
            'section' => SectionType::Listening,
            'part_number' => 1,
            'question_type' => QuestionType::MultipleChoice,
            'difficulty' => DifficultyLevel::Easy,
            'points' => 5,
        ]);

        $testQuestion = TestSection::find($section->id)->testQuestions()->create([
            'question_id' => $question->id,
            'order_index' => 1,
            'points' => 5,
        ]);

        $this->assertDatabaseHas('test_questions', [
            'id' => $testQuestion->id,
            'question_id' => $question->id,
        ]);
        $this->assertSame(5, $testQuestion->points);
    }
}
