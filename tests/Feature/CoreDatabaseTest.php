<?php

use App\Models\User;
use App\Modules\Academic\Enums\CourseLevel;
use App\Modules\Academic\Models\Course;
use App\Modules\Academic\Models\CourseCategory;
use App\Modules\Assessment\Enums\AttemptStatus;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\Test;
use App\Modules\CMS\Models\Page;
use App\Modules\Finance\Enums\PaymentStatus;
use App\Modules\Finance\Models\Payment;
use App\Modules\QuestionBank\Enums\TestType;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Modules\Reporting\Services\DashboardMetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('academic module creates category and course with ulid', function () {
    $category = CourseCategory::create([
        'name' => 'General English',
        'slug' => 'general-english',
        'description' => 'General English Description',
        'is_active' => true,
    ]);

    expect($category->id)->not()->toBeEmpty();

    $course = Course::create([
        'category_id' => $category->id,
        'title' => 'Basic English Grammar',
        'slug' => 'basic-english-grammar',
        'code' => 'ENG-101',
        'description' => 'Basic grammar course',
        'level' => CourseLevel::Beginner,
        'is_published' => true,
    ]);

    expect($course->category->id)->toBe($category->id);
    expect($course->level)->toBe(CourseLevel::Beginner);
});

test('question bank and assessment module links question banks and tests', function () {
    $user = User::factory()->create();

    $bank = QuestionBank::create([
        'title' => 'Sample Question Pool',
        'slug' => 'sample-question-pool',
        'created_by' => $user->id,
        'test_type' => TestType::General,
    ]);

    $test = Test::create([
        'title' => 'General Proficiency Test',
        'slug' => 'general-proficiency-test',
        'test_type' => TestType::General,
        'duration_minutes' => 60,
        'pass_score' => 60,
        'is_published' => true,
        'created_by' => $user->id,
    ]);

    $attempt = Attempt::create([
        'test_id' => $test->id,
        'user_id' => $user->id,
        'started_at' => now(),
        'status' => AttemptStatus::InProgress,
    ]);

    expect($bank->id)->not()->toBeEmpty();
    expect($test->attempts)->toHaveCount(1);
    expect($attempt->test->id)->toBe($test->id);
});

test('finance and cms module creates payments and pages', function () {
    $user = User::factory()->create();

    $payment = Payment::create([
        'reference_number' => 'INV-TEST-001',
        'user_id' => $user->id,
        'amount' => 500000.00,
        'currency' => 'IDR',
        'status' => PaymentStatus::Paid,
        'paid_at' => now(),
    ]);

    $page = Page::create([
        'title' => 'Terms of Service',
        'slug' => 'terms-of-service',
        'content' => 'Terms and conditions content...',
        'is_published' => true,
    ]);

    expect($payment->status)->toBe(PaymentStatus::Paid);
    expect($page->slug)->toBe('terms-of-service');
});

test('dashboard metrics service returns calculated metrics', function () {
    User::factory()->create();
    $service = new DashboardMetricsService;

    expect($service->getTotalUsers())->toBeGreaterThanOrEqual(1);
    expect($service->getActiveTestsCount())->toBeGreaterThanOrEqual(0);
    expect($service->getTotalRevenue())->toBeGreaterThanOrEqual(0.0);
});
