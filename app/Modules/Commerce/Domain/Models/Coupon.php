<?php

namespace App\Modules\Commerce\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $code
 * @property string $type
 * @property float $value
 * @property int $usage_limit
 * @property int $used_count
 * @property Carbon|null $valid_from
 * @property Carbon|null $valid_until
 * @property Carbon|null $expires_at
 * @property bool $is_active
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Coupon extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $table = 'coupons';

    protected $fillable = [
        'campaign_id',
        'code',
        'type',
        'value',
        'usage_limit',
        'used_count',
        'valid_from',
        'valid_until',
        'expires_at',
        'is_active',
    ];

    /**
     * Get parent campaign if generated under a campaign.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<CouponCampaign, $this>
     */
    public function campaign(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(CouponCampaign::class, 'campaign_id');
    }

    /**
     * Get redemptions for this coupon.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<CouponRedemption, $this>
     */
    public function redemptions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CouponRedemption::class, 'coupon_id');
    }

    /**
     * Get orders that used this coupon.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<Order, $this>
     */
    public function orders(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Order::class, 'coupon_id');
    }

    /**
     * Get active reservations count (pending orders that haven't been consumed or released).
     */
    public function getActiveReservationsCount(): int
    {
        return (int) $this->redemptions()->where('status', 'reserved')->count();
    }

    /**
     * Get available remaining uses capacity.
     */
    public function getAvailableUses(): int
    {
        $activeReservations = $this->getActiveReservationsCount();
        $consumed = (int) $this->used_count;

        return max(0, $this->usage_limit - ($consumed + $activeReservations));
    }

    protected function casts(): array
    {
        return [
            'value'        => 'float',
            'usage_limit'  => 'integer',
            'used_count'   => 'integer',
            'valid_from'   => 'datetime',
            'valid_until'  => 'datetime',
            'expires_at'   => 'datetime',
            'is_active'    => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Coupon $coupon) {
            // Keep valid_until and expires_at synchronized for backward compatibility
            if ($coupon->valid_until !== null && $coupon->expires_at === null) {
                $coupon->expires_at = $coupon->valid_until;
            } elseif ($coupon->expires_at !== null && $coupon->valid_until === null) {
                $coupon->valid_until = $coupon->expires_at;
            }
        });
    }

    /**
     * Get the effective lifecycle state: ACTIVE, SCHEDULED, INACTIVE, EXPIRED.
     */
    public function getEffectiveState(): string
    {
        if ($this->campaign_id && $this->campaign) {
            $campaignState = $this->campaign->getEffectiveState();
            if ($campaignState !== 'ACTIVE') {
                return $campaignState;
            }
        }

        if (!$this->is_active) {
            return 'INACTIVE';
        }

        if ($this->valid_until === null) {
            return 'INACTIVE';
        }

        $now = now();

        if ($this->valid_from !== null && $now->lt($this->valid_from)) {
            return 'SCHEDULED';
        }

        if ($now->gt($this->valid_until)) {
            return 'EXPIRED';
        }

        return 'ACTIVE';
    }

    /**
     * Accessor for effective state.
     */
    public function getEffectiveStateAttribute(): string
    {
        return $this->getEffectiveState();
    }

    /**
     * Check if coupon is currently effectively active and eligible for redemption.
     */
    public function isEffectiveActive(): bool
    {
        return $this->getEffectiveState() === 'ACTIVE';
    }

    /**
     * Check if coupon is scheduled for future activation.
     */
    public function isScheduled(): bool
    {
        return $this->getEffectiveState() === 'SCHEDULED';
    }

    /**
     * Check if coupon has expired.
     */
    public function isExpired(): bool
    {
        return $this->getEffectiveState() === 'EXPIRED';
    }

    /**
     * Check if coupon has any redemption / usage history.
     */
    public function hasRedemptionHistory(): bool
    {
        return ((int) $this->used_count > 0);
    }

    /**
     * Check if voucher is eligible for safe deletion (0 redemptions, no business dependencies).
     */
    public function isDeletable(): bool
    {
        return !$this->hasRedemptionHistory();
    }

    /**
     * Get formatted validity range display string for UI.
     */
    public function getValidityDisplayAttribute(): string
    {
        if ($this->valid_from && $this->valid_until) {
            return $this->valid_from->format('d M Y') . ' → ' . $this->valid_until->format('d M Y');
        }

        if ($this->valid_until) {
            return 'Until ' . $this->valid_until->format('d M Y');
        }

        return 'Validity not configured';
    }
}
