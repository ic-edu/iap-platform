<?php

namespace App\Modules\Academic\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Academic\Models\Course;
use App\Modules\Academic\Models\CourseCategory;
use App\Modules\Academic\Models\CourseEnrollment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AcademicController extends Controller
{
    /**
     * Display Academic Courses, Categories, and Enrollments management.
     */
    public function index(): View
    {
        $courses = Course::with(['category', 'enrollments'])->latest()->paginate(10);
        $categories = CourseCategory::all();
        $totalEnrollments = CourseEnrollment::count();

        /** @var view-string $viewName */
        $viewName = 'academic::index';

        return view($viewName, compact('courses', 'categories', 'totalEnrollments'));
    }

    /**
     * Store new Academic Course.
     */
    public function storeCourse(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:courses,code'],
            'category_id' => ['nullable', 'exists:course_categories,id'],
            'description' => ['nullable', 'string'],
        ]);

        Course::create([
            'title' => $validated['title'],
            'code' => strtoupper($validated['code']),
            'slug' => Str::slug($validated['title']).'-'.Str::random(4),
            'category_id' => $validated['category_id'] ?? null,
            'description' => $validated['description'] ?? null,
            'is_active' => true,
        ]);

        return redirect()->route('admin.academic.index')->with('status', 'academic-course-created');
    }

    /**
     * Delete course.
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
