<?php

namespace App\Services;

use App\Models\AclAuditTrail;
use App\Models\AclVersion;
use App\Models\User;
use App\Modules\QuestionBank\Models\QuestionBank;

/**
 * Service to manage content snapshot versioning and rollback execution for ACL.
 */
class AclVersioningService
{
    /**
     * Create a snapshot version for a Question Bank.
     */
    public function createVersion(QuestionBank $bank, ?User $user = null, string $changeReason = 'Content update'): AclVersion
    {
        $bank->load(['questions.choices', 'aclCategory']);

        // Determine next version number (e.g. 1.0 -> 1.1 -> 2.0)
        $latestVersion = AclVersion::where('resource_type', 'QuestionBank')
            ->where('resource_id', $bank->id)
            ->latest('created_at')
            ->first();

        if (!$latestVersion) {
            $nextVersionNum = '1.0';
        } else {
            $parts = explode('.', $latestVersion->version_number);
            $major = (int) ($parts[0] ?? 1);
            $minor = (int) ($parts[1] ?? 0) + 1;
            $nextVersionNum = "{$major}.{$minor}";
        }

        // Mark existing versions as not current
        AclVersion::where('resource_type', 'QuestionBank')
            ->where('resource_id', $bank->id)
            ->update(['is_current' => false]);

        $snapshot = [
            'id'             => $bank->id,
            'title'          => $bank->title,
            'test_type'      => is_object($bank->test_type) ? $bank->test_type->value : $bank->test_type,
            'description'    => $bank->description,
            'status'         => $bank->status,
            'questions_count'=> $bank->questions->count(),
            'questions'      => $bank->questions->map(function ($q) {
                return [
                    'id'            => $q->id,
                    'prompt'        => $q->prompt,
                    'question_type' => $q->question_type,
                    'difficulty'    => $q->difficulty,
                    'points'        => $q->points,
                    'choices'       => $q->choices->map(fn($c) => [
                        'label'      => $c->label,
                        'content'    => $c->content,
                        'is_correct' => $c->is_correct,
                    ])->toArray(),
                ];
            })->toArray(),
        ];

        $version = AclVersion::create([
            'resource_type'  => 'QuestionBank',
            'resource_id'    => $bank->id,
            'version_number' => $nextVersionNum,
            'title'          => $bank->title,
            'snapshot_data'  => $snapshot,
            'created_by'     => $user?->id,
            'change_reason'  => $changeReason,
            'is_current'     => true,
        ]);

        $bank->update(['current_version' => $nextVersionNum]);

        // Audit Trail log
        AclAuditTrail::create([
            'resource_type' => 'QuestionBank',
            'resource_id'   => $bank->id,
            'action'        => 'version_created',
            'actor_id'      => $user?->id,
            'version'       => $nextVersionNum,
            'reason'        => $changeReason,
            'metadata'      => ['version_id' => $version->id],
        ]);

        return $version;
    }

    /**
     * Rollback a Question Bank to a specific historical snapshot version.
     */
    public function rollbackVersion(AclVersion $version, ?User $user = null): QuestionBank
    {
        /** @var QuestionBank $bank */
        $bank = QuestionBank::findOrFail($version->resource_id);
        $snapshot = $version->snapshot_data;

        $bank->update([
            'title'           => $snapshot['title'] ?? $bank->title,
            'description'     => $snapshot['description'] ?? $bank->description,
            'current_version' => $version->version_number,
        ]);

        // Mark target version as current
        AclVersion::where('resource_type', 'QuestionBank')
            ->where('resource_id', $bank->id)
            ->update(['is_current' => false]);

        $version->update(['is_current' => true]);

        // Audit Trail log
        AclAuditTrail::create([
            'resource_type' => 'QuestionBank',
            'resource_id'   => $bank->id,
            'action'        => 'version_rolled_back',
            'actor_id'      => $user?->id,
            'version'       => $version->version_number,
            'reason'        => "Rolled back to version {$version->version_number}",
            'metadata'      => ['version_id' => $version->id],
        ]);

        return $bank;
    }
}
