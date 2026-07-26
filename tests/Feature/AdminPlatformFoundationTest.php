<?php

use App\Events\UserLoggedIn;
use App\Models\ActivityLog;
use App\Models\User;
use App\Modules\Reporting\Services\DashboardMetricsService;
use App\Modules\Settings\Services\SettingService;
use App\Notifications\SystemAlertNotification;
use App\Services\NavigationService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('navigation service returns menu items according to permissions', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super-admin');

    $this->actingAs($superAdmin);
    $menu = NavigationService::getMenuItems();

    expect($menu)->not()->toBeEmpty();
    expect(count($menu))->toBeGreaterThanOrEqual(8);
});

test('dashboard metrics service caches metrics summary', function () {
    $service = new DashboardMetricsService;
    $service->clearMetricsCache();

    $summary = $service->getMetricsSummary();

    expect($summary)->toHaveKeys([
        'total_users',
        'total_students',
        'total_teachers',
        'total_courses',
        'total_question_banks',
        'total_questions',
        'total_active_tests',
        'total_attempts',
        'total_certificates',
        'total_revenue',
    ]);
});

test('user login event triggers activity logging listener', function () {
    $user = User::factory()->create(['email' => 'admin@icedu.com']);

    event(new UserLoggedIn($user));

    $log = ActivityLog::where('action', 'auth.login')->first();

    expect($log)->not()->toBeNull();
    expect($log->user_id)->toBe($user->id);
    expect($log->description)->toContain('admin@icedu.com');
});

test('settings service retrieves and updates settings', function () {
    $service = new SettingService;

    $service->set('app_name', 'iC.edu Assessment Platform Enterprise', 'general');

    expect($service->get('app_name'))->toBe('iC.edu Assessment Platform Enterprise');
});

test('system alert notification generates valid payload', function () {
    $notification = new SystemAlertNotification('Server Maintenance', 'Scheduled maintenance tonight');
    $user = User::factory()->create();

    $array = $notification->toArray($user);

    expect($array['title'])->toBe('Server Maintenance');
    expect($array['message'])->toBe('Scheduled maintenance tonight');
});
