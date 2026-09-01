<?php

use App\Models\User;
use App\Notifications\EnterpriseSystemNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->rm = User::factory()->create(['name' => 'Dr. Eleanor Vance', 'status' => 'active']);
    $this->rm->assignRole('repository-manager');
});

test('TEST 01 & 02: Notification Center contains theme-adaptive classes and no hard-coded dark navy CSS', function () {
    $response = $this->actingAs($this->rm)->get(route('notifications.index'));

    $response->assertOk();
    $content = $response->getContent();

    // Verify adaptive classes exist
    expect($content)->toContain('bg-white dark:bg-slate-900')
        ->and($content)->toContain('text-slate-900 dark:text-white')
        ->and($content)->toContain('border-slate-200 dark:border-slate-800');

    // Verify old hardcoded dark styles are gone
    expect($content)->not->toContain('linear-gradient(135deg, #0f172a')
        ->and($content)->not->toContain('background: #0f172a')
        ->and($content)->not->toContain('.notif-header-card {');
});

test('TEST 03 & 04: Empty state renders adaptive Light and Dark Theme classes', function () {
    $response = $this->actingAs($this->rm)->get(route('notifications.index'));

    $response->assertOk();
    $content = $response->getContent();

    expect($content)->toContain('No Notifications Yet')
        ->and($content)->toContain('bg-slate-50 dark:bg-slate-900/50')
        ->and($content)->toContain('text-slate-500 dark:text-slate-400');
});

test('TEST 05 & 06: Populated notification cards render adaptive typography and surfaces', function () {
    $this->rm->notify(new EnterpriseSystemNotification(
        title: 'Assessment Submitted for Review',
        message: 'Teacher submitted TOEIC Test Alpha.',
        type: 'ASSESSMENT_SUBMITTED',
        priority: 'HIGH',
        entityType: 'Test',
        entityId: '01m1testalpha',
        targetUrl: '/admin/repository-manager/assessments'
    ));

    $response = $this->actingAs($this->rm)->get(route('notifications.index'));

    $response->assertOk();
    $content = $response->getContent();

    expect($content)->toContain('Assessment Submitted for Review')
        ->and($content)->toContain('Teacher submitted TOEIC Test Alpha.')
        ->and($content)->toContain('text-slate-900 dark:text-slate-100')
        ->and($content)->toContain('text-slate-600 dark:text-slate-300')
        ->and($content)->toContain('bg-white dark:bg-slate-900');
});

test('TEST 07 & 08: Top-nav dropdown and JSON feed behavior remain intact', function () {
    $this->rm->notify(new EnterpriseSystemNotification(
        title: 'New Review Alert',
        message: 'Review pending.',
        type: 'SYSTEM_ALERT',
        priority: 'NORMAL'
    ));

    $feedResponse = $this->actingAs($this->rm)->getJson(route('notifications.feed'));

    $feedResponse->assertOk()
        ->assertJsonFragment([
            'success' => true,
            'unread_count' => 1,
            'title' => 'New Review Alert',
        ]);
});

test('TEST 09: Read and unread toggle behavior unchanged', function () {
    $this->rm->notify(new EnterpriseSystemNotification(
        title: 'Item to Mark Read',
        message: 'Notification body.',
        type: 'SYSTEM_ALERT'
    ));

    $notif = $this->rm->unreadNotifications()->first();
    expect($this->rm->unreadNotifications()->count())->toBe(1);

    $readResponse = $this->actingAs($this->rm)->post(route('notifications.read', $notif->id));
    $this->rm->refresh();

    expect($this->rm->unreadNotifications()->count())->toBe(0);
});
