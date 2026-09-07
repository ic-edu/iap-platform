<?php

namespace App\Modules\Commerce\Domain\Models;

use App\Models\User;
use App\Modules\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $coupon_id
 * @property string $order_id
 * @property int|null $user_id
 * @property string|null $organization_id
 * @property string $status // reserved, consumed, released
 * @property float $discount_amount
 * @property Carbon $reserved_at
 * @property Carbon|null $consumed_at
 * @property Carbon|null $released_at
 * @property string|null $released_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Coupon|null $coupon
 * @property Order|null $order
 * @property User|null $user
 * @property Organization|null $organization
 */
class CouponRedemption extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'coupon_redemptions';

    protected $fillable = [
        'coupon_id',
        'order_id',
        'user_id',
        'organization_id',
        'status',
        'discount_amount',
        'reserved_at',
        'consumed_at',
        'released_at',
        'released_reason',
    ];

    protected function casts(): array
    {
        return [
            'discount_amount' => 'float',
            'reserved_at'     => 'datetime',
            'consumed_at'     => 'datetime',
            'released_at'     => 'datetime',
        ];
    }

    /**
     * Get parent coupon.
     *
     * @return BelongsTo<Coupon, $this>
     */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class, 'coupon_id');
    }

    /**
     * Get parent order.
     *
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    /**
     * Get user.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get organization.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    /**
     * Determine if redemption is active (reserved or consumed).
     */
    public function isHold(): bool
    {
        return in_array($this->status, ['reserved', 'consumed'], true);
    }
}
