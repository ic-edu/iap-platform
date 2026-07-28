<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Modules\Academic\Models\Course;
use App\Modules\Assessment\Models\Test as AssessmentTest;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TeacherDashboardController extends Controller
{
    /**
     * Display Teacher Authoring & Academic Workspace Dashboard.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        $totalQuestionBanks = QuestionBank::count();
        $totalQuestions = Question::count();
        $draftTests = AssessmentTest::where('is_published', false)->count();
        $publishedTests = AssessmentTest::where('is_published', true)->count();
        $totalCourses = Course::count();

        $recentQuestionBanks = QuestionBank::with(['category', 'questions'])
            ->latest()
            ->take(5)
            ->get();

        return view('teacher.dashboard', compact(
            'totalQuestionBanks',
            'totalQuestions',
            'draftTests',
            'publishedTests',
            'totalCourses',
            'recentQuestionBanks'
        ));
    }
}
