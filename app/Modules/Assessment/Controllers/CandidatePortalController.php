<?php

namespace App\Modules\Assessment\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Assessment\Engines\AssessmentEngine;
use App\Modules\Assessment\Events\RuleViolationDetected;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\Test;
use App\Modules\Certificate\Models\Certificate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CandidatePortalController extends Controller
{
    public function __construct(
        protected AssessmentEngine $engine
    ) {}

    /**
     * Candidate Portal Dashboard.
     */
    public function portal(Request $request): View
    {
        $userId = (int) $request->user()?->id;

        $availableTestsCount = Test::where('is_published', true)->count();
        $myAttemptsCount = Attempt::where('user_id', $userId)->count();
        $completedAttemptsCount = Attempt::where('user_id', $userId)->whereIn('status', ['submitted', 'expired'])->count();
        $issuedCertificatesCount = Certificate::where('user_id', $userId)->count();
        $ongoingAttempts = Attempt::with(['test.sections'])
            ->where('user_id', $userId)
            ->where('status', 'in_progress')
            ->orderBy('started_at', 'desc')
            ->get()
            ->filter(function (Attempt $attempt) {
                return !$this->engine->timerEngine->isExpired($attempt);
            })
            ->values();

        /** @var view-string $viewName */
        $viewName = 'assessment::candidate.portal';

        return view($viewName, compact(
            'availableTestsCount',
            'myAttemptsCount',
            'completedAttemptsCount',
            'issuedCertificatesCount',
            'ongoingAttempts'
        ));
    }

    /**
     * List available tests to take.
     */
    public function availableTests(): View
    {
        $tests = Test::where('is_published', true)->with('sections')->paginate(9);

        /** @var view-string $viewName */
        $viewName = 'assessment::candidate.available_tests';

        return view($viewName, compact('tests'));
    }

    /**
     * Candidate attempt history.
     */
    public function myAttempts(Request $request): View
    {
        $userId = (int) $request->user()?->id;
        $attempts = Attempt::where('user_id', $userId)->with(['test', 'certificate'])->latest()->paginate(10);

        /** @var view-string $viewName */
        $viewName = 'assessment::candidate.my_attempts';

        return view($viewName, compact('attempts'));
    }

    /**
     * Candidate issued certificates history.
     */
    public function myCertificates(Request $request): View
    {
        $userId = (int) $request->user()?->id;
        $certificates = Certificate::where('user_id', $userId)
            ->with(['attempt.test'])
            ->latest('issued_at')
            ->paginate(10);

        /** @var view-string $viewName */
        $viewName = 'assessment::candidate.my_certificates';

        return view($viewName, compact('certificates'));
    }

    /**
     * Pre-test Assessment Instructions Screen.
     */
    public function instructions(Test $test): View
    {
        $test->loadMissing('sections.testQuestions');
        $totalQuestions = $test->sections->sum(fn($s) => $s->testQuestions->count());

        /** @var view-string $viewName */
        $viewName = 'assessment::candidate.instructions';

        return view($viewName, compact('test', 'totalQuestions'));
    }

    /**
     * Start test attempt.
     */
    public function startAttempt(Request $request, Test $test): RedirectResponse
    {
        $user = $request->user();
        if (!$user) {
            return redirect()->route('login');
        }

        // Eligibility check for Real Tests
        $assignmentEngine = app(\App\Modules\Assessment\Engines\AssignmentEngine::class);
        if (!$assignmentEngine->isEligibleToStart($test, $user)) {
            return redirect()->route('candidate.available-tests')
                ->with('error', "Real Test '{$test->title}' requires a confirmed paid assignment before you can start.");
        }

        $attempt = $this->engine->startAttempt($test, $user);

        \App\Services\ActivityLogger::log('EXAM_STARTED', "Started assessment attempt for '{$test->title}'", $attempt);

        return redirect()->route('candidate.exam', $attempt);
    }

    /**
     * CBT Exam Interface.
     */
    public function exam(Attempt $attempt): View|RedirectResponse
    {
        $attempt = $this->engine->resumeAttempt($attempt);

        if ($attempt->status->value !== 'in_progress') {
            return redirect()->route('candidate.review', $attempt);
        }

        $attempt->loadMissing(['test.sections.testQuestions.question.choices', 'test.sections.mediaAssets', 'answers']);

        $allQuestions = collect();
        $sections = $attempt->test ? $attempt->test->sections : collect();
        foreach ($sections as $section) {
            foreach ($section->testQuestions as $tq) {
                if ($tq->question) {
                    $q = $tq->question;
                    $q->section_model = $section;
                    $allQuestions->push($q);
                }
            }
        }

        $shuffledQuestions = $this->engine->randomizationEngine->getShuffledQuestions($attempt, $allQuestions);
        $remainingSeconds = $this->engine->timerEngine->getRemainingSeconds($attempt);
        $isRealTest = $attempt->test?->isRealTest() ?? false;

        // Played audio tracking for Real Test single-play
        $playedAudioQuestionIds = \App\Modules\Assessment\Models\AttemptAudioPlay::where('attempt_id', $attempt->id)
            ->pluck('question_id')
            ->toArray();

        /** @var view-string $viewName */
        $viewName = 'assessment::candidate.exam';

        return view($viewName, compact('attempt', 'shuffledQuestions', 'remainingSeconds', 'sections', 'isRealTest', 'playedAudioQuestionIds'));
    }

    /**
     * Secure Audio Streaming Endpoint (Enforces Single Play for Real Tests).
     */
    public function streamAudio(Request $request, Attempt $attempt, \App\Modules\QuestionBank\Models\Question $question)
    {
        $user = $request->user();
        if (!$user || (int)$attempt->user_id !== (int)$user->id) {
            abort(403, 'Unauthorized attempt access.');
        }

        if ($attempt->status->value !== 'in_progress') {
            abort(403, 'Test session is no longer in progress.');
        }

        $test = $attempt->test;
        $isRealTest = $test?->isRealTest() ?? false;

        if ($isRealTest) {
            $existingPlay = \App\Modules\Assessment\Models\AttemptAudioPlay::where('attempt_id', $attempt->id)
                ->where('question_id', $question->id)
                ->first();

            if ($existingPlay && $existingPlay->play_count >= 1) {
                \App\Services\ActivityLogger::log('AUDIO_REPLAY_BLOCKED', "Blocked audio replay for Question {$question->id}", $attempt);
                return response()->json(['error' => 'Audio already played for this question.'], 403);
            }

            \App\Modules\Assessment\Models\AttemptAudioPlay::create([
                'attempt_id'     => $attempt->id,
                'question_id'    => $question->id,
                'media_asset_id' => $question->media_asset_id,
                'play_count'     => 1,
                'started_at'     => now(),
                'ip_address'     => $request->ip(),
            ]);

            \App\Services\ActivityLogger::log('AUDIO_PLAY_STARTED', "Started single audio play for Question {$question->id}", $attempt);
        } else {
            \App\Services\ActivityLogger::log('AUDIO_PLAY_STARTED', "Started simulator audio play for Question {$question->id}", $attempt);
        }

        // Resolve audio source
        $audioUrl = $question->audio_url;
        if (empty($audioUrl) && $question->mediaAsset) {
            $audioUrl = $question->mediaAsset->path;
        }

        if (empty($audioUrl)) {
            abort(404, 'Audio resource not found.');
        }

        $filePath = storage_path('app/public/' . ltrim($audioUrl, '/'));
        if (!file_exists($filePath)) {
            $filePath = public_path(ltrim($audioUrl, '/'));
        }

        if (file_exists($filePath)) {
            $mime = mime_content_type($filePath) ?: 'audio/mpeg';
            return response()->file($filePath, [
                'Content-Type'        => $mime,
                'Content-Disposition' => 'inline; filename="' . basename($filePath) . '"',
                'Cache-Control'       => 'no-store, no-cache, must-revalidate, private',
                'Accept-Ranges'       => 'bytes',
            ]);
        }

        // Return inline redirection if URL is remote
        if (filter_var($audioUrl, FILTER_VALIDATE_URL)) {
            return redirect()->away($audioUrl);
        }

        abort(404, 'Physical audio file not found.');
    }

    /**
     * Auto save response via AJAX.
     */
    public function autoSave(Request $request, Attempt $attempt): JsonResponse
    {
        $validated = $request->validate([
            'question_id' => ['required', 'string'],
            'selected_choice' => ['nullable'],
            'answer_text' => ['nullable', 'string'],
        ]);

        $this->engine->autoSaveEngine->saveAnswer(
            $attempt,
            $validated['question_id'],
            $validated['selected_choice'] ?? null,
            $validated['answer_text'] ?? null
        );

        return response()->json(['status' => 'saved']);
    }

    /**
     * Toggle flag question via AJAX.
     */
    public function toggleFlag(Request $request, Attempt $attempt): JsonResponse
    {
        $validated = $request->validate([
            'question_id' => ['required', 'string'],
        ]);

        $this->engine->navigationEngine->toggleFlag($attempt, $validated['question_id']);

        return response()->json(['status' => 'flagged']);
    }

    /**
     * Record anti-cheating violation via AJAX.
     */
    public function recordViolation(Request $request, Attempt $attempt): JsonResponse
    {
        $type = $request->input('violation_type', 'window_blur');
        event(new RuleViolationDetected($attempt, $type));

        if ($type === 'fullscreen_exit') {
            \App\Services\ActivityLogger::log('FULLSCREEN_EXITED', 'Candidate exited secure fullscreen mode', $attempt);
        } elseif ($type === 'fullscreen_enter') {
            \App\Services\ActivityLogger::log('FULLSCREEN_ENTERED', 'Candidate entered secure fullscreen mode', $attempt);
        } elseif ($type === 'audio_completed') {
            $questionId = $request->input('question_id');
            \App\Services\ActivityLogger::log('AUDIO_PLAY_COMPLETED', "Candidate completed audio play for Question {$questionId}", $attempt);
            if ($questionId) {
                \App\Modules\Assessment\Models\AttemptAudioPlay::where('attempt_id', $attempt->id)
                    ->where('question_id', $questionId)
                    ->update(['completed_at' => now()]);
            }
        } else {
            \App\Services\ActivityLogger::log('WINDOW_BLUR_DETECTED', "Candidate window blur detected ({$type})", $attempt);
        }

        return response()->json(['status' => 'logged']);
    }

    /**
     * Submit attempt.
     */
    public function submit(Attempt $attempt): RedirectResponse
    {
        $attempt->loadMissing(['test.sections.testQuestions.question', 'answers']);

        $totalQuestionsCount = 0;
        if ($attempt->test) {
            foreach ($attempt->test->sections as $sec) {
                $totalQuestionsCount += $sec->testQuestions->count();
            }
        }

        $isExpired = $this->engine->timerEngine->isExpired($attempt);

        if (!$isExpired && $totalQuestionsCount > 0) {
            $answeredCount = $attempt->answers->filter(function ($a) {
                return !is_null($a->selected_choice_id) || !empty($a->text_response);
            })->count();

            if ($answeredCount < $totalQuestionsCount) {
                return redirect()->back()->with('error', 'Please answer all questions before submitting the assessment.');
            }
        }

        $this->engine->submitAttempt($attempt);

        \App\Services\ActivityLogger::log('EXAM_SUBMITTED', "Candidate submitted assessment attempt for '{$attempt->test?->title}'", $attempt);

        return redirect()->route('candidate.review', $attempt);
    }

    /**
     * Candidate result review view.
     */
    public function review(Attempt $attempt): View
    {
        $summary = $this->engine->reviewAttempt($attempt);

        /** @var view-string $viewName */
        $viewName = 'assessment::candidate.review';

        return view($viewName, compact('summary', 'attempt'));
    }
}
