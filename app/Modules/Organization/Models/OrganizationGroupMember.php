<?php

namespace App\Modules\Organization\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $group_id
 * @property string $membership_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class OrganizationGroupMember extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'organization_group_members';

    protected $fillable = [
        'group_id',
        'membership_id',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(OrganizationGroup::class, 'group_id');
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(OrganizationMembership::class, 'membership_id');
    }
}
