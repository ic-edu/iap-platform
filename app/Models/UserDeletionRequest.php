<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserDeletionRequest extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'user_deletion_requests';

    protected $fillable = [
        'user_id',
        'requested_by',
        'reason',
        'status',
        'actioned_by',
        'actioned_at',
    ];

    protected function casts(): array
    {
        return [
            'actioned_at' => 'datetime',
        ];
    }

    /**
     * Target user requested to be deleted.
     */
    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    /**
     * Admin requester who submitted deletion request.
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * Super Admin who actioned (approved/rejected) the request.
     */
    public function actioner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actioned_by');
    }
}
