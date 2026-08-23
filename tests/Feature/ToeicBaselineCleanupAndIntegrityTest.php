<?php

namespace Tests\Feature;

use App\Modules\Assessment\Database\Seeders\AssessmentSeeder;
use App\Modules\Assessment\Models\Answer;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestQuestion;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\Finance\Database\Seeders\FinanceSeeder;
use App\Modules\Finance\Models\Payment;
use App\Modules\QuestionBank\Database\Seeders\QuestionBankSeeder;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Services\ToeicQuestionValidator;
use Database\Seeders\AclStarterLibrarySeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ToeicBaselineCleanupAndIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AclStarterLibrarySeeder::class);
        $this->seed(QuestionBankSeeder::class);
        $this->seed(AssessmentSeeder::class);
        $this->seed(FinanceSeeder::class);
    }

    /**
     * 1 & 2: Legacy assessment is not seeded and is absent after normal seed setup.
     */
    public function test_legacy_assessment_is_not_seeded_and_absent_after_seed_setup(): void
    {
        $legacyBySlug = Test::where('slug', 'toeic-full-simulation-test-01')->first();
        $this->assertNull($legacyBySlug, 'Legacy TOEIC Full Simulation Test 01 must not exist by slug.');

        $legacyByTitle = Test::where('title', 'TOEIC Full Simulation Test 01')->first();
        $this->assertNull($legacyByTitle, 'Legacy TOEIC Full Simulation Test 01 must not exist by title.');

        $this->assertSame(0, Test::count(), 'Assessment table must remain clean with 0 seeded legacy tests in this phase.');
    }

    /**
     * 3: Shared TOEIC Question Banks remain available.
     */
    public function test_shared_toeic_question_banks_remain_available(): void
    {
        $toeicBanks = QuestionBank::where('test_type', 'toeic')->get();
        $this->assertGreaterThanOrEqual(7, $toeicBanks->count(), 'At least 7 TOEIC question banks (Parts 1-7) must be present.');

        $bankTitles = $toeicBanks->pluck('title')->toArray();
        $this->assertTrue(collect($bankTitles)->contains(fn($t) => str_contains($t, 'Part 1')));
        $this->assertTrue(collect($bankTitles)->contains(fn($t) => str_contains($t, 'Part 2')));
        $this->assertTrue(collect($bankTitles)->contains(fn($t) => str_contains($t, 'Part 3')));
        $this->assertTrue(collect($bankTitles)->contains(fn($t) => str_contains($t, 'Part 4')));
        $this->assertTrue(collect($bankTitles)->contains(fn($t) => str_contains($t, 'Part 5')));
        $this->assertTrue(collect($bankTitles)->contains(fn($t) => str_contains($t, 'Part 6')));
        $this->assertTrue(collect($bankTitles)->contains(fn($t) => str_contains($t, 'Part 7')));
    }

    /**
     * 4: Shared TOEIC Questions remain available.
     */
    public function test_shared_toeic_questions_remain_available(): void
    {
        $toeicQuestions = Question::whereHas('questionBank', fn($q) => $q->where('test_type', 'toeic'))->get();
        $this->assertGreaterThanOrEqual(30, $toeicQuestions->count(), 'Master TOEIC questions must remain preserved in Question Banks.');

        foreach ($toeicQuestions as $question) {
            $this->assertNotNull($question->question_bank_id);
            $this->assertNotEmpty($question->prompt);
        }
    }

    /**
     * 5: No orphan assessment-question bindings or test sections remain.
     */
    public function test_no_orphan_assessment_question_bindings_or_sections_remain(): void
    {
        $this->assertSame(0, TestSection::count(), 'No test sections should remain when no assessments exist.');
        $this->assertSame(0, TestQuestion::count(), 'No test questions pivot bindings should remain.');
    }

    /**
     * 6: No orphan attempt/answer records remain from the legacy assessment.
     */
    public function test_no_orphan_attempt_or_answer_records_remain(): void
    {
        $this->assertSame(0, Attempt::count(), 'No attempts should remain when no assessments exist.');
        $this->assertSame(0, Answer::count(), 'No answers should remain when no attempts exist.');
    }

    /**
     * 7: No stale payment references remain.
     */
    public function test_no_stale_payment_references_to_deleted_test(): void
    {
        $paymentsWithTest = Payment::whereNotNull('test_id')->get();
        $this->assertSame(0, $paymentsWithTest->count(), 'No payment record should reference a non-existent or legacy test.');

        $demoPayment = Payment::where('reference_number', 'INV-20260726-0001')->first();
        $this->assertNotNull($demoPayment, 'Demo course payment should be preserved.');
        $this->assertNull($demoPayment->test_id, 'Demo payment test_id must be null.');
    }

    /**
     * 8: Existing TOEIC validator is intact and functioning.
     */
    public function test_toeic_validator_functions_as_expected(): void
    {
        $this->assertTrue(ToeicQuestionValidator::isToeic('toeic'));
        $this->assertSame('listening', ToeicQuestionValidator::deriveSection(1));
        $this->assertSame('listening', ToeicQuestionValidator::deriveSection(2));
        $this->assertSame('listening', ToeicQuestionValidator::deriveSection(3));
        $this->assertSame('listening', ToeicQuestionValidator::deriveSection(4));
        $this->assertSame('reading', ToeicQuestionValidator::deriveSection(5));
        $this->assertSame('reading', ToeicQuestionValidator::deriveSection(6));
        $this->assertSame('reading', ToeicQuestionValidator::deriveSection(7));
    }
}
