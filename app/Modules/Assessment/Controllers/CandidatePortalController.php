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

        $attempt = $this->engine->startAttempt($test, $user);

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

        /** @var view-string $viewName */
        $viewName = 'assessment::candidate.exam';

        return view($viewName, compact('attempt', 'shuffledQuestions', 'remainingSeconds', 'sections'));
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
