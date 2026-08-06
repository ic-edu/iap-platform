<?php

namespace App\Models;

use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RepositoryFinding extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'question_bank_id',
        'question_id',
        'finding_code',
        'title',
        'description',
        'severity',
        'status',
    ];

    public function questionBank(): BelongsTo
    {
        return $this->belongsTo(QuestionBank::class, 'question_bank_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'question_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(RepositoryFindingHistory::class, 'repository_finding_id');
    }
}
