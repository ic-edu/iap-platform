<?php

namespace App\Modules\Organization\Models;

use App\Models\User;
use App\Modules\Organization\Enums\MembershipRole;
use App\Modules\Organization\Enums\MembershipStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $organization_id
 * @property int $user_id
 * @property MembershipRole $role
 * @property string|null $member_identifier
 * @property string|null $department
 * @property MembershipStatus $status
 * @property Carbon|null $joined_at
 * @property Carbon|null $left_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class OrganizationMembership extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'organization_memberships';

    protected $attributes = [
        'role'   => MembershipRole::Member,
        'status' => MembershipStatus::Active,
    ];

    protected $fillable = [
        'organization_id',
        'user_id',
        'role',
        'member_identifier',
        'department',
        'status',
        'joined_at',
        'left_at',
    ];

    protected function casts(): array
    {
        return [
            'role'      => MembershipRole::class,
            'status'    => MembershipStatus::class,
            'joined_at' => 'datetime',
            'left_at'   => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function groupMemberships(): HasMany
    {
        return $this->hasMany(OrganizationGroupMember::class, 'membership_id');
    }

    public function groups(): HasManyThrough
    {
        return $this->hasManyThrough(
            OrganizationGroup::class,
            OrganizationGroupMember::class,
            'membership_id',
            'id',
            'id',
            'group_id'
        );
    }

    public function isOwner(): bool
    {
        return $this->role === MembershipRole::Owner;
    }

    public function isAdmin(): bool
    {
        return $this->role === MembershipRole::Admin;
    }

    public function isCoordinator(): bool
    {
        return $this->role === MembershipRole::Coordinator;
    }

    public function isMember(): bool
    {
        return $this->role === MembershipRole::Member;
    }

    public function hasManagementAuthority(): bool
    {
        return in_array($this->role, [MembershipRole::Owner, MembershipRole::Admin, MembershipRole::Coordinator], true);
    }

    public function isActive(): bool
    {
        return $this->status === MembershipStatus::Active;
    }

    /**
     * Get member_id attribute alias for member_identifier.
     */
    public function getMemberIdAttribute(): ?string
    {
        return $this->member_identifier;
    }
}
