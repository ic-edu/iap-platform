<?php

use App\Models\MediaAsset;
use App\Models\RepositoryActivityLog;
use App\Models\RepositoryRevisionItem;
use App\Models\RepositoryRevisionRequest;
use App\Models\User;
use App\Modules\Assessment\Models\Test;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    // Create Teacher
    $this->teacher = User::factory()->create(['name' => 'Teacher Jane', 'status' => 'active']);
    $this->teacher->assignRole('teacher');

    // Create Repository Managers
    $this->rm1 = User::factory()->create(['name' => 'Dr. Eleanor Vance (RM 1)', 'status' => 'active']);
    $this->rm1->assignRole('repository-manager');

    $this->rm2 = User::factory()->create(['name' => 'Dr. Marcus Brody (RM 2)', 'status' => 'active']);
    $this->rm2->assignRole('repository-manager');

    // Create Admin, Student, Finance users
    $this->admin = User::factory()->create(['name' => 'Admin Alex', 'status' => 'active']);
    $this->admin->assignRole('admin');

    $this->student = User::factory()->create(['name' => 'Student Sam', 'status' => 'active']);
    $this->student->assignRole('student');

    $this->finance = User::factory()->create(['name' => 'Finance Frank', 'status' => 'active']);
    $this->finance->assignRole('finance');

    // Create Question Bank & Question
    $this->bank = QuestionBank::create([
        'title'           => 'TOEFL iBT Reading Starter Pool',
        'slug'            => 'toefl-ibt-reading-starter-' . uniqid(),
        'test_type'       => 'toefl',
        'current_version' => '1.0',
        'status'          => 'approved',
        'created_by'      => $this->teacher->id,
    ]);

    $this->question = Question::create([
        'question_bank_id' => $this->bank->id,
        'prompt'           => 'Which of the following is true about photosynthesis?',
        'section'          => 'reading',
        'part_number'      => 1,
        'question_type'    => 'multiple_choice',
        'difficulty'       => 'medium',
        'points'           => 1,
    ]);
    $this->question->choices()->createMany([
        ['label' => 'A', 'content' => 'Plants absorb sunlight', 'is_correct' => true, 'order' => 1],
        ['label' => 'B', 'content' => 'Plants absorb nitrogen only', 'is_correct' => false, 'order' => 2],
        ['label' => 'C', 'content' => 'Plants produce carbon dioxide', 'is_correct' => false, 'order' => 3],
        ['label' => 'D', 'content' => 'Plants require complete darkness', 'is_correct' => false, 'order' => 4],
    ]);
});

/*
|--------------------------------------------------------------------------
| New Revision Request Tests (TEST 01 - 12)
|--------------------------------------------------------------------------
*/

test('TEST 01 & 02 & 03: Authorized Teacher can request revision, status is OPEN, appears in RM queue', function () {
    $response = $this->actingAs($this->teacher)
        ->post(route('teacher.repository-revisions.request'), [
            'question_bank_id' => $this->bank->id,
            'question_id'      => $this->question->id,
            'notes'            => 'Please update option B wording.',
        ]);

    $response->assertRedirect();
    $revRequest = RepositoryRevisionRequest::where('question_bank_id', $this->bank->id)->first();
    expect($revRequest)->not->toBeNull()
        ->and($revRequest->status)->toBe('OPEN')
        ->and($revRequest->teacher_id)->toBe($this->teacher->id);

    // RM Dashboard teacherSubmissionsQueue
    $rmDashboard = $this->actingAs($this->rm1)->get(route('admin.repository-manager.dashboard'));
    $rmDashboard->assertOk();
    $rmDashboard->assertSee('Teacher Revision Queue');
    $rmDashboard->assertSee('TOEFL iBT Reading Starter Pool');
});

test('TEST 04 & 05 & 06 & 07 & 08: RM receives unread notification with correct payload and deep link', function () {
    $this->actingAs($this->teacher)
        ->post(route('teacher.repository-revisions.request'), [
            'question_bank_id' => $this->bank->id,
            'question_id'      => $this->question->id,
            'notes'            => 'Need review on prompt phrasing.',
        ]);

    expect($this->rm1->notifications()->count())->toBe(1)
        ->and($this->rm1->unreadNotifications()->count())->toBe(1);

    $notif = $this->rm1->unreadNotifications()->first();
    expect($notif->read_at)->toBeNull();

    $data = $notif->data;
    expect($data['title'])->toBe('New Teacher Revision Request')
        ->and($data['notification_type'])->toBe('REPOSITORY_REVISION_REQUESTED')
        ->and($data['priority'])->toBe('HIGH')
        ->and($data['entity_type'])->toBe('QuestionBank')
        ->and($data['entity_id'])->toBe((string) $this->bank->id)
        ->and($data['message'])->toContain('Teacher Jane')
        ->and($data['message'])->toContain('TOEFL iBT Reading Starter Pool');

    // Deep link resolver
    $controller = app(\App\Http\Controllers\NotificationController::class);
    $resolvedUrl = $controller->resolveTargetUrl($notif, $this->rm1);
    expect($resolvedUrl)->toBe('/admin/repository-manager/questions/' . $this->bank->id);
});

test('TEST 09, 10, 11, 12: Multiple RM users notified, non-RM users receive zero notifications', function () {
    $this->actingAs($this->teacher)
        ->post(route('teacher.repository-revisions.request'), [
            'question_bank_id' => $this->bank->id,
            'question_id'      => $this->question->id,
            'notes'            => 'Revision note for multiple RMs.',
        ]);

    expect($this->rm1->notifications()->count())->toBe(1)
        ->and($this->rm2->notifications()->count())->toBe(1)
        ->and($this->admin->notifications()->count())->toBe(0)
        ->and($this->student->notifications()->count())->toBe(0)
        ->and($this->finance->notifications()->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Validation & Authorization Tests (TEST 13, 14, 15)
|--------------------------------------------------------------------------
*/

test('TEST 13 & 14 & 15: Invalid or unauthorized revision requests create no notifications', function () {
    // Missing required fields
    $response = $this->actingAs($this->teacher)
        ->post(route('teacher.repository-revisions.request'), [
            'question_bank_id' => '',
            'notes'            => '',
        ]);
    $response->assertSessionHasErrors(['question_bank_id', 'notes']);
    expect($this->rm1->notifications()->count())->toBe(0);

    // Guest user
    Auth::logout();
    $guestResponse = $this->post(route('teacher.repository-revisions.request'), [
        'question_bank_id' => $this->bank->id,
        'notes'            => 'Unauthorized note.',
    ]);
    expect($this->rm1->notifications()->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Resubmission Tests (TEST 16 - 21)
|--------------------------------------------------------------------------
*/

test('TEST 16 & 17 & 18 & 19 & 20 & 21: Resubmission creates canonical EnterpriseSystemNotification without legacy duplicates', function () {
    // Create revision request and item
    $revRequest = RepositoryRevisionRequest::create([
        'question_bank_id' => $this->bank->id,
        'teacher_id'       => $this->teacher->id,
        'requested_by_id'  => $this->rm1->id,
        'status'           => 'OPEN',
        'notes'            => 'Please revise option D.',
    ]);
    RepositoryRevisionItem::create([
        'repository_revision_request_id' => $revRequest->id,
        'question_bank_id'               => $this->bank->id,
        'question_id'                    => $this->question->id,
        'feedback'                       => 'Please revise option D.',
        'finding_type'                   => 'teacher_revision_request',
        'severity'                       => 'medium',
        'status'                         => 'CLOSED', // All closed to allow resubmit
    ]);

    // Clear notifications
    $this->rm1->notifications()->delete();
    $this->rm2->notifications()->delete();

    // Teacher resubmits
    $response = $this->actingAs($this->teacher)
        ->post(route('teacher.repository-revisions.resubmit', $revRequest->id));

    $response->assertRedirect();
    $revRequest->refresh();
    expect($revRequest->status)->toBe('RESUBMITTED');

    // RM receives canonical notification
    expect($this->rm1->notifications()->count())->toBe(1)
        ->and($this->rm2->notifications()->count())->toBe(1);

    $notif = $this->rm1->notifications()->first();
    expect($notif->type)->toBe(\App\Notifications\EnterpriseSystemNotification::class)
        ->and($notif->data['title'])->toBe('Teacher Revision Resubmitted')
        ->and($notif->data['notification_type'])->toBe('REPOSITORY_RESUBMITTED')
        ->and($notif->data['priority'])->toBe('HIGH');
});

/*
|--------------------------------------------------------------------------
| Bell & Feed Tests (TEST 22 - 26)
|--------------------------------------------------------------------------
*/

test('TEST 22 & 23 & 24 & 25 & 26: RM bell count, feed endpoint, and read/unread toggle', function () {
    $this->actingAs($this->teacher)
        ->post(route('teacher.repository-revisions.request'), [
            'question_bank_id' => $this->bank->id,
            'question_id'      => $this->question->id,
            'notes'            => 'Testing feed response.',
        ]);

    expect($this->rm1->unreadNotifications()->count())->toBe(1);

    $feedRes = $this->actingAs($this->rm1)->getJson(route('notifications.feed'));
    $feedRes->assertOk();
    $feedRes->assertJsonFragment([
        'title'             => 'New Teacher Revision Request',
        'notification_type' => 'REPOSITORY_REVISION_REQUESTED',
        'unread'            => true,
    ]);

    $notifId = $this->rm1->unreadNotifications()->first()->id;
    $markRes = $this->actingAs($this->rm1)->post(route('notifications.read', $notifId));
    $this->rm1->refresh();

    expect($this->rm1->unreadNotifications()->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Regression Tests (TEST 27 - 34)
|--------------------------------------------------------------------------
*/

test('TEST 27 - 34: Media, Assessment, AssessmentRequest, and ActivityLog regressions remain intact', function () {
    // 1. Media submission
    $media = MediaAsset::create([
        'title'         => 'Listening Sample 1',
        'filename'      => 'sample1.mp3',
        'original_name' => 'sample1.mp3',
        'path'          => 'audio/sample1.mp3',
        'file_type'     => 'audio/mpeg',
        'mime_type'     => 'audio/mpeg',
        'file_size'     => 1024,
        'sha256_hash'   => hash('sha256', 'dummy1'),
        'status'        => 'draft',
        'uploaded_by'   => $this->teacher->id,
    ]);
    $this->actingAs($this->teacher)->post(route('admin.media.submit-review', $media->id));
    expect($this->rm1->notifications()->where('data->entity_type', 'MediaAsset')->count())->toBe(1);

    // 2. Assessment Request
    $this->actingAs($this->admin)->post(route('admin.assessment-requests.store'), [
        'title'       => 'IELTS General Practice Test',
        'test_type'   => 'ielts',
        'target_part' => 'part_1',
        'notes'       => 'Q4 intake',
    ]);
    expect($this->rm1->notifications()->where('data->notification_type', 'ASSESSMENT_REQUEST_SUBMITTED')->count())->toBe(1);

    // 3. Activity Log
    $this->actingAs($this->teacher)->post(route('teacher.repository-revisions.request'), [
        'question_bank_id' => $this->bank->id,
        'question_id'      => $this->question->id,
        'notes'            => 'Checking audit log record.',
    ]);
    expect(RepositoryActivityLog::where('action', 'teacher_requested_revision')->count())->toBe(1);
});
