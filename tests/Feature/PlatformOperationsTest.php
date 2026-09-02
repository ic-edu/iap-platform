<?php

use App\Models\User;
use App\Modules\Academic\Engines\EnrollmentEngine;
use App\Modules\Academic\Enums\CourseLevel;
use App\Modules\Academic\Enums\EnrollmentStatus;
use App\Modules\Academic\Events\EnrollmentCancelled;
use App\Modules\Academic\Events\EnrollmentCreated;
use App\Modules\Academic\Models\Course;
use App\Modules\Academic\Models\CourseCategory;
use App\Modules\Academic\Models\CourseEnrollment;
use App\Modules\Assessment\Engines\AssignmentEngine;
use App\Modules\Assessment\Engines\ReminderEngine;
use App\Modules\Assessment\Engines\SchedulingEngine;
use App\Modules\Assessment\Enums\AttemptStatus;
use App\Modules\Assessment\Events\AssignmentRevoked;
use App\Modules\Assessment\Events\ReminderSent;
use App\Modules\Assessment\Events\TestAssigned;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\Test;
use App\Modules\QuestionBank\Enums\TestType;
use App\Modules\Reporting\Services\SystemHealthService;
use App\Services\BatchOperationService;
use App\Services\CalendarService;
use App\Services\ImportEngine;
use App\Services\NotificationService;
use App\Services\TimelineService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('enrollment engine enrolls student and cancels enrollment', function () {
    Event::fake([EnrollmentCreated::class, EnrollmentCancelled::class]);
    $engine = new EnrollmentEngine;
    $student = User::factory()->create();
    $cat = CourseCategory::create(['name' => 'Cat 1', 'slug' => 'cat-1']);
    $course = Course::create(['title' => 'Course 1', 'slug' => 'course-1', 'code' => 'C1', 'category_id' => $cat->id, 'level' => CourseLevel::Beginner, 'is_published' => true]);

    $enrollment = $engine->enrollStudent($course, $student);
    expect($enrollment->status)->toBe(EnrollmentStatus::Active);
    Event::assertDispatched(EnrollmentCreated::class);

    $cancelled = $engine->cancelEnrollment($enrollment);
    expect($cancelled->status)->toBe(EnrollmentStatus::Cancelled);
    Event::assertDispatched(EnrollmentCancelled::class);
});

test('enrollment engine handles duplicate enrollment idempotently', function () {
    $engine = new EnrollmentEngine;
    $student = User::factory()->create();
    $cat = CourseCategory::create(['name' => 'Cat Dup', 'slug' => 'cat-dup']);
    $course = Course::create(['title' => 'Course Dup', 'slug' => 'course-dup', 'code' => 'CDUP', 'category_id' => $cat->id, 'level' => CourseLevel::Beginner, 'is_published' => true]);

    $e1 = $engine->enrollStudent($course, $student);
    $e2 = $engine->enrollStudent($course, $student);

    expect($e1->id)->toBe($e2->id);
});

test('enrollment engine bulk enrolls students', function () {
    $engine = new EnrollmentEngine;
    $s1 = User::factory()->create();
    $s2 = User::factory()->create();
    $cat = CourseCategory::create(['name' => 'Cat 2', 'slug' => 'cat-2']);
    $course = Course::create(['title' => 'Course 2', 'slug' => 'course-2', 'code' => 'C2', 'category_id' => $cat->id, 'level' => CourseLevel::Beginner, 'is_published' => true]);

    $enrollments = $engine->bulkEnroll($course, [$s1->id, $s2->id]);
    expect(count($enrollments))->toBe(2);
});

test('enrollment engine lists enrollment history correctly', function () {
    $engine = new EnrollmentEngine;
    $student = User::factory()->create();
    $cat = CourseCategory::create(['name' => 'Cat Hist', 'slug' => 'cat-hist']);
    $course = Course::create(['title' => 'Course Hist', 'slug' => 'course-hist', 'code' => 'CHIST', 'category_id' => $cat->id, 'level' => CourseLevel::Beginner, 'is_published' => true]);

    $engine->enrollStudent($course, $student);
    $historyCount = CourseEnrollment::where('user_id', $student->id)->count();

    expect($historyCount)->toBe(1);
});

test('assignment engine assigns and revokes test assignments', function () {
    Event::fake([TestAssigned::class, AssignmentRevoked::class]);
    $engine = new AssignmentEngine;
    $user = User::factory()->create();
    $test = Test::create(['title' => 'Assign Test', 'slug' => 'assign-test', 'test_type' => TestType::General, 'duration_minutes' => 60, 'pass_score' => 70, 'status' => 'published', 'status' => 'published', 'is_published' => true, 'created_by' => $user->id]);

    $attempt = $engine->assignToUser($test, $user);
    expect($attempt->user_id)->toBe($user->id);
    Event::assertDispatched(TestAssigned::class);

    $revoked = $engine->revokeAssignment($attempt);
    expect($revoked->status)->toBeIn(['unassigned', AttemptStatus::Cancelled]);
    Event::assertDispatched(AssignmentRevoked::class);
});

test('assignment engine assigns test to enrolled course students', function () {
    $engine = new AssignmentEngine;
    $enrollEngine = new EnrollmentEngine;
    $student = User::factory()->create();
    $teacher = User::factory()->create();
    $cat = CourseCategory::create(['name' => 'Cat Course', 'slug' => 'cat-course']);
    $course = Course::create(['title' => 'Course Target', 'slug' => 'course-target', 'code' => 'CT', 'category_id' => $cat->id, 'level' => CourseLevel::Beginner, 'is_published' => true]);
    $enrollEngine->enrollStudent($course, $student);

    $test = Test::create(['title' => 'Course Exam', 'slug' => 'course-exam', 'test_type' => TestType::General, 'duration_minutes' => 60, 'pass_score' => 70, 'status' => 'published', 'status' => 'published', 'is_published' => true, 'created_by' => $teacher->id]);

    $attempts = $engine->assignToCourse($test, $course->id);
    expect(count($attempts))->toBe(1);
    expect($attempts[0]->user_id)->toBe($student->id);
});

test('scheduling engine validates candidate test access', function () {
    $engine = new SchedulingEngine;
    $user = User::factory()->create();
    $draftTest = Test::create(['title' => 'Draft Test', 'slug' => 'draft-test', 'test_type' => TestType::General, 'duration_minutes' => 60, 'pass_score' => 70, 'is_published' => false, 'created_by' => $user->id]);

    $validation = $engine->validateCandidateAccess($draftTest, $user);
    expect($validation['can_access'])->toBeFalse();

    $pubTest = Test::create(['title' => 'Pub Test', 'slug' => 'pub-test', 'test_type' => TestType::General, 'duration_minutes' => 60, 'pass_score' => 70, 'status' => 'published', 'is_published' => true, 'created_by' => $user->id]);
    $validation2 = $engine->validateCandidateAccess($pubTest, $user);
    expect($validation2['can_access'])->toBeTrue();
});

test('scheduling engine blocks access when max attempts reached', function () {
    $engine = new SchedulingEngine;
    $user = User::factory()->create();
    $test = Test::create(['title' => 'Max Test', 'slug' => 'max-test', 'test_type' => TestType::General, 'duration_minutes' => 60, 'pass_score' => 70, 'status' => 'published', 'is_published' => true, 'created_by' => $user->id]);

    for ($i = 0; $i < 3; $i++) {
        Attempt::create(['test_id' => $test->id, 'user_id' => $user->id, 'status' => AttemptStatus::Submitted]);
    }

    $validation = $engine->validateCandidateAccess($test, $user);
    expect($validation['can_access'])->toBeFalse();
    expect($validation['reason'])->toContain('Maximum allowed attempt limit');
});

test('scheduling engine allows access when attempt count is below maximum', function () {
    $engine = new SchedulingEngine;
    $user = User::factory()->create();
    $test = Test::create(['title' => 'Allowed Test', 'slug' => 'allowed-test', 'test_type' => TestType::General, 'duration_minutes' => 60, 'pass_score' => 70, 'status' => 'published', 'is_published' => true, 'created_by' => $user->id]);

    Attempt::create(['test_id' => $test->id, 'user_id' => $user->id, 'status' => AttemptStatus::Submitted]);

    $validation = $engine->validateCandidateAccess($test, $user);
    expect($validation['can_access'])->toBeTrue();
});

test('calendar service retrieves upcoming assessment events', function () {
    $service = new CalendarService;
    $user = User::factory()->create();
    Test::create(['title' => 'Cal Test', 'slug' => 'cal-test', 'test_type' => TestType::General, 'duration_minutes' => 60, 'pass_score' => 70, 'status' => 'published', 'is_published' => true, 'created_by' => $user->id]);

    $events = $service->getUpcomingEvents($user);
    expect(count($events))->toBeGreaterThanOrEqual(1);
});

test('notification service handles notification send request', function () {
    $notifService = new NotificationService;
    $user = User::factory()->create();

    $notifService->send($user, 'System Message', 'Test body notification');
    expect(true)->toBeTrue();
});

test('reminder engine dispatches test reminder', function () {
    Event::fake([ReminderSent::class]);
    $notifService = new NotificationService;
    $engine = new ReminderEngine($notifService);
    $user = User::factory()->create();

    $engine->sendTestReminderTomorrow($user, 'Final Exam');

    Event::assertDispatched(ReminderSent::class);
});

test('system health service checks operational health', function () {
    $healthService = new SystemHealthService;
    $report = $healthService->checkHealth();

    expect($report['status'])->toBe('healthy');
    expect($report['db_connection'])->toBe('connected');
});

test('import engine previews and imports student csv', function () {
    $engine = new ImportEngine;
    $csv = "name,email\nStudent One,student1@example.com\nStudent Two,student2@example.com";

    $preview = $engine->previewCsv($csv);
    expect(count($preview))->toBe(2);

    $importRes = $engine->importStudents($preview);
    expect($importRes['imported'])->toBe(2);
    expect(User::where('email', 'student1@example.com')->exists())->toBeTrue();
});

test('import engine handles empty or invalid csv rows gracefully', function () {
    $engine = new ImportEngine;
    $csv = "name,email\nInvalid Row Without Email";

    $preview = $engine->previewCsv($csv);
    $importRes = $engine->importStudents($preview);

    expect($importRes['errors'])->toBe(0);
});

test('batch operation service bulk publishes tests', function () {
    $service = new BatchOperationService;
    $user = User::factory()->create();
    $t1 = Test::create(['title' => 'T1', 'slug' => 't1', 'test_type' => TestType::General, 'duration_minutes' => 60, 'pass_score' => 70, 'is_published' => false, 'created_by' => $user->id]);

    $count = $service->bulkPublishTests([$t1->id]);
    expect($count)->toBe(1);
    expect($t1->fresh()->is_published)->toBeTrue();
});

test('batch operation service handles empty array safely', function () {
    $service = new BatchOperationService;

    $count = $service->bulkPublishTests([]);
    expect($count)->toBe(0);
});

test('timeline service generates activity log timeline for candidate', function () {
    $service = new TimelineService;
    $user = User::factory()->create();

    $timeline = $service->getUserTimeline($user);
    expect(is_array($timeline))->toBeTrue();
});

test('calendar service handles null user safely', function () {
    $service = new CalendarService;
    $events = $service->getUpcomingEvents(null);

    expect(is_array($events))->toBeTrue();
});

test('import engine handles empty csv string without failure', function () {
    $engine = new ImportEngine;
    $preview = $engine->previewCsv('');

    expect($preview)->toBeEmpty();
});
