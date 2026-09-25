<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Assessment\Engines\AttemptEngine;
use App\Modules\Assessment\Enums\AssessmentMode;
use App\Modules\Assessment\Enums\AttemptStatus;
use App\Modules\Assessment\Enums\EvaluationStatus;
use App\Modules\Assessment\Models\Answer;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\AttemptAudioPlay;
use App\Modules\Assessment\Models\CandidateTestAssignment;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestQuestion;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\QuestionBank\Enums\QuestionType;
use App\Modules\QuestionBank\Enums\SectionType;
use App\Modules\QuestionBank\Enums\TestType;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionChoice;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CandidateCbtAttemptConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected User $candidate;
    protected Test $realTest;
    protected Question $question1;
    protected QuestionChoice $choice1A;
    protected QuestionChoice $choice1B;
    protected CandidateTestAssignment $assignment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->candidate = User::factory()->create([
            'name' => 'Concurrency Candidate',
            'email' => 'concurrency.candidate@iap.test',
            'status' => 'active',
        ]);
        $this->candidate->assignRole('student');

        $this->realTest = Test::create([
            'title' => 'TOEIC Concurrency Test',
            'slug' => 'toeic-concurrency-test',
            'test_type' => TestType::Toeic,
            'assessment_mode' => AssessmentMode::RealTest,
            'duration_minutes' => 60,
            'pass_score' => 75,
            'is_published' => true,
            'status' => 'published',
            'created_by' => $this->candidate->id,
        ]);

        $section = TestSection::create([
            'test_id' => $this->realTest->id,
            'title' => 'Listening Section',
            'section_type' => SectionType::Listening,
            'duration_minutes' => 30,
            'order' => 1,
        ]);

        $this->question1 = Question::create([
            'prompt' => 'Concurrency Question 1',
            'section' => SectionType::Listening,
            'question_type' => QuestionType::MultipleChoice,
            'points' => 10,
        ]);

        $this->choice1A = QuestionChoice::create([
            'question_id' => $this->question1->id,
            'label' => 'A',
            'content' => 'Choice A',
            'is_correct' => true,
        ]);

        $this->choice1B = QuestionChoice::create([
            'question_id' => $this->question1->id,
            'label' => 'B',
            'content' => 'Choice B',
            'is_correct' => false,
        ]);

        TestQuestion::create([
            'test_section_id' => $section->id,
            'question_id' => $this->question1->id,
            'order' => 1,
            'points' => 10,
        ]);

        $this->assignment = CandidateTestAssignment::create([
            'user_id' => $this->candidate->id,
            'test_id' => $this->realTest->id,
            'status' => 'active',
            'max_attempts' => 2,
            'attempts_count' => 0,
            'assigned_at' => now(),
        ]);
    }

    /**
     * Helper to set up an isolated SQLite database and return connection and runner.
     */
    protected function prepareMultiprocessEnvironment(): array
    {
        $tempDb = sys_get_temp_dir() . '/cbt_concurrency_' . uniqid() . '.sqlite';
        touch($tempDb);

        config(['database.connections.temp_sqlite' => [
            'driver'                  => 'sqlite',
            'database'                => $tempDb,
            'prefix'                  => '',
            'foreign_key_constraints' => true,
        ]]);

        Artisan::call('migrate', [
            '--database' => 'temp_sqlite',
            '--force'    => true,
        ]);

        $db = DB::connection('temp_sqlite');
        $now = now()->toDateTimeString();

        // Seed roles into temp DB (auto-increment integer id)
        $studentRoleId = $db->table('roles')->insertGetId([
            'name'       => 'student',
            'guard_name' => 'web',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Seed candidate user (auto-increment integer id)
        $candidateId = $db->table('users')->insertGetId([
            'name'       => 'Isolated Candidate',
            'email'      => 'isolated_' . uniqid() . '@iap.test',
            'password'   => bcrypt('password'),
            'status'     => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $db->table('model_has_roles')->insert([
            'role_id'    => $studentRoleId,
            'model_type' => User::class,
            'model_id'   => $candidateId,
        ]);

        // Seed Real Test (ULID id)
        $testId = (string) \Illuminate\Support\Str::ulid();
        $db->table('tests')->insert([
            'id'               => $testId,
            'title'            => 'Isolated TOEIC Test',
            'slug'             => 'isolated-toeic-' . uniqid(),
            'test_type'        => 'toeic',
            'assessment_mode'  => 'real_test',
            'duration_minutes' => 60,
            'pass_score'       => 75,
            'is_published'     => 1,
            'status'           => 'published',
            'created_by'       => $candidateId,
            'created_at'       => $now,
            'updated_at'       => $now,
        ]);

        $sectionId = (string) \Illuminate\Support\Str::ulid();
        $db->table('test_sections')->insert([
            'id'               => $sectionId,
            'test_id'          => $testId,
            'title'            => 'Listening Section',
            'section_type'     => 'listening',
            'duration_minutes' => 30,
            'order'            => 1,
            'created_at'       => $now,
            'updated_at'       => $now,
        ]);

        $questionId = (string) \Illuminate\Support\Str::ulid();
        $db->table('questions')->insert([
            'id'            => $questionId,
            'prompt'        => 'Isolated Question 1',
            'section'       => 'listening',
            'question_type' => 'multiple_choice',
            'points'        => 10,
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);

        $choiceAId = (string) \Illuminate\Support\Str::ulid();
        $db->table('question_choices')->insert([
            'id'          => $choiceAId,
            'question_id' => $questionId,
            'label'       => 'A',
            'content'     => 'Option A',
            'is_correct'  => 1,
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);

        $choiceBId = (string) \Illuminate\Support\Str::ulid();
        $db->table('question_choices')->insert([
            'id'          => $choiceBId,
            'question_id' => $questionId,
            'label'       => 'B',
            'content'     => 'Option B',
            'is_correct'  => 0,
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);

        $testQuestionId = (string) \Illuminate\Support\Str::ulid();
        $db->table('test_questions')->insert([
            'id'              => $testQuestionId,
            'test_section_id' => $sectionId,
            'question_id'     => $questionId,
            'order'           => 1,
            'points'          => 10,
            'created_at'      => $now,
            'updated_at'      => $now,
        ]);

        $assignmentId = (string) \Illuminate\Support\Str::ulid();
        $db->table('candidate_test_assignments')->insert([
            'id'             => $assignmentId,
            'user_id'        => $candidateId,
            'test_id'        => $testId,
            'status'         => 'active',
            'max_attempts'   => 2,
            'attempts_count' => 0,
            'assigned_at'    => $now,
            'created_at'     => $now,
            'updated_at'     => $now,
        ]);

        $runnerScript = base_path('cbt_multiprocess_worker.php');
        $runnerCode = <<<'PHP'
<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$tempDb = $argv[1];
$action = $argv[2];
$userId = $argv[3];
$param1 = $argv[4] ?? null;
$param2 = $argv[5] ?? null;
$param3 = $argv[6] ?? null;

config(['database.default' => 'sqlite']);
config(['database.connections.sqlite.database' => $tempDb]);
config(['cache.default' => 'file']);
config(['cache.stores.file.path' => storage_path('framework/cache/data')]);
\Illuminate\Support\Facades\DB::purge('sqlite');
\Illuminate\Support\Facades\DB::reconnect('sqlite');

$user = \App\Models\User::on('sqlite')->find($userId);
if (!$user) {
    echo json_encode(['status' => 'error', 'message' => 'User not found']);
    exit(1);
}
\Illuminate\Support\Facades\Auth::login($user);

$controller = $app->make(\App\Modules\Assessment\Controllers\CandidatePortalController::class);

try {
    if ($action === 'start') {
        $test = \App\Modules\Assessment\Models\Test::on('sqlite')->find($param1);
        $req = \Illuminate\Http\Request::create("/candidate/tests/{$test->id}/start", 'POST');
        $req->setUserResolver(fn() => $user);
        $req->setLaravelSession($app['session']->driver());
        $res = $controller->startAttempt($req, $test);
        echo json_encode(['status' => 'ok', 'type' => 'start', 'redirect' => $res->getTargetUrl()]);
    } elseif ($action === 'submit') {
        $attempt = \App\Modules\Assessment\Models\Attempt::on('sqlite')->find($param1);
        $req = \Illuminate\Http\Request::create("/candidate/exam/{$attempt->id}/submit", 'POST');
        $req->setUserResolver(fn() => $user);
        $req->setLaravelSession($app['session']->driver());
        $res = $controller->submit($req, $attempt);
        echo json_encode(['status' => 'ok', 'type' => 'submit', 'redirect' => $res->getTargetUrl()]);
    } elseif ($action === 'autosave') {
        $attempt = \App\Modules\Assessment\Models\Attempt::on('sqlite')->find($param1);
        $questionId = $param2;
        $choiceId = $param3;
        $req = \Illuminate\Http\Request::create("/candidate/exam/{$attempt->id}/autosave", 'POST', [
            'question_id' => $questionId,
            'selected_choice' => $choiceId,
        ]);
        $req->setUserResolver(fn() => $user);
        $req->setLaravelSession($app['session']->driver());
        $res = $controller->autoSave($req, $attempt);
        echo json_encode(['status' => 'ok', 'type' => 'autosave', 'code' => $res->getStatusCode()]);
    } elseif ($action === 'audio') {
        $attempt = \App\Modules\Assessment\Models\Attempt::on('sqlite')->find($param1);
        $question = \App\Modules\QuestionBank\Models\Question::on('sqlite')->find($param2);
        $req = \Illuminate\Http\Request::create("/candidate/exam/{$attempt->id}/questions/{$question->id}/audio-stream", 'GET');
        $req->setUserResolver(fn() => $user);
        $req->setLaravelSession($app['session']->driver());
        $res = $controller->streamAudio($req, $attempt, $question);
        $code = ($res instanceof \Symfony\Component\HttpFoundation\Response) ? $res->getStatusCode() : 200;
        echo json_encode(['status' => 'ok', 'type' => 'audio', 'code' => $code]);
    } elseif ($action === 'finalize') {
        $attempt = \App\Modules\Assessment\Models\Attempt::on('sqlite')->find($param1);
        $req = \Illuminate\Http\Request::create("/candidate/exam/{$attempt->id}/finalize", 'POST');
        $req->setUserResolver(fn() => $user);
        $req->setLaravelSession($app['session']->driver());
        $res = $controller->finalizeAttempt($req, $attempt);
        echo json_encode(['status' => 'ok', 'type' => 'finalize', 'redirect' => $res->getTargetUrl()]);
    } elseif ($action === 'retry') {
        $attempt = \App\Modules\Assessment\Models\Attempt::on('sqlite')->find($param1);
        $req = \Illuminate\Http\Request::create("/candidate/exam/{$attempt->id}/retry", 'POST');
        $req->setUserResolver(fn() => $user);
        $req->setLaravelSession($app['session']->driver());
        $res = $controller->retryAttempt($req, $attempt);
        echo json_encode(['status' => 'ok', 'type' => 'retry', 'redirect' => $res->getTargetUrl()]);
    }
} catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
    echo json_encode(['status' => 'http_exception', 'code' => $e->getStatusCode(), 'message' => $e->getMessage()]);
} catch (\Throwable $e) {
    echo json_encode(['status' => 'exception', 'class' => get_class($e), 'message' => $e->getMessage()]);
}
PHP;
        file_put_contents($runnerScript, $runnerCode);

        return [
            'tempDb'        => $tempDb,
            'db'            => $db,
            'runnerScript'  => $runnerScript,
            'candidateId'   => $candidateId,
            'testId'        => $testId,
            'questionId'    => $questionId,
            'choiceAId'     => $choiceAId,
            'choiceBId'     => $choiceBId,
            'assignmentId'  => $assignmentId,
        ];
    }

    protected function cleanupMultiprocessEnvironment(array $env): void
    {
        if (isset($env['tempDb']) && file_exists($env['tempDb'])) {
            @unlink($env['tempDb']);
        }
        if (isset($env['runnerScript']) && file_exists($env['runnerScript'])) {
            @unlink($env['runnerScript']);
        }
    }

    protected function runParallelProcesses(array $commands): array
    {
        $processes = [];
        $pipes = [];

        foreach ($commands as $i => $cmd) {
            $p = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipe);
            $processes[$i] = $p;
            $pipes[$i] = $pipe;
        }

        $outputs = [];
        foreach ($processes as $i => $p) {
            $stdout = stream_get_contents($pipes[$i][1]);
            $stderr = stream_get_contents($pipes[$i][2]);
            fclose($pipes[$i][1]);
            fclose($pipes[$i][2]);
            $exitCode = proc_close($p);

            $parsed = json_decode(trim($stdout), true);
            $outputs[$i] = [
                'exitCode' => $exitCode,
                'raw'      => $stdout,
                'stderr'   => $stderr,
                'parsed'   => $parsed,
            ];
        }

        return $outputs;
    }

    // ==========================================
    // 1. TRUE MULTI-PROCESS CONCURRENCY TESTS
    // ==========================================

    public function test_true_multiprocess_double_start_creates_single_attempt(): void
    {
        $env = $this->prepareMultiprocessEnvironment();

        try {
            $cmd1 = sprintf('php %s %s start %s %s', escapeshellarg($env['runnerScript']), escapeshellarg($env['tempDb']), escapeshellarg((string) $env['candidateId']), escapeshellarg($env['testId']));
            $cmd2 = sprintf('php %s %s start %s %s', escapeshellarg($env['runnerScript']), escapeshellarg($env['tempDb']), escapeshellarg((string) $env['candidateId']), escapeshellarg($env['testId']));

            $results = $this->runParallelProcesses([$cmd1, $cmd2]);

            $attemptsCount = $env['db']->table('attempts')->where('user_id', $env['candidateId'])->where('test_id', $env['testId'])->count();
            $this->assertEquals(1, $attemptsCount, "Expected exactly 1 Attempt created across concurrent processes. Found: {$attemptsCount}");

            $assignmentAttemptsCount = $env['db']->table('candidate_test_assignments')->where('id', $env['assignmentId'])->value('attempts_count');
            $this->assertEquals(1, $assignmentAttemptsCount);

            $attempt = $env['db']->table('attempts')->where('user_id', $env['candidateId'])->where('test_id', $env['testId'])->first();
            $this->assertEquals('in_progress', $attempt->status);
            $this->assertEquals(1, $attempt->attempt_number);
        } finally {
            $this->cleanupMultiprocessEnvironment($env);
        }
    }

    public function test_true_multiprocess_submit_vs_autosave_serialization(): void
    {
        $env = $this->prepareMultiprocessEnvironment();

        try {
            $attemptId = (string) \Illuminate\Support\Str::ulid();
            $now = now()->toDateTimeString();
            $env['db']->table('attempts')->insert([
                'id'                => $attemptId,
                'test_id'           => $env['testId'],
                'user_id'           => $env['candidateId'],
                'assignment_id'     => $env['assignmentId'],
                'attempt_number'    => 1,
                'status'            => 'in_progress',
                'evaluation_status' => 'pending_evaluation',
                'started_at'        => $now,
                'seed'              => 'seed123',
                'created_at'        => $now,
                'updated_at'        => $now,
            ]);
            $env['db']->table('candidate_test_assignments')->where('id', $env['assignmentId'])->update(['attempts_count' => 1]);

            // Initial answer Choice A so attempt has answers before submit
            $answerId = (string) \Illuminate\Support\Str::ulid();
            $env['db']->table('answers')->insert([
                'id'                 => $answerId,
                'attempt_id'         => $attemptId,
                'question_id'        => $env['questionId'],
                'selected_choice_id' => $env['choiceAId'],
                'is_correct'         => 1,
                'score_earned'       => 10,
                'created_at'         => $now,
                'updated_at'         => $now,
            ]);

            // Process 1: Submit, Process 2: Autosave Choice B
            $cmdSubmit = sprintf('php %s %s submit %s %s', escapeshellarg($env['runnerScript']), escapeshellarg($env['tempDb']), escapeshellarg((string) $env['candidateId']), escapeshellarg($attemptId));
            $cmdAutosave = sprintf('php %s %s autosave %s %s %s %s', escapeshellarg($env['runnerScript']), escapeshellarg($env['tempDb']), escapeshellarg((string) $env['candidateId']), escapeshellarg($attemptId), escapeshellarg($env['questionId']), escapeshellarg($env['choiceBId']));

            $results = $this->runParallelProcesses([$cmdSubmit, $cmdAutosave]);

            $attempt = $env['db']->table('attempts')->where('id', $attemptId)->first();
            $this->assertEquals('submitted', $attempt->status);

            $finalAnswer = $env['db']->table('answers')->where('attempt_id', $attemptId)->where('question_id', $env['questionId'])->first();

            // Either autosave won before submit -> answer is Choice B and total_score = 0
            // OR submit won first -> autosave rejected (403), answer remains Choice A and total_score > 0
            if ($finalAnswer->selected_choice_id === $env['choiceAId']) {
                $this->assertGreaterThan(0, (int) $attempt->total_score);
            } else {
                $this->assertEquals($env['choiceBId'], $finalAnswer->selected_choice_id);
                $this->assertEquals(0, (int) $attempt->total_score);
            }
        } finally {
            $this->cleanupMultiprocessEnvironment($env);
        }
    }

    public function test_true_multiprocess_double_submit_is_evaluated_once(): void
    {
        $env = $this->prepareMultiprocessEnvironment();

        try {
            $attemptId = (string) \Illuminate\Support\Str::ulid();
            $now = now()->toDateTimeString();
            $env['db']->table('attempts')->insert([
                'id'                => $attemptId,
                'test_id'           => $env['testId'],
                'user_id'           => $env['candidateId'],
                'assignment_id'     => $env['assignmentId'],
                'attempt_number'    => 1,
                'status'            => 'in_progress',
                'evaluation_status' => 'pending_evaluation',
                'started_at'        => $now,
                'seed'              => 'seed123',
                'created_at'        => $now,
                'updated_at'        => $now,
            ]);
            $env['db']->table('candidate_test_assignments')->where('id', $env['assignmentId'])->update(['attempts_count' => 1]);

            // Save answer Choice A
            $answerId = (string) \Illuminate\Support\Str::ulid();
            $env['db']->table('answers')->insert([
                'id'                 => $answerId,
                'attempt_id'         => $attemptId,
                'question_id'        => $env['questionId'],
                'selected_choice_id' => $env['choiceAId'],
                'is_correct'         => 1,
                'score_earned'       => 10,
                'created_at'         => $now,
                'updated_at'         => $now,
            ]);

            $cmd1 = sprintf('php %s %s submit %s %s', escapeshellarg($env['runnerScript']), escapeshellarg($env['tempDb']), escapeshellarg((string) $env['candidateId']), escapeshellarg($attemptId));
            $cmd2 = sprintf('php %s %s submit %s %s', escapeshellarg($env['runnerScript']), escapeshellarg($env['tempDb']), escapeshellarg((string) $env['candidateId']), escapeshellarg($attemptId));

            $results = $this->runParallelProcesses([$cmd1, $cmd2]);

            $attempt = $env['db']->table('attempts')->where('id', $attemptId)->first();
            $this->assertEquals('submitted', $attempt->status);
            $this->assertNotNull($attempt->submitted_at);
            $this->assertNotNull($attempt->total_score);

            // Both processes returned valid redirect
            $this->assertNotEmpty($results[0]['parsed']['redirect'] ?? '');
            $this->assertNotEmpty($results[1]['parsed']['redirect'] ?? '');
        } finally {
            $this->cleanupMultiprocessEnvironment($env);
        }
    }

    public function test_true_multiprocess_audio_vs_submit_race_protection(): void
    {
        $env = $this->prepareMultiprocessEnvironment();

        try {
            $attemptId = (string) \Illuminate\Support\Str::ulid();
            $now = now()->toDateTimeString();
            $env['db']->table('attempts')->insert([
                'id'                => $attemptId,
                'test_id'           => $env['testId'],
                'user_id'           => $env['candidateId'],
                'assignment_id'     => $env['assignmentId'],
                'attempt_number'    => 1,
                'status'            => 'in_progress',
                'evaluation_status' => 'pending_evaluation',
                'started_at'        => $now,
                'seed'              => 'seed123',
                'created_at'        => $now,
                'updated_at'        => $now,
            ]);
            $env['db']->table('candidate_test_assignments')->where('id', $env['assignmentId'])->update(['attempts_count' => 1]);

            // Save answer so submit can succeed
            $answerId = (string) \Illuminate\Support\Str::ulid();
            $env['db']->table('answers')->insert([
                'id'                 => $answerId,
                'attempt_id'         => $attemptId,
                'question_id'        => $env['questionId'],
                'selected_choice_id' => $env['choiceAId'],
                'is_correct'         => 1,
                'score_earned'       => 10,
                'created_at'         => $now,
                'updated_at'         => $now,
            ]);

            $cmdSubmit = sprintf('php %s %s submit %s %s', escapeshellarg($env['runnerScript']), escapeshellarg($env['tempDb']), escapeshellarg((string) $env['candidateId']), escapeshellarg($attemptId));
            $cmdAudio = sprintf('php %s %s audio %s %s %s', escapeshellarg($env['runnerScript']), escapeshellarg($env['tempDb']), escapeshellarg((string) $env['candidateId']), escapeshellarg($attemptId), escapeshellarg($env['questionId']));

            $results = $this->runParallelProcesses([$cmdSubmit, $cmdAudio]);

            $attempt = $env['db']->table('attempts')->where('id', $attemptId)->first();
            $this->assertEquals('submitted', $attempt->status);

            $audioPlaysCount = $env['db']->table('attempt_audio_plays')->where('attempt_id', $attemptId)->count();
            // Either audio was reserved before submit (1 play) or submit won first (0 plays)
            $this->assertContains($audioPlaysCount, [0, 1]);
        } finally {
            $this->cleanupMultiprocessEnvironment($env);
        }
    }

    public function test_true_multiprocess_finalize_vs_retry_race_protection(): void
    {
        $env = $this->prepareMultiprocessEnvironment();

        try {
            $attemptId = (string) \Illuminate\Support\Str::ulid();
            $now = now()->toDateTimeString();
            $env['db']->table('attempts')->insert([
                'id'                => $attemptId,
                'test_id'           => $env['testId'],
                'user_id'           => $env['candidateId'],
                'assignment_id'     => $env['assignmentId'],
                'attempt_number'    => 1,
                'status'            => 'submitted',
                'evaluation_status' => 'evaluated',
                'decision_status'   => 'pending_decision',
                'started_at'        => $now,
                'submitted_at'      => $now,
                'total_score'       => 80,
                'seed'              => 'seed123',
                'created_at'        => $now,
                'updated_at'        => $now,
            ]);
            $env['db']->table('candidate_test_assignments')->where('id', $env['assignmentId'])->update(['attempts_count' => 1]);

            $cmdFinalize = sprintf('php %s %s finalize %s %s', escapeshellarg($env['runnerScript']), escapeshellarg($env['tempDb']), escapeshellarg((string) $env['candidateId']), escapeshellarg($attemptId));
            $cmdRetry = sprintf('php %s %s retry %s %s', escapeshellarg($env['runnerScript']), escapeshellarg($env['tempDb']), escapeshellarg((string) $env['candidateId']), escapeshellarg($attemptId));

            $results = $this->runParallelProcesses([$cmdFinalize, $cmdRetry]);

            $attempt1 = $env['db']->table('attempts')->where('id', $attemptId)->first();
            $attempt2Count = $env['db']->table('attempts')->where('assignment_id', $env['assignmentId'])->where('attempt_number', 2)->count();

            // Exactly one valid terminal outcome:
            // Outcome A: Finalize won -> decision_status = finalized, is_final = 1, attempt2Count = 0
            // Outcome B: Retry won -> decision_status = retried, is_final = 0, attempt2Count = 1
            if ($attempt1->decision_status === 'finalized') {
                $this->assertEquals(1, $attempt1->is_final);
                $this->assertEquals(0, $attempt2Count);
            } else {
                $this->assertEquals('retried', $attempt1->decision_status);
                $this->assertEquals(0, $attempt1->is_final);
                $this->assertEquals(1, $attempt2Count);
            }
        } finally {
            $this->cleanupMultiprocessEnvironment($env);
        }
    }

    // ==========================================
    // 2. GOVERNANCE & REGRESSION TESTS
    // ==========================================

    public function test_direct_start_attempt_does_not_bypass_attempt_two_decision_workflow(): void
    {
        // Real Test Attempt #1 submitted, pending_decision
        $attempt1 = Attempt::create([
            'test_id'           => $this->realTest->id,
            'user_id'           => $this->candidate->id,
            'assignment_id'     => $this->assignment->id,
            'attempt_number'    => 1,
            'status'            => AttemptStatus::Submitted,
            'evaluation_status' => EvaluationStatus::Evaluated,
            'decision_status'   => 'pending_decision',
            'started_at'        => now()->subMinutes(30),
            'submitted_at'      => now()->subMinutes(10),
            'score'             => 450,
            'seed'              => 'seed1',
        ]);
        $this->assignment->update(['attempts_count' => 1]);

        // Direct POST to start attempt again
        $response = $this->actingAs($this->candidate)->post(route('candidate.tests.start', $this->realTest));

        // Must redirect safely to the Attempt #1 decision/review lifecycle without creating Attempt #2
        $response->assertRedirect(route('candidate.review', $attempt1));

        $attempt1->refresh();
        $this->assertEquals('pending_decision', $attempt1->decision_status);
        $this->assertEquals(1, Attempt::where('assignment_id', $this->assignment->id)->count());

        // Now, POST to retry endpoint
        $retryResponse = $this->actingAs($this->candidate)->post(route('candidate.exam.retry', $attempt1));

        $attempt1->refresh();
        $this->assertEquals('retried', $attempt1->decision_status);

        $attempt2 = Attempt::where('assignment_id', $this->assignment->id)->where('attempt_number', 2)->first();
        $this->assertNotNull($attempt2);
        $retryResponse->assertRedirect(route('candidate.exam', $attempt2));
        $this->assertEquals(2, Attempt::where('assignment_id', $this->assignment->id)->count());
    }

    public function test_start_attempt_lock_timeout_handles_gracefully(): void
    {
        $lockKey = "start_attempt:user_{$this->candidate->id}:test_{$this->realTest->id}";
        $lock = Cache::lock($lockKey, 10);
        $lock->acquire();

        try {
            // When lock is held, startAttempt should return a user-facing redirect with error
            $response = $this->actingAs($this->candidate)->post(route('candidate.tests.start', $this->realTest));
            $response->assertRedirect(route('candidate.available-tests'));
            $response->assertSessionHas('error', 'Assessment start request is already being processed. Please try again.');
        } finally {
            $lock->release();
        }
    }

    public function test_submit_lock_timeout_handles_gracefully(): void
    {
        $attempt = Attempt::create([
            'test_id'           => $this->realTest->id,
            'user_id'           => $this->candidate->id,
            'assignment_id'     => $this->assignment->id,
            'attempt_number'    => 1,
            'status'            => AttemptStatus::InProgress,
            'evaluation_status' => EvaluationStatus::PendingEvaluation,
            'started_at'        => now(),
            'seed'              => 'seed123',
        ]);

        $mockLock = \Mockery::mock(\Illuminate\Contracts\Cache\Lock::class);
        $mockLock->shouldReceive('block')->andThrow(new LockTimeoutException('Lock acquisition timed out.'));

        Cache::shouldReceive('lock')
            ->with("candidate_attempt:{$attempt->id}", \Mockery::any())
            ->andReturn($mockLock);

        $response = $this->actingAs($this->candidate)->post(route('candidate.exam.submit', $attempt));
        $response->assertRedirect(route('candidate.review', $attempt));
    }

    public function test_autosave_lock_timeout_returns_409(): void
    {
        $attempt = Attempt::create([
            'test_id'           => $this->realTest->id,
            'user_id'           => $this->candidate->id,
            'assignment_id'     => $this->assignment->id,
            'attempt_number'    => 1,
            'status'            => AttemptStatus::InProgress,
            'evaluation_status' => EvaluationStatus::PendingEvaluation,
            'started_at'        => now(),
            'seed'              => 'seed123',
        ]);

        $mockLock = \Mockery::mock(\Illuminate\Contracts\Cache\Lock::class);
        $mockLock->shouldReceive('block')->andThrow(new LockTimeoutException('Lock acquisition timed out.'));

        Cache::shouldReceive('lock')
            ->with("candidate_attempt:{$attempt->id}", \Mockery::any())
            ->andReturn($mockLock);

        $response = $this->actingAs($this->candidate)->postJson(route('candidate.exam.autosave', $attempt), [
            'question_id'     => $this->question1->id,
            'selected_choice' => $this->choice1A->id,
        ]);
        $response->assertStatus(409);
    }

    public function test_audio_stream_single_play_is_strictly_enforced_in_real_test(): void
    {
        $attempt = Attempt::create([
            'test_id'           => $this->realTest->id,
            'user_id'           => $this->candidate->id,
            'assignment_id'     => $this->assignment->id,
            'attempt_number'    => 1,
            'status'            => AttemptStatus::InProgress,
            'evaluation_status' => EvaluationStatus::PendingEvaluation,
            'started_at'        => now(),
            'seed'              => 'seed123',
        ]);

        // First stream call records single-play reservation
        AttemptAudioPlay::create([
            'attempt_id'  => $attempt->id,
            'question_id' => $this->question1->id,
            'play_count'  => 1,
            'started_at'  => now(),
        ]);

        // Second stream call must return 403
        $response = $this->actingAs($this->candidate)->get(route('candidate.exam.audio-stream', [
            'attempt'  => $attempt,
            'question' => $this->question1,
        ]));

        $response->assertStatus(403);
    }
}
