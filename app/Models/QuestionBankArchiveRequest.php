<?php

namespace App\Models;

use App\Modules\QuestionBank\Models\QuestionBank;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionBankArchiveRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'question_bank_id',
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

    public function questionBank(): BelongsTo
    {
        return $this->belongsTo(QuestionBank::class, 'question_bank_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actioned_by');
    }
}
