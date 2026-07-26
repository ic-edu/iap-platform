<?php

namespace App\Modules\Commerce\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $code
 * @property string $type
 * @property float $value
 * @property int $usage_limit
 * @property int $used_count
 * @property Carbon|null $expires_at
 * @property bool $is_active
 */
class Coupon extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'coupons';

    protected $fillable = [
        'code',
        'type',
        'value',
        'usage_limit',
        'used_count',
        'expires_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'float',
            'usage_limit' => 'integer',
            'used_count' => 'integer',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }
}
