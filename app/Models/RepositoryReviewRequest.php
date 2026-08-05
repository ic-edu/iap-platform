<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RepositoryReviewRequest extends Model
{
    use HasUlids, SoftDeletes;

    protected $table = 'repository_review_requests';

    protected $fillable = [
        'resource_type',
        'resource_id',
        'submitted_by',
        'reviewer_id',
        'status',
        'review_notes',
        'changes_data',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'changes_data' => 'array',
            'approved_at'  => 'datetime',
        ];
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(RepositoryComment::class, 'review_request_id');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(RepositoryApproval::class, 'review_request_id');
    }
}
