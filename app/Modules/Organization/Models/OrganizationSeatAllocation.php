<?php

namespace App\Modules\Organization\Models;

use App\Models\User;
use App\Modules\Organization\Enums\SeatAllocationStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $organization_entitlement_id
 * @property string $organization_membership_id
 * @property int|null $allocated_by
 * @property SeatAllocationStatus $status
 * @property Carbon|null $allocated_at
 * @property Carbon|null $released_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property OrganizationEntitlement $entitlement
 * @property OrganizationMembership $membership
 * @property User|null $allocatedBy
 */
class OrganizationSeatAllocation extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'organization_seat_allocations';

    protected $fillable = [
        'organization_entitlement_id',
        'organization_membership_id',
        'allocated_by',
        'status',
        'allocated_at',
        'released_at',
    ];

    protected function casts(): array
    {
        return [
            'status'       => SeatAllocationStatus::class,
            'allocated_at' => 'datetime',
            'released_at'  => 'datetime',
        ];
    }

    public function entitlement(): BelongsTo
    {
        return $this->belongsTo(OrganizationEntitlement::class, 'organization_entitlement_id');
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(OrganizationMembership::class, 'organization_membership_id');
    }

    public function allocatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'allocated_by');
    }

    public function isActive(): bool
    {
        return $this->status === SeatAllocationStatus::Active;
    }

    public function release(): void
    {
        $this->update([
            'status'      => SeatAllocationStatus::Released,
            'released_at' => now(),
        ]);
    }
}
