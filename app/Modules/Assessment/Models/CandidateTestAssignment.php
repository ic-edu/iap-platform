<?php

namespace App\Modules\Assessment\Models;

use App\Models\User;
use App\Modules\Commerce\Domain\Models\Order;
use App\Modules\Commerce\Domain\Models\Payment;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $user_id
 * @property string $test_id
 * @property int|null $assigned_by
 * @property string|null $payment_id
 * @property string|null $order_id
 * @property string $status
 * @property Carbon $assigned_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CandidateTestAssignment extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'candidate_test_assignments';

    protected $fillable = [
        'user_id',
        'test_id',
        'assigned_by',
        'payment_id',
        'order_id',
        'status',
        'assigned_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'user_id'      => 'integer',
            'assigned_by'  => 'integer',
            'assigned_at'  => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function test(): BelongsTo
    {
        return $this->belongsTo(Test::class, 'test_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
