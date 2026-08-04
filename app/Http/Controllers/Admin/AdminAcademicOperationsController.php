<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use App\Models\StudentApplication;
use App\Models\TeacherAssignment;
use App\Models\User;
use App\Modules\Academic\Models\Course;
use App\Modules\Academic\Models\CourseCategory;
use App\Modules\Academic\Models\CourseEnrollment;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Notifications\SystemAlertNotification;
use App\Services\AclCoverageService;
use App\Services\AclHealthScoreService;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AdminAcademicOperationsController extends Controller
{
    public function __construct(
        protected AclCoverageService $coverageService,
        protected AclHealthScoreService $healthScoreService
    ) {}

    /**
     * 1. Student Applications Module
     */
    public function applications(Request $request): View
    {
        $query = StudentApplication::query();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('registration_id', 'like', "%{$search}%")
                  ->orWhere('student_name', 'like', "%{$search}%");
            });
        }

        if ($program = $request->input('program')) {
            $query->where('selected_program', $program);
        }

        if ($status = $request->input('status')) {
            $query->where('application_status', $status);
        }

        $applications = $query->latest()->paginate(10)->withQueryString();

        // Seed representative applications if empty
        if ($applications->isEmpty() && ! $request->has('search')) {
            $sampleApps = [
                ['registration_id' => 'APP-2026-001', 'student_name' => 'Budi Santoso', 'selected_program' => 'TOEFL', 'english_level' => 'Intermediate', 'placement_test_status' => 'completed', 'target_score' => 580, 'application_status' => 'ready_for_assignment'],
                ['registration_id' => 'APP-2026-002', 'student_name' => 'Siti Rahmawati', 'selected_program' => 'TOEIC', 'english_level' => 'Upper-Intermediate', 'placement_test_status' => 'waived', 'target_score' => 780, 'application_status' => 'assigned'],
                ['registration_id' => 'APP-2026-003', 'student_name' => 'Ahmad Hidayat', 'selected_program' => 'IELTS', 'english_level' => 'Intermediate', 'placement_test_status' => 'placement_required', 'target_score' => 7, 'application_status' => 'placement_required'],
                ['registration_id' => 'APP-2026-004', 'student_name' => 'Dewi Lestari', 'selected_program' => 'TOEFL', 'english_level' => 'Beginner', 'placement_test_status' => 'placement_required', 'target_score' => 500, 'application_status' => 'waiting_review'],
            ];
            foreach ($sampleApps as $sa) {
                StudentApplication::updateOrCreate(['registration_id' => $sa['registration_id']], $sa);
            }
            $applications = StudentApplication::latest()->paginate(10)->withQueryString();
        }

        return view('admin.academic_operations.applications', compact('applications'));
    }

    /**
     * Update Student Application Status
     */
    public function updateApplicationStatus(Request $request, StudentApplication $application): RedirectResponse
    {
        $validated = $request->validate([
            'application_status' => ['required', 'string'],
            'notes'              => ['nullable', 'string'],
        ]);

        $application->update($validated);

        ActivityLogger::log('APPLICATION_STATUS_UPDATED', "Updated application {$application->registration_id} status to {$application->application_status}", request()->user());

        return back()->with('status', "Application {$application->registration_id} status updated to {$application->application_status}.");
    }

    /**
     * 2. Master Course Management & Approval
     */
    public function courses(Request $request): View
    {
        $courses = Course::with(['category', 'assignedTeachers', 'teacherAssignments.teacher', 'assignedTeacher'])->latest()->paginate(10);
        $categories = CourseCategory::all();
        $teachers = User::role('teacher')->get();

        return view('admin.academic_operations.courses', compact('courses', 'categories', 'teachers'));
    }

    /**
     * Store Master Course (Admin creates -> status waiting_approval)
     */
    public function storeMasterCourse(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user && $user->hasRole('teacher')) {
            abort(403, 'Teachers cannot create master courses.');
        }

        $validated = $request->validate([
            'title'       => ['required', 'string', 'max:255'],
            'code'        => ['required', 'string', 'max:50', 'unique:courses,code'],
            'program'     => ['required', 'string'],
            'category_id' => ['nullable', 'exists:course_categories,id'],
            'capacity'    => ['required', 'integer', 'min:1'],
            'start_date'  => ['nullable', 'date'],
            'end_date'    => ['nullable', 'date'],
            'schedule'    => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
        ]);

        $course = Course::create([
            'title'         => $validated['title'],
            'code'          => strtoupper($validated['code']),
            'slug'          => Str::slug($validated['title']).'-'.Str::random(4),
            'program'       => $validated['program'],
            'category_id'   => $validated['category_id'] ?? null,
            'capacity'      => $validated['capacity'],
            'start_date'    => $validated['start_date'] ?? null,
            'end_date'      => $validated['end_date'] ?? null,
            'schedule'      => $validated['schedule'] ?? 'Mon, Wed 09:00 - 11:00 AM',
            'description'   => $validated['description'] ?? null,
            'course_status' => 'waiting_approval',
            'is_active'     => false,
        ]);

        ActivityLogger::log('MASTER_COURSE_CREATED', "Created master course {$course->code} pending SA approval", $user);

        // Clickable Notification to Super Admins
        $superAdmins = User::role('super-admin')->get();
        foreach ($superAdmins as $sa) {
            try {
                $sa->notify(new SystemAlertNotification(
                    'Master Course Approval Required',
                    "Operational Admin {$user->name} created Master Course {$course->title} ({$course->code}) requiring approval.",
                    route('admin.academic-operations.courses')
                ));
            } catch (\Throwable $e) {
                // Silently handle
            }
        }

        return redirect()->route('admin.academic-operations.courses')
            ->with('status', "Master Course '{$course->title}' created. Submitted to Super Admin for approval.");
    }

    /**
     * Super Admin Approves Master Course
     */
    public function approveCourse(Course $course): RedirectResponse
    {
        $user = request()->user();
        if (!$user || !$user->hasRole('super-admin')) {
            abort(403, 'Only Super Admin can approve master courses.');
        }

        $course->update([
            'course_status' => 'active',
            'is_active'     => true,
        ]);

        ActivityLogger::log('MASTER_COURSE_APPROVED', "Approved master course {$course->code}", $user);

        // Clickable Notification to Admins
        $admins = User::role('admin')->get();
        foreach ($admins as $admin) {
            try {
                $admin->notify(new SystemAlertNotification(
                    'Master Course Approved',
                    "Super Admin {$user->name} approved Master Course '{$course->title}' ({$course->code}). Course is now ACTIVE.",
                    route('admin.academic-operations.courses')
                ));
            } catch (\Throwable $e) {
                // Silently handle
            }
        }

        return redirect()->route('admin.academic-operations.courses')
            ->with('status', "Master Course '{$course->title}' approved and activated successfully.");
    }

    /**
     * Super Admin Rejects Master Course
     */
    public function rejectCourse(Request $request, Course $course): RedirectResponse
    {
        $user = $request->user();
        if (!$user || !$user->hasRole('super-admin')) {
            abort(403, 'Only Super Admin can reject master courses.');
        }

        $course->update(['course_status' => 'draft', 'is_active' => false]);

        return redirect()->route('admin.academic-operations.courses')
            ->with('status', "Master Course '{$course->title}' rejected and reverted to draft.");
    }

    /**
     * 3. Teacher Assignment Module
     */
    public function teacherAssignments(Request $request): View
    {
        $assignments = TeacherAssignment::with(['teacher', 'course'])->latest()->paginate(10);
        $teachers = User::role('teacher')->get();
        $activeCourses = Course::whereIn('course_status', ['active', 'approved'])->orWhere('is_active', true)->get();

        return view('admin.academic_operations.teacher_assignments', compact('assignments', 'teachers', 'activeCourses'));
    }

    /**
     * Assign Teacher into approved course & trigger Clickable Notification
     */
    public function storeTeacherAssignment(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user && $user->hasRole('teacher')) {
            abort(403, 'Teachers cannot assign teachers.');
        }

        $validated = $request->validate([
            'teacher_id'       => ['required', 'exists:users,id'],
            'course_id'        => ['required', 'exists:courses,id'],
            'role'             => ['required', 'string'],
            'assignment_notes' => ['nullable', 'string'],
        ]);

        $roleClean = in_array($validated['role'], ['lead', 'assistant', 'mentor'])
            ? $validated['role']
            : (str_contains($validated['role'], 'lead') ? 'lead' : (str_contains($validated['role'], 'assistant') ? 'assistant' : 'mentor'));

        $assignment = TeacherAssignment::create([
            'teacher_id'       => $validated['teacher_id'],
            'course_id'        => $validated['course_id'],
            'role'             => $roleClean,
            'assigned_by'      => $user?->id,
            'assigned_at'      => now(),
            'status'           => 'active',
            'assignment_notes' => $validated['assignment_notes'] ?? null,
        ]);

        $course = Course::find($validated['course_id']);
        if ($course) {
            $course->update(['assigned_teacher_id' => $validated['teacher_id']]);
        }

        $teacherUser = User::find($validated['teacher_id']);

        ActivityLogger::log('TEACHER_ASSIGNED', "Assigned teacher {$teacherUser?->name} to course {$course?->title}", $user);

        // Clickable Notification to Assigned Teacher
        if ($teacherUser) {
            try {
                $teacherUser->notify(new SystemAlertNotification(
                    'New Academic Assignment',
                    "You have been assigned as " . strtoupper(str_replace('_', ' ', $validated['role'])) . " for '{$course?->title}' ({$course?->code}).",
                    route('admin.academic.index')
                ));
            } catch (\Throwable $e) {
                // Silently handle
            }
        }

        return redirect()->route('admin.academic-operations.teacher-assignments')
            ->with('status', "Teacher '{$teacherUser?->name}' assigned to '{$course?->title}' successfully.");
    }

    /**
     * 4. Student Enrolments Module
     */
    public function enrollments(Request $request): View
    {
        $enrollments = CourseEnrollment::with(['student', 'course.assignedTeachers', 'course.teacherAssignments.teacher', 'course.assignedTeacher'])->latest()->paginate(10);
        $students = User::role('student')->get();
        $activeCourses = Course::whereIn('course_status', ['active', 'approved'])->orWhere('is_active', true)->get();

        return view('admin.academic_operations.enrollments', compact('enrollments', 'students', 'activeCourses'));
    }

    /**
     * Store Student Enrolment
     */
    public function storeEnrollment(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'student_id' => ['required', 'exists:users,id'],
            'course_id'  => ['required', 'exists:courses,id'],
        ]);

        $enrollment = CourseEnrollment::updateOrCreate(
            ['user_id' => $validated['student_id'], 'course_id' => $validated['course_id']],
            ['status' => 'active', 'enrolled_at' => now()]
        );

        $student = User::find($validated['student_id']);
        $course  = Course::find($validated['course_id']);

        // Clickable Notification to Student
        if ($student) {
            try {
                $student->notify(new SystemAlertNotification(
                    'Enrollment Completed',
                    "You have been successfully enrolled into '{$course?->title}'. Your student dashboard is active.",
                    route('candidate.portal')
                ));
            } catch (\Throwable $e) {
                // Silently handle
            }
        }

        return redirect()->route('admin.academic-operations.enrollments')
            ->with('status', "Student '{$student?->name}' enrolled into '{$course?->title}'.");
    }

    /**
     * 5. Academic Libraries Monitoring Page
     */
    public function libraries(Request $request): View
    {
        $testTypes = ['toefl' => 'TOEFL Library', 'toeic' => 'TOEIC Library', 'ielts' => 'IELTS Library', 'general' => 'Foundational Library'];

        $monitoringData = [];
        foreach ($testTypes as $typeKey => $typeName) {
            $banks = QuestionBank::where('test_type', $typeKey)->get();
            $bankIds = $banks->pluck('id');

            $qCount = Question::whereIn('question_bank_id', $bankIds)->count();
            $mCount = MediaAsset::where('test_type', $typeKey)->count();
            $draftCount = $banks->where('status', 'draft')->count();
            $pubCount = $banks->whereIn('status', ['published', 'approved'])->count();
            $archCount = $banks->where('status', 'archived')->count();

            $monitoringData[$typeKey] = [
                'name'            => $typeName,
                'banks_count'     => $banks->count(),
                'questions_count' => $qCount,
                'media_count'     => $mCount,
                'draft_count'     => $draftCount,
                'published_count' => $pubCount,
                'archived_count'  => $archCount,
                'health_score'    => min(100, (int) round(($pubCount / max(1, $banks->count())) * 100)),
            ];
        }

        return view('admin.academic_operations.libraries', compact('monitoringData'));
    }

    /**
     * 6. Course Monitoring Dashboard
     */
    public function monitoring(Request $request): View
    {
        $activeCoursesCount    = Course::whereIn('course_status', ['active', 'approved'])->orWhere('is_active', true)->count();
        $studentsAssignedCount = CourseEnrollment::where('status', 'active')->count();
        $teachersAssignedCount = TeacherAssignment::where('status', 'active')->count();
        $waitingApprovalCount  = Course::where('course_status', 'waiting_approval')->count();

        $recentlyCreatedCourses = Course::latest()->take(5)->get();
        $recentNotifications    = \App\Models\Notification::latest()->take(5)->get();

        return view('admin.academic_operations.monitoring', compact(
            'activeCoursesCount',
            'studentsAssignedCount',
            'teachersAssignedCount',
            'waitingApprovalCount',
            'recentlyCreatedCourses',
            'recentNotifications'
        ));
    }
}
