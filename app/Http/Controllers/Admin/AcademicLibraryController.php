<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AclCategory;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Services\AclCoverageService;
use App\Services\AclHealthScoreService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AcademicLibraryController extends Controller
{
    public function __construct(
        protected AclCoverageService $coverageService,
        protected AclHealthScoreService $healthScoreService
    ) {}

    /**
     * Display main Academic Library Index (Institutional Overview ONLY).
     * Responsibility: Overall repository health, category coverage, repository navigation.
     * NO repository tables, NO author items, NO draft/pending/published counters here.
     */
    public function index(Request $request): View
    {
        $coverageReport = $this->coverageService->getCategoryCoverageReport();
        $healthData     = $this->healthScoreService->calculateHealthScore();
        $reportArray    = is_array($coverageReport) ? $coverageReport : (method_exists($coverageReport, 'all') ? $coverageReport->all() : (array) $coverageReport);

        // Group coverage items into formal academic program sections:
        // 1. TOEFL (Listening, Structure, Reading)
        // 2. TOEIC (Part 1 to Part 7)
        // 3. IELTS (Listening, Reading, Writing, Speaking)
        // 4. Institutional Foundation (Placement Test, Grammar, Vocabulary)
        $groupedCoverage = [
            'TOEFL' => array_filter($reportArray, fn($c) => str_contains(strtolower($c['category']->slug ?? ''), 'toefl')),
            'TOEIC' => array_filter($reportArray, fn($c) => str_contains(strtolower($c['category']->slug ?? ''), 'toeic')),
            'IELTS' => array_filter($reportArray, fn($c) => str_contains(strtolower($c['category']->slug ?? ''), 'ielts')),
            'Institutional Foundation' => array_filter($reportArray, fn($c) => !str_contains(strtolower($c['category']->slug ?? ''), 'toefl') && !str_contains(strtolower($c['category']->slug ?? ''), 'toeic') && !str_contains(strtolower($c['category']->slug ?? ''), 'ielts')),
        ];

        return view('admin.academic_library.index', compact(
            'coverageReport',
            'groupedCoverage',
            'healthData'
        ));
    }

    /**
     * Display a dedicated Library Category Page (PAGE 2).
     * e.g. /admin/academic-library/toefl-listening
     * Responsibility: Display ONLY repositories belonging to that specific category.
     */
    public function show(Request $request, string $slug): View
    {
        $user = $request->user();
        $category = AclCategory::where('slug', $slug)->firstOrFail();

        // Query repositories belonging ONLY to this specific category — strictly approved and published assets (TASK 6)
        $query = QuestionBank::with(['aclCategory', 'creator', 'questions'])
            ->whereIn('status', ['approved', 'published'])
            ->where(function ($q) use ($category) {
                $q->where('acl_category_id', $category->id)
                  ->orWhere(function ($sub) use ($category) {
                      $sub->where('test_type', $category->test_type)
                          ->where('slug', 'like', "%{$category->slug}%");
                  });
            });

        // Search by Title or Description
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Sort
        $sort = $request->input('sort', 'updated');
        match ($sort) {
            'created'      => $query->latest('created_at'),
            'questions'    => $query->withCount('questions')->orderBy('questions_count', 'desc'),
            'alphabetical' => $query->orderBy('title', 'asc'),
            default        => $query->latest('updated_at'),
        };

        $banks = $query->paginate(12)->withQueryString();

        // Metrics for THIS category
        $catBanksQuery = QuestionBank::where('acl_category_id', $category->id);
        $catTotal     = (clone $catBanksQuery)->count();
        $catPublished = (clone $catBanksQuery)->whereIn('status', ['published', 'approved'])->count();
        $catDraft     = (clone $catBanksQuery)->where('status', 'draft')->count();
        $catPending   = (clone $catBanksQuery)->whereIn('status', ['pending_approval', 'submitted'])->count();
        $catArchived  = (clone $catBanksQuery)->where('status', 'archived')->count();

        $allCategories = AclCategory::where('is_active', true)->get();

        return view('admin.academic_library.show', compact(
            'category',
            'banks',
            'catTotal',
            'catPublished',
            'catDraft',
            'catPending',
            'catArchived',
            'allCategories'
        ));
    }

    /**
     * Display Institution Repository Quality Dashboard (PART 11).
     */
    public function quality(Request $request): View
    {
        $qualityService = app(\App\Services\RepositoryQualityService::class);
        $summary = $qualityService->getGlobalQualitySummary();

        return view('admin.academic_library.quality', compact('summary'));
    }

    /**
     * Display Repository Explorer (TASK 2).
     */
    public function explorer(Request $request): View
    {
        $qualityService = app(\App\Services\RepositoryQualityService::class);
        $explorerData   = $qualityService->getExplorerAudits([
            'filter' => $request->input('filter', 'all'),
            'search' => $request->input('search', ''),
            'sort'   => $request->input('sort', ''),
        ]);

        return view('admin.academic_library.explorer', compact('explorerData'));
    }

    /**
     * Display Read-Only IRQA Analytics (TASK 1.5).
     */
    public function analytics(Request $request): View
    {
        $qualityService = app(\App\Services\RepositoryQualityService::class);
        $analyticsData  = $qualityService->getAnalyticsData();

        return view('admin.academic_library.analytics', compact('analyticsData'));
    }
}
