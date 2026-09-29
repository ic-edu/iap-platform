<?php

namespace App\Modules\QuestionBank\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\QuestionBank\Enums\DifficultyLevel;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Modules\QuestionEngine\DTO\ToeicBlueprintRequest;
use App\Modules\QuestionEngine\Enums\GenerationBatchStatus;
use App\Modules\QuestionEngine\Models\QuestionGenerationBatch;
use App\Modules\QuestionEngine\Models\QuestionGenerationItem;
use App\Modules\QuestionEngine\Services\GenerationBatchFactory;
use App\Modules\QuestionEngine\Services\QuestionGenerationOrchestrator;
use App\Modules\QuestionEngine\Services\ToeicBlueprintPlanner;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QuestionGenerationWorkspaceController extends Controller
{
    public function __construct(
        protected ToeicBlueprintPlanner $toeicPlanner,
        protected GenerationBatchFactory $batchFactory,
        protected QuestionGenerationOrchestrator $orchestrator
    ) {}

    /**
     * Display Question Generator landing page listing Teacher's owned editable question banks.
     */
    public function landing(Request $request): View
    {
        $user = $request->user();

        if (!$user || !$user->hasRole('teacher')) {
            abort(403, 'Unauthorized access to question generator.');
        }

        $questionBanks = QuestionBank::where('created_by', $user->id)
            ->whereIn('status', ['draft', 'needs_revision', 'rejected'])
            ->withCount('questions')
            ->orderBy('updated_at', 'desc')
            ->get();

        /** @var view-string $viewName */
        $viewName = 'question_bank::generation_landing';

        return view($viewName, compact('questionBanks'));
    }

    /**
     * Display the Question Generation Workspace for an authorized Question Bank.
     */
    public function index(Request $request, QuestionBank $questionBank): View
    {
        $this->authorizeTeacherAccess($request, $questionBank);

        $batches = QuestionGenerationBatch::where('question_bank_id', $questionBank->id)
            ->with(['items.question.choices'])
            ->latest('created_at')
            ->take(10)
            ->get();

        $activeBatchId = $request->query('batch_id') ?? session('active_batch_id');
        $activeBatch = null;

        if ($activeBatchId) {
            $activeBatch = $batches->firstWhere('id', $activeBatchId)
                ?? QuestionGenerationBatch::where('id', $activeBatchId)
                    ->where('question_bank_id', $questionBank->id)
                    ->with(['items.question.choices'])
                    ->first();
        }

        if (!$activeBatch && $batches->isNotEmpty()) {
            $activeBatch = $batches->first();
        }

        $providerKey = strtolower((string) config('question_generation.provider', 'groq'));
        $providerDisplay = match ($providerKey) {
            'groq' => 'Groq (System Configured)',
            'openai' => 'OpenAI (System Configured)',
            'fake' => 'Simulated Provider (Test Mode)',
            default => ucfirst($providerKey).' (System Configured)',
        };

        $supportedOptions = [
            'quantities' => [1, 2, 3, 4, 5],
            'difficulties' => [
                'any' => 'Any (Balanced Distribution)',
                'easy' => DifficultyLevel::Easy->label(),
                'medium' => DifficultyLevel::Medium->label(),
                'hard' => DifficultyLevel::Hard->label(),
            ],
            'proficiencies' => [
                'any' => 'Any (Standard B1 Distribution)',
                'b1_low' => 'B1 Low — Emerging Intermediate',
                'b1_standard' => 'B1 Standard — Intermediate (Recommended)',
                'b2_low' => 'B2 Low — Emerging Upper Intermediate',
                'b2_standard' => 'B2 Standard — Upper Intermediate',
            ],
            'constructs' => [
                'any' => 'Any (Balanced Grammar & Vocabulary)',
                'grammar' => 'Grammar & Syntactic Structures',
                'vocabulary' => 'Vocabulary & Collocations',
            ],
        ];

        /** @var view-string $viewName */
        $viewName = 'question_bank::generation';

        return view($viewName, compact(
            'questionBank',
            'batches',
            'activeBatch',
            'providerDisplay',
            'supportedOptions'
        ));
    }

    /**
     * Submit and process a new generation batch.
     */
    public function generate(Request $request, QuestionBank $questionBank): RedirectResponse
    {
        $this->authorizeTeacherAccess($request, $questionBank);

        $validated = $request->validate([
            'quantity' => 'required|integer|min:1|max:5',
            'assessment' => 'required|string|in:toeic',
            'part' => 'required|integer|in:5',
            'difficulty' => 'nullable|string|in:any,easy,medium,hard',
            'proficiency_target' => 'nullable|string|in:any,b1_low,b1_standard,b2_low,b2_standard',
            'construct' => 'nullable|string|in:any,grammar,vocabulary',
        ]);

        try {
            $quantity = (int) $validated['quantity'];
            $difficultyChoice = $validated['difficulty'] ?? 'any';
            $proficiencyChoice = $validated['proficiency_target'] ?? 'any';
            $constructChoice = $validated['construct'] ?? 'any';

            // Build payload for ToeicBlueprintRequest
            $blueprintData = [
                'mode' => 'part',
                'part_number' => 5,
                'item_count' => $quantity,
                'content_mode' => 'general',
                'domain' => 'general_workplace',
            ];

            if ($difficultyChoice !== 'any') {
                $blueprintData['difficulty_distribution'] = [
                    $difficultyChoice => 100,
                ];
            }

            if ($proficiencyChoice !== 'any') {
                $blueprintData['proficiency_distribution'] = [
                    $proficiencyChoice => 100,
                ];
            }

            if ($constructChoice !== 'any') {
                $blueprintData['construct_distribution'] = [
                    5 => [
                        $constructChoice => 100,
                    ],
                ];
            }

            $blueprintRequest = ToeicBlueprintRequest::fromArray($blueprintData);
            $plan = $this->toeicPlanner->plan($blueprintRequest);

            $batch = $this->batchFactory->createFromPlan($plan, [
                'question_bank_id' => $questionBank->id,
                'requested_by' => $request->user()?->id,
                'force_new_run' => true,
            ]);

            $processedBatch = $this->orchestrator->processBatch($batch);

            $matCount = $processedBatch->validated_slots;
            $failCount = $processedBatch->failed_slots;

            if ($processedBatch->status === GenerationBatchStatus::Completed) {
                $flashType = 'success';
                $message = "Successfully generated and materialized {$matCount} question draft".($matCount > 1 ? 's' : '')." into '{$questionBank->title}'.";
            } elseif ($processedBatch->status === GenerationBatchStatus::PartiallyCompleted) {
                $flashType = 'warning';
                $message = "Batch generated with partial results: {$matCount} materialized, {$failCount} failed.";
            } else {
                $flashType = 'error';
                $message = "Question generation batch failed ({$failCount} slot".($failCount > 1 ? 's' : '').' failed). You may inspect details and retry below.';
            }

            return redirect()->route('admin.question-banks.generation.index', [
                'questionBank' => $questionBank->id,
                'batch_id' => $processedBatch->id,
            ])->with($flashType, $message)->with('active_batch_id', $processedBatch->id);
        } catch (Exception $e) {
            return redirect()->route('admin.question-banks.generation.index', $questionBank->id)
                ->with('error', 'Generation planning failed: '.$e->getMessage())
                ->withInput();
        }
    }

    /**
     * Retry a single eligible failed generation item.
     */
    public function retryItem(Request $request, QuestionBank $questionBank, QuestionGenerationItem $item): RedirectResponse
    {
        $this->authorizeTeacherAccess($request, $questionBank);

        // Enforce item belongs to batch belonging to this question bank
        $batch = $item->batch;
        if (!$batch || (string) $batch->question_bank_id !== (string) $questionBank->id) {
            abort(403, 'Unauthorized. Item does not belong to this Question Bank.');
        }

        if (!$item->isEligibleForRetry(maxAttempts: 3)) {
            return redirect()->route('admin.question-banks.generation.index', [
                'questionBank' => $questionBank->id,
                'batch_id' => $batch->id,
            ])->with('error', 'This item is not eligible for automated retry.');
        }

        try {
            $processedItem = $this->orchestrator->processItem($item, options: ['max_attempts' => 3]);

            if ($processedItem->status->value === 'materialized') {
                $msg = 'Item retried successfully and materialized as draft question.';
                $flashType = 'success';
            } else {
                $msg = "Item retry attempt {$processedItem->attempt_count} failed: ".($processedItem->last_error_message ?? 'Unknown error');
                $flashType = 'error';
            }

            return redirect()->route('admin.question-banks.generation.index', [
                'questionBank' => $questionBank->id,
                'batch_id' => $batch->id,
            ])->with($flashType, $msg)->with('active_batch_id', $batch->id);
        } catch (Exception $e) {
            return redirect()->route('admin.question-banks.generation.index', [
                'questionBank' => $questionBank->id,
                'batch_id' => $batch->id,
            ])->with('error', 'Retry execution error: '.$e->getMessage());
        }
    }

    /**
     * Retry all eligible failed items in a generation batch.
     */
    public function retryBatch(Request $request, QuestionBank $questionBank, QuestionGenerationBatch $batch): RedirectResponse
    {
        $this->authorizeTeacherAccess($request, $questionBank);

        if ((string) $batch->question_bank_id !== (string) $questionBank->id) {
            abort(403, 'Unauthorized. Batch does not belong to this Question Bank.');
        }

        try {
            $retriedBatch = $this->orchestrator->retryFailedItems($batch, maxAttempts: 3);

            $matCount = $retriedBatch->validated_slots;
            $failCount = $retriedBatch->failed_slots;

            if ($retriedBatch->status === GenerationBatchStatus::Completed) {
                $msg = "All items retried successfully. Total {$matCount} questions materialized.";
                $flashType = 'success';
            } elseif ($matCount > 0) {
                $msg = "Batch retry finished: {$matCount} materialized, {$failCount} remaining failed.";
                $flashType = 'warning';
            } else {
                $msg = "Batch retry finished: {$failCount} items failed.";
                $flashType = 'error';
            }

            return redirect()->route('admin.question-banks.generation.index', [
                'questionBank' => $questionBank->id,
                'batch_id' => $retriedBatch->id,
            ])->with($flashType, $msg)->with('active_batch_id', $retriedBatch->id);
        } catch (Exception $e) {
            return redirect()->route('admin.question-banks.generation.index', [
                'questionBank' => $questionBank->id,
                'batch_id' => $batch->id,
            ])->with('error', 'Batch retry execution error: '.$e->getMessage());
        }
    }

    /**
     * Server-side Teacher authorization & QuestionBank editability check.
     */
    protected function authorizeTeacherAccess(Request $request, QuestionBank $questionBank): void
    {
        $user = $request->user();
        if (!$user) {
            abort(401, 'Unauthenticated.');
        }

        // 1. Role Check: Strictly Teacher role only
        if (!$user->hasRole('teacher')) {
            abort(403, 'Question Generation Workspace is restricted to Teachers.');
        }

        // 2. Ownership Check: Teacher must own the Question Bank
        if ((int) $questionBank->created_by !== (int) $user->id) {
            abort(403, 'Unauthorized access to question bank. You can only generate questions for your own question banks.');
        }

        // 3. Editability Check: Question Bank must be in an editable status
        $editableStatuses = ['draft', 'needs_revision', 'revision_requested', 'rejected', null];
        $isEditable = in_array($questionBank->status, $editableStatuses, true)
            && !(bool) $questionBank->is_published
            && $questionBank->getLockMessage() === null;

        if (!$isEditable) {
            abort(403, 'Question Bank is locked or non-editable and cannot accept question generation.');
        }
    }
}
