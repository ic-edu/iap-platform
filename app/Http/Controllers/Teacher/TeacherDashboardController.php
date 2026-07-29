<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Modules\Academic\Models\Course;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TeacherDashboardController extends Controller
{
    /**
     * Display Teacher Authoring & Academic Workspace Dashboard (QB-001 Issue 2).
     */
    public function index(Request $request): View
    {
        $totalQuestionBanks = QuestionBank::count();
        $draftQuestionBanks = QuestionBank::where('status', 'draft')->count();
        $pendingApprovalQuestionBanks = QuestionBank::where('status', 'pending_approval')->count();
        $publishedQuestionBanks = QuestionBank::where(function ($q) {
            $q->where('status', 'published')->orWhere('is_published', true);
        })->count();

        $totalQuestions = Question::count();
        $totalCourses = Course::count();

        $recentQuestionBanks = QuestionBank::with(['category', 'questions'])
            ->latest()
            ->take(5)
            ->get();

        return view('teacher.dashboard', compact(
            'totalQuestionBanks',
            'draftQuestionBanks',
            'pendingApprovalQuestionBanks',
            'publishedQuestionBanks',
            'totalQuestions',
            'totalCourses',
            'recentQuestionBanks'
        ));
    }
}
