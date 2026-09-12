<?php

use App\Models\User;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestQuestion;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\QuestionBank\Models\Question;
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

    // Create a valid draft test
    $this->test = Test::create([
        'title'      => 'TOEIC Practice Test Alpha',
        'slug'       => 'toeic-practice-test-alpha-' . uniqid(),
        'type'       => 'simulator',
        'test_type'  => 'general',
        'status'     => 'draft',
        'is_active'  => true,
        'created_by' => $this->teacher->id,
    ]);

    $this->section5 = TestSection::create([
        'test_id'      => $this->test->id,
        'title'        => 'General Section',
        'section_type' => 'reading',
        'order'        => 1,
    ]);

    // Attach 2 valid questions to section5
    for ($i = 1; $i <= 2; $i++) {
        $q = Question::create([
            'prompt'        => "Sentence completion question stem {$i}",
            'section'       => 'reading',
            'question_type' => 'multiple_choice',
            'difficulty'    => 'medium',
            'points'        => 1,
        ]);
        $q->choices()->createMany([
            ['label' => 'A', 'content' => 'Option A', 'is_correct' => true, 'order' => 1],
            ['label' => 'B', 'content' => 'Option B', 'is_correct' => false, 'order' => 2],
            ['label' => 'C', 'content' => 'Option C', 'is_correct' => false, 'order' => 3],
            ['label' => 'D', 'content' => 'Option D', 'is_correct' => false, 'order' => 4],
        ]);
        TestQuestion::create([
            'test_section_id' => $this->section5->id,
            'question_id'     => $q->id,
            'order'           => $i,
            'points'          => 1,
        ]);
    }
});

/*
|--------------------------------------------------------------------------
| First Submission & Notification Dispatch Tests
|--------------------------------------------------------------------------
*/

test('TEST 01: Valid Teacher submission transitions assessment to pending_approval', function () {
    $response = $this->actingAs($this->teacher)
        ->post(route('teacher.tests.resubmit', $this->test->id));

    $response->assertRedirect(route('teacher.tests.show', $this->test->id));
    $this->test->refresh();
    expect($this->test->status)->toBe('pending_approval')
        ->and($this->test->is_published)->toBeFalse();
});

test('TEST 02 & 03: Repository Manager receives unread database notification', function () {
    $this->actingAs($this->teacher)
        ->post(route('teacher.tests.resubmit', $this->test->id));

    expect($this->rm1->notifications()->count())->toBe(1)
        ->and($this->rm1->unreadNotifications()->count())->toBe(1);

    $notif = $this->rm1->unreadNotifications()->first();
    expect($notif->read_at)->toBeNull();
});

test('TEST 04 & 05: Notification title, type, and payload identify assessment submission', function () {
    $this->actingAs($this->teacher)
        ->post(route('teacher.tests.resubmit', $this->test->id));

    $notif = $this->rm1->notifications()->first();
    $data = $notif->data;

    expect($data['title'])->toBe('Assessment Submitted for Review')
        ->and($data['notification_type'])->toBe('ASSESSMENT_SUBMITTED')
        ->and($data['priority'])->toBe('HIGH')
        ->and($data['entity_type'])->toBe('Test')
        ->and($data['entity_id'])->toBe((string) $this->test->id)
        ->and($data['message'])->toContain('Teacher Jane')
        ->and($data['message'])->toContain('TOEIC Practice Test Alpha');
});

test('TEST 06: Target URL resolves to RM-authorized review destination', function () {
    $this->actingAs($this->teacher)
        ->post(route('teacher.tests.resubmit', $this->test->id));

    $notif = $this->rm1->notifications()->first();
    $controller = app(\App\Http\Controllers\NotificationController::class);
    $resolvedUrl = $controller->resolveTargetUrl($notif, $this->rm1);

    expect($resolvedUrl)->toBe('/admin/repository-manager/assessments/' . $this->test->id . '/review');
});

test('TEST 07: Multiple RM users each receive exactly one notification', function () {
    $this->actingAs($this->teacher)
        ->post(route('teacher.tests.resubmit', $this->test->id));

    expect($this->rm1->notifications()->count())->toBe(1)
        ->and($this->rm2->notifications()->count())->toBe(1);
});

test('TEST 08, 09, 10: Admin, Student, and Finance do not receive routine submission notification', function () {
    $this->actingAs($this->teacher)
        ->post(route('teacher.tests.resubmit', $this->test->id));

    expect($this->admin->notifications()->count())->toBe(0)
        ->and($this->student->notifications()->count())->toBe(0)
        ->and($this->finance->notifications()->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Invalid / Unauthorized Submission Tests (No False Notifications)
|--------------------------------------------------------------------------
*/

test('TEST 11: Invalid assessment submission creates no RM notification', function () {
    // Add an invalid question with 0 choices
    $invalidQ = Question::create([
        'prompt'        => '',
        'section'       => 'reading',
        'part_number'   => 5,
        'question_type' => 'multiple_choice',
        'difficulty'    => 'medium',
        'points'        => 1,
    ]);
    TestQuestion::create([
        'test_section_id' => $this->section5->id,
        'question_id'     => $invalidQ->id,
        'order'           => 99,
        'points'          => 1,
    ]);

    $response = $this->actingAs($this->teacher)
        ->post(route('teacher.tests.resubmit', $this->test->id));

    $response->assertSessionHas('error');
    $this->test->refresh();
    expect($this->test->status)->toBe('draft');
    expect($this->rm1->notifications()->count())->toBe(0);
});

test('TEST 12: Unauthorized submission creates no RM notification', function () {
    $otherTeacher = User::factory()->create(['status' => 'active']);
    $otherTeacher->assignRole('teacher');

    $response = $this->actingAs($otherTeacher)
        ->post(route('teacher.tests.resubmit', $this->test->id));

    $response->assertStatus(403);
    $this->test->refresh();
    expect($this->test->status)->toBe('draft');
    expect($this->rm1->notifications()->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Resubmission Tests
|--------------------------------------------------------------------------
*/

test('TEST 14 & 15 & 16: Assessment returned for revision can be resubmitted and creates new distinguished notification', function () {
    // Initial submission
    $this->actingAs($this->teacher)
        ->post(route('teacher.tests.resubmit', $this->test->id));
    expect($this->rm1->notifications()->count())->toBe(1);

    // RM requests revision
    $this->actingAs($this->rm1)
        ->post(route('admin.repository-manager.assessment-revision', $this->test->id), [
            'notes' => 'Please check question 2 choices.',
        ]);
    $this->test->refresh();
    expect($this->test->status)->toBe('needs_revision');

    // Teacher resubmits
    $this->actingAs($this->teacher)
        ->post(route('teacher.tests.resubmit', $this->test->id));

    $this->test->refresh();
    expect($this->test->status)->toBe('pending_approval');
    expect($this->rm1->notifications()->count())->toBe(2);

    $resubNotif = $this->rm1->notifications()->where('data->notification_type', 'ASSESSMENT_RESUBMITTED')->first();
    expect($resubNotif)->not->toBeNull();
    $data = $resubNotif->data;
    expect($data['title'])->toBe('Assessment Resubmitted for Review')
        ->and($data['notification_type'])->toBe('ASSESSMENT_RESUBMITTED')
        ->and($data['message'])->toContain('resubmitted');
});

test('TEST 17: One legitimate resubmission creates exactly one notification per RM', function () {
    $this->test->update(['status' => 'needs_revision']);

    $this->actingAs($this->teacher)
        ->post(route('teacher.tests.resubmit', $this->test->id));

    expect($this->rm1->notifications()->count())->toBe(1)
        ->and($this->rm2->notifications()->count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Bell & Feed Tests
|--------------------------------------------------------------------------
*/

test('TEST 18 & 19 & 20 & 21 & 22: RM bell unread count, feed response, and mark-read mechanics', function () {
    $this->actingAs($this->teacher)
        ->post(route('teacher.tests.resubmit', $this->test->id));

    // Bell unread count
    expect($this->rm1->unreadNotifications()->count())->toBe(1);

    // Feed response
    $feedRes = $this->actingAs($this->rm1)
        ->getJson(route('notifications.feed'));

    $feedRes->assertOk();
    $feedRes->assertJsonFragment([
        'title'             => 'Assessment Submitted for Review',
        'notification_type' => 'ASSESSMENT_SUBMITTED',
        'unread'            => true,
    ]);

    $notifId = $this->rm1->unreadNotifications()->first()->id;

    // Mark as read
    $markRes = $this->actingAs($this->rm1)
        ->post(route('notifications.read', $notifId));

    expect($this->rm1->unreadNotifications()->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Regression Tests (Media, Request, Decisions, KPI, Queue)
|--------------------------------------------------------------------------
*/

test('TEST 23: Media submission to RM remains functional', function () {
    $media = \App\Models\MediaAsset::create([
        'title'         => 'Listening Clip 1',
        'filename'      => 'clip1.mp3',
        'original_name' => 'clip1.mp3',
        'path'          => 'audio/clip1.mp3',
        'file_type'     => 'audio/mpeg',
        'mime_type'     => 'audio/mpeg',
        'file_size'     => 1024,
        'sha256_hash'   => hash('sha256', 'dummy'),
        'status'        => 'draft',
        'uploaded_by'   => $this->teacher->id,
    ]);

    $this->actingAs($this->teacher)
        ->post(route('admin.media.submit-review', $media->id));

    expect($this->rm1->notifications()->where('data->entity_type', 'MediaAsset')->count())->toBe(1);
});

test('TEST 24: AssessmentRequest creation notifies RM', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.assessment-requests.store'), [
            'title'       => 'Need Business English Test',
            'test_type'   => 'toeic',
            'target_part' => 'part_5',
            'notes'       => 'Urgent request for Q3',
        ]);

    expect($this->rm1->notifications()->where('data->notification_type', 'ASSESSMENT_REQUEST_SUBMITTED')->count())->toBe(1);
});

test('TEST 25: RM assessment approval notifies Teacher', function () {
    $this->test->update(['status' => 'pending_approval']);

    $this->actingAs($this->rm1)
        ->post(route('admin.repository-manager.assessment-approve', $this->test->id));

    expect($this->teacher->notifications()->where('data->notification_type', 'ASSESSMENT_APPROVED')->count())->toBe(1);
});

test('TEST 26: RM revision request notifies Teacher', function () {
    $this->test->update(['status' => 'pending_approval']);

    $this->actingAs($this->rm1)
        ->post(route('admin.repository-manager.assessment-revision', $this->test->id), [
            'notes' => 'Please adjust choices for question 1',
        ]);

    expect($this->teacher->notifications()->where('data->notification_type', 'ASSESSMENT_REVISION_REQUESTED')->count())->toBe(1);
});

test('TEST 29 & 30: Assessment Approval KPI and Queue queries remain accurate and unchanged', function () {
    $workflowService = app(\App\Services\AssessmentWorkflowService::class);
    $initialMetrics = $workflowService->getRepositoryManagerMetrics();
    expect($initialMetrics['pendingAssessmentsCount'])->toBe(0);

    // Submit
    $this->actingAs($this->teacher)
        ->post(route('teacher.tests.resubmit', $this->test->id));

    $afterMetrics = $workflowService->getRepositoryManagerMetrics();
    expect($afterMetrics['pendingAssessmentsCount'])->toBe(1);

    // Approval Queue view response
    $queueRes = $this->actingAs($this->rm1)
        ->get(route('admin.repository-manager.assessment-approval'));
    $queueRes->assertOk();
    $queueRes->assertSee('TOEIC Practice Test Alpha');
});
