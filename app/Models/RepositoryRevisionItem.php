<?php

namespace App\Models;

use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepositoryRevisionItem extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'repository_revision_request_id',
        'question_bank_id',
        'question_id',
        'finding_type',
        'field',
        'severity',
        'feedback',
        'suggested_fix',
        'status',
    ];

    public function revisionRequest(): BelongsTo
    {
        return $this->belongsTo(RepositoryRevisionRequest::class, 'repository_revision_request_id');
    }

    public function questionBank(): BelongsTo
    {
        return $this->belongsTo(QuestionBank::class, 'question_bank_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'question_id');
    }
}
