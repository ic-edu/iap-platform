<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Assessment\Engines\AttemptEngine;
use App\Modules\Assessment\Engines\ResultEngine;
use App\Modules\Assessment\Enums\AssessmentMode;
use App\Modules\Assessment\Enums\AttemptStatus;
use App\Modules\Assessment\Models\Answer;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\CandidateTestAssignment;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestQuestion;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\Certificate\Engines\CertificateEngine;
use App\Modules\Certificate\Models\Certificate;
use App\Modules\Certificate\Services\CertificateEligibilityService;
use App\Modules\Commerce\Domain\Enums\OrderStatus;
use App\Modules\Commerce\Domain\Enums\PaymentStatus;
use App\Modules\Commerce\Domain\Models\Invoice;
use App\Modules\Commerce\Domain\Models\Order;
use App\Modules\Commerce\Domain\Models\Payment;
use App\Modules\Commerce\Domain\Models\Product;
use App\Modules\QuestionBank\Enums\QuestionType;
use App\Modules\QuestionBank\Enums\TestType;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Modules\QuestionBank\Models\QuestionChoice;
use App\Services\VerificationService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimulatorCertificateEligibilityTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $candidate;
    protected Test $simulatorTest;
    protected Test $realTest;
    protected Question $q1;
    protected Question $q2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create(['name' => 'Admin User', 'email' => 'admin.cert@example.com', 'status' => 'active']);
        $this->admin->assignRole('admin');

        $this->candidate = User::factory()->create(['name' => 'Test Candidate', 'email' => 'candidate.cert@example.com', 'status' => 'active']);
        $this->candidate->assignRole('student');

        $bank = QuestionBank::create([
            'title' => 'Bank Cert Test',
            'slug' => 'bank-cert-test',
            'test_type' => TestType::General,
            'created_by' => $this->admin->id,
            'is_approved' => true,
        ]);

        $this->q1 = Question::create([
            'question_bank_id' => $bank->id,
            'prompt' => 'Question 1 Prompt',
            'question_type' => QuestionType::MultipleChoice,
            'points' => 10,
        ]);
        QuestionChoice::create(['question_id' => $this->q1->id, 'label' => 'A', 'content' => 'Correct Option', 'is_correct' => true]);
        QuestionChoice::create(['question_id' => $this->q1->id, 'label' => 'B', 'content' => 'Wrong Option', 'is_correct' => false]);

        $this->q2 = Question::create([
            'question_bank_id' => $bank->id,
            'prompt' => 'Question 2 Prompt',
            'question_type' => QuestionType::MultipleChoice,
            'points' => 10,
        ]);
        QuestionChoice::create(['question_id' => $this->q2->id, 'label' => 'A', 'content' => 'Correct Option 2', 'is_correct' => true]);
        QuestionChoice::create(['question_id' => $this->q2->id, 'label' => 'B', 'content' => 'Wrong Option 2', 'is_correct' => false]);

        // 1. Simulator Test (Practice Mode)
        $this->simulatorTest = Test::create([
            'title' => 'TOEIC Practice Simulator Test',
            'slug' => 'toeic-practice-sim-test',
            'assessment_mode' => AssessmentMode::Simulator,
            'status' => 'published',
            'is_published' => true,
            'duration_minutes' => 60,
            'pass_score' => 75,
            'created_by' => $this->admin->id,
        ]);

        $simSection = TestSection::create([
            'test_id' => $this->simulatorTest->id,
            'title' => 'Sim Section',
            'order' => 1,
        ]);
        TestQuestion::create(['test_section_id' => $simSection->id, 'question_id' => $this->q1->id, 'order' => 1]);
        TestQuestion::create(['test_section_id' => $simSection->id, 'question_id' => $this->q2->id, 'order' => 2]);

        // 2. Real Test (Mock Test)
        $this->realTest = Test::create([
            'title' => 'Official TOEFL Mock Test',
            'slug' => 'official-toefl-mock-test',
            'assessment_mode' => AssessmentMode::RealTest,
            'status' => 'published',
            'is_published' => true,
            'duration_minutes' => 60,
            'pass_score' => 50,
            'created_by' => $this->admin->id,
        ]);

        $realSection = TestSection::create([
            'test_id' => $this->realTest->id,
            'title' => 'Real Section',
            'order' => 1,
        ]);
        TestQuestion::create(['test_section_id' => $realSection->id, 'question_id' => $this->q1->id, 'order' => 1]);
        TestQuestion::create(['test_section_id' => $realSection->id, 'question_id' => $this->q2->id, 'order' => 2]);
    }

    /**
     * TEST CERT-01: Submitting a passed Simulator attempt does NOT generate a Certificate record.
     */
    public function test_cert_01_simulator_submission_does_not_create_certificate(): void
    {
        $attemptEngine = app(AttemptEngine::class);
        $attempt = $attemptEngine->startAttempt($this->simulatorTest, $this->candidate);

        // Answer 100% correct
        Answer::create([
            'attempt_id' => $attempt->id,
            'question_id' => $this->q1->id,
            'selected_choice_id' => $this->q1->choices()->firstWhere('is_correct', true)->id,
        ]);
        Answer::create([
            'attempt_id' => $attempt->id,
            'question_id' => $this->q2->id,
            'selected_choice_id' => $this->q2->choices()->firstWhere('is_correct', true)->id,
        ]);

        $attemptEngine->submitAttempt($attempt);

        $attempt->refresh();
        $this->assertSame(AttemptStatus::Submitted, $attempt->status);
        $this->assertCount(0, Certificate::all());
        $this->assertNull(Certificate::where('attempt_id', $attempt->id)->first());
    }

    /**
     * TEST CERT-02 & CERT-03: Simulator Result page does NOT render Certificate Issued banner or Download CTA.
     */
    public function test_cert_02_03_simulator_result_does_not_render_certificate_banner_or_download_cta(): void
    {
        $attemptEngine = app(AttemptEngine::class);
        $attempt = $attemptEngine->startAttempt($this->simulatorTest, $this->candidate);

        Answer::create([
            'attempt_id' => $attempt->id,
            'question_id' => $this->q1->id,
            'selected_choice_id' => $this->q1->choices()->firstWhere('is_correct', true)->id,
        ]);
        Answer::create([
            'attempt_id' => $attempt->id,
            'question_id' => $this->q2->id,
            'selected_choice_id' => $this->q2->choices()->firstWhere('is_correct', true)->id,
        ]);

        $attemptEngine->submitAttempt($attempt);

        $response = $this->actingAs($this->candidate)->get(route('candidate.review', $attempt));
        $response->assertStatus(200);
        $response->assertSee('RESULT: PASSED');
        $response->assertDontSee('Official Digital Certificate Issued!');
        $response->assertDontSee('Download Digital Certificate');
    }

    /**
     * TEST CERT-04: Completing Simulator does NOT increment Candidate My Certificates KPI on dashboard.
     */
    public function test_cert_04_simulator_completion_does_not_increment_my_certificates_kpi(): void
    {
        $attemptEngine = app(AttemptEngine::class);
        $attempt = $attemptEngine->startAttempt($this->simulatorTest, $this->candidate);

        Answer::create([
            'attempt_id' => $attempt->id,
            'question_id' => $this->q1->id,
            'selected_choice_id' => $this->q1->choices()->firstWhere('is_correct', true)->id,
        ]);
        Answer::create([
            'attempt_id' => $attempt->id,
            'question_id' => $this->q2->id,
            'selected_choice_id' => $this->q2->choices()->firstWhere('is_correct', true)->id,
        ]);

        $attemptEngine->submitAttempt($attempt);

        $response = $this->actingAs($this->candidate)->get(route('candidate.portal'));
        $response->assertStatus(200);
        $response->assertViewHas('issuedCertificatesCount', 0);
    }

    /**
     * TEST CERT-05: Simulator completion does not appear in candidate certificate list.
     */
    public function test_cert_05_simulator_completion_does_not_appear_in_my_certificates_list(): void
    {
        $attemptEngine = app(AttemptEngine::class);
        $attempt = $attemptEngine->startAttempt($this->simulatorTest, $this->candidate);

        Answer::create([
            'attempt_id' => $attempt->id,
            'question_id' => $this->q1->id,
            'selected_choice_id' => $this->q1->choices()->firstWhere('is_correct', true)->id,
        ]);
        Answer::create([
            'attempt_id' => $attempt->id,
            'question_id' => $this->q2->id,
            'selected_choice_id' => $this->q2->choices()->firstWhere('is_correct', true)->id,
        ]);

        $attemptEngine->submitAttempt($attempt);

        $response = $this->actingAs($this->candidate)->get(route('candidate.my-certificates'));
        $response->assertStatus(200);
        $response->assertSee('No Certificates Issued Yet');
    }

    /**
     * TEST CERT-06: Direct access to download a Certificate for a Simulator assessment returns 404.
     */
    public function test_cert_06_direct_access_to_simulator_certificate_is_rejected(): void
    {
        $attempt = Attempt::create([
            'test_id' => $this->simulatorTest->id,
            'user_id' => $this->candidate->id,
            'status' => AttemptStatus::Submitted,
            'total_score' => 100,
        ]);

        // Synthesize an erroneous historical certificate for test
        $cert = Certificate::create([
            'certificate_number' => 'CERT-20260904-TEST',
            'verification_code' => 'VRF-TEST-0001',
            'attempt_id' => $attempt->id,
            'user_id' => $this->candidate->id,
            'status' => \App\Modules\Certificate\Enums\CertificateStatus::Valid,
            'template' => 'internal',
            'issued_at' => now(),
            'expires_at' => now()->addYears(2),
        ]);

        $response = $this->actingAs($this->candidate)->get(route('candidate.certificates.download', $cert));
        $response->assertStatus(404);
    }

    /**
     * TEST CERT-07 & CERT-08: Mock / Real assessment still follows authoritative finality certificate issuance policy.
     */
    public function test_cert_07_08_real_test_preserves_existing_certificate_policy(): void
    {
        // Setup paid assignment for candidate
        $assignment = CandidateTestAssignment::create([
            'test_id' => $this->realTest->id,
            'user_id' => $this->candidate->id,
            'max_attempts' => 2,
            'attempts_used' => 0,
            'status' => 'active',
            'assigned_at' => now(),
        ]);

        $attemptEngine = app(AttemptEngine::class);
        $attempt = $attemptEngine->startAttempt($this->realTest, $this->candidate);

        Answer::create([
            'attempt_id' => $attempt->id,
            'question_id' => $this->q1->id,
            'selected_choice_id' => $this->q1->choices()->firstWhere('is_correct', true)->id,
        ]);
        Answer::create([
            'attempt_id' => $attempt->id,
            'question_id' => $this->q2->id,
            'selected_choice_id' => $this->q2->choices()->firstWhere('is_correct', true)->id,
        ]);

        $attemptEngine->submitAttempt($attempt);

        // While pending decision (non-final), no certificate yet
        $this->assertCount(0, Certificate::all());

        // Finalize Attempt 1
        $finalizeResponse = $this->actingAs($this->candidate)->post(route('candidate.exam.finalize', $attempt));
        $finalizeResponse->assertRedirect(route('candidate.review', $attempt));

        // Now Certificate IS generated for the finalized Real Test
        $this->assertCount(1, Certificate::all());
        $cert = Certificate::first();
        $this->assertSame($attempt->id, $cert->attempt_id);

        // Candidate review for Real Test displays Certificate banner
        $reviewRes = $this->actingAs($this->candidate)->get(route('candidate.review', $attempt));
        $reviewRes->assertStatus(200);
        $reviewRes->assertSee('Official Digital Certificate Issued!');
        $reviewRes->assertSee('Download Digital Certificate');

        // Verification service resolves valid certificate
        $verification = app(VerificationService::class)->verify($cert->verification_code);
        $this->assertSame('valid', $verification['status']);
    }

    /**
     * TEST CERT-09 & CERT-10: Legacy Simulator Certificate does NOT count towards Candidate KPI or appear in active list.
     */
    public function test_cert_09_10_legacy_simulator_certificate_is_safely_excluded(): void
    {
        $attempt = Attempt::create([
            'test_id' => $this->simulatorTest->id,
            'user_id' => $this->candidate->id,
            'status' => AttemptStatus::Submitted,
            'total_score' => 100,
        ]);

        // Create legacy certificate record in DB
        $legacyCert = Certificate::create([
            'certificate_number' => 'CERT-20260904-LEGACY',
            'verification_code' => 'VRF-LEGACY-001',
            'attempt_id' => $attempt->id,
            'user_id' => $this->candidate->id,
            'status' => \App\Modules\Certificate\Enums\CertificateStatus::Valid,
            'template' => 'internal',
            'issued_at' => now(),
            'expires_at' => now()->addYears(2),
        ]);

        // 1. Candidate KPI excludes legacy simulator certificate
        $dashboardRes = $this->actingAs($this->candidate)->get(route('candidate.portal'));
        $dashboardRes->assertViewHas('issuedCertificatesCount', 0);

        // 2. Candidate my-certificates list excludes legacy simulator certificate
        $listRes = $this->actingAs($this->candidate)->get(route('candidate.my-certificates'));
        $listRes->assertDontSee($legacyCert->certificate_number);

        // 3. Verification service rejects verification
        $verification = app(VerificationService::class)->verify($legacyCert->verification_code);
        $this->assertSame('not_eligible', $verification['status']);
    }

    /**
     * TEST UI-01 to UI-08: Main Candidate Result footer renders Back to Top and eliminates duplicate navigation.
     */
    public function test_ui_01_to_08_result_footer_renders_back_to_top_and_no_duplicate_nav(): void
    {
        $attempt = Attempt::create([
            'test_id' => $this->simulatorTest->id,
            'user_id' => $this->candidate->id,
            'status' => AttemptStatus::Submitted,
            'total_score' => 90,
        ]);

        $response = $this->actingAs($this->candidate)->get(route('candidate.review', $attempt));
        $response->assertStatus(200);

        // UI-01 & UI-02: Does not render duplicate bottom buttons
        $response->assertDontSee('&larr; Back to Attempts List', false);
        $response->assertDontSee('Return to Dashboard');

        // UI-03 & UI-04 & UI-05: Renders Back to Top button targeting page top without route navigation
        $response->assertSee('Back to Top');
        $response->assertSee('id="candidate-result-top"', false);
        $response->assertSee('onclick="window.scrollTo({ top: 0, behavior: \'smooth\' })"', false);
        $response->assertSee('aria-label="Scroll back to top of result page"', false);

        // UI-06: Top navigation to Attempt History remains available
        $response->assertSee('&larr; Back to Attempt History', false);
        $response->assertSee(route('candidate.my-attempts'));

        // UI-07 & UI-08: Theme safe classes present
        $response->assertSee('bg-slate-100');
        $response->assertSee('dark:bg-slate-800');
    }
}
