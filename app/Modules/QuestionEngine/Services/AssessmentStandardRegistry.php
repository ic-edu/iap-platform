<?php

namespace App\Modules\QuestionEngine\Services;

use App\Modules\QuestionEngine\Enums\AssessmentFamily;
use App\Modules\QuestionEngine\Enums\StandardStatus;
use App\Modules\QuestionEngine\Models\AssessmentStandard;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class AssessmentStandardRegistry
{
    /**
     * Get the authoritative active standard for an assessment family.
     *
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function getActiveStandard(AssessmentFamily|string $family): AssessmentStandard
    {
        $standard = $this->findActiveStandard($family);

        if ($standard === null) {
            $familyValue = $family instanceof AssessmentFamily ? $family->value : $family;
            throw new RuntimeException("No active AssessmentStandard found for family '{$familyValue}'.");
        }

        return $standard;
    }

    /**
     * Find the active standard for an assessment family without throwing.
     */
    public function findActiveStandard(AssessmentFamily|string $family): ?AssessmentStandard
    {
        $resolvedFamily = $family instanceof AssessmentFamily ? $family : AssessmentFamily::tryFrom((string) $family);

        if ($resolvedFamily === null) {
            throw new InvalidArgumentException("Unknown AssessmentFamily '{$family}'.");
        }

        return AssessmentStandard::where('assessment_family', $resolvedFamily)
            ->where('status', StandardStatus::Active)
            ->first();
    }

    /**
     * Get a specific standard version for an assessment family.
     *
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function getStandardByVersion(AssessmentFamily|string $family, string $version): AssessmentStandard
    {
        $standard = $this->findStandardByVersion($family, $version);

        if ($standard === null) {
            $familyValue = $family instanceof AssessmentFamily ? $family->value : $family;
            throw new RuntimeException("AssessmentStandard '{$familyValue}' version '{$version}' not found.");
        }

        return $standard;
    }

    /**
     * Find a specific standard version without throwing.
     */
    public function findStandardByVersion(AssessmentFamily|string $family, string $version): ?AssessmentStandard
    {
        $resolvedFamily = $family instanceof AssessmentFamily ? $family : AssessmentFamily::tryFrom((string) $family);

        if ($resolvedFamily === null) {
            throw new InvalidArgumentException("Unknown AssessmentFamily '{$family}'.");
        }

        return AssessmentStandard::where('assessment_family', $resolvedFamily)
            ->where('version', $version)
            ->first();
    }

    /**
     * List all version records for a given assessment family.
     *
     * @return Collection<int, AssessmentStandard>
     */
    public function listVersions(AssessmentFamily|string $family): Collection
    {
        $resolvedFamily = $family instanceof AssessmentFamily ? $family : AssessmentFamily::tryFrom((string) $family);

        if ($resolvedFamily === null) {
            throw new InvalidArgumentException("Unknown AssessmentFamily '{$family}'.");
        }

        return AssessmentStandard::where('assessment_family', $resolvedFamily)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Register a new versioned standard definition.
     *
     * Initial registration only permits draft, detected, or pending_review.
     * Direct registration of active, superseded, or archived is strictly rejected.
     *
     * @param  array<string, mixed>  $data
     */
    public function registerStandard(array $data): AssessmentStandard
    {
        $familyRaw = $data['assessment_family'] ?? null;
        $family = $familyRaw instanceof AssessmentFamily ? $familyRaw : AssessmentFamily::tryFrom((string) $familyRaw);

        if ($family === null) {
            throw new InvalidArgumentException('Valid assessment_family is required.');
        }

        $version = (string) ($data['version'] ?? '');
        if (empty($version)) {
            throw new InvalidArgumentException('Version is required to register an AssessmentStandard.');
        }

        $status = StandardStatus::Draft;
        if (array_key_exists('status', $data) && $data['status'] !== null) {
            $statusRaw = $data['status'];
            if ($statusRaw instanceof StandardStatus) {
                $status = $statusRaw;
            } elseif (is_string($statusRaw) && $statusRaw !== '') {
                $resolvedStatus = StandardStatus::tryFrom($statusRaw);
                if ($resolvedStatus === null) {
                    throw new InvalidArgumentException("Invalid status '{$statusRaw}' for AssessmentStandard.");
                }
                $status = $resolvedStatus;
            } else {
                throw new InvalidArgumentException("Invalid status '".(is_scalar($statusRaw) ? (string) $statusRaw : gettype($statusRaw))."' for AssessmentStandard.");
            }
        }

        if (!in_array($status, [StandardStatus::Draft, StandardStatus::Detected, StandardStatus::PendingReview], true)) {
            throw new InvalidArgumentException("Cannot directly register AssessmentStandard in status '{$status->value}'. Initial registration permits only draft, detected, or pending_review.");
        }

        return AssessmentStandard::create([
            'assessment_family' => $family,
            'standard_code' => (string) ($data['standard_code'] ?? strtoupper($family->value).'-'.$version),
            'version' => $version,
            'title' => (string) ($data['title'] ?? $family->label().' ('.$version.')'),
            'provider' => (string) ($data['provider'] ?? 'IAP Assessment Standards Authority'),
            'status' => $status,
            'effective_from' => $data['effective_from'] ?? null,
            'effective_until' => $data['effective_until'] ?? null,
            'source_name' => $data['source_name'] ?? null,
            'source_url' => $data['source_url'] ?? null,
            'source_checked_at' => $data['source_checked_at'] ?? null,
            'structure_definition' => (array) ($data['structure_definition'] ?? []),
            'blueprint_definition' => $data['blueprint_definition'] ?? null,
            'validation_definition' => $data['validation_definition'] ?? null,
            'scoring_definition' => $data['scoring_definition'] ?? null,
            'metadata' => $data['metadata'] ?? null,
        ]);
    }

    /**
     * Formally activate a standard, superseding any previously active version for that family.
     * Enforces single-active invariant, activation idempotency, and lifecycle guards.
     * Fails closed if the family activation lock cannot be acquired.
     */
    public function activateStandard(AssessmentStandard|string $standard): AssessmentStandard
    {
        $targetId = $standard instanceof AssessmentStandard ? $standard->id : (string) $standard;

        $initial = AssessmentStandard::where('id', $targetId)->firstOrFail();

        // Idempotent return if already active
        if ($initial->isActive()) {
            return $initial;
        }

        if (!$initial->status->canBeActivated()) {
            throw new InvalidArgumentException("AssessmentStandard in status '{$initial->status->value}' cannot be activated. Only draft, detected, or pending_review standards may be activated.");
        }

        $family = $initial->assessment_family;
        $lockKey = "assessment_standard_activation:{$family->value}";

        $activationLogic = function () use ($targetId, $family) {
            return DB::transaction(function () use ($targetId, $family) {
                $lockedTarget = AssessmentStandard::where('id', $targetId)->lockForUpdate()->firstOrFail();

                if ($lockedTarget->isActive()) {
                    return $lockedTarget;
                }

                if (!$lockedTarget->status->canBeActivated()) {
                    throw new InvalidArgumentException("AssessmentStandard in status '{$lockedTarget->status->value}' cannot be activated. Only draft, detected, or pending_review standards may be activated.");
                }

                // Supersede any active standards for this family (excluding lockedTarget)
                AssessmentStandard::where('assessment_family', $family)
                    ->where('status', StandardStatus::Active)
                    ->where('id', '!=', $lockedTarget->id)
                    ->lockForUpdate()
                    ->update(['status' => StandardStatus::Superseded]);

                $lockedTarget->update([
                    'status' => StandardStatus::Active,
                    'effective_from' => $lockedTarget->effective_from ?? now(),
                ]);

                return $lockedTarget->fresh();
            });
        };

        try {
            return Cache::lock($lockKey, 10)->block(5, $activationLogic);
        } catch (LockTimeoutException $e) {
            throw new RuntimeException("Unable to acquire activation lock for assessment family '{$family->value}': lock timed out.", 0, $e);
        } catch (\Throwable $e) {
            if ($e instanceof InvalidArgumentException) {
                throw $e;
            }
            throw new RuntimeException("Unable to acquire activation lock for assessment family '{$family->value}': ".$e->getMessage(), 0, $e);
        }
    }
}
