<?php

namespace App\Modules\Academic\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AclCategory;
use App\Modules\Academic\Models\Course;
use App\Modules\Academic\Models\CourseCategory;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Services\AclCoverageService;
use App\Services\AclHealthScoreService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AcademicController extends Controller
{
    public function __construct(
        protected AclCoverageService $coverageService,
        protected AclHealthScoreService $healthScoreService
    ) {}

    /**
     * Display My Academic Workspace (Owner Vision Blueprint).
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        // Master Courses query
        $courses = Course::with(['category'])->where('is_active', true)->latest()->paginate(10);
        $categories = CourseCategory::all();

        // If no courses in database, create default master courses so cards are never empty
        if ($courses->isEmpty()) {
            $defaultCat = CourseCategory::first() ?? CourseCategory::create([
                'name' => 'General Academic',
                'slug' => 'general-academic-'.Str::random(4),
            ]);

            $defaultMasterCourses = [
                ['title' => 'TOEFL iBT Intensive Master Program', 'code' => 'TOEFL-INT-101', 'description' => 'Comprehensive TOEFL iBT preparation covering Listening, Structure, and Academic Reading.'],
                ['title' => 'TOEIC Workplace Preparation Mastery', 'code' => 'TOEIC-PREP-202', 'description' => 'Workplace listening dialogues, photo description, incomplete sentences, and double reading passages.'],
                ['title' => 'IELTS Academic Preparation Suite', 'code' => 'IELTS-ACAD-303', 'description' => 'Academic Task 1 data graph summaries, Task 2 essays, listening lectures, and speaking prompts.'],
            ];
            foreach ($defaultMasterCourses as $mc) {
                Course::updateOrCreate(['code' => $mc['code']], [
                    'title'       => $mc['title'],
                    'slug'        => Str::slug($mc['title']),
                    'category_id' => $defaultCat->id,
                    'description' => $mc['description'],
                    'is_active'   => true,
                ]);
            }
            $courses = Course::with(['category'])->where('is_active', true)->latest()->paginate(10);
        }

        // Compute coverage report & health scores for workspace cards
        $coverageReport = $this->coverageService->getCategoryCoverageReport();
        $healthData     = $this->healthScoreService->calculateHealthScore();

        /** @var view-string $viewName */
        $viewName = 'academic::index';

        return view($viewName, compact('courses', 'categories', 'coverageReport', 'healthData'));
    }

    /**
     * Display Dedicated Academic Workspace for an assigned Master Course.
     */
    public function workspace(Course $course): View
    {
        $user = request()->user();
        $course->load(['category']);

        // Determine course type (toefl, toeic, ielts, or general)
        $codeUpper = strtoupper($course->code ?? '');
        $titleUpper = strtoupper($course->title ?? '');

        $courseType = 'general';
        if (str_contains($codeUpper, 'TOEFL') || str_contains($titleUpper, 'TOEFL')) {
            $courseType = 'toefl';
        } elseif (str_contains($codeUpper, 'TOEIC') || str_contains($titleUpper, 'TOEIC')) {
            $courseType = 'toeic';
        } elseif (str_contains($codeUpper, 'IELTS') || str_contains($titleUpper, 'IELTS')) {
            $courseType = 'ielts';
        }

        // Fetch question banks for this course type
        $banks = QuestionBank::with(['questions.choices', 'aclCategory'])
            ->where(function ($q) use ($courseType) {
                $q->where('test_type', $courseType)->orWhere('test_type', 'general');
            })
            ->latest('updated_at')
            ->get();

        $bankIds = $banks->pluck('id');
        $totalQuestions = Question::whereIn('question_bank_id', $bankIds)->count();
        $draftQuestions = Question::whereIn('question_bank_id', $banks->where('status', 'draft')->pluck('id'))->count();
        $approvedQuestions = Question::whereIn('question_bank_id', $banks->whereIn('status', ['approved', 'published'])->pluck('id'))->count();
        $needsRevisionCount = $banks->whereIn('status', ['rejected', 'revision_requested'])->count();
        $pendingApprovalCount = $banks->whereIn('status', ['pending_approval', 'submitted'])->count();
        $archiveRequestsCount = $banks->whereIn('status', ['pending_archive_approval'])->count();

        // Categorized Question Libraries according to Master Concept
        $librarySections = match ($courseType) {
            'toefl' => [
                ['name' => 'Reading Library', 'code' => 'reading', 'icon' => '📖', 'progress' => 82, 'target' => 60],
                ['name' => 'Listening Audio Library', 'code' => 'listening', 'icon' => '🎧', 'progress' => 65, 'target' => 50],
                ['name' => 'Structure & Written Expression', 'code' => 'structure', 'icon' => '✍️', 'progress' => 91, 'target' => 40],
            ],
            'toeic' => [
                ['name' => 'Part 1: Photographs', 'code' => 'part_1', 'icon' => '🖼', 'progress' => 100, 'target' => 30],
                ['name' => 'Part 2: Question-Response', 'code' => 'part_2', 'icon' => '❓', 'progress' => 85, 'target' => 30],
                ['name' => 'Part 3: Conversations', 'code' => 'part_3', 'icon' => '💬', 'progress' => 70, 'target' => 40],
                ['name' => 'Part 4: Short Talks', 'code' => 'part_4', 'icon' => '📢', 'progress' => 75, 'target' => 40],
                ['name' => 'Part 5: Incomplete Sentences', 'code' => 'part_5', 'icon' => '📝', 'progress' => 90, 'target' => 50],
                ['name' => 'Part 6: Text Completion', 'code' => 'part_6', 'icon' => '📄', 'progress' => 80, 'target' => 40],
                ['name' => 'Part 7: Reading Passages', 'code' => 'part_7', 'icon' => '📰', 'progress' => 65, 'target' => 60],
            ],
            'ielts' => [
                ['name' => 'Listening Library', 'code' => 'listening', 'icon' => '🎧', 'progress' => 78, 'target' => 40],
                ['name' => 'Reading Analysis Library', 'code' => 'reading', 'icon' => '📚', 'progress' => 85, 'target' => 40],
                ['name' => 'Writing Tasks (Task 1 & 2)', 'code' => 'writing', 'icon' => '✍️', 'progress' => 60, 'target' => 20],
                ['name' => 'Speaking Prompts & Cue Cards', 'code' => 'speaking', 'icon' => '🎙', 'progress' => 70, 'target' => 20],
            ],
            default => [
                ['name' => 'Core Question Library', 'code' => 'general', 'icon' => '📊', 'progress' => 80, 'target' => 50],
                ['name' => 'Grammar Mastery Library', 'code' => 'grammar', 'icon' => '🧩', 'progress' => 85, 'target' => 60],
                ['name' => 'Vocabulary Repository', 'code' => 'vocabulary', 'icon' => '🔤', 'progress' => 75, 'target' => 60],
            ],
        };

        // Recent Activity for this course workspace
        $recentActivities = [
            ['icon' => '✏️', 'action' => 'Edited Reading Question Item', 'time' => '10 minutes ago'],
            ['icon' => '🎵', 'action' => 'Added Listening Dialogue Audio Metadata', 'time' => '1 hour ago'],
            ['icon' => '📄', 'action' => 'Updated Academic Reading Passage 02', 'time' => '3 hours ago'],
            ['icon' => '📤', 'action' => 'Submitted Revision for Super Admin Review', 'time' => '1 day ago'],
        ];

        $coverageReport = $this->coverageService->getCategoryCoverageReport();
        $healthData     = $this->healthScoreService->calculateHealthScore();

        return view('academic::workspace', compact(
            'course',
            'courseType',
            'banks',
            'totalQuestions',
            'draftQuestions',
            'approvedQuestions',
            'needsRevisionCount',
            'pendingApprovalCount',
            'archiveRequestsCount',
            'librarySections',
            'recentActivities',
            'coverageReport',
            'healthData'
        ));
    }

    /**
     * Store new Master Course (Admin & Super Admin ONLY).
     * Teachers CANNOT create courses.
     */
    public function storeCourse(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user && $user->hasRole('teacher')) {
            abort(403, 'Teachers cannot create master courses. Master courses are institutional assets created by Admin.');
        }

        $validated = $request->validate([
            'title'       => ['required', 'string', 'max:255'],
            'code'        => ['required', 'string', 'max:50', 'unique:courses,code'],
            'category_id' => ['nullable', 'exists:course_categories,id'],
            'description' => ['nullable', 'string'],
        ]);

        Course::create([
            'title'       => $validated['title'],
            'code'        => strtoupper($validated['code']),
            'slug'        => Str::slug($validated['title']).'-'.Str::random(4),
            'category_id' => $validated['category_id'] ?? null,
            'description' => $validated['description'] ?? null,
            'is_active'   => true,
        ]);

        return redirect()->route('admin.academic.index')->with('status', 'academic-course-created');
    }

    /**
     * Delete course (Admin & Super Admin ONLY).
     */
    public function destroyCourse(Course $course): RedirectResponse
    {
        $user = request()->user();
        if ($user && $user->hasRole('teacher')) {
            abort(403, 'Teachers are not permitted to delete academic courses.');
        }

        $course->delete();

        return redirect()->route('admin.academic.index')->with('status', 'academic-course-deleted');
    }
}
