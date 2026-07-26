<?php

namespace App\Modules\Commerce\Domain\Models;

use App\Models\User;
use App\Modules\Commerce\Domain\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property string $id
 * @property int $user_id
 * @property string $order_number
 * @property OrderStatus $status
 * @property float $subtotal
 * @property float $discount
 * @property float $tax
 * @property float $grand_total
 * @property User|null $user
 * @property Invoice|null $invoice
 */
class Order extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'orders';

    protected $fillable = [
        'user_id',
        'order_number',
        'status',
        'subtotal',
        'discount',
        'tax',
        'grand_total',
    ];

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
     * Get user.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
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
