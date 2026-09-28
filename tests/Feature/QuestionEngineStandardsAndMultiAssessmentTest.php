<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Assessment\Models\Test;
use App\Modules\QuestionBank\Enums\DifficultyLevel;
use App\Modules\QuestionBank\Enums\QuestionType;
use App\Modules\QuestionBank\Enums\SectionType;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Modules\QuestionEngine\Behaviours\GeneralEnglishAssessmentBehaviour;
use App\Modules\QuestionEngine\Behaviours\IeltsAssessmentBehaviour;
use App\Modules\QuestionEngine\Behaviours\ToeflIbtAssessmentBehaviour;
use App\Modules\QuestionEngine\Behaviours\ToeicAssessmentBehaviour;
use App\Modules\QuestionEngine\DTO\AssessmentItemIdentity;
use App\Modules\QuestionEngine\DTO\QuestionGenerationRequest;
use App\Modules\QuestionEngine\Enums\AssessmentFamily;
use App\Modules\QuestionEngine\Enums\StandardStatus;
use App\Modules\QuestionEngine\Models\AssessmentStandard;
use App\Modules\QuestionEngine\Services\AssessmentBehaviourResolver;
use App\Modules\QuestionEngine\Services\AssessmentStandardRegistry;
use App\Modules\QuestionEngine\Services\QuestionGenerationRequestValidator;
use App\Services\ToeicQuestionValidator;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
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
     * TEST A: Question with valid assessment_standard_id persists.
     */
    public function test_a_question_with_valid_assessment_standard_id_persists(): void
    {
        $standard = AssessmentStandard::create([
            'assessment_family' => AssessmentFamily::Toeic,
            'standard_code' => 'TOEIC-2026.1',
            'version' => '2026.1',
            'title' => 'TOEIC Standard 2026.1',
            'provider' => 'IAP Authority',
            'status' => StandardStatus::Active,
            'structure_definition' => ['parts' => [1, 2, 3, 4, 5, 6, 7]],
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
        ]);

        $this->assertDatabaseHas('questions', [
            'id' => $question->id,
            'assessment_standard_id' => $standard->id,
            'standard_version' => '2026.1',
        ]);
        $this->assertSame($standard->id, $question->fresh()->assessmentStandard->id);
    }

    /**
     * TEST B: Question with NULL assessment_standard_id remains valid.
     */
    public function test_b_question_with_null_assessment_standard_id_remains_valid(): void
    {
        $question = Question::create([
            'question_bank_id' => $this->questionBank->id,
            'prompt' => 'Legacy question without standard binding.',
            'section' => SectionType::Reading,
            'part_number' => 5,
            'question_type' => QuestionType::MultipleChoice,
            'difficulty' => DifficultyLevel::Easy,
            'assessment_family' => null,
            'assessment_standard_id' => null,
            'standard_version' => null,
        ]);

        $this->assertDatabaseHas('questions', [
            'id' => $question->id,
            'assessment_standard_id' => null,
        ]);
        $this->assertNull($question->fresh()->assessment_standard_id);
        $this->assertNull($question->fresh()->assessmentStandard);
    }

    /**
     * TEST C: Non-existent assessment_standard_id is rejected by DB/reference integrity.
     */
    public function test_c_non_existent_assessment_standard_id_is_rejected(): void
    {
        // Enforce SQLite foreign keys if on sqlite
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = ON;');
        }

        $this->expectException(QueryException::class);

        Question::create([
            'question_bank_id' => $this->questionBank->id,
            'prompt' => 'Question referencing non-existent standard.',
            'section' => SectionType::Reading,
            'part_number' => 5,
            'question_type' => QuestionType::MultipleChoice,
            'difficulty' => DifficultyLevel::Easy,
            'assessment_family' => AssessmentFamily::Toeic,
            'assessment_standard_id' => '01nonexistentstandardid0000000',
            'standard_version' => '2026.1',
        ]);
    }

    /**
     * TEST D: Deleting referenced standard does not cascade-delete Question.
     */
    public function test_d_deleting_referenced_standard_does_not_cascade_delete_question(): void
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
            'prompt' => 'Question bound to standard before deletion test.',
            'section' => SectionType::Reading,
            'part_number' => 5,
            'question_type' => QuestionType::MultipleChoice,
            'difficulty' => DifficultyLevel::Easy,
            'assessment_family' => AssessmentFamily::Toeic,
            'assessment_standard_id' => $standard->id,
            'standard_version' => '2026.1',
        ]);

        // Soft-delete the standard
        $standard->delete();

        // Question MUST still exist and retain its standard binding reference
        $this->assertDatabaseHas('questions', [
            'id' => $question->id,
            'assessment_standard_id' => $standard->id,
        ]);
        $this->assertNotNull(Question::find($question->id));
    }

    /**
     * TEST E: Sequential activation leaves exactly one active.
     */
    public function test_e_sequential_activation_leaves_exactly_one_active(): void
    {
        $std1 = $this->registry->registerStandard([
            'assessment_family' => AssessmentFamily::Toeic,
            'version' => '2024.1',
            'title' => 'TOEIC 2024',
            'status' => StandardStatus::Draft,
            'structure_definition' => [],
        ]);

        $std2 = $this->registry->registerStandard([
            'assessment_family' => AssessmentFamily::Toeic,
            'version' => '2025.1',
            'title' => 'TOEIC 2025',
            'status' => StandardStatus::Draft,
            'structure_definition' => [],
        ]);

        $std3 = $this->registry->registerStandard([
            'assessment_family' => AssessmentFamily::Toeic,
            'version' => '2026.1',
            'title' => 'TOEIC 2026',
            'status' => StandardStatus::Draft,
            'structure_definition' => [],
        ]);

        $this->registry->activateStandard($std1);
        $this->assertTrue($std1->fresh()->isActive());

        $this->registry->activateStandard($std2);
        $this->assertTrue($std2->fresh()->isActive());
        $this->assertSame(StandardStatus::Superseded, $std1->fresh()->status);

        $this->registry->activateStandard($std3);
        $this->assertTrue($std3->fresh()->isActive());
        $this->assertSame(StandardStatus::Superseded, $std2->fresh()->status);

        $activeCount = AssessmentStandard::where('assessment_family', AssessmentFamily::Toeic)
            ->where('status', StandardStatus::Active)
            ->count();
        $this->assertSame(1, $activeCount);
        $this->assertSame($std3->id, $this->registry->getActiveStandard(AssessmentFamily::Toeic)->id);
    }

    /**
     * TEST F: Repeated activation of same target is idempotent.
     */
    public function test_f_repeated_activation_of_same_target_is_idempotent(): void
    {
        $standard = $this->registry->registerStandard([
            'assessment_family' => AssessmentFamily::Toeic,
            'version' => '2026.1',
            'title' => 'TOEIC 2026',
            'status' => StandardStatus::Draft,
            'structure_definition' => [],
        ]);

        $activatedFirst = $this->registry->activateStandard($standard);
        $this->assertTrue($activatedFirst->isActive());
        $this->assertSame(StandardStatus::Active, $activatedFirst->status);

        // Repeated activation of already active standard
        $activatedSecond = $this->registry->activateStandard($standard);
        $this->assertTrue($activatedSecond->isActive());
        $this->assertSame(StandardStatus::Active, $activatedSecond->status);
        $this->assertSame($activatedFirst->id, $activatedSecond->id);

        $activeCount = AssessmentStandard::where('assessment_family', AssessmentFamily::Toeic)
            ->where('status', StandardStatus::Active)
            ->count();
        $this->assertSame(1, $activeCount);
    }

    /**
     * TEST G: Concurrent activation attempts cannot leave two active standards.
     */
    public function test_g_concurrent_activation_attempts_cannot_leave_two_active_standards(): void
    {
        $v1 = $this->registry->registerStandard([
            'assessment_family' => AssessmentFamily::Toeic,
            'version' => '2026.A',
            'status' => StandardStatus::Draft,
            'structure_definition' => [],
        ]);

        $v2 = $this->registry->registerStandard([
            'assessment_family' => AssessmentFamily::Toeic,
            'version' => '2026.B',
            'status' => StandardStatus::Draft,
            'structure_definition' => [],
        ]);

        // Simulate concurrent attempts by running activations in rapid sequence within individual transactions
        $results = [];
        DB::transaction(function () use ($v1, &$results) {
            $results[] = $this->registry->activateStandard($v1);
        });

        DB::transaction(function () use ($v2, &$results) {
            $results[] = $this->registry->activateStandard($v2);
        });

        $activeToeic = AssessmentStandard::where('assessment_family', AssessmentFamily::Toeic)
            ->where('status', StandardStatus::Active)
            ->get();

        $this->assertCount(1, $activeToeic);
        $this->assertSame($v2->id, $activeToeic->first()->id);
        $this->assertSame(StandardStatus::Superseded, $v1->fresh()->status);
    }

    /**
     * TEST H: Different families can each have one active standard independently.
     */
    public function test_h_different_families_have_independent_active_standards(): void
    {
        $toeicStd = $this->registry->registerStandard([
            'assessment_family' => AssessmentFamily::Toeic,
            'version' => '2026.1',
            'status' => StandardStatus::Draft,
            'structure_definition' => [],
        ]);

        $toeflStd = $this->registry->registerStandard([
            'assessment_family' => AssessmentFamily::ToeflIbt,
            'version' => '2026.1',
            'status' => StandardStatus::Draft,
            'structure_definition' => [],
        ]);

        $geStd = $this->registry->registerStandard([
            'assessment_family' => AssessmentFamily::GeneralEnglish,
            'version' => '1.0',
            'status' => StandardStatus::Draft,
            'structure_definition' => [],
        ]);

        $ieltsStd = $this->registry->registerStandard([
            'assessment_family' => AssessmentFamily::Ielts,
            'version' => '2026.1',
            'status' => StandardStatus::Draft,
            'structure_definition' => [],
        ]);

        $this->registry->activateStandard($toeicStd);
        $this->registry->activateStandard($toeflStd);
        $this->registry->activateStandard($geStd);
        $this->registry->activateStandard($ieltsStd);

        $this->assertSame($toeicStd->id, $this->registry->getActiveStandard(AssessmentFamily::Toeic)->id);
        $this->assertSame($toeflStd->id, $this->registry->getActiveStandard(AssessmentFamily::ToeflIbt)->id);
        $this->assertSame($geStd->id, $this->registry->getActiveStandard(AssessmentFamily::GeneralEnglish)->id);
        $this->assertSame($ieltsStd->id, $this->registry->getActiveStandard(AssessmentFamily::Ielts)->id);

        $this->assertSame(4, AssessmentStandard::where('status', StandardStatus::Active)->count());
    }

    /**
     * TEST I: Activating Draft succeeds.
     */
    public function test_i_activating_draft_succeeds(): void
    {
        $draft = $this->registry->registerStandard([
            'assessment_family' => AssessmentFamily::Toeic,
            'version' => '2026.DRAFT',
            'status' => StandardStatus::Draft,
            'structure_definition' => [],
        ]);

        $this->assertSame(StandardStatus::Draft, $draft->status);
        $activated = $this->registry->activateStandard($draft);
        $this->assertSame(StandardStatus::Active, $activated->status);
    }

    /**
     * TEST J: Activating Detected succeeds.
     */
    public function test_j_activating_detected_succeeds(): void
    {
        $detected = $this->registry->registerStandard([
            'assessment_family' => AssessmentFamily::ToeflIbt,
            'version' => '2026.DETECTED',
            'status' => StandardStatus::Detected,
            'structure_definition' => [],
        ]);

        $this->assertSame(StandardStatus::Detected, $detected->status);
        $activated = $this->registry->activateStandard($detected);
        $this->assertSame(StandardStatus::Active, $activated->status);
    }

    /**
     * TEST K: Activating PendingReview succeeds.
     */
    public function test_k_activating_pending_review_succeeds(): void
    {
        $pending = $this->registry->registerStandard([
            'assessment_family' => AssessmentFamily::GeneralEnglish,
            'version' => '2.0.PENDING',
            'status' => StandardStatus::PendingReview,
            'structure_definition' => [],
        ]);

        $this->assertSame(StandardStatus::PendingReview, $pending->status);
        $activated = $this->registry->activateStandard($pending);
        $this->assertSame(StandardStatus::Active, $activated->status);
    }

    /**
     * TEST L: Activating Archived through normal activation is rejected.
     */
    public function test_l_activating_archived_is_rejected(): void
    {
        $archived = $this->registry->registerStandard([
            'assessment_family' => AssessmentFamily::Toeic,
            'version' => '2020.ARCHIVED',
            'status' => StandardStatus::Archived,
            'structure_definition' => [],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("AssessmentStandard in status 'archived' cannot be activated");

        $this->registry->activateStandard($archived);
    }

    /**
     * TEST M: Activating Superseded through normal activation is rejected.
     */
    public function test_m_activating_superseded_is_rejected(): void
    {
        $superseded = $this->registry->registerStandard([
            'assessment_family' => AssessmentFamily::Toeic,
            'version' => '2024.SUPERSEDED',
            'status' => StandardStatus::Superseded,
            'structure_definition' => [],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("AssessmentStandard in status 'superseded' cannot be activated");

        $this->registry->activateStandard($superseded);
    }

    /**
     * TEST N: Omitted status defaults Draft.
     */
    public function test_n_omitted_status_defaults_draft(): void
    {
        $std = $this->registry->registerStandard([
            'assessment_family' => AssessmentFamily::Toeic,
            'version' => '2026.DEFAULT_DRAFT',
            'structure_definition' => [],
        ]);

        $this->assertSame(StandardStatus::Draft, $std->status);
    }

    /**
     * TEST O: Valid explicit status accepted.
     */
    public function test_o_valid_explicit_status_accepted(): void
    {
        $stdPending = $this->registry->registerStandard([
            'assessment_family' => AssessmentFamily::Toeic,
            'version' => '2026.PENDING_OK',
            'status' => 'pending_review',
            'structure_definition' => [],
        ]);
        $this->assertSame(StandardStatus::PendingReview, $stdPending->status);

        $stdActive = $this->registry->registerStandard([
            'assessment_family' => AssessmentFamily::Toeic,
            'version' => '2026.ACTIVE_OK',
            'status' => StandardStatus::Active,
            'structure_definition' => [],
        ]);
        $this->assertSame(StandardStatus::Active, $stdActive->status);
    }

    /**
     * TEST P: Invalid explicit status rejected (fails closed).
     */
    public function test_p_invalid_explicit_status_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid status 'activ' for AssessmentStandard.");

        $this->registry->registerStandard([
            'assessment_family' => AssessmentFamily::Toeic,
            'version' => '2026.INVALID',
            'status' => 'activ',
            'structure_definition' => [],
        ]);
    }

    /**
     * TEST Q: AssessmentItemIdentity part_number integer 5 accepted.
     */
    public function test_q_assessment_item_identity_integer_accepted(): void
    {
        $identity = AssessmentItemIdentity::fromArray([
            'assessment_family' => 'toeic',
            'part_number' => 5,
            'section' => 'reading',
        ]);

        $this->assertSame(5, $identity->partNumber);
    }

    /**
     * TEST R: AssessmentItemIdentity string integer "5" accepted.
     */
    public function test_r_assessment_item_identity_string_integer_accepted(): void
    {
        $identity = AssessmentItemIdentity::fromArray([
            'assessment_family' => 'toeic',
            'part_number' => '5',
            'section' => 'reading',
        ]);

        $this->assertSame(5, $identity->partNumber);
    }

    /**
     * TEST S: AssessmentItemIdentity decimal float 5.5 rejected.
     */
    public function test_s_assessment_item_identity_decimal_float_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Part number must be a valid integer, given');

        AssessmentItemIdentity::fromArray([
            'assessment_family' => 'toeic',
            'part_number' => 5.5,
            'section' => 'reading',
        ]);
    }

    /**
     * TEST T: AssessmentItemIdentity decimal string "5.5" rejected.
     */
    public function test_t_assessment_item_identity_decimal_string_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Part number must be a valid integer, given '5.5'.");

        AssessmentItemIdentity::fromArray([
            'assessment_family' => 'toeic',
            'part_number' => '5.5',
            'section' => 'reading',
        ]);
    }

    /**
     * TEST U: Absent part_number remains NULL for TOEFL/GE/IELTS identity.
     */
    public function test_u_absent_part_number_remains_null_for_non_toeic(): void
    {
        $toeflIdentity = AssessmentItemIdentity::fromArray([
            'assessment_family' => 'toefl_ibt',
            'section' => 'reading',
            'task_type' => 'independent',
        ]);
        $this->assertNull($toeflIdentity->partNumber);

        $geIdentity = AssessmentItemIdentity::fromArray([
            'assessment_family' => 'general_english',
            'skill' => 'reading',
            'proficiency_target' => 'b1_standard',
        ]);
        $this->assertNull($geIdentity->partNumber);

        $ieltsIdentity = AssessmentItemIdentity::fromArray([
            'assessment_family' => 'ielts',
            'section' => 'academic_reading',
            'task_type' => 'multiple_choice',
        ]);
        $this->assertNull($ieltsIdentity->partNumber);
    }

    /**
     * TEST V: TOEFL behaviour still rejects explicitly provided TOEIC part.
     */
    public function test_v_toefl_behaviour_rejects_explicit_part(): void
    {
        $toefl = $this->resolver->resolve(AssessmentFamily::ToeflIbt);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('TOEFL iBT does not support TOEIC part numbers');

        $toefl->validateStructure([
            'part_number' => 1,
            'section' => 'reading',
        ]);
    }

    /**
     * TEST W: Historical question standard binding remains immutable.
     */
    public function test_w_historical_question_standard_binding_remains_immutable(): void
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
            'prompt' => 'Historical question bound under 2024 standard.',
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
     * TEST X: Sprint 1 generation contract regression PASS.
     */
    public function test_x_sprint1_generation_contract_regression_pass(): void
    {
        $validator = new QuestionGenerationRequestValidator;

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
        $this->assertSame('general_workplace', $validated['domain']);
    }

    /**
     * TEST Y: ToeicQuestionValidator regression PASS.
     */
    public function test_y_toeic_question_validator_regression_pass(): void
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
     * TEST Z: Question Bank governance regression PASS.
     */
    public function test_z_question_bank_governance_regression_pass(): void
    {
        $this->assertDatabaseCount('question_banks', 1);
        $this->assertTrue($this->questionBank->is_published);
        $this->assertSame($this->teacher->id, $this->questionBank->created_by);
    }
}
