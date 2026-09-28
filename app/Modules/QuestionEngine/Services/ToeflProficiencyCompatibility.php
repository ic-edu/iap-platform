<?php

namespace App\Modules\QuestionEngine\Services;

use App\Modules\QuestionEngine\Enums\ProficiencyTarget;
use App\Modules\QuestionEngine\Enums\ToeflTaskType;
use InvalidArgumentException;

class ToeflProficiencyCompatibility
{
    /**
     * Numerical CEFR level ranks.
     */
    protected const CEFR_RANKS = [
        'A1' => 1.0,
        'A2' => 2.0,
        'B1' => 3.0,
        'B2' => 4.0,
        'C1' => 5.0,
        'C1+' => 5.5,
        'C2' => 6.0,
    ];

    /**
     * Check if an IAP ProficiencyTarget is compatible with the official CEFR range envelope of a TOEFL task.
     */
    public static function isTargetCompatible(ToeflTaskType|string $taskType, ProficiencyTarget|string $target): bool
    {
        $resolvedTask = $taskType instanceof ToeflTaskType ? $taskType : ToeflTaskType::tryFrom((string) $taskType);
        if ($resolvedTask === null) {
            throw new InvalidArgumentException("Unknown TOEFL task type '{$taskType}'.");
        }

        $resolvedTarget = $target instanceof ProficiencyTarget ? $target : ProficiencyTarget::tryFrom((string) $target);
        if ($resolvedTarget === null) {
            throw new InvalidArgumentException("Unknown proficiency target '{$target}'.");
        }

        $targetCefr = $resolvedTarget->cefrBand();
        $minCefr = $resolvedTask->cefrMin();
        $maxCefr = $resolvedTask->cefrMax();

        return self::isCefrInRange($targetCefr, $minCefr, $maxCefr);
    }

    /**
     * Check if a CEFR band falls within [min, max] range.
     */
    public static function isCefrInRange(string $targetCefr, string $minCefr, string $maxCefr): bool
    {
        $targetRank = self::CEFR_RANKS[$targetCefr] ?? null;
        $minRank = self::CEFR_RANKS[$minCefr] ?? null;
        $maxRank = self::CEFR_RANKS[$maxCefr] ?? null;

        if ($targetRank === null || $minRank === null || $maxRank === null) {
            return false;
        }

        return $targetRank >= $minRank && $targetRank <= $maxRank;
    }

    /**
     * Get all compatible ProficiencyTarget enum cases for a given TOEFL task type.
     *
     * @return list<ProficiencyTarget>
     */
    public static function getAllowedTargetsForTask(ToeflTaskType $taskType): array
    {
        $allowed = [];
        foreach (ProficiencyTarget::cases() as $target) {
            if (self::isTargetCompatible($taskType, $target)) {
                $allowed[] = $target;
            }
        }

        return $allowed;
    }

    /**
     * Get deterministic balanced default proficiency distribution for a task based on its allowed envelope.
     *
     * @return array<string, int|float>
     */
    public static function getDefaultDistributionForTask(ToeflTaskType $taskType): array
    {
        $allowed = self::getAllowedTargetsForTask($taskType);
        $count = count($allowed);
        if ($count === 0) {
            return ['b1_standard' => 100];
        }

        $equalWeight = round(100.0 / $count, 4);
        $dist = [];
        $sum = 0.0;

        foreach ($allowed as $idx => $target) {
            if ($idx === $count - 1) {
                $dist[$target->value] = round(100.0 - $sum, 4);
            } else {
                $dist[$target->value] = $equalWeight;
                $sum += $equalWeight;
            }
        }

        return $dist;
    }
}
