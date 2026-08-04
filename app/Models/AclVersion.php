<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ACL Content Version Model for tracking snapshot history and rollback.
 *
 * @property string $id
 * @property string $resource_type
 * @property string $resource_id
 * @property string $version_number
 * @property string $title
 * @property array $snapshot_data
 * @property int|null $created_by
 * @property string|null $change_reason
 * @property bool $is_current
 */
class AclVersion extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'acl_versions';

    protected $fillable = [
        'resource_type',
        'resource_id',
        'version_number',
        'title',
        'snapshot_data',
        'created_by',
        'change_reason',
        'is_current',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_data' => 'array',
            'is_current'    => 'boolean',
        ];
    }

    /**
     * Get the creator of this version.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
