<?php

namespace App\Modules\QuestionEngine\Services;

use App\Modules\QuestionEngine\Enums\AssessmentFamily;
use App\Modules\QuestionEngine\Enums\StandardStatus;
use App\Modules\QuestionEngine\Models\AssessmentStandard;
use Illuminate\Database\Eloquent\Collection;
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

        $statusRaw = $data['status'] ?? StandardStatus::Draft->value;
        $status = $statusRaw instanceof StandardStatus ? $statusRaw : (StandardStatus::tryFrom((string) $statusRaw) ?? StandardStatus::Draft);

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
     */
    public function activateStandard(AssessmentStandard|string $standard): AssessmentStandard
    {
        return DB::transaction(function () use ($standard) {
            $target = $standard instanceof AssessmentStandard
                ? AssessmentStandard::where('id', $standard->id)->lockForUpdate()->firstOrFail()
                : AssessmentStandard::where('id', $standard)->lockForUpdate()->firstOrFail();

            // Supersede any existing active standard for this family
            AssessmentStandard::where('assessment_family', $target->assessment_family)
                ->where('status', StandardStatus::Active)
                ->where('id', '!=', $target->id)
                ->update(['status' => StandardStatus::Superseded]);

            $target->update([
                'status' => StandardStatus::Active,
                'effective_from' => $target->effective_from ?? now(),
            ]);

            return $target->fresh();
        });
    }
}
