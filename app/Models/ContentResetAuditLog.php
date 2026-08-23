<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $request_id
 * @property string $action
 * @property int|null $actor_id
 * @property array|null $payload
 * @property \Illuminate\Support\Carbon $created_at
 */
class ContentResetAuditLog extends Model
{
    use HasFactory, HasUlids;

    public $timestamps = false;

    protected $table = 'content_reset_audit_logs';

    protected $fillable = [
        'request_id',
        'action',
        'actor_id',
        'payload',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'payload'    => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(ContentResetRequest::class, 'request_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
