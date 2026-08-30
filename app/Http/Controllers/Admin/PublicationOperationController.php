<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\QuestionBankArchiveRequest;
use App\Models\User;
use App\Modules\Assessment\Models\Test;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Notifications\EnterpriseSystemNotification;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicationOperationController extends Controller
{
    /**
     * Display Question Bank Publication Queue (ADMIN-OPS-001 Section 3).
     */
    public function questionBanksQueue(Request $request): View
    {
        $actor = $request->user();
        if (! $actor || (! $actor->hasRole('repository-manager') && ! $actor->hasRole('super-admin'))) {
            abort(403, 'Question Bank publication queue is strictly reserved for Repository Managers.');
        }

        $query = QuestionBank::with(['creator', 'category', 'questions', 'archiveRequests']);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        } else {
            // Default show items relevant to publication operation
            $query->whereIn('status', ['approved', 'published', 'archived', 'pending_archive_approval']);
        }

        $questionBanks = $query->latest()->paginate(10)->withQueryString();

        return view('admin.publications.question_banks', compact('questionBanks'));
    }

    /**
     * Display Assessment Publication Queue (ADMIN-OPS-001 Section 4).
     *
     * Real relationship hierarchy (no questions() on Test):
     * Test → sections() → testQuestions()
     */
    public function assessmentsQueue(Request $request): View
    {
        $query = Test::with(['creator', 'sections.testQuestions']);

        if ($search = $request->input('search')) {
            $query->where('title', 'like', "%{$search}%");
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        } else {
            $query->where(function ($q) {
                $q->whereIn('status', ['approved', 'published', 'archived', 'pending_archive_approval'])
                  ->orWhere('is_published', true);
            });
        }

        $assessments = $query->latest()->paginate(10)->withQueryString();

        return view('admin.publications.assessments', compact('assessments'));
    }

    /**
     * Display Published Contents Repository (ADMIN-OPS-001 Section 2 & 3).
     *
     * Test question count calculated via sections.testQuestions (no questions() on Test).
     */
    public function publishedContents(Request $request): View
    {
        $publishedBanks = QuestionBank::with(['creator', 'questions'])
            ->where(function ($q) {
                $q->where('status', 'published')->orWhere('is_published', true);
            })
            ->latest()
            ->get();

        $publishedAssessments = Test::with(['creator', 'sections.testQuestions'])
            ->where('is_published', true)
            ->latest()
            ->get();

        return view('admin.publications.published', compact('publishedBanks', 'publishedAssessments'));
    }

    /**
     * Display Archive Requests Queue for Admin (ADMIN-OPS-001 Section 2 & 12).
     */
    public function archiveRequests(Request $request): View
    {
        $archiveRequests = QuestionBankArchiveRequest::with(['questionBank', 'requester', 'reviewer'])
            ->latest()
            ->paginate(10);

        return view('admin.publications.archive_requests', compact('archiveRequests'));
    }

    /**
     * Publish approved Assessment Test (Repository Manager Operation).
     */
    public function publishAssessment(Request $request, Test $test): RedirectResponse
    {
        $actor = $request->user();
        if (! $actor || (! $actor->hasRole('repository-manager') && ! $actor->hasRole('super-admin'))) {
            abort(403, 'Publishing Assessment Tests is strictly reserved for Repository Managers.');
        }

        if ($test->status !== 'approved') {
            abort(403, 'Cannot publish: Assessment Test must be approved by Super Admin or Repository Manager first.');
        }

        $test->update([
            'status' => 'published',
            'is_published' => true,
        ]);

        ActivityLogger::log(
            'PUBLISH',
            "Published Assessment Test '{$test->title}' live",
            $test
        );

        ActivityLogger::log(
            'TASK_COMPLETED',
            "Completed task: Published Assessment Test '{$test->title}'",
            $test
        );

        // Notify Teacher Author (ADMIN-OPS-001 Section 8)
        if ($test->creator) {
            try {
                $test->creator->notify(new EnterpriseSystemNotification(
                    title: 'Assessment Published',
                    message: "Your Assessment Test '{$test->title}' has been published live by Repository Manager {$actor->name}.",
                    type: 'ASSESSMENT_PUBLISHED',
                    priority: 'HIGH',
                    entityType: 'assessment',
                    entityId: (string) $test->id,
                    targetUrl: route('admin.tests.show', $test->id)
                ));
            } catch (\Throwable $e) {
                // Silently handle in dev
            }
        }

        return redirect()->route('admin.publications.assessments')->with('status', "Assessment Test '{$test->title}' published live successfully.");
    }

    /**
     * Unpublish Assessment Test (Repository Manager Operation).
     */
    public function unpublishAssessment(Request $request, Test $test): RedirectResponse
    {
        $actor = $request->user();
        if (! $actor || (! $actor->hasRole('repository-manager') && ! $actor->hasRole('super-admin'))) {
            abort(403, 'Unpublishing Assessment Tests is strictly reserved for Repository Managers.');
        }

        $test->update([
            'status' => 'approved',
            'is_published' => false,
        ]);

        ActivityLogger::log(
            'UNPUBLISH',
            "Unpublished Assessment Test '{$test->title}'",
            $test
        );

        return redirect()->route('admin.publications.assessments')->with('status', "Assessment Test '{$test->title}' unpublished.");
    }

    /**
     * Publish approved Question Bank (Repository Manager Operation - ADMIN-OPS-001 Section 3).
     */
    public function publishQuestionBank(Request $request, QuestionBank $questionBank): RedirectResponse
    {
        $actor = $request->user();
        if (! $actor || ! $actor->hasRole('repository-manager')) {
            abort(403, 'Publishing Question Banks is strictly reserved for Repository Managers.');
        }

        if ($questionBank->status !== 'approved') {
            abort(403, 'Cannot publish: Question Bank must be approved by Super Admin first.');
        }

        $questionBank->update([
            'status' => 'published',
            'is_published' => true,
        ]);

        ActivityLogger::log(
            'PUBLISH',
            "Published Question Bank '{$questionBank->title}' live",
            $questionBank
        );

        // Notify Teacher Author
        if ($questionBank->creator) {
            try {
                $questionBank->creator->notify(new EnterpriseSystemNotification(
                    title: 'Question Bank Published',
                    message: "Your Question Bank '{$questionBank->title}' has been published live by Repository Manager {$actor->name}.",
                    type: 'QUESTION_BANK_PUBLISHED',
                    priority: 'HIGH',
                    entityType: 'question_bank',
                    entityId: (string) $questionBank->id,
                    targetUrl: route('admin.question-banks.show', $questionBank->id)
                ));
            } catch (\Throwable $e) {
                // Silently handle in dev
            }
        }

        return redirect()->route('admin.publications.question-banks')->with('status', "Question Bank '{$questionBank->title}' published live successfully.");
    }

    /**
     * Unpublish Question Bank (Repository Manager Operation - ADMIN-OPS-001 Section 3).
     */
    public function unpublishQuestionBank(Request $request, QuestionBank $questionBank): RedirectResponse
    {
        $actor = $request->user();
        if (! $actor || ! $actor->hasRole('repository-manager')) {
            abort(403, 'Unpublishing Question Banks is strictly reserved for Repository Managers.');
        }

        $questionBank->update([
            'status' => 'approved',
            'is_published' => false,
        ]);

        ActivityLogger::log(
            'UNPUBLISH',
            "Unpublished Question Bank '{$questionBank->title}'",
            $questionBank
        );

        return redirect()->route('admin.publications.question-banks')->with('status', "Question Bank '{$questionBank->title}' unpublished. Status reverted to Approved.");
    }
}
