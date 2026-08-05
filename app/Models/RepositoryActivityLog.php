<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepositoryActivityLog extends Model
{
    use HasUlids;

    protected $table = 'repository_activity_logs';

    protected $fillable = [
        'resource_type',
        'resource_id',
        'actor_id',
        'reviewer_id',
        'action',
        'old_values',
        'new_values',
        'approval_note',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
