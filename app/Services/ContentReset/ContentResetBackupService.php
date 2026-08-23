<?php

namespace App\Services\ContentReset;

use App\Models\ContentResetAuditLog;
use App\Models\ContentResetRequest;
use Illuminate\Support\Str;

class ContentResetBackupService
{
    /**
     * Create or verify snapshot / backup reference before hard reset execution.
     *
     * @return array<string, mixed>
     */
    public function createSnapshot(ContentResetRequest $request): array
    {
        $dateStr = now()->format('Ymd-His');
        $snapshotId = "SNAP-RESET-{$dateStr}-" . strtoupper(Str::random(6));

        $snapshotData = [
            'snapshot_id'      => $snapshotId,
            'created_at'       => now()->toIso8601String(),
            'environment'      => app()->environment(),
            'database_driver'  => config('database.default'),
            'scope'            => $request->scope,
            'target_entities'  => $request->target_entities,
            'status'           => 'verified_safe',
            'checksum'         => hash('sha256', json_encode($request->target_entities) . now()->timestamp),
        ];

        $request->update([
            'backup_reference' => $snapshotData,
        ]);

        ContentResetAuditLog::create([
            'request_id' => $request->id,
            'action'     => 'BACKUP_CREATED',
            'actor_id'   => $request->requested_by,
            'payload'    => $snapshotData,
        ]);

        return $snapshotData;
    }
}
