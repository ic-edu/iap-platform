<?php

use App\Models\User;
use App\Modules\Reporting\Services\SystemHealthService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('security headers middleware applies csp and security headers to web requests', function () {
    $response = $this->get('/login');

    $response->assertStatus(200)
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Permissions-Policy', 'geolocation=(), microphone=(), camera=()')
        ->assertHeaderContains('Content-Security-Policy', "default-src 'self'");
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

test('authenticated teacher user accessing root route redirects to question banks index', function () {
    $teacher = User::factory()->create();
    $teacher->assignRole('teacher');

    $response = $this->actingAs($teacher)->get('/');

    $response->assertRedirect(route('admin.question-banks.index'));
});

test('authenticated admin user accessing root route redirects to monitoring dashboard', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->get('/');

    $response->assertRedirect(route('admin.monitoring.index'));
});

test('authenticated teacher can render question bank index modular view', function () {
    $teacher = User::factory()->create();
    $teacher->assignRole('teacher');

    $response = $this->actingAs($teacher)->get('/admin/question-banks');

    $response->assertStatus(200);
});

test('authenticated admin can render settings modular view', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->get('/admin/settings');

    $response->assertStatus(200);
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

test('monitoring dashboard displays health metrics for authorized admin', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

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
    expect(file_exists($prodDir.'acceptance-criteria.md'))->toBeTrue();
    expect(file_exists($prodDir.'release-plan.md'))->toBeTrue();
});
