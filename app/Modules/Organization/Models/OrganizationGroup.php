<?php

namespace App\Modules\Organization\Models;

use App\Models\User;
use App\Modules\Organization\Enums\GroupType;
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
 * @property string $name
 * @property GroupType|string|null $group_type
 * @property string|null $description
 * @property bool $is_active
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class OrganizationGroup extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'organization_groups';

    protected $fillable = [
        'organization_id',
        'name',
        'group_type',
        'description',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function groupMembers(): HasMany
    {
        return $this->hasMany(OrganizationGroupMember::class, 'group_id');
    }

    public function memberships(): HasManyThrough
    {
        return $this->hasManyThrough(
            OrganizationMembership::class,
            OrganizationGroupMember::class,
            'group_id',
            'id',
            'id',
            'membership_id'
        );
    }

    public function hasMembership(OrganizationMembership $membership): bool
    {
        return $this->groupMembers()->where('membership_id', $membership->id)->exists();
    }

    public function addMembership(OrganizationMembership $membership): OrganizationGroupMember
    {
        if ((string) $membership->organization_id !== (string) $this->organization_id) {
            throw new \InvalidArgumentException("Cannot add member from Organization '{$membership->organization_id}' to Group of Organization '{$this->organization_id}'.");
        }

        return $this->groupMembers()->firstOrCreate([
            'membership_id' => $membership->id,
        ]);
    }

    public function removeMembership(OrganizationMembership $membership): bool
    {
        return (bool) $this->groupMembers()->where('membership_id', $membership->id)->delete();
    }
}
