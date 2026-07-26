<?php

namespace App\Modules\Reporting\Services;

use App\Models\User;
use App\Modules\Academic\Models\Course;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\Test;
use App\Modules\Certificate\Models\Certificate;
use App\Modules\Finance\Enums\PaymentStatus;
use App\Modules\Finance\Models\Payment;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class DashboardMetricsService
{
    /**
     * Get aggregated metrics with 5-minute cache.
     *
     * @return array<string, mixed>
     */
    public function getMetricsSummary(): array
    {
        return Cache::remember('admin.dashboard.metrics', 300, function () {
            return [
                'total_users' => $this->getTotalUsers(),
                'total_students' => $this->getTotalStudents(),
                'total_teachers' => $this->getTotalTeachers(),
                'total_courses' => $this->getTotalCourses(),
                'total_question_banks' => $this->getTotalQuestionBanks(),
                'total_questions' => $this->getTotalQuestions(),
                'total_active_tests' => $this->getActiveTestsCount(),
                'total_attempts' => $this->getTotalAttempts(),
                'total_certificates' => $this->getTotalCertificates(),
                'total_revenue' => $this->getTotalRevenue(),
            ];
        });
    }

    /**
     * Clear dashboard metrics cache.
     */
    public function clearMetricsCache(): void
    {
        Cache::forget('admin.dashboard.metrics');
    }

    /**
     * Get total registered users count.
     */
    public function getTotalUsers(): int
    {
        return User::count();
    }

    /**
     * Get total student users count.
     */
    public function getTotalStudents(): int
    {
        return User::role('student')->count();
    }

    /**
     * Get total teacher users count.
     */
    public function getTotalTeachers(): int
    {
        return User::role('teacher')->count();
    }

    /**
     * Get total courses count.
     */
    public function getTotalCourses(): int
    {
        return Course::count();
    }

    /**
     * Get total question banks count.
     */
    public function getTotalQuestionBanks(): int
    {
        return QuestionBank::count();
    }

    /**
     * Get total questions count.
     */
    public function getTotalQuestions(): int
    {
        return Question::count();
    }

    /**
     * Get active published tests count.
     */
    public function getActiveTestsCount(): int
    {
        return Test::where('is_published', true)->count();
    }

    /**
     * Get total attempts count.
     */
    public function getTotalAttempts(): int
    {
        return Attempt::count();
    }

    /**
     * Get total certificates issued count.
     */
    public function getTotalCertificates(): int
    {
        return Certificate::count();
    }

    /**
     * Get total revenue earned from paid payments.
     */
    public function getTotalRevenue(): float
    {
        return (float) Payment::where('status', PaymentStatus::Paid->value)->sum('amount');
    }

    /**
     * Get recent test submission activities.
     *
     * @return Collection<int, Attempt>
     */
    public function getRecentActivities(int $limit = 5): Collection
    {
        return Attempt::with(['user', 'test'])
            ->latest('updated_at')
            ->limit($limit)
            ->get();
    }
}
