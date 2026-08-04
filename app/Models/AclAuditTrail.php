<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ACL Audit Trail Model for recording all governance lifecycle actions.
 *
 * @property string $id
 * @property string $resource_type
 * @property string $resource_id
 * @property string $action
 * @property int|null $actor_id
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $reviewer_id
 * @property int|null $approver_id
 * @property int|null $published_by
 * @property int|null $archive_requested_by
 * @property int|null $restore_requested_by
 * @property string $version
 * @property string|null $reason
 * @property array|null $metadata
 */
class AclAuditTrail extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'acl_audit_trails';

    protected $fillable = [
        'resource_type',
        'resource_id',
        'action',
        'actor_id',
        'created_by',
        'updated_by',
        'reviewer_id',
        'approver_id',
        'published_by',
        'archive_requested_by',
        'restore_requested_by',
        'version',
        'reason',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    /**
     * Get actor user who performed the action.
     *
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
