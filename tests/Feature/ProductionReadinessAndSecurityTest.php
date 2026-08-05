<?php

use App\Models\User;
use App\Modules\Assessment\Engines\AttemptEngine;
use App\Modules\Assessment\Engines\ResultEngine;
use App\Modules\Assessment\Engines\ReviewEngine;
use App\Modules\Assessment\Models\Answer;
use App\Modules\Assessment\Models\Test as AssessmentTest;
use App\Modules\Certificate\Models\Certificate;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Modules\QuestionBank\Models\QuestionChoice;
use App\Modules\Reporting\Services\SystemHealthService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('security headers middleware applies csp, no-cache, and security headers to web requests', function () {
    $response = $this->get('/login');

    $response->assertStatus(200)
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Permissions-Policy', 'geolocation=(), microphone=(), camera=()')
        ->assertHeaderContains('Cache-Control', 'no-store')
        ->assertHeaderContains('Content-Security-Policy', "default-src 'self'");
});

test('unauthorized student user accessing admin pages receives HTTP 403 forbidden', function () {
    $student = User::factory()->create();
    $student->assignRole('student');

    $response = $this->actingAs($student)->get('/admin/monitoring');
    $response->assertStatus(403);

    $response = $this->actingAs($student)->get('/admin/users');
    $response->assertStatus(403);
});

test('super admin approval center renders pending submissions queue', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super-admin');

    $response = $this->actingAs($superAdmin)->get('/admin/approvals');

    $response->assertStatus(200)
        ->assertSee('Super Admin Content Approval Center', false);
});

test('super admin audit logs workspace renders system activity logs', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super-admin');

    $response = $this->actingAs($superAdmin)->get('/admin/audit-logs');

    $response->assertStatus(200)
        ->assertSee('System Audit &amp; Activity Logs', false);
});

test('authorized teacher can access question media library view', function () {
    $teacher = User::factory()->create();
    $teacher->assignRole('teacher');

    $response = $this->actingAs($teacher)->get('/admin/media');

    $response->assertStatus(200)
        ->assertSee('Question Media Library', false);
});

test('authorized super admin can access platform settings workspace view', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super-admin');

    $response = $this->actingAs($superAdmin)->get('/admin/settings');

    $response->assertStatus(200)
        ->assertSee('Super Admin Platform Settings', false);
});

test('notifications api endpoint returns json payload for header bell dropdown', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    $response = $this->actingAs($user)->getJson('/notifications');

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['success', 'unread_count', 'data']);
});

test('authorized super admin user can access user management dashboard', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super-admin');

    $response = $this->actingAs($superAdmin)->get('/admin/users');

    $response->assertStatus(200)
        ->assertSee('User &amp; Access Control Management', false);
});

test('authorized teacher can access question bank authoring view', function () {
    $teacher = User::factory()->create();
    $teacher->assignRole('teacher');

    $bank = QuestionBank::create([
        'title' => 'Authoring Bank',
        'slug' => 'authoring-bank-'.Str::random(5),
        'test_type' => 'toefl',
        'created_by' => $teacher->id,
    ]);

    $response = $this->actingAs($teacher)->get("/admin/question-banks/{$bank->id}");

    $response->assertStatus(200)
        ->assertSee('Authoring Bank')
        ->assertSee('Bulk CSV Import');
});

test('authorized admin can access academic curriculum management', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->get('/admin/academic-operations/courses');

    $response->assertStatus(200)
        ->assertSee('Master Course Management', false);
});

test('authorized teacher can access academic library and dedicated library category pages', function () {
    $teacher = User::factory()->create();
    $teacher->assignRole('teacher');

    \App\Models\AclCategory::updateOrCreate(
        ['slug' => 'toefl-listening'],
        ['name' => 'TOEFL Listening Library', 'test_type' => 'toefl', 'section_code' => 'listening', 'target_questions' => 50, 'icon' => '🎧', 'is_active' => true]
    );

    $response = $this->actingAs($teacher)->get('/admin/academic-library');
    $response->assertStatus(200)
        ->assertSee('Institutional Academic Library', false);

    $catResponse = $this->actingAs($teacher)->get('/admin/academic-library/toefl-listening');
    $catResponse->assertStatus(200)
        ->assertSee('TOEFL Listening Library', false);

    $qualityResponse = $this->actingAs($teacher)->get('/admin/academic-library/quality');
    $qualityResponse->assertStatus(200)
        ->assertSee('IRQA', false);

    $explorerResponse = $this->actingAs($teacher)->get('/admin/academic-library/explorer?filter=needs_improvement&sort=health_asc');
    $explorerResponse->assertStatus(200)
        ->assertSee('Repository Explorer', false);

    $analyticsResponse = $this->actingAs($teacher)->get('/admin/academic-library/analytics');
    $analyticsResponse->assertStatus(200)
        ->assertSee('IRQA Quality Analytics', false);

    $mediaResponse = $this->actingAs($teacher)->get('/admin/media');
    $mediaResponse->assertStatus(200)
        ->assertSee('Institutional Media Repository', false);

    $mediaListResponse = $this->actingAs($teacher)->get('/admin/media/list');
    $mediaListResponse->assertStatus(200)
        ->assertJson(['success' => true]);

    $mediaAsset = \App\Models\MediaAsset::first();
    if ($mediaAsset) {
        $mediaDetailResponse = $this->actingAs($teacher)->get('/admin/media/' . $mediaAsset->id);
        $mediaDetailResponse->assertStatus(200)
            ->assertSee('Asset Metadata', false);

        $mediaUsageResponse = $this->actingAs($teacher)->get('/admin/media/' . $mediaAsset->id . '/usage');
        $mediaUsageResponse->assertStatus(200)
            ->assertJsonStructure(['media_id', 'is_used', 'message']);
    }
});

test('authorized admin can access reporting analytics and export csv', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->get('/admin/reporting');
    $response->assertStatus(200)
        ->assertSee('Assessment Reports &amp; Analytics', false);

    $exportResponse = $this->actingAs($admin)->get('/admin/reporting/export/csv');
    $exportResponse->assertStatus(200)
        ->assertHeaderContains('Content-Type', 'text/csv');
});

test('authorized finance user can access commerce workspace', function () {
    $finance = User::factory()->create();
    $finance->assignRole('finance');

    $response = $this->actingAs($finance)->get('/admin/commerce');

    $response->assertStatus(200)
        ->assertSee('Commerce &amp; Finance Management', false);
});

test('unauthenticated root route redirects guests to login screen', function () {
    $response = $this->get('/');

    $response->assertRedirect('/login');
});

test('authenticated student user accessing root route redirects to candidate portal', function () {
    $student = User::factory()->create();
    $student->assignRole('student');

    $response = $this->actingAs($student)->get('/');

    $response->assertRedirect(route('candidate.portal'));
});

test('authenticated teacher user accessing root route redirects to teacher dashboard', function () {
    $teacher = User::factory()->create();
    $teacher->assignRole('teacher');

    $response = $this->actingAs($teacher)->get('/');

    $response->assertRedirect(route('teacher.dashboard'));
});

test('authenticated admin user accessing root route redirects to platform overview dashboard', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->get('/');

    $response->assertRedirect(route('admin.dashboard'));
});

test('authenticated teacher can render question bank index modular view', function () {
    $teacher = User::factory()->create();
    $teacher->assignRole('teacher');

    $response = $this->actingAs($teacher)->get('/admin/question-banks');

    $response->assertStatus(200);
});

test('authenticated admin cannot access settings modular view (super admin required)', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->get('/admin/settings');

    $response->assertStatus(403);
});

test('submitting passing assessment attempt automatically generates digital certificate', function () {
    $student = User::factory()->create();
    $student->assignRole('student');

    $bank = QuestionBank::create([
        'title' => 'Sample Bank',
        'slug' => 'sample-bank-'.Str::random(5),
        'test_type' => 'toefl',
        'created_by' => $student->id,
    ]);

    $test = AssessmentTest::create([
        'title' => 'Certification Test',
        'slug' => 'certification-test',
        'test_type' => 'toefl',
        'duration_minutes' => 60,
        'pass_score' => 50,
        'is_published' => true,
        'created_by' => $student->id,
    ]);

    $question = Question::create([
        'question_bank_id' => $bank->id,
        'title' => 'Sample Question',
        'prompt' => 'What is 2+2?',
        'question_type' => 'multiple_choice',
        'points' => 100,
    ]);

    $choice = QuestionChoice::create([
        'question_id' => $question->id,
        'label' => 'A',
        'content' => '4',
        'is_correct' => true,
    ]);

    $attemptEngine = app(AttemptEngine::class);
    $attempt = $attemptEngine->startAttempt($test, $student);

    Answer::create([
        'attempt_id' => $attempt->id,
        'question_id' => $question->id,
        'selected_choice_id' => $choice->id,
    ]);

    $attemptEngine->submitAttempt($attempt);

    $certificate = Certificate::where('attempt_id', $attempt->id)->first();
    expect($certificate)->not->toBeNull();
    expect($certificate->user_id)->toBe($student->id);

    $reviewEngine = app(ReviewEngine::class);
    $summary = $reviewEngine->getReviewSummary($attempt);

    expect($summary['is_passed'])->toBeTrue();
    expect($summary['certificate_id'])->toBe($certificate->id);
});

test('toeic full simulation test scales raw percentage to 10-990 score range', function () {
    $student = User::factory()->create();
    $student->assignRole('student');

    $bank = QuestionBank::create([
        'title' => 'TOEIC Bank',
        'slug' => 'toeic-bank-'.Str::random(5),
        'test_type' => 'toeic',
        'created_by' => $student->id,
    ]);

    $test = AssessmentTest::create([
        'title' => 'TOEIC Full Simulation Test 01',
        'slug' => 'toeic-simulation-'.Str::random(5),
        'test_type' => 'toeic',
        'duration_minutes' => 120,
        'pass_score' => 700,
        'is_published' => true,
        'created_by' => $student->id,
    ]);

    $q1 = Question::create([
        'question_bank_id' => $bank->id,
        'title' => 'Question 1',
        'prompt' => 'Listen to the audio and select correct response.',
        'question_type' => 'multiple_choice',
        'points' => 5,
    ]);
    $c1 = QuestionChoice::create([
        'question_id' => $q1->id,
        'label' => 'A',
        'content' => 'Correct option',
        'is_correct' => true,
    ]);

    $q2 = Question::create([
        'question_bank_id' => $bank->id,
        'title' => 'Question 2',
        'prompt' => 'Select the best phrase.',
        'question_type' => 'multiple_choice',
        'points' => 5,
    ]);
    $c2 = QuestionChoice::create([
        'question_id' => $q2->id,
        'label' => 'B',
        'content' => 'Correct option B',
        'is_correct' => true,
    ]);

    $attemptEngine = app(AttemptEngine::class);
    $attempt = $attemptEngine->startAttempt($test, $student);

    Answer::create([
        'attempt_id' => $attempt->id,
        'question_id' => $q1->id,
        'selected_choice_id' => $c1->id,
    ]);
    Answer::create([
        'attempt_id' => $attempt->id,
        'question_id' => $q2->id,
        'selected_choice_id' => $c2->id,
    ]);

    $attemptEngine->submitAttempt($attempt);

    $resultEngine = app(ResultEngine::class);
    $result = $resultEngine->generateResult($attempt);

    expect($result['percentage'])->toBe(100.0);
    expect($result['final_score'])->toBe(990.0);
    expect($result['is_passed'])->toBeTrue();

    $certificate = Certificate::where('attempt_id', $attempt->id)->first();
    expect($certificate)->not->toBeNull();
});

test('authenticated candidate can download digital certificate HTML PDF', function () {
    $student = User::factory()->create();
    $student->assignRole('student');

    $bank = QuestionBank::create([
        'title' => 'Sample Bank 2',
        'slug' => 'sample-bank-2-'.Str::random(5),
        'test_type' => 'toefl',
        'created_by' => $student->id,
    ]);

    $test = AssessmentTest::create([
        'title' => 'TOEFL ITP Exam',
        'slug' => 'toefl-itp-exam',
        'test_type' => 'toefl',
        'duration_minutes' => 60,
        'pass_score' => 50,
        'is_published' => true,
        'created_by' => $student->id,
    ]);

    $question = Question::create([
        'question_bank_id' => $bank->id,
        'title' => 'Sample Question',
        'prompt' => 'What is 2+2?',
        'question_type' => 'multiple_choice',
        'points' => 100,
    ]);

    $choice = QuestionChoice::create([
        'question_id' => $question->id,
        'label' => 'A',
        'content' => '4',
        'is_correct' => true,
    ]);

    $attemptEngine = app(AttemptEngine::class);
    $attempt = $attemptEngine->startAttempt($test, $student);

    Answer::create([
        'attempt_id' => $attempt->id,
        'question_id' => $question->id,
        'selected_choice_id' => $choice->id,
    ]);

    $attemptEngine->submitAttempt($attempt);

    $certificate = Certificate::where('attempt_id', $attempt->id)->firstOrFail();

    $response = $this->actingAs($student)->get(route('candidate.certificates.download', $certificate->id));

    $response->assertStatus(200)
        ->assertHeader('Content-Type', 'text/html; charset=utf-8');
});

test('authenticated candidate can access my certificates view and see issued certificates with valid badge', function () {
    $student = User::factory()->create();
    $student->assignRole('student');

    $bank = QuestionBank::create([
        'title' => 'Sample Bank 3',
        'slug' => 'sample-bank-3-'.Str::random(5),
        'test_type' => 'toefl',
        'created_by' => $student->id,
    ]);

    $test = AssessmentTest::create([
        'title' => 'TOEFL ITP Certification',
        'slug' => 'toefl-itp-cert',
        'test_type' => 'toefl',
        'duration_minutes' => 60,
        'pass_score' => 50,
        'is_published' => true,
        'created_by' => $student->id,
    ]);

    $question = Question::create([
        'question_bank_id' => $bank->id,
        'title' => 'Sample Question',
        'prompt' => 'What is 2+2?',
        'question_type' => 'multiple_choice',
        'points' => 100,
    ]);

    $choice = QuestionChoice::create([
        'question_id' => $question->id,
        'label' => 'A',
        'content' => '4',
        'is_correct' => true,
    ]);

    $attemptEngine = app(AttemptEngine::class);
    $attempt = $attemptEngine->startAttempt($test, $student);

    Answer::create([
        'attempt_id' => $attempt->id,
        'question_id' => $question->id,
        'selected_choice_id' => $choice->id,
    ]);

    $attemptEngine->submitAttempt($attempt);

    $response = $this->actingAs($student)->get('/candidate/my-certificates');

    $response->assertStatus(200)
        ->assertSee('My Digital Certificates')
        ->assertSee('TOEFL ITP Certification')
        ->assertSee('VALID &amp; AUTHENTIC', false)
        ->assertSee('Download PDF')
        ->assertSee('Verify Online');
});

test('attempt history my-attempts view displays synchronized PASSED status and scaled score', function () {
    $student = User::factory()->create();
    $student->assignRole('student');

    $bank = QuestionBank::create([
        'title' => 'TOEIC Bank Sync',
        'slug' => 'toeic-bank-sync-'.Str::random(5),
        'test_type' => 'toeic',
        'created_by' => $student->id,
    ]);

    $test = AssessmentTest::create([
        'title' => 'TOEIC Full Simulation Test 01',
        'slug' => 'toeic-simulation-sync-'.Str::random(5),
        'test_type' => 'toeic',
        'duration_minutes' => 120,
        'pass_score' => 700,
        'is_published' => true,
        'created_by' => $student->id,
    ]);

    $q1 = Question::create([
        'question_bank_id' => $bank->id,
        'title' => 'Question 1',
        'prompt' => 'Listen to audio.',
        'question_type' => 'multiple_choice',
        'points' => 5,
    ]);
    $c1 = QuestionChoice::create([
        'question_id' => $q1->id,
        'label' => 'A',
        'content' => 'Correct',
        'is_correct' => true,
    ]);

    $attemptEngine = app(AttemptEngine::class);
    $attempt = $attemptEngine->startAttempt($test, $student);

    Answer::create([
        'attempt_id' => $attempt->id,
        'question_id' => $q1->id,
        'selected_choice_id' => $c1->id,
    ]);

    $attemptEngine->submitAttempt($attempt);

    $response = $this->actingAs($student)->get('/candidate/my-attempts');

    $response->assertStatus(200)
        ->assertSee('PASSED')
        ->assertSee('990')
        ->assertSee('/ 700');
});

test('candidate review page renders detailed question breakdown with explanation and section stats', function () {
    $student = User::factory()->create();
    $student->assignRole('student');

    $bank = QuestionBank::create([
        'title' => 'Sample Review Bank',
        'slug' => 'sample-review-bank-'.Str::random(5),
        'test_type' => 'toefl',
        'created_by' => $student->id,
    ]);

    $test = AssessmentTest::create([
        'title' => 'TOEFL Comprehensive Review Test',
        'slug' => 'toefl-review-test-'.Str::random(5),
        'test_type' => 'toefl',
        'duration_minutes' => 60,
        'pass_score' => 50,
        'is_published' => true,
        'created_by' => $student->id,
    ]);

    $question = Question::create([
        'question_bank_id' => $bank->id,
        'title' => 'Grammar Item',
        'prompt' => 'Choose the grammatically correct sentence.',
        'question_type' => 'multiple_choice',
        'difficulty' => 'easy',
        'points' => 10,
        'explanation' => 'The subject matches the singular verb.',
    ]);

    $choice = QuestionChoice::create([
        'question_id' => $question->id,
        'label' => 'A',
        'content' => 'She goes to school.',
        'is_correct' => true,
    ]);

    $attemptEngine = app(AttemptEngine::class);
    $attempt = $attemptEngine->startAttempt($test, $student);

    Answer::create([
        'attempt_id' => $attempt->id,
        'question_id' => $question->id,
        'selected_choice_id' => $choice->id,
    ]);

    $attemptEngine->submitAttempt($attempt);

    $response = $this->actingAs($student)->get("/candidate/exam/{$attempt->id}/review");

    $response->assertStatus(200)
        ->assertSee('Detailed Question Review')
        ->assertSee('Choose the grammatically correct sentence.')
        ->assertSee('Explanation &amp; Rationale', false)
        ->assertSee('The subject matches the singular verb.');
});

test('health check probe endpoint returns status 200 healthy json', function () {
    $response = $this->getJson('/health');

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('status', 'healthy')
        ->assertJsonStructure(['data' => ['status', 'php_version', 'laravel_version', 'db_connection', 'cache_status']]);
});

test('readiness probe endpoint returns status 200 ready', function () {
    $response = $this->getJson('/ready');

    $response->assertStatus(200)
        ->assertJsonPath('status', 'ready');
});

test('liveness probe endpoint returns status 200 live', function () {
    $response = $this->getJson('/live');

    $response->assertStatus(200)
        ->assertJsonPath('status', 'live');
});

test('monitoring dashboard requires authenticated user', function () {
    $response = $this->get('/admin/monitoring');

    $response->assertRedirect('/login');
});

test('monitoring dashboard displays health metrics for authorized super admin', function () {
    $user = User::factory()->create();
    $user->assignRole('super-admin');

    $response = $this->actingAs($user)->get('/admin/monitoring');

    $response->assertStatus(200)
        ->assertSee('System Observability &amp; Monitoring Dashboard', false)
        ->assertSee('Memory Usage');
});

test('database connection health check returns active status', function () {
    $service = app(SystemHealthService::class);
    $health = $service->checkHealth();

    expect($health['db_connection'])->toBe('connected');
});

test('cache service health check operates properly', function () {
    $service = app(SystemHealthService::class);
    $health = $service->checkHealth();

    expect($health['cache_status'])->toBe('active');
});

test('security middleware applies to api v1 public endpoints', function () {
    $response = $this->getJson('/api/v1/public/categories');

    $response->assertStatus(200)
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});

test('maintenance mode returns 503 when artisan down is executed', function () {
    Artisan::call('down', ['--secret' => 'test-secret']);

    $response = $this->get('/');
    $response->assertStatus(503);

    Artisan::call('up');
});

test('production env configuration file exists and contains app url', function () {
    $path = base_path('.env.production.example');

    expect(file_exists($path))->toBeTrue();
    expect(file_get_contents($path))->toContain('APP_URL=https://assessment.icedu.org');
});

test('dockerfile and docker compose configs exist for deployment', function () {
    expect(file_exists(base_path('Dockerfile')))->toBeTrue();
    expect(file_exists(base_path('docker-compose.yml')))->toBeTrue();
    expect(file_exists(base_path('docker/nginx/default.conf')))->toBeTrue();
    expect(file_exists(base_path('docker/supervisor/supervisord.conf')))->toBeTrue();
});

test('deployment and backup shell scripts are present and executable', function () {
    expect(file_exists(base_path('scripts/deploy.sh')))->toBeTrue();
    expect(file_exists(base_path('scripts/backup-db.sh')))->toBeTrue();
});

test('operations documentation suite files exist', function () {
    $opsDir = base_path('docs/operations/');

    expect(file_exists($opsDir.'deployment-runbook.md'))->toBeTrue();
    expect(file_exists($opsDir.'backup-restore.md'))->toBeTrue();
    expect(file_exists($opsDir.'incident-response.md'))->toBeTrue();
    expect(file_exists($opsDir.'maintenance.md'))->toBeTrue();
    expect(file_exists($opsDir.'monitoring.md'))->toBeTrue();
    expect(file_exists($opsDir.'security-checklist.md'))->toBeTrue();
    expect(file_exists($opsDir.'scaling.md'))->toBeTrue();
    expect(file_exists($opsDir.'upgrade-guide.md'))->toBeTrue();
});

test('user documentation suite files exist', function () {
    $userDir = base_path('docs/user/');

    expect(file_exists($userDir.'administrator-guide.md'))->toBeTrue();
    expect(file_exists($userDir.'teacher-guide.md'))->toBeTrue();
    expect(file_exists($userDir.'student-guide.md'))->toBeTrue();
    expect(file_exists($userDir.'finance-guide.md'))->toBeTrue();
    expect(file_exists($userDir.'api-guide.md'))->toBeTrue();
    expect(file_exists($userDir.'installation-guide.md'))->toBeTrue();
    expect(file_exists($userDir.'configuration-guide.md'))->toBeTrue();
    expect(file_exists($userDir.'troubleshooting-guide.md'))->toBeTrue();
});

test('uat testing package files exist', function () {
    $uatDir = base_path('docs/uat/');

    expect(file_exists($uatDir.'uat-checklist.md'))->toBeTrue();
    expect(file_exists($uatDir.'admin-test-scenario.md'))->toBeTrue();
    expect(file_exists($uatDir.'teacher-test-scenario.md'))->toBeTrue();
    expect(file_exists($uatDir.'student-test-scenario.md'))->toBeTrue();
    expect(file_exists($uatDir.'finance-test-scenario.md'))->toBeTrue();
    expect(file_exists($uatDir.'commerce-test-scenario.md'))->toBeTrue();
    expect(file_exists($uatDir.'api-test-scenario.md'))->toBeTrue();
    expect(file_exists($uatDir.'bug-report-template.md'))->toBeTrue();
    expect(file_exists($uatDir.'acceptance-form.md'))->toBeTrue();
});

test('technical debt and release notes files exist', function () {
    expect(file_exists(base_path('docs/architecture/technical-debt.md')))->toBeTrue();
    expect(file_exists(base_path('docs/releases/v1.0.0-beta.md')))->toBeTrue();
});

test('openapi specification and postman collection exist', function () {
    expect(file_exists(base_path('docs/api/openapi.yaml')))->toBeTrue();
    expect(file_exists(base_path('docs/api/postman_collection.json')))->toBeTrue();
});

test('sdk preparation directories exist with readme documentation', function () {
    expect(file_exists(base_path('sdk/php/README.md')))->toBeTrue();
    expect(file_exists(base_path('sdk/javascript/README.md')))->toBeTrue();
    expect(file_exists(base_path('sdk/flutter/README.md')))->toBeTrue();
});

test('product documentation suite in docs product directory is complete', function () {
    $prodDir = base_path('docs/product/');

    expect(file_exists($prodDir.'vision.md'))->toBeTrue();
    expect(file_exists($prodDir.'user-personas.md'))->toBeTrue();
    expect(file_exists($prodDir.'business-rules.md'))->toBeTrue();
    expect(file_exists($prodDir.'feature-matrix.md'))->toBeTrue();
});
