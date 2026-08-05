<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepositoryApproval extends Model
{
    use HasUlids;

    protected $table = 'repository_approvals';

    protected $fillable = [
        'review_request_id',
        'approved_by',
        'decision',
        'notes',
    ];

    public function reviewRequest(): BelongsTo
    {
        return $this->belongsTo(RepositoryReviewRequest::class, 'review_request_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
