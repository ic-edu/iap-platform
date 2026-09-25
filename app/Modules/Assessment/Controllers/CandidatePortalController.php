<?php

namespace App\Modules\Assessment\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Assessment\Engines\AssessmentEngine;
use App\Modules\Assessment\Enums\AttemptStatus;
use App\Modules\Assessment\Enums\EvaluationStatus;
use App\Modules\Assessment\Events\RuleViolationDetected;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\Test;
use App\Modules\Certificate\Models\Certificate;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionChoice;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CandidatePortalController extends Controller
{
    public function __construct(
        protected AssessmentEngine $engine
    ) {}

    /**
     * Authorize that the current candidate owns the given attempt session.
     */
    protected function authorizeAttemptAccess(Attempt $attempt, ?User $user = null): void
    {
        $user = $user ?? request()->user();
        if (!$user || (int) $attempt->user_id !== (int) $user->id) {
            abort(403, 'Unauthorized access to assessment attempt.');
        }
    }

    /**
     * Assert that the attempt is in an active mutable lifecycle state.
     * Rejects if status is not in_progress or if server timer has expired.
     */
    protected function assertAttemptMutable(Attempt $attempt): void
    {
        $statusValue = is_object($attempt->status) ? $attempt->status->value : (string) $attempt->status;

        if ($statusValue !== AttemptStatus::InProgress->value && $statusValue !== 'in_progress') {
            abort(403, 'Assessment attempt is no longer in progress.');
        }

        if ($this->engine->timerEngine->isExpired($attempt)) {
            abort(403, 'Assessment attempt duration has expired.');
        }
    }

    /**
     * Candidate Portal Dashboard.
     */
    public function portal(Request $request): View
    {
        $userId = (int) $request->user()?->id;

        $availableTestsCount = Test::published()
            ->where(function ($query) use ($userId) {
                $query->where('assessment_mode', 'simulator')
                    ->orWhere(function ($q) use ($userId) {
                        $q->where('assessment_mode', 'real_test')
                          ->whereHas('assignments', function ($a) use ($userId) {
                              $a->where('user_id', $userId)->where('status', 'active');
                          });
                    });
            })
            ->count();

        $myAttemptsCount = Attempt::where('user_id', $userId)->count();
        $completedAttemptsCount = Attempt::where('user_id', $userId)->where('status', AttemptStatus::Submitted->value)->count();
        $issuedCertificatesCount = Certificate::where('user_id', $userId)
            ->whereHas('attempt.test', function ($q) {
                $q->where('assessment_mode', '!=', \App\Modules\Assessment\Enums\AssessmentMode::Simulator->value);
            })
            ->count();
        $ongoingAttempts = Attempt::with(['test.sections'])
            ->where('user_id', $userId)
            ->where('status', 'in_progress')
            ->orderBy('started_at', 'desc')
            ->get()
            ->filter(function (Attempt $attempt) {
                return !$this->engine->timerEngine->isExpired($attempt);
            })
            ->values();

        $openOrdersCount = \App\Modules\Commerce\Domain\Models\Order::where('user_id', $userId)
            ->where('status', \App\Modules\Commerce\Domain\Enums\OrderStatus::Pending)
            ->count();
        $pendingPaymentsCount = \App\Modules\Commerce\Domain\Models\Payment::where('user_id', $userId)
            ->where('status', \App\Modules\Commerce\Domain\Enums\PaymentStatus::Pending)
            ->count();

        $user = $request->user();
        $institutionalMemberships = $user ? $user->activeOrganizationMemberships()
            ->with([
                'organization',
                'groups' => function ($query) {
                    $query->where('is_active', true);
                },
            ])
            ->get()
            ->filter(fn ($m) => $m->organization && $m->organization->isActive())
            ->values() : collect();

        /** @var view-string $viewName */
        $viewName = 'assessment::candidate.portal';

        return view($viewName, compact(
            'availableTestsCount',
            'myAttemptsCount',
            'completedAttemptsCount',
            'issuedCertificatesCount',
            'ongoingAttempts',
            'openOrdersCount',
            'pendingPaymentsCount',
            'institutionalMemberships'
        ));
    }

    /**
     * List available tests to take (Simulators + Assigned Real Tests).
     */
    public function availableTests(Request $request): View
    {
        $user = $request->user();
        $userId = (int) $user?->id;

        $tests = Test::published()
            ->where(function ($query) use ($userId) {
                $query->where('assessment_mode', 'simulator')
                    ->orWhere(function ($q) use ($userId) {
                        $q->where('assessment_mode', 'real_test')
                          ->whereHas('assignments', function ($a) use ($userId) {
                              $a->where('user_id', $userId)->where('status', 'active');
                          });
                    });
            })
            ->with('sections')
            ->paginate(9);

        /** @var view-string $viewName */
        $viewName = 'assessment::candidate.available_tests';

        return view($viewName, compact('tests'));
    }

    /**
     * Candidate attempt history (all candidate-owned attempt sessions).
     */
    public function myAttempts(Request $request): View
    {
        $userId = (int) $request->user()?->id;
        $query = Attempt::where('user_id', $userId)->with(['test', 'certificate']);

        if ($request->input('filter') === 'completed' || $request->input('status') === 'completed') {
            $query->where('status', AttemptStatus::Submitted->value);
        }

        $attempts = $query->latest('started_at')->latest('created_at')->paginate(10)->withQueryString();

        /** @var view-string $viewName */
        $viewName = 'assessment::candidate.my_attempts';

        return view($viewName, compact('attempts'));
    }

    /**
     * Candidate completed assessment results history (finalized submitted assessments only).
     */
    public function myResults(Request $request): View
    {
        $userId = (int) $request->user()?->id;

        $results = Attempt::where('user_id', $userId)
            ->where('status', AttemptStatus::Submitted->value)
            ->with(['test', 'certificate'])
            ->latest('submitted_at')
            ->latest('created_at')
            ->paginate(10)
            ->withQueryString();

        /** @var view-string $viewName */
        $viewName = 'assessment::candidate.my_results';

        return view($viewName, compact('results'));
    }

    /**
     * Candidate issued certificates history.
     */
    public function myCertificates(Request $request): View
    {
        $userId = (int) $request->user()?->id;
        $certificates = Certificate::where('user_id', $userId)
            ->whereHas('attempt.test', function ($q) {
                $q->where('assessment_mode', '!=', \App\Modules\Assessment\Enums\AssessmentMode::Simulator->value);
            })
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
    public function instructions(Request $request, Test $test): View
    {
        $user = $request->user();
        $assignmentEngine = app(\App\Modules\Assessment\Engines\AssignmentEngine::class);
        if (!$assignmentEngine->isEligibleToStart($test, $user)) {
            abort(403, "Unauthorized access. Mock Test '{$test->title}' requires an active paid assignment.");
        }

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
            abort(403, "Unauthorized access. Mock Test '{$test->title}' requires a confirmed paid assignment before you can start.");
        }

        try {
            $attempt = $this->engine->startAttempt($test, $user);
        } catch (LockTimeoutException $e) {
            return redirect()->route('candidate.available-tests')
                ->with('error', 'Assessment start request is already being processed. Please try again.');
        } catch (\DomainException|\InvalidArgumentException $e) {
            $existingAttempt = Attempt::where('test_id', $test->id)
                ->where('user_id', $user->id)
                ->latest('attempt_number')
                ->first();

            if ($existingAttempt) {
                if ($existingAttempt->status === AttemptStatus::InProgress && !$this->engine->timerEngine->isExpired($existingAttempt)) {
                    return redirect()->route('candidate.exam', $existingAttempt);
                }
                return redirect()->route('candidate.review', $existingAttempt);
            }

            return redirect()->route('candidate.available-tests')->with('error', $e->getMessage());
        }

        \App\Services\ActivityLogger::log('EXAM_STARTED', "Started assessment attempt for '{$test->title}'", $attempt);

        if ($attempt->status !== AttemptStatus::InProgress || $this->engine->timerEngine->isExpired($attempt)) {
            return redirect()->route('candidate.review', $attempt);
        }

        return redirect()->route('candidate.exam', $attempt);
    }

    /**
     * CBT Exam Interface.
     */
    public function exam(Request $request, Attempt $attempt): View|RedirectResponse
    {
        $this->authorizeAttemptAccess($attempt, $request->user());

        $attempt = $this->engine->resumeAttempt($attempt);

        if ($attempt->status->value !== 'in_progress') {
            return redirect()->route('candidate.review', $attempt);
        }

        $attempt->loadMissing([
            'test.sections' => function ($q) {
                $q->orderBy('order');
            },
            'test.sections.testQuestions' => function ($q) {
                $q->orderBy('order');
            },
            'test.sections.testQuestions.question.choices',
            'test.sections.testQuestions.question.passage',
            'test.sections.testQuestions.question.passageGroup.passages',
            'test.sections.testQuestions.question.audioGroup.mediaAsset',
            'test.sections.testQuestions.question.mediaAsset',
            'test.sections.mediaAssets',
            'answers',
        ]);

        $deliveryData = \App\Modules\Assessment\Services\DeliveryUnitBuilder::build($attempt->test, $attempt);
        $questions = $deliveryData['questions'];
        $sections = $deliveryData['sections'];
        $remainingSeconds = $this->engine->timerEngine->getRemainingSeconds($attempt);
        $isRealTest = $attempt->test?->isRealTest() ?? false;

        // Played audio tracking for Real Test single-play
        $playedAudioQuestionIds = \App\Modules\Assessment\Models\AttemptAudioPlay::where('attempt_id', $attempt->id)
            ->pluck('question_id')
            ->toArray();

        /** @var view-string $viewName */
        $viewName = 'assessment::candidate.exam';

        return view($viewName, array_merge([
            'attempt'                => $attempt,
            'shuffledQuestions'      => $questions,
            'remainingSeconds'       => $remainingSeconds,
            'sections'               => $sections,
            'isRealTest'             => $isRealTest,
            'playedAudioQuestionIds' => $playedAudioQuestionIds,
        ], $deliveryData));
    }

    /**
     * Secure Audio Streaming Endpoint (Enforces Single Play for Real Tests).
     */
    public function streamAudio(Request $request, Attempt $attempt, \App\Modules\QuestionBank\Models\Question $question)
    {
        $test = $attempt->test;
        $isRealTest = $test?->isRealTest() ?? false;

        if ($isRealTest) {
            $attemptLockKey = "candidate_attempt:{$attempt->id}";
            $audioLockKey = 'audio_play:' . $attempt->id . ':' . ($question->audio_group_id ? "group_{$question->audio_group_id}" : "q_{$question->id}");

            try {
                $reservationResult = Cache::lock($attemptLockKey, 15)->block(5, function () use ($request, $attempt, $question, $audioLockKey) {
                    $attempt->refresh();
                    $this->authorizeAttemptAccess($attempt, $request->user());

                    if ($attempt->status->value !== 'in_progress') {
                        abort(403, 'Test session is no longer in progress.');
                    }

                    if ($this->engine->timerEngine->isExpired($attempt)) {
                        abort(403, 'Assessment attempt duration has expired.');
                    }

                    if (!$attempt->hasQuestion($question->id)) {
                        abort(403, 'Question does not belong to this assessment attempt.');
                    }

                    return Cache::lock($audioLockKey, 15)->block(5, function () use ($attempt, $question, $request) {
                        $groupQuestionIds = $question->audio_group_id
                            ? Question::where('audio_group_id', $question->audio_group_id)->pluck('id')->toArray()
                            : [];

                        $existingPlayQuery = \App\Modules\Assessment\Models\AttemptAudioPlay::where('attempt_id', $attempt->id);
                        if (!empty($groupQuestionIds)) {
                            $existingPlayQuery->whereIn('question_id', $groupQuestionIds);
                        } else {
                            $existingPlayQuery->where('question_id', $question->id);
                        }
                        $existingPlay = $existingPlayQuery->first();

                        if ($existingPlay && $existingPlay->play_count >= 1) {
                            \App\Services\ActivityLogger::log('AUDIO_REPLAY_BLOCKED', "Blocked audio replay for Question {$question->id}" . ($question->audio_group_id ? " (Group {$question->audio_group_id})" : ""), $attempt);
                            return response()->json(['error' => 'Audio already played for this question group.'], 403);
                        }

                        $targetQuestionIds = !empty($groupQuestionIds) ? $groupQuestionIds : [$question->id];
                        foreach ($targetQuestionIds as $targetQId) {
                            try {
                                \App\Modules\Assessment\Models\AttemptAudioPlay::firstOrCreate([
                                    'attempt_id'  => $attempt->id,
                                    'question_id' => $targetQId,
                                ], [
                                    'media_asset_id' => $question->audio_media_asset_id ?? $question->audioGroup?->media_asset_id ?? $question->media_asset_id,
                                    'play_count'     => 1,
                                    'started_at'     => now(),
                                    'ip_address'     => $request->ip(),
                                ]);
                            } catch (\Illuminate\Database\QueryException $e) {
                                return response()->json(['error' => 'Audio already played for this question group.'], 403);
                            }
                        }

                        \App\Services\ActivityLogger::log('AUDIO_PLAY_STARTED', "Started single audio play for Question {$question->id}" . ($question->audio_group_id ? " (Group {$question->audio_group_id})" : ""), $attempt);
                        return null;
                    });
                });

                if ($reservationResult instanceof JsonResponse) {
                    return $reservationResult;
                }
            } catch (LockTimeoutException $e) {
                return response()->json(['error' => 'Audio playback request in progress.'], 409);
            }
        } else {
            $this->authorizeAttemptAccess($attempt, $request->user());

            if (!$attempt->hasQuestion($question->id)) {
                abort(403, 'Question does not belong to this assessment attempt.');
            }

            $isCompletedAttempt = in_array($attempt->status->value, ['submitted', 'expired', 'evaluated'], true) || $attempt->isEvaluated();
            if ($attempt->status->value !== 'in_progress' && !$isCompletedAttempt) {
                abort(403, 'Test session is no longer in progress.');
            }

            \App\Services\ActivityLogger::log('AUDIO_PLAY_STARTED', "Started simulator audio play for Question {$question->id}", $attempt);
        }

        // Resolve audio source (Group or Question)
        $mediaAsset = $question->audioGroup?->mediaAsset ?? $question->getEffectiveAudioMedia();
        $filePath = null;

        if ($mediaAsset && !empty($mediaAsset->path)) {
            $disk = \Illuminate\Support\Facades\Storage::disk('public');
            if ($disk->exists($mediaAsset->path)) {
                $filePath = $disk->path($mediaAsset->path);
            } elseif (file_exists(storage_path('app/public/' . $mediaAsset->path))) {
                $filePath = storage_path('app/public/' . $mediaAsset->path);
            } elseif (file_exists(public_path($mediaAsset->path))) {
                $filePath = public_path($mediaAsset->path);
            }
        }

        $audioUrl = $question->audioGroup?->audio_url ?: ($question->getEffectiveAudioUrl() ?: $question->audio_url);

        if (!$filePath && !empty($audioUrl)) {
            // If it's a preview URL containing media ID, extract and resolve
            if (preg_match('#/media/([0-9a-zA-Z]+)/preview#', $audioUrl, $matches)) {
                $foundAsset = \App\Models\MediaAsset::find($matches[1]);
                if ($foundAsset && !empty($foundAsset->path)) {
                    if (file_exists(storage_path('app/public/' . $foundAsset->path))) {
                        $filePath = storage_path('app/public/' . $foundAsset->path);
                    } elseif (file_exists(public_path($foundAsset->path))) {
                        $filePath = public_path($foundAsset->path);
                    }
                }
            }

            if (!$filePath) {
                $cleanedPath = ltrim(parse_url($audioUrl, PHP_URL_PATH) ?: $audioUrl, '/');
                if (file_exists(storage_path('app/public/' . $cleanedPath))) {
                    $filePath = storage_path('app/public/' . $cleanedPath);
                } elseif (file_exists(public_path($cleanedPath))) {
                    $filePath = public_path($cleanedPath);
                }
            }
        }

        if ($filePath && file_exists($filePath)) {
            $mime = $mediaAsset?->mime_type ?: (mime_content_type($filePath) ?: 'audio/mpeg');
            $filesize = filesize($filePath);
            return response()->file($filePath, [
                'Content-Type'        => $mime,
                'Content-Length'      => (string) $filesize,
                'Content-Disposition' => 'inline; filename="' . basename($filePath) . '"',
                'Cache-Control'       => 'no-store, no-cache, must-revalidate, private',
                'Accept-Ranges'       => 'bytes',
            ]);
        }

        // Return inline redirection if URL is remote
        if (!empty($audioUrl) && filter_var($audioUrl, FILTER_VALIDATE_URL)) {
            return redirect()->away($audioUrl);
        }

        abort(404, 'Physical audio file not found.');
    }

    /**
     * Auto save response via AJAX.
     */
    public function autoSave(Request $request, Attempt $attempt): JsonResponse
    {
        $lockKey = "candidate_attempt:{$attempt->id}";

        try {
            return Cache::lock($lockKey, 10)->block(5, function () use ($request, $attempt) {
                $attempt->refresh();
                $this->authorizeAttemptAccess($attempt, $request->user());
                $this->assertAttemptMutable($attempt);

                $validated = $request->validate([
                    'question_id' => ['required', 'string'],
                    'selected_choice' => ['nullable'],
                    'answer_text' => ['nullable', 'string'],
                ]);

                if (!$attempt->hasQuestion($validated['question_id'])) {
                    abort(403, 'Question does not belong to this assessment attempt.');
                }

                $selectedChoice = $validated['selected_choice'] ?? null;
                if (!empty($selectedChoice)) {
                    $choiceIds = is_array($selectedChoice) ? $selectedChoice : [$selectedChoice];
                    $validCount = QuestionChoice::where('question_id', $validated['question_id'])
                        ->whereIn('id', $choiceIds)
                        ->count();

                    if ($validCount !== count($choiceIds)) {
                        abort(422, 'Invalid choice for the specified question.');
                    }
                }

                $this->engine->autoSaveEngine->saveAnswer(
                    $attempt,
                    $validated['question_id'],
                    $validated['selected_choice'] ?? null,
                    $validated['answer_text'] ?? null
                );

                return response()->json(['status' => 'saved']);
            });
        } catch (LockTimeoutException $e) {
            abort(409, 'Another operation is currently modifying this assessment attempt.');
        }
    }

    /**
     * Toggle flag question via AJAX.
     */
    public function toggleFlag(Request $request, Attempt $attempt): JsonResponse
    {
        $lockKey = "candidate_attempt:{$attempt->id}";

        try {
            return Cache::lock($lockKey, 10)->block(5, function () use ($request, $attempt) {
                $attempt->refresh();
                $this->authorizeAttemptAccess($attempt, $request->user());
                $this->assertAttemptMutable($attempt);

                $validated = $request->validate([
                    'question_id' => ['required', 'string'],
                ]);

                if (!$attempt->hasQuestion($validated['question_id'])) {
                    abort(403, 'Question does not belong to this assessment attempt.');
                }

                $this->engine->navigationEngine->toggleFlag($attempt, $validated['question_id']);

                return response()->json(['status' => 'flagged']);
            });
        } catch (LockTimeoutException $e) {
            abort(409, 'Another operation is currently modifying this assessment attempt.');
        }
    }

    /**
     * Record anti-cheating violation via AJAX.
     */
    public function recordViolation(Request $request, Attempt $attempt): JsonResponse
    {
        $lockKey = "candidate_attempt:{$attempt->id}";

        try {
            return Cache::lock($lockKey, 10)->block(5, function () use ($request, $attempt) {
                $attempt->refresh();
                $this->authorizeAttemptAccess($attempt, $request->user());
                $this->assertAttemptMutable($attempt);

                $questionId = $request->input('question_id');
                if ($questionId && !$attempt->hasQuestion($questionId)) {
                    abort(403, 'Question does not belong to this assessment attempt.');
                }

                $type = $request->input('violation_type', 'window_blur');
                event(new RuleViolationDetected($attempt, $type));

                if ($type === 'fullscreen_exit') {
                    \App\Services\ActivityLogger::log('FULLSCREEN_EXITED', 'Candidate exited secure fullscreen mode', $attempt);
                } elseif ($type === 'fullscreen_enter') {
                    \App\Services\ActivityLogger::log('FULLSCREEN_ENTERED', 'Candidate entered secure fullscreen mode', $attempt);
                } elseif ($type === 'audio_completed') {
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
            });
        } catch (LockTimeoutException $e) {
            abort(409, 'Another operation is currently modifying this assessment attempt.');
        }
    }

    /**
     * Submit attempt.
     */
    public function submit(Request $request, Attempt $attempt): RedirectResponse
    {
        $lockKey = "candidate_attempt:{$attempt->id}";

        try {
            return Cache::lock($lockKey, 60)->block(15, function () use ($request, $attempt) {
                $attempt->refresh();
                $this->authorizeAttemptAccess($attempt, $request->user());

                $statusValue = is_object($attempt->status) ? $attempt->status->value : (string) $attempt->status;

                // Idempotency: If attempt is already submitted/terminal, safely redirect to review without recalculating or re-scoring
                if ($statusValue !== AttemptStatus::InProgress->value && $statusValue !== 'in_progress') {
                    return redirect()->route('candidate.review', $attempt);
                }

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
            });
        } catch (LockTimeoutException $e) {
            return redirect()->route('candidate.review', $attempt);
        }
    }

    /**
     * Candidate result review view.
     */
    public function review(Request $request, Attempt $attempt): View|RedirectResponse
    {
        $this->authorizeAttemptAccess($attempt, $request->user());

        $statusValue = is_object($attempt->status) ? $attempt->status->value : (string) $attempt->status;

        // Do not render review / result for an in_progress attempt
        if ($statusValue === AttemptStatus::InProgress->value || $statusValue === 'in_progress') {
            return redirect()->route('candidate.exam', $attempt)
                ->with('error', 'Assessment is still in progress. Please submit before viewing results.');
        }

        $summary = $this->engine->reviewAttempt($attempt);

        /** @var view-string $viewName */
        $viewName = 'assessment::candidate.review';

        return view($viewName, compact('summary', 'attempt'));
    }

    /**
     * Candidate Simulator Wrong Answer Review (Read-Only).
     */
    public function wrongAnswersReview(Request $request, Attempt $attempt): View|RedirectResponse
    {
        $this->authorizeAttemptAccess($attempt, $request->user());

        if ($attempt->status->value === 'in_progress') {
            abort(403, 'Wrong answer review is not available while test is in progress.');
        }

        if (!$attempt->test?->isSimulator()) {
            abort(403, 'Wrong answer review is only available for Simulator practice assessments.');
        }

        $attempt->loadMissing([
            'test.sections' => fn($q) => $q->orderBy('order'),
            'test.sections.testQuestions' => fn($q) => $q->orderBy('order'),
            'test.sections.testQuestions.question.choices',
            'test.sections.testQuestions.question.passage',
            'test.sections.testQuestions.question.passageGroup.passages',
            'test.sections.testQuestions.question.audioGroup.mediaAsset',
            'test.sections.testQuestions.question.mediaAsset',
            'test.sections.mediaAssets',
            'answers.question.choices',
            'answers.question.passage',
            'answers.selectedChoice',
        ]);

        $summary = $this->engine->reviewAttempt($attempt);
        $deliveryData = \App\Modules\Assessment\Services\DeliveryUnitBuilder::build($attempt->test, $attempt);

        $answersByQuestion = $attempt->answers->keyBy('question_id');
        $incorrectQuestionIds = $attempt->answers->where('is_correct', false)->pluck('question_id')->toArray();

        $wrongDeliveryUnits = $deliveryData['deliveryUnits']->filter(function ($unit) use ($incorrectQuestionIds) {
            return $unit['questions']->contains(fn($q) => in_array($q->id, $incorrectQuestionIds, true));
        })->values();

        /** @var view-string $viewName */
        $viewName = 'assessment::candidate.wrong_answers';

        return view($viewName, [
            'attempt'              => $attempt,
            'summary'              => $summary,
            'wrongDeliveryUnits'   => $wrongDeliveryUnits,
            'incorrectQuestionIds' => $incorrectQuestionIds,
            'answersByQuestion'    => $answersByQuestion,
            'deliveryData'         => $deliveryData,
            'totalQuestionsCount'  => $deliveryData['totalQuestionsCount'],
        ]);
    }

    /**
     * Finalize Attempt 1 result & release final score.
     */
    public function finalizeAttempt(Request $request, Attempt $attempt): RedirectResponse
    {
        $lockKey = "candidate_attempt_decision:{$attempt->id}";

        try {
            return Cache::lock($lockKey, 15)->block(10, function () use ($request, $attempt) {
                $attempt->refresh();
                $this->authorizeAttemptAccess($attempt, $request->user());

                $attempt->loadMissing(['test', 'assignment.attempts']);
                $assignment = $attempt->assignment;

                // Verify attempt is submitted
                if ($attempt->status !== AttemptStatus::Submitted) {
                    return redirect()->route('candidate.review', $attempt)
                        ->with('error', 'Only completed assessments can be finalized.');
                }

                // Idempotency: if already finalized, return success
                if ($attempt->is_final && $attempt->decision_status === 'finalized') {
                    return redirect()->route('candidate.review', $attempt)
                        ->with('status', 'Assessment result has already been finalized.');
                }

                // Verify assignment exists and is active (or matching)
                if (!$assignment) {
                    $attempt->update([
                        'is_final'        => true,
                        'decision_status' => 'finalized',
                    ]);
                    return redirect()->route('candidate.review', $attempt)
                        ->with('status', 'Assessment score finalized successfully.');
                }

                // If Attempt 2 has already been started, Attempt 1 cannot be arbitrarily finalized
                $hasSecondAttempt = $assignment->attempts()->where('attempt_number', 2)->exists();
                if ($hasSecondAttempt) {
                    return redirect()->route('candidate.review', $attempt)
                        ->with('error', 'A second attempt has already been initiated for this assessment.');
                }

                \Illuminate\Support\Facades\DB::transaction(function () use ($attempt, $assignment) {
                    $attempt->update([
                        'is_final'        => true,
                        'decision_status' => 'finalized',
                    ]);

                    $assignment->update([
                        'status'           => 'completed',
                        'completed_at'     => now(),
                        'final_attempt_id' => $attempt->id,
                    ]);

                    \App\Services\ActivityLogger::log(
                        'CANDIDATE_ATTEMPT_FINALIZED',
                        "Candidate finalized Attempt #{$attempt->attempt_number} for '{$attempt->test?->title}'",
                        $attempt
                    );
                });

                // Issue digital certificate for authoritative final result if eligible
                app(\App\Modules\Certificate\Engines\CertificateEngine::class)->issueCertificateForFinalResult($assignment->fresh());

                return redirect()->route('candidate.review', $attempt)
                    ->with('status', 'Result finalized. Your score has been released as your final institutional result.');
            });
        } catch (LockTimeoutException $e) {
            return redirect()->route('candidate.review', $attempt);
        }
    }

    /**
     * Retry second attempt for Mock Test.
     */
    public function retryAttempt(Request $request, Attempt $attempt): RedirectResponse
    {
        $lockKey = "candidate_attempt_decision:{$attempt->id}";

        try {
            return Cache::lock($lockKey, 15)->block(10, function () use ($request, $attempt) {
                $attempt->refresh();
                $this->authorizeAttemptAccess($attempt, $request->user());

                $user = $request->user();

                $attempt->loadMissing(['test', 'assignment.attempts']);
                $assignment = $attempt->assignment;

                // Verify Attempt 1 is submitted
                if ($attempt->status !== AttemptStatus::Submitted) {
                    return redirect()->route('candidate.review', $attempt)
                        ->with('error', 'Please complete your first attempt before starting a retry.');
                }

                // Verify Attempt 1 is not finalized
                if ($attempt->is_final || $attempt->decision_status === 'finalized') {
                    return redirect()->route('candidate.review', $attempt)
                        ->with('error', 'Cannot retry an assessment that has already been finalized.');
                }

                if (!$assignment || $assignment->status !== 'active') {
                    return redirect()->route('candidate.review', $attempt)
                        ->with('error', 'This assessment assignment is no longer active.');
                }

                // Idempotency / Double-click check: If Attempt 2 already exists, redirect to it
                $existingAttempt2 = $assignment->attempts()->where('attempt_number', 2)->first();
                if ($existingAttempt2) {
                    if ($existingAttempt2->status === AttemptStatus::InProgress) {
                        return redirect()->route('candidate.exam', $existingAttempt2);
                    }
                    return redirect()->route('candidate.review', $existingAttempt2);
                }

                // Verify attempt limit
                if ($assignment->attempts()->count() >= $assignment->max_attempts) {
                    return redirect()->route('candidate.review', $attempt)
                        ->with('error', 'Maximum number of attempts reached for this assignment.');
                }

                $attempt2 = \Illuminate\Support\Facades\DB::transaction(function () use ($attempt, $assignment, $user) {
                    $attempt->update([
                        'decision_status' => 'retried',
                        'is_final'        => false,
                    ]);

                    $evaluationStatus = $attempt->test?->requiresEvaluation()
                        ? EvaluationStatus::PendingEvaluation
                        : EvaluationStatus::NotRequired;

                    $newAttempt = Attempt::create([
                        'test_id'           => $attempt->test_id,
                        'user_id'           => $user->id,
                        'assignment_id'     => $assignment->id,
                        'attempt_number'    => 2,
                        'is_final'          => false,
                        'decision_status'   => 'pending_decision',
                        'started_at'        => now(),
                        'status'            => AttemptStatus::InProgress,
                        'evaluation_status' => $evaluationStatus,
                        'seed'              => \Illuminate\Support\Str::random(10),
                    ]);

                    $assignment->update([
                        'attempts_count' => $assignment->attempts()->count(),
                    ]);

                    \App\Services\ActivityLogger::log(
                        'CANDIDATE_ATTEMPT_RETRIED',
                        "Candidate started Attempt #2 for '{$attempt->test?->title}'",
                        $newAttempt
                    );

                    return $newAttempt;
                });

                return redirect()->route('candidate.exam', $attempt2);
            });
        } catch (LockTimeoutException $e) {
            return redirect()->route('candidate.review', $attempt);
        }
    }
}
