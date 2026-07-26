<?php

namespace App\Modules\Commerce\Domain\Models;

use App\Models\User;
use App\Modules\Commerce\Domain\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string|null $invoice_id
 * @property int $user_id
 * @property string|null $reference_number
 * @property string $payment_gateway
 * @property string|null $transaction_id
 * @property PaymentStatus $status
 * @property float $amount
 * @property Carbon|null $confirmed_at
 * @property Invoice|null $invoice
 * @property User|null $user
 */
class Payment extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'payments';

    protected $fillable = [
        'invoice_id',
        'user_id',
        'reference_number',
        'payment_gateway',
        'transaction_id',
        'status',
        'amount',
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'amount' => 'float',
            'confirmed_at' => 'datetime',
        ];
    }

    /**
     * Get parent invoice.
     *
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
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
}
