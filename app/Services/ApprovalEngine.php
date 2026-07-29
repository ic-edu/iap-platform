<?php

namespace App\Services;

use App\Models\QuestionBankArchiveRequest;
use App\Models\UserCreationRequest;
use App\Models\UserDeletionRequest;
use App\Modules\Assessment\Models\Test;
use App\Modules\QuestionBank\Models\QuestionBank;

class ApprovalEngine
{
    /**
     * Registered approval providers.
     * Each callback resolves the pending count for an approval category.
     *
     * @var array<string, callable(): int>
     */
    protected static array $providers = [];

    /**
     * Initialize default core approval providers.
     */
    protected static function initProviders(): void
    {
        if (!empty(self::$providers)) {
            return;
        }

        self::$providers = [
            'question_banks' => fn (): int => QuestionBank::where('status', 'pending_approval')->count(),
            'question_bank_archives' => fn (): int => QuestionBankArchiveRequest::where('status', 'pending')->count(),
            'tests' => fn (): int => Test::where('status', 'pending_approval')->count(),
            'user_creations' => fn (): int => UserCreationRequest::where('status', 'pending')->count(),
            'user_deletions' => fn (): int => UserDeletionRequest::where('status', 'pending')->count(),
        ];
    }

    /**
     * Register a new approval provider dynamically for future feature modules.
     *
     * @param  callable(): int  $provider
     */
    public static function registerProvider(string $key, callable $provider): void
    {
        self::initProviders();
        self::$providers[$key] = $provider;
    }

    /**
     * Get pending counts per approval category.
     *
     * @return array<string, int>
     */
    public static function getPendingCounts(): array
    {
        self::initProviders();
        $counts = [];
        foreach (self::$providers as $key => $callback) {
            $counts[$key] = (int) call_user_func($callback);
        }

        return $counts;
    }

    /**
     * Get the aggregate total of all pending approval requests across all registered categories.
     */
    public static function getTotalPendingCount(): int
    {
        return array_sum(self::getPendingCounts());
    }
}
