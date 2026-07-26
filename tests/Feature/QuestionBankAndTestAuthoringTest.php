<?php

use App\Models\User;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\Assessment\Services\TestBuilderService;
use App\Modules\QuestionBank\Enums\DifficultyLevel;
use App\Modules\QuestionBank\Enums\QuestionType;
use App\Modules\QuestionBank\Enums\SectionType;
use App\Modules\QuestionBank\Enums\TestType;
use App\Modules\QuestionBank\Models\Passage;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Services\MediaService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('passage can be created and linked to multiple questions', function () {
    $user = User::factory()->create();

    $bank = QuestionBank::create([
        'title' => 'Reading Bank',
        'slug' => 'reading-bank',
        'created_by' => $user->id,
        'test_type' => TestType::Toeic,
    ]);

    $passage = Passage::create([
        'question_bank_id' => $bank->id,
        'title' => 'Climate Change Article',
        'content' => 'Long reading text passage content...',
    ]);

    $q1 = Question::create([
        'question_bank_id' => $bank->id,
        'passage_id' => $passage->id,
        'prompt' => 'What is the main topic of the passage?',
        'section' => SectionType::Reading,
        'question_type' => QuestionType::MultipleChoice,
        'difficulty' => DifficultyLevel::Medium,
        'points' => 5,
    ]);

    expect($passage->questions)->toHaveCount(1);
    expect($q1->passage->id)->toBe($passage->id);
    expect($q1->difficulty)->toBe(DifficultyLevel::Medium);
});

test('media service uploads and returns file url', function () {
    Storage::fake('public');

    $service = new MediaService;
    $file = UploadedFile::fake()->image('diagram.png');

    $path = $service->upload($file, 'questions');

    expect($path)->not()->toBeEmpty();
    Storage::disk('public')->assertExists($path);
});

test('test builder service throws validation exception when publishing empty test', function () {
    $user = User::factory()->create();

    $test = Test::create([
        'title' => 'Empty Test',
        'slug' => 'empty-test',
        'test_type' => TestType::General,
        'duration_minutes' => 60,
        'pass_score' => 70,
        'is_published' => false,
        'created_by' => $user->id,
    ]);

    $service = new TestBuilderService;

    expect(fn () => $service->publishTest($test))->toThrow(ValidationException::class);
});

test('test builder service publishes test with valid sections and questions', function () {
    $user = User::factory()->create();

    $bank = QuestionBank::create([
        'title' => 'Pool Bank',
        'slug' => 'pool-bank',
        'created_by' => $user->id,
        'test_type' => TestType::General,
    ]);

    $q = Question::create([
        'question_bank_id' => $bank->id,
        'prompt' => 'Sample Question',
        'section' => SectionType::Reading,
        'question_type' => QuestionType::MultipleChoice,
        'points' => 5,
    ]);

    $test = Test::create([
        'title' => 'Valid Test',
        'slug' => 'valid-test',
        'test_type' => TestType::General,
        'duration_minutes' => 60,
        'pass_score' => 70,
        'is_published' => false,
        'created_by' => $user->id,
    ]);

    $section = TestSection::create([
        'test_id' => $test->id,
        'title' => 'Section 1',
        'duration_minutes' => 30,
        'order' => 1,
    ]);

    $service = new TestBuilderService;
    $service->assignQuestionToSection($section, $q->id);

    $publishedTest = $service->publishTest($test);

    expect($publishedTest->is_published)->toBeTrue();
});
