<?php

namespace App\Models;

use App\Modules\QuestionBank\Models\QuestionBank;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RepositoryRevisionRequest extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'question_bank_id',
        'teacher_id',
        'requested_by_id',
        'status',
        'notes',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function questionBank(): BelongsTo
    {
        return $this->belongsTo(QuestionBank::class, 'question_bank_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(RepositoryRevisionItem::class, 'repository_revision_request_id');
    }
}
