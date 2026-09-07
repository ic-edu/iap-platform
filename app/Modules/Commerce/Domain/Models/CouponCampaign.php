<?php

namespace App\Modules\Commerce\Domain\Models;

use App\Models\User;
use App\Modules\Commerce\Domain\Enums\AssessmentFamily;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $name
 * @property string $assessment_family
 * @property string $scope_mode
 * @property string $discount_type
 * @property float $discount_value
 * @property string $generation_mode
 * @property string $code_prefix
 * @property int $code_length
 * @property int $uses_per_code
 * @property int $total_codes
 * @property Carbon|null $valid_from
 * @property Carbon|null $valid_until
 * @property bool $is_active
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property User|null $creator
 */
class CouponCampaign extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $table = 'coupon_campaigns';

    protected $fillable = [
        'name',
        'assessment_family',
        'scope_mode',
        'discount_type',
        'discount_value',
        'generation_mode',
        'code_prefix',
        'code_length',
        'uses_per_code',
        'total_codes',
        'valid_from',
        'valid_until',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'discount_value' => 'float',
            'code_length'    => 'integer',
            'uses_per_code'  => 'integer',
            'total_codes'    => 'integer',
            'valid_from'     => 'datetime',
            'valid_until'    => 'datetime',
            'is_active'      => 'boolean',
        ];
    }

    /**
     * Get user who created this campaign.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get coupons generated under this campaign.
     *
     * @return HasMany<Coupon, $this>
     */
    public function coupons(): HasMany
    {
        return $this->hasMany(Coupon::class, 'campaign_id');
    }

    /**
     * Get specific products eligible under this campaign (if scope_mode = selected_products).
     *
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(
            Product::class,
            'coupon_campaign_product',
            'campaign_id',
            'product_id'
        )->withTimestamps();
    }

    /**
     * Determine if campaign is currently active and within valid date window.
     */
    public function isEffectiveActive(): bool
    {
        if (!$this->is_active || $this->trashed()) {
            return false;
        }

        if ($this->valid_until === null) {
            return false;
        }

        $now = now();

        if ($this->valid_from !== null && $now->lt($this->valid_from)) {
            return false;
        }

        if ($now->gt($this->valid_until)) {
            return false;
        }

        return true;
    }

    /**
     * Check if a product is eligible for this campaign.
     */
    public function isProductEligible(Product $product): bool
    {
        // 1. Assessment family must match
        $productFamily = $product->getEffectiveFamily();
        if (empty($productFamily) || strtolower($productFamily) !== strtolower($this->assessment_family)) {
            return false;
        }

        // 2. If scope is all products in family, it matches
        if ($this->scope_mode === 'all_products_in_family') {
            return true;
        }

        // 3. If selected products, check relation
        return $this->products()->where('products.id', $product->id)->exists();
    }

    /**
     * Get human-readable lifecycle state.
     */
    public function getEffectiveState(): string
    {
        if ($this->trashed()) {
            return 'DELETED';
        }

        if (!$this->is_active || $this->valid_until === null) {
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
}
