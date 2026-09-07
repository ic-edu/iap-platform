<?php

namespace App\Modules\Commerce\Domain\Models;

use App\Models\User;
use App\Modules\Commerce\Domain\Enums\OrderStatus;
use App\Modules\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $user_id
 * @property string|null $organization_id
 * @property string $order_number
 * @property OrderStatus $status
 * @property float $subtotal
 * @property float $discount
 * @property float $tax
 * @property float $grand_total
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property User|null $user
 * @property Organization|null $organization
 * @property Invoice|null $invoice
 */
class Order extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'orders';

    protected $fillable = [
        'user_id',
        'organization_id',
        'coupon_id',
        'order_number',
        'status',
        'subtotal',
        'discount',
        'tax',
        'grand_total',
    ];

    /**
     * Get applied coupon if any.
     *
     * @return BelongsTo<Coupon, $this>
     */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class, 'coupon_id')->withTrashed();
    }

    /**
     * Get redemption record if any.
     *
     * @return HasOne<CouponRedemption, $this>
     */
    public function redemption(): HasOne
    {
        return $this->hasOne(CouponRedemption::class, 'order_id');
    }

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'subtotal' => 'float',
            'discount' => 'float',
            'tax' => 'float',
            'grand_total' => 'float',
        ];
    }

    /**
     * Get user who initiated the order.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get owning organization if institutional order.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    /**
     * Determine if this is an institutional order.
     */
    public function isInstitutional(): bool
    {
        return !is_null($this->organization_id);
    }

    /**
     * Scope query to institutional orders.
     */
    public function scopeInstitutional($query)
    {
        return $query->whereNotNull('organization_id');
    }

    /**
     * Scope query to individual candidate orders.
     */
    public function scopeIndividual($query)
    {
        return $query->whereNull('organization_id');
    }

    /**
     * Get order items.
     *
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }

    /**
     * Get order invoice.
     *
     * @return HasOne<Invoice, $this>
     */
    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class, 'order_id');
    }
}
