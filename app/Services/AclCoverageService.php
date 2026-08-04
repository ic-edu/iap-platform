<?php

namespace App\Services;

use App\Models\AclCategory;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use Illuminate\Support\Collection;

/**
 * Service to dynamically calculate completion coverage levels per Academic Content Library (ACL) category.
 */
class AclCoverageService
{
    /**
     * Calculate coverage for all active ACL categories.
     *
     * @return Collection<int, array{category: AclCategory, total_questions: int, approved_questions: int, approved_banks: int, target: int, percentage: int}>
     */
    public function getCategoryCoverageReport(): Collection
    {
        $categories = AclCategory::where('is_active', true)->get();

        return $categories->map(function (AclCategory $category) {
            // Find all question banks in this category
            $bankIds = QuestionBank::where('acl_category_id', $category->id)
                ->orWhere(function ($q) use ($category) {
                    $q->where('test_type', $category->test_type)
                      ->where('slug', 'like', "%{$category->slug}%");
                })
                ->pluck('id');

            $totalQuestions = Question::whereIn('question_bank_id', $bankIds)->count();

            $approvedBankIds = QuestionBank::whereIn('id', $bankIds)
                ->whereIn('status', ['approved', 'published'])
                ->pluck('id');

            $approvedBanks = $approvedBankIds->count();
            $approvedQuestions = Question::whereIn('question_bank_id', $approvedBankIds)->count();

            $target = max(1, $category->target_questions);
            $percentage = min(100, (int) round(($approvedQuestions / $target) * 100));

            return [
                'category'           => $category,
                'total_questions'    => $totalQuestions,
                'approved_questions' => $approvedQuestions,
                'approved_banks'     => $approvedBanks,
                'target'             => $target,
                'percentage'         => $percentage,
            ];
        });
    }

    /**
     * Calculate overall institutional ACL coverage score.
     */
    public function getOverallCoveragePercentage(): int
    {
        $reports = $this->getCategoryCoverageReport();
        if ($reports->isEmpty()) {
            return 0;
        }

        return (int) round($reports->avg('percentage'));
    }
}
