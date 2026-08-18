<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Modules\QuestionBank\Models\QuestionBank;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class TeacherArchivedRepositoryController extends Controller
{
    /**
     * Display listing of archived question banks for the authenticated teacher.
     */
    public function index(Request $request): View
    {
        $teacherId = Auth::id();

        $query = QuestionBank::with(['category', 'questions', 'creator', 'archiveRequests'])
            ->where('status', 'archived');

        // Scoped to teacher unless super-admin
        if (!Auth::user()?->hasRole('super-admin')) {
            $query->where('created_by', $teacherId);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        $archivedBanks = $query->latest('updated_at')->paginate(12);

        return view('teacher.archived_repositories.index', compact('archivedBanks'));
    }
}
