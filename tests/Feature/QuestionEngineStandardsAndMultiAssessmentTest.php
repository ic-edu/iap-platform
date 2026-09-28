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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
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
     * TEST A: Omitted registration status defaults to Draft.
     */
    public function test_a_omitted_registration_status_defaults_to_draft(): void
    {
        $std = $this->registry->registerStandard([
            'assessment_family' => AssessmentFamily::Toeic,
            'version' => '2026.DEFAULT_DRAFT',
            'structure_definition' => [],
        ]);

        $this->assertSame(StandardStatus::Draft, $std->status);
        $this->assertDatabaseHas('assessment_standards', [
            'id' => $std->id,
            'status' => 'draft',
        ]);
    }

    /**
     * TEST B: Draft registration is valid.
     */
    public function test_b_draft_registration_is_valid(): void
    {
        $draft = $this->registry->registerStandard([
            'assessment_family' => AssessmentFamily::Toeic,
            'version' => '2026.DRAFT_OK',
            'status' => 'draft',
            'structure_definition' => [],
        ]);

        $this->assertSame(StandardStatus::Draft, $draft->status);
    }

    /**
     * TEST C: Detected registration is valid.
     */
    public function test_c_detected_registration_is_valid(): void
    {
        $detected = $this->registry->registerStandard([
            'assessment_family' => AssessmentFamily::ToeflIbt,
            'version' => '2026.DETECTED_OK',
            'status' => StandardStatus::Detected,
            'structure_definition' => [],
        ]);

        $this->assertSame(StandardStatus::Detected, $detected->status);
    }

    /**
     * TEST D: PendingReview registration is valid.
     */
    public function test_d_pending_review_registration_is_valid(): void
    {
        $pending = $this->registry->registerStandard([
            'assessment_family' => AssessmentFamily::GeneralEnglish,
            'version' => '2026.PENDING_OK',
            'status' => 'pending_review',
            'structure_definition' => [],
        ]);

        $this->assertSame(StandardStatus::PendingReview, $pending->status);
    }

    /**
     * TEST E: Active registration is rejected via registerStandard.
     */
    public function test_e_active_registration_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Cannot directly register AssessmentStandard in status 'active'. Initial registration permits only draft, detected, or pending_review.");

        $this->registry->registerStandard([
            'assessment_family' => AssessmentFamily::Toeic,
            'version' => '2026.ACTIVE_FORBIDDEN',
            'status' => StandardStatus::Active,
            'structure_definition' => [],
        ]);
    }

    /**
     * TEST F: Superseded registration is rejected via registerStandard.
     */
    public function test_f_superseded_registration_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Cannot directly register AssessmentStandard in status 'superseded'. Initial registration permits only draft, detected, or pending_review.");

        $this->registry->registerStandard([
            'assessment_family' => AssessmentFamily::Toeic,
            'version' => '2026.SUPERSEDED_FORBIDDEN',
            'status' => 'superseded',
            'structure_definition' => [],
        ]);
    }

    /**
     * TEST G: Archived registration is rejected via registerStandard.
     */
    public function test_g_archived_registration_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Cannot directly register AssessmentStandard in status 'archived'. Initial registration permits only draft, detected, or pending_review.");

        $this->registry->registerStandard([
            'assessment_family' => AssessmentFamily::Toeic,
            'version' => '2026.ARCHIVED_FORBIDDEN',
            'status' => 'archived',
            'structure_definition' => [],
        ]);
    }

    /**
     * TEST H: Direct Active bypass cannot create a second active standard.
     */
    public function test_h_direct_active_bypass_cannot_create_second_active(): void
    {
        // 1. Create and activate Standard A legitimately
        $stdA = $this->registry->registerStandard([
            'assessment_family' => AssessmentFamily::Toeic,
            'version' => '2026.A',
            'status' => StandardStatus::Draft,
            'structure_definition' => [],
        ]);
        $this->registry->activateStandard($stdA);
        $this->assertTrue($stdA->fresh()->isActive());

        // 2. Attempt direct registration with status=active for Standard B
        try {
            $this->registry->registerStandard([
                'assessment_family' => AssessmentFamily::Toeic,
                'version' => '2026.B',
                'status' => 'active',
                'structure_definition' => [],
            ]);
            $this->fail('registerStandard with status=active should have thrown InvalidArgumentException.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString("Cannot directly register AssessmentStandard in status 'active'", $e->getMessage());
        }

        // 3. Verify Standard A remains sole ACTIVE and total active count is exactly 1
        $activeStandards = AssessmentStandard::where('assessment_family', AssessmentFamily::Toeic)
            ->where('status', StandardStatus::Active)
            ->get();

        $this->assertCount(1, $activeStandards);
        $this->assertSame($stdA->id, $activeStandards->first()->id);
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
        $this->assertTrue($activated->isActive());
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
        $this->assertTrue($activated->isActive());
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
        $this->assertTrue($activated->isActive());
    }

    /**
     * TEST L: Repeated activation of same target is idempotent.
     */
    public function test_l_activate_already_active_target_is_idempotent(): void
    {
        $standard = $this->registry->registerStandard([
            'assessment_family' => AssessmentFamily::Toeic,
            'version' => '2026.IDEMPOTENT',
            'status' => StandardStatus::Draft,
            'structure_definition' => [],
        ]);

        $first = $this->registry->activateStandard($standard);
        $this->assertTrue($first->isActive());

        $second = $this->registry->activateStandard($standard);
        $this->assertTrue($second->isActive());
        $this->assertSame($first->id, $second->id);

        $activeCount = AssessmentStandard::where('assessment_family', AssessmentFamily::Toeic)
            ->where('status', StandardStatus::Active)
            ->count();
        $this->assertSame(1, $activeCount);
    }

    /**
     * TEST M: Archived and Superseded activation is rejected.
     */
    public function test_m_archived_and_superseded_activation_is_rejected(): void
    {
        // Force create archived and superseded records for testing activation guard
        $archived = AssessmentStandard::create([
            'assessment_family' => AssessmentFamily::Toeic,
            'standard_code' => 'TOEIC-2020.ARCHIVED',
            'version' => '2020.ARCHIVED',
            'title' => 'TOEIC 2020 Archived',
            'provider' => 'IAP',
            'status' => StandardStatus::Archived,
            'structure_definition' => [],
        ]);

        $superseded = AssessmentStandard::create([
            'assessment_family' => AssessmentFamily::Toeic,
            'standard_code' => 'TOEIC-2024.SUPERSEDED',
            'version' => '2024.SUPERSEDED',
            'title' => 'TOEIC 2024 Superseded',
            'provider' => 'IAP',
            'status' => StandardStatus::Superseded,
            'structure_definition' => [],
        ]);

        try {
            $this->registry->activateStandard($archived);
            $this->fail('Activating archived should throw InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString("AssessmentStandard in status 'archived' cannot be activated", $e->getMessage());
        }

        try {
            $this->registry->activateStandard($superseded);
            $this->fail('Activating superseded should throw InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString("AssessmentStandard in status 'superseded' cannot be activated", $e->getMessage());
        }
    }

    /**
     * TEST N: Lock acquisition failure -> activation does NOT run (fails closed).
     */
    public function test_n_lock_acquisition_failure_fails_closed(): void
    {
        $standard = $this->registry->registerStandard([
            'assessment_family' => AssessmentFamily::Toeic,
            'version' => '2026.LOCK_FAIL',
            'status' => StandardStatus::Draft,
            'structure_definition' => [],
        ]);

        $lockKey = 'assessment_standard_activation:toeic';
        $externalLock = Cache::lock($lockKey, 10);
        $this->assertTrue($externalLock->acquire());

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage("Unable to acquire activation lock for assessment family 'toeic'");

            $this->registry->activateStandard($standard);
        } finally {
            $externalLock->release();
        }

        // Standard must not have been activated
        $this->assertSame(StandardStatus::Draft, $standard->fresh()->status);
    }

    /**
     * TEST O: Real concurrent same-family activation leaves exactly 1 active standard.
     */
    public function test_o_real_concurrent_same_family_activation_leaves_single_active(): void
    {
        $tempDb = sys_get_temp_dir().'/concurrency_standards_'.uniqid().'.sqlite';
        touch($tempDb);

        $tempCacheDir = sys_get_temp_dir().'/concurrency_cache_'.uniqid();
        @mkdir($tempCacheDir, 0777, true);

        $runnerScript = base_path('standards_concurrency_worker.php');

        try {
            config(['database.connections.temp_concurrency' => [
                'driver' => 'sqlite',
                'database' => $tempDb,
                'prefix' => '',
                'foreign_key_constraints' => true,
            ]]);

            $tempConn = DB::connection('temp_concurrency');

            $tempConn->getSchemaBuilder()->create('assessment_standards', function ($table) {
                $table->ulid('id')->primary();
                $table->string('assessment_family', 40)->index();
                $table->string('standard_code', 60);
                $table->string('version', 30);
                $table->string('title', 255);
                $table->string('provider', 100);
                $table->string('status', 30)->default('draft')->index();
                $table->dateTime('effective_from')->nullable();
                $table->dateTime('effective_until')->nullable();
                $table->string('source_name', 255)->nullable();
                $table->text('source_url')->nullable();
                $table->dateTime('source_checked_at')->nullable();
                $table->json('structure_definition')->nullable();
                $table->json('blueprint_definition')->nullable();
                $table->json('validation_definition')->nullable();
                $table->json('scoring_definition')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->unique(['assessment_family', 'version']);
            });

            $idA = (string) Str::ulid();
            $idB = (string) Str::ulid();

            $tempConn->table('assessment_standards')->insert([
                [
                    'id' => $idA,
                    'assessment_family' => 'toeic',
                    'standard_code' => 'TOEIC-2026.CONC_A',
                    'version' => '2026.CONC_A',
                    'title' => 'TOEIC 2026 Concurrent A',
                    'provider' => 'IAP',
                    'status' => 'draft',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'id' => $idB,
                    'assessment_family' => 'toeic',
                    'standard_code' => 'TOEIC-2026.CONC_B',
                    'version' => '2026.CONC_B',
                    'title' => 'TOEIC 2026 Concurrent B',
                    'provider' => 'IAP',
                    'status' => 'draft',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);

            $runnerCode = <<<'PHP'
<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$tempDb = $argv[1];
$standardId = $argv[2];
$cacheDir = $argv[3];

config(['database.default' => 'sqlite']);
config(['database.connections.sqlite.database' => $tempDb]);
config(['cache.default' => 'file']);
config(['cache.stores.file.path' => $cacheDir]);

\Illuminate\Support\Facades\DB::purge('sqlite');
\Illuminate\Support\Facades\DB::reconnect('sqlite');

$registry = new \App\Modules\QuestionEngine\Services\AssessmentStandardRegistry();
try {
    $res = $registry->activateStandard($standardId);
    echo json_encode(['status' => 'success', 'id' => $res->id, 'version' => $res->version]);
} catch (\Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
PHP;
            file_put_contents($runnerScript, $runnerCode);

            $cmd1 = sprintf('php %s %s %s %s', escapeshellarg($runnerScript), escapeshellarg($tempDb), escapeshellarg($idA), escapeshellarg($tempCacheDir));
            $cmd2 = sprintf('php %s %s %s %s', escapeshellarg($runnerScript), escapeshellarg($tempDb), escapeshellarg($idB), escapeshellarg($tempCacheDir));

            $p1 = proc_open($cmd1, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes1, base_path());
            $p2 = proc_open($cmd2, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes2, base_path());

            $out1 = stream_get_contents($pipes1[1]);
            $err1 = stream_get_contents($pipes1[2]);
            fclose($pipes1[1]);
            fclose($pipes1[2]);
            proc_close($p1);

            $out2 = stream_get_contents($pipes2[1]);
            $err2 = stream_get_contents($pipes2[2]);
            fclose($pipes2[1]);
            fclose($pipes2[2]);
            proc_close($p2);

            $activeCount = $tempConn->table('assessment_standards')
                ->where('assessment_family', 'toeic')
                ->where('status', 'active')
                ->count();

            $this->assertSame(1, $activeCount, "Expected exactly 1 active standard after concurrent activations. Outputs: P1={$out1} (err={$err1}), P2={$out2} (err={$err2})");

            $supersededCount = $tempConn->table('assessment_standards')
                ->where('assessment_family', 'toeic')
                ->where('status', 'superseded')
                ->count();
            $this->assertSame(1, $supersededCount);
        } finally {
            DB::purge('temp_concurrency');
            if (file_exists($tempDb)) {
                @unlink($tempDb);
            }
            if (file_exists($runnerScript)) {
                @unlink($runnerScript);
            }
            if (file_exists($tempCacheDir)) {
                @File::deleteDirectory($tempCacheDir);
            }
        }
    }

    /**
     * TEST P: Different families activate independently.
     */
    public function test_p_different_families_activate_independently(): void
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
     * TEST Q: Valid FK binding works.
     */
    public function test_q_valid_fk_binding_works(): void
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
        ]);

        $this->assertDatabaseHas('questions', [
            'id' => $question->id,
            'assessment_standard_id' => $standard->id,
        ]);
        $this->assertSame($standard->id, $question->fresh()->assessmentStandard->id);
    }

    /**
     * TEST R: Invalid FK binding is rejected.
     */
    public function test_r_invalid_fk_binding_is_rejected(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = ON;');
        }

        $this->expectException(QueryException::class);

        Question::create([
            'question_bank_id' => $this->questionBank->id,
            'prompt' => 'Question with fake FK.',
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
     * TEST S: Historical question standard binding remains immutable.
     */
    public function test_s_historical_binding_remains_immutable(): void
    {
        $std2024 = $this->registry->registerStandard([
            'assessment_family' => AssessmentFamily::Toeic,
            'version' => '2024.1',
            'title' => 'TOEIC 2024 Standard',
            'status' => StandardStatus::Draft,
            'structure_definition' => [],
        ]);
        $this->registry->activateStandard($std2024);

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
     * TEST T: Sprint 1 generation contract regression PASS.
     */
    public function test_t_sprint1_generation_contract_regression_pass(): void
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
     * TEST U: TOEIC validator regression PASS.
     */
    public function test_u_toeic_validator_regression_pass(): void
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
     * TEST V: Question Bank governance regression PASS.
     */
    public function test_v_question_bank_governance_regression_pass(): void
    {
        $this->assertDatabaseCount('question_banks', 1);
        $this->assertTrue($this->questionBank->is_published);
        $this->assertSame($this->teacher->id, $this->questionBank->created_by);
    }

    /**
     * Extra Test: Strict AssessmentItemIdentity parsing.
     */
    public function test_strict_assessment_item_identity_part_number_parsing(): void
    {
        // 5 accepted
        $id1 = AssessmentItemIdentity::fromArray(['assessment_family' => 'toeic', 'part_number' => 5]);
        $this->assertSame(5, $id1->partNumber);

        // "5" accepted
        $id2 = AssessmentItemIdentity::fromArray(['assessment_family' => 'toeic', 'part_number' => '5']);
        $this->assertSame(5, $id2->partNumber);

        // 5.5 rejected
        try {
            AssessmentItemIdentity::fromArray(['assessment_family' => 'toeic', 'part_number' => 5.5]);
            $this->fail('5.5 should have thrown InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Part number must be a valid integer', $e->getMessage());
        }

        // "5.5" rejected
        try {
            AssessmentItemIdentity::fromArray(['assessment_family' => 'toeic', 'part_number' => '5.5']);
            $this->fail('"5.5" should have thrown InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Part number must be a valid integer', $e->getMessage());
        }
    }

    /**
     * Extra Test: Force delete referenced AssessmentStandard is restricted.
     */
    public function test_force_delete_referenced_standard_is_restricted(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = ON;');
        }

        $standard = AssessmentStandard::create([
            'assessment_family' => AssessmentFamily::Toeic,
            'standard_code' => 'TOEIC-2026.FK_RESTRICT',
            'version' => '2026.FK_RESTRICT',
            'title' => 'TOEIC Standard 2026.FK_RESTRICT',
            'provider' => 'IAP Authority',
            'status' => StandardStatus::Active,
            'structure_definition' => [],
        ]);

        $question = Question::create([
            'question_bank_id' => $this->questionBank->id,
            'prompt' => 'Question bound to standard before force delete test.',
            'section' => SectionType::Reading,
            'part_number' => 5,
            'question_type' => QuestionType::MultipleChoice,
            'difficulty' => DifficultyLevel::Easy,
            'assessment_family' => AssessmentFamily::Toeic,
            'assessment_standard_id' => $standard->id,
            'standard_version' => '2026.FK_RESTRICT',
        ]);

        // Attempt force delete on referenced standard -> MUST fail with QueryException due to ON DELETE RESTRICT
        try {
            $standard->forceDelete();
            $this->fail('forceDelete on referenced AssessmentStandard should fail with QueryException');
        } catch (QueryException $e) {
            $this->assertStringContainsString('FOREIGN KEY', $e->getMessage());
        }

        // Question remains intact
        $this->assertDatabaseHas('questions', [
            'id' => $question->id,
            'assessment_standard_id' => $standard->id,
        ]);
    }
}
