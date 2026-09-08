<?php

namespace App\Modules\Organization\Models;

use App\Modules\Commerce\Domain\Models\Order;
use App\Modules\Commerce\Domain\Models\OrderItem;
use App\Modules\Commerce\Domain\Models\Product;
use App\Modules\Organization\Enums\EntitlementStatus;
use App\Modules\Organization\Enums\SeatAllocationStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $order_item_id
 * @property string $product_id
 * @property int $total_seats
 * @property EntitlementStatus $status
 * @property Carbon|null $activated_at
 * @property Carbon|null $valid_from
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Organization $organization
 * @property OrderItem $orderItem
 * @property Order|null $order
 * @property Product $product
 */
class OrganizationEntitlement extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'organization_entitlements';

    protected $fillable = [
        'organization_id',
        'order_item_id',
        'product_id',
        'total_seats',
        'status',
        'activated_at',
        'valid_from',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'total_seats'  => 'integer',
            'status'       => EntitlementStatus::class,
            'activated_at' => 'datetime',
            'valid_from'   => 'datetime',
            'expires_at'   => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }

    /**
     * Resolve the source Order through the associated OrderItem.
     */
    public function getOrderAttribute(): ?Order
    {
        return $this->orderItem?->order;
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(OrganizationSeatAllocation::class, 'organization_entitlement_id');
    }

    public function activeAllocations(): HasMany
    {
        return $this->allocations()->where('status', SeatAllocationStatus::Active);
    }

    public function allocatedSeatsCount(): int
    {
        return $this->activeAllocations()->count();
    }

    public function availableSeatsCount(): int
    {
        return max(0, $this->total_seats - $this->allocatedSeatsCount());
    }

    public function hasAvailableSeats(): bool
    {
        return $this->availableSeatsCount() > 0;
    }

    public function isActive(): bool
    {
        return $this->status === EntitlementStatus::Active;
    }
}
