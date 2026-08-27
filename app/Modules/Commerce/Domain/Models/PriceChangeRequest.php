<?php

namespace App\Modules\Commerce\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $product_id
 * @property int $requested_by
 * @property float $current_price_snapshot
 * @property float $proposed_price
 * @property string $reason
 * @property string $status
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property string|null $rejection_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Product|null $product
 * @property User|null $requester
 * @property User|null $reviewer
 */
class PriceChangeRequest extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'price_change_requests';

    protected $fillable = [
        'product_id',
        'requested_by',
        'current_price_snapshot',
        'proposed_price',
        'reason',
        'status',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'current_price_snapshot' => 'float',
            'proposed_price'         => 'float',
            'reviewed_at'            => 'datetime',
        ];
    }

    /**
     * Associated product.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * Operational Admin who requested the price change.
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * Super Admin who reviewed/actioned the request.
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Check if request is currently pending review.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if request has been approved.
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Check if request has been rejected.
     */
    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }
}
