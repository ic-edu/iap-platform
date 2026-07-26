<?php

namespace App\Modules\Reporting\Services;

use App\Models\User;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\Test;
use App\Modules\Finance\Enums\PaymentStatus;
use App\Modules\Finance\Models\Payment;
use Illuminate\Support\Collection;

class DashboardMetricsService
{
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
     * Get active published tests count.
     */
    public function getActiveTestsCount(): int
    {
        return Test::where('is_published', true)->count();
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
