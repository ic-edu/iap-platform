<?php

namespace App\Modules\Organization\Models;

use App\Models\User;
use App\Modules\Organization\Enums\MembershipRole;
use App\Modules\Organization\Enums\MembershipStatus;
use App\Modules\Organization\Enums\OrganizationStatus;
use App\Modules\Organization\Enums\OrganizationType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property OrganizationType $organization_type
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $website
 * @property string|null $address
 * @property string|null $city
 * @property string|null $province
 * @property string|null $country
 * @property string|null $postal_code
 * @property OrganizationStatus $status
 * @property string|null $logo_path
 * @property int|null $created_by
 * @property Carbon|null $archived_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Organization extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'organizations';

    protected $attributes = [
        'status'            => OrganizationStatus::Pending,
        'organization_type' => OrganizationType::Other,
    ];

    protected $fillable = [
        'name',
        'slug',
        'organization_type',
        'email',
        'phone',
        'website',
        'address',
        'city',
        'province',
        'country',
        'postal_code',
        'status',
        'logo_path',
        'created_by',
        'submitted_by',
        'submitted_at',
        'reviewed_by',
        'reviewed_at',
        'revision_note',
        'rejection_reason',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'organization_type' => OrganizationType::class,
            'status'            => OrganizationStatus::class,
            'submitted_at'      => 'datetime',
            'reviewed_at'       => 'datetime',
            'archived_at'       => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Retrieve the model for a bound value.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        return $this->where('slug', $value)->orWhere('id', $value)->first();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(OrganizationMembership::class, 'organization_id');
    }

    public function activeMemberships(): HasMany
    {
        return $this->memberships()->where('status', 'active');
    }

    public function users(): HasManyThrough
    {
        return $this->hasManyThrough(
            User::class,
            OrganizationMembership::class,
            'organization_id',
            'id',
            'id',
            'user_id'
        );
    }

    public function groups(): HasMany
    {
        return $this->hasMany(OrganizationGroup::class, 'organization_id');
    }

    public function activeGroups(): HasMany
    {
        return $this->groups()->where('is_active', true);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(OrganizationInvitation::class, 'organization_id');
    }

    public function pendingInvitations(): HasMany
    {
        return $this->invitations()->where('status', 'pending');
    }

    public function primaryCoordinatorInvitation(): HasOne
    {
        return $this->hasOne(OrganizationInvitation::class, 'organization_id')
            ->whereIn('intended_role', [MembershipRole::Coordinator, MembershipRole::Owner])
            ->latestOfMany();
    }

    public function primaryCoordinatorMembership(): HasOne
    {
        return $this->hasOne(OrganizationMembership::class, 'organization_id')
            ->whereIn('role', [MembershipRole::Coordinator, MembershipRole::Owner])
            ->where('status', MembershipStatus::Active);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isDraft(): bool
    {
        return $this->status === OrganizationStatus::Draft;
    }

    public function isPending(): bool
    {
        return $this->status === OrganizationStatus::Pending;
    }

    public function needsRevision(): bool
    {
        return $this->status === OrganizationStatus::NeedsRevision;
    }

    public function isRejected(): bool
    {
        return $this->status === OrganizationStatus::Rejected;
    }

    public function isActive(): bool
    {
        return $this->status === OrganizationStatus::Active;
    }

    public function isSuspended(): bool
    {
        return $this->status === OrganizationStatus::Suspended;
    }

    public function isArchived(): bool
    {
        return $this->status === OrganizationStatus::Archived;
    }

    public function hasUser(User $user): bool
    {
        return $this->memberships()->where('user_id', $user->id)->exists();
    }

    public function getMembership(User $user): ?OrganizationMembership
    {
        return $this->memberships()->where('user_id', $user->id)->first();
    }
}
