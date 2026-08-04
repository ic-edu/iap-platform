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
     * Display main Academic Library Index (overview of all categories).
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $coverageReport = $this->coverageService->getCategoryCoverageReport();
        $healthData = $this->healthScoreService->calculateHealthScore();
        $categories = AclCategory::where('is_active', true)->get();

        // Total institutional banks summary
        $myBanksQuery = QuestionBank::query();
        if ($user && $user->hasRole('teacher')) {
            $myBanksQuery->where('created_by', $user->id);
        }

        $totalBanks     = (clone $myBanksQuery)->count();
        $publishedBanks = (clone $myBanksQuery)->whereIn('status', ['published', 'approved'])->count();
        $draftBanks     = (clone $myBanksQuery)->where('status', 'draft')->count();
        $pendingBanks   = (clone $myBanksQuery)->whereIn('status', ['pending_approval', 'submitted', 'pending_archive_approval'])->count();

        return view('admin.academic_library.index', compact(
            'coverageReport',
            'healthData',
            'categories',
            'totalBanks',
            'publishedBanks',
            'draftBanks',
            'pendingBanks'
        ));
    }

    /**
     * Display a dedicated Library Category Page (PART 2 & 3).
     * e.g. /admin/academic-library/toefl-listening
     */
    public function show(Request $request, string $slug): View
    {
        $user = $request->user();
        $category = AclCategory::where('slug', $slug)->firstOrFail();

        // Query banks belonging ONLY to this specific category
        $query = QuestionBank::with(['aclCategory', 'creator', 'questions'])
            ->where(function ($q) use ($category) {
                $q->where('acl_category_id', $category->id)
                  ->orWhere(function ($sub) use ($category) {
                      $sub->where('test_type', $category->test_type)
                          ->where('slug', 'like', "%{$category->slug}%");
                  });
            });

        // Filter by author if requested
        if ($request->input('author') === 'me' || $request->has('my')) {
            $query->where('created_by', $user->id);
        }

        // Search by Title or Description
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Workflow status filter
        if ($status = $request->input('status')) {
            if ($status === 'published') {
                $query->whereIn('status', ['published', 'approved']);
            } else {
                $query->where('status', $status);
            }
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
}
