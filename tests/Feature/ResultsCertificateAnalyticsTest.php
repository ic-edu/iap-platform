<?php

use App\Models\User;
use App\Modules\Assessment\Engines\ResultEngine;
use App\Modules\Assessment\Enums\AttemptStatus;
use App\Modules\Assessment\Models\Answer;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\Test;
use App\Modules\Certificate\Engines\CertificateEngine;
use App\Modules\Certificate\Enums\CertificateStatus;
use App\Modules\Certificate\Events\CertificateIssued;
use App\Modules\Certificate\Events\CertificateReissued;
use App\Modules\Certificate\Events\CertificateRevoked;
use App\Modules\QuestionBank\Enums\QuestionType;
use App\Modules\QuestionBank\Enums\SectionType;
use App\Modules\QuestionBank\Enums\TestType;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Modules\Reporting\Services\AnalyticsEngine;
use App\Modules\Reporting\Services\ItemAnalysisService;
use App\Services\ExportService;
use App\Services\VerificationService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('result engine evaluates final score pass fail and percentage', function () {
    $engine = new ResultEngine;
    $user = User::factory()->create();
    $test = Test::create(['title' => 'Result Test', 'slug' => 'result-test', 'test_type' => TestType::General, 'duration_minutes' => 60, 'pass_score' => 70, 'created_by' => $user->id]);
    $bank = QuestionBank::create(['title' => 'Bank', 'slug' => 'bank', 'test_type' => TestType::General, 'created_by' => $user->id]);
    $attempt = Attempt::create(['test_id' => $test->id, 'user_id' => $user->id, 'status' => AttemptStatus::Submitted, 'total_score' => 85.0]);

    $q1 = Question::create(['question_bank_id' => $bank->id, 'prompt' => 'Q1', 'section' => SectionType::Reading, 'question_type' => QuestionType::MultipleChoice, 'points' => 10]);
    Answer::create(['attempt_id' => $attempt->id, 'question_id' => $q1->id, 'is_correct' => true, 'score_earned' => 10]);

    $result = $engine->generateResult($attempt);

    expect($result['is_passed'])->toBeTrue();
    expect($result['final_score'])->toBe(85.0);
    expect($result['percentage'])->toBe(100.0);
    expect($result['grade'])->toBe('A+');
});

test('result engine handles failing attempt score', function () {
    $engine = new ResultEngine;
    $user = User::factory()->create();
    $test = Test::create(['title' => 'Fail Test', 'slug' => 'fail-test', 'test_type' => TestType::General, 'duration_minutes' => 60, 'pass_score' => 75, 'created_by' => $user->id]);
    $attempt = Attempt::create(['test_id' => $test->id, 'user_id' => $user->id, 'status' => AttemptStatus::Submitted, 'total_score' => 50.0]);

    $result = $engine->generateResult($attempt);

    expect($result['is_passed'])->toBeFalse();
    expect($result['grade'])->toBe('D');
});

test('certificate engine issues certificate with unique codes and events', function () {
    Event::fake([CertificateIssued::class]);
    $engine = new CertificateEngine;
    $user = User::factory()->create();
    $test = Test::create(['title' => 'Cert Test', 'slug' => 'cert-test', 'test_type' => TestType::General, 'assessment_mode' => 'real_test', 'duration_minutes' => 60, 'pass_score' => 70, 'created_by' => $user->id]);
    $attempt = Attempt::create(['test_id' => $test->id, 'user_id' => $user->id, 'status' => AttemptStatus::Submitted, 'total_score' => 90.0]);

    $cert = $engine->issueCertificate($attempt);

    expect($cert->certificate_number)->toContain('CERT-');
    expect($cert->verification_code)->toContain('VRF-');
    expect($cert->status)->toBe(CertificateStatus::Valid);

    Event::assertDispatched(CertificateIssued::class);
});

test('certificate engine returns existing certificate if already issued', function () {
    $engine = new CertificateEngine;
    $user = User::factory()->create();
    $test = Test::create(['title' => 'Cert Dup', 'slug' => 'cert-dup', 'test_type' => TestType::General, 'assessment_mode' => 'real_test', 'duration_minutes' => 60, 'pass_score' => 70, 'created_by' => $user->id]);
    $attempt = Attempt::create(['test_id' => $test->id, 'user_id' => $user->id, 'status' => AttemptStatus::Submitted]);

    $c1 = $engine->issueCertificate($attempt);
    $c2 = $engine->issueCertificate($attempt);

    expect($c1->id)->toBe($c2->id);
});

test('certificate engine can reissue and revoke certificate', function () {
    Event::fake([CertificateReissued::class, CertificateRevoked::class]);
    $engine = new CertificateEngine;
    $user = User::factory()->create();
    $test = Test::create(['title' => 'Cert 2', 'slug' => 'cert-2', 'test_type' => TestType::General, 'assessment_mode' => 'real_test', 'duration_minutes' => 60, 'pass_score' => 70, 'created_by' => $user->id]);
    $attempt = Attempt::create(['test_id' => $test->id, 'user_id' => $user->id, 'status' => AttemptStatus::Submitted]);
    $cert = $engine->issueCertificate($attempt);

    $revoked = $engine->revokeCertificate($cert);
    expect($revoked->status)->toBe(CertificateStatus::Revoked);
    Event::assertDispatched(CertificateRevoked::class);

    $reissued = $engine->reissueCertificate($cert);
    expect($reissued->status)->toBe(CertificateStatus::Valid);
    Event::assertDispatched(CertificateReissued::class);
});

test('verification service validates valid revoked and expired certificates', function () {
    $verifService = new VerificationService;
    $engine = new CertificateEngine;
    $user = User::factory()->create();
    $test = Test::create(['title' => 'Verif Test', 'slug' => 'verif-test', 'test_type' => TestType::General, 'assessment_mode' => 'real_test', 'duration_minutes' => 60, 'pass_score' => 70, 'created_by' => $user->id]);
    $attempt = Attempt::create(['test_id' => $test->id, 'user_id' => $user->id, 'status' => AttemptStatus::Submitted]);

    $cert = $engine->issueCertificate($attempt);

    $validRes = $verifService->verify($cert->verification_code);
    expect($validRes['status'])->toBe('valid');

    $engine->revokeCertificate($cert);
    $revokedRes = $verifService->verify($cert->certificate_number);
    expect($revokedRes['status'])->toBe('revoked');

    $notFoundRes = $verifService->verify('INVALID_CODE_123');
    expect($notFoundRes['status'])->toBe('not_found');
});

test('public verification portal renders result for input code', function () {
    $engine = new CertificateEngine;
    $user = User::factory()->create();
    $test = Test::create(['title' => 'Public Test', 'slug' => 'public-test', 'test_type' => TestType::General, 'assessment_mode' => 'real_test', 'duration_minutes' => 60, 'pass_score' => 70, 'created_by' => $user->id]);
    $attempt = Attempt::create(['test_id' => $test->id, 'user_id' => $user->id, 'status' => AttemptStatus::Submitted]);
    $cert = $engine->issueCertificate($attempt);

    $response = $this->get(route('public.verify', ['code' => $cert->verification_code]));

    $response->assertStatus(200);
    $response->assertSeeText('Valid & Authentic');
    $response->assertSee($cert->certificate_number);
});

test('analytics engine aggregates platform metrics', function () {
    $analytics = new AnalyticsEngine;
    $user = User::factory()->create();
    $user->assignRole('student');

    $summary = $analytics->getAnalyticsSummary();

    expect($summary)->toHaveKeys(['total_attempts', 'pass_rate', 'completion_rate', 'active_candidates', 'certificates_issued']);
});

test('item analysis service calculates question metrics', function () {
    $service = new ItemAnalysisService;
    $user = User::factory()->create();
    $bank = QuestionBank::create(['title' => 'Item Bank', 'slug' => 'item-bank', 'test_type' => TestType::General, 'created_by' => $user->id]);
    $question = Question::create(['question_bank_id' => $bank->id, 'prompt' => 'Item Q', 'section' => SectionType::Reading, 'question_type' => QuestionType::MultipleChoice, 'points' => 5]);

    $metrics = $service->analyzeQuestion($question);

    expect($metrics['question_id'])->toBe($question->id);
    expect($metrics['total_responses'])->toBe(0);
});

test('export service generates csv report', function () {
    $service = new ExportService;
    $user = User::factory()->create();
    $test = Test::create(['title' => 'Export Test', 'slug' => 'export-test', 'test_type' => TestType::General, 'duration_minutes' => 60, 'pass_score' => 70, 'created_by' => $user->id]);
    Attempt::create(['test_id' => $test->id, 'user_id' => $user->id, 'status' => AttemptStatus::Submitted, 'total_score' => 95.0]);

    $csv = $service->exportAttemptsToCsv();

    expect($csv)->toContain('Attempt ID');
    expect($csv)->toContain('Export Test');
});

test('admin can view certificates list and revoke certificate', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $engine = new CertificateEngine;
    $user = User::factory()->create();
    $test = Test::create(['title' => 'Admin Cert', 'slug' => 'admin-cert', 'test_type' => TestType::General, 'assessment_mode' => 'real_test', 'duration_minutes' => 60, 'pass_score' => 70, 'created_by' => $admin->id]);
    $attempt = Attempt::create(['test_id' => $test->id, 'user_id' => $user->id, 'status' => AttemptStatus::Submitted]);
    $cert = $engine->issueCertificate($attempt);

    $response = $this->actingAs($admin)->get(route('admin.certificates.index'));
    $response->assertStatus(200);
    $response->assertSee($cert->certificate_number);

    $revokeResp = $this->actingAs($admin)->post(route('admin.certificates.revoke', $cert));
    $revokeResp->assertRedirect(route('admin.certificates.index'));

    $cert->refresh();
    expect($cert->status)->toBe(CertificateStatus::Revoked);
});
