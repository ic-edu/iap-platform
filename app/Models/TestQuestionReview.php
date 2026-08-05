<?php

namespace App\Models;

use App\Modules\Assessment\Models\Test;
use App\Modules\QuestionBank\Models\Question;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TestQuestionReview extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'test_question_reviews';

    protected $fillable = [
        'test_id',
        'test_section_id',
        'question_id',
        'status',
        'field',
        'comment',
        'severity',
        'reviewer_id',
    ];

    public function test(): BelongsTo
    {
        return $this->belongsTo(Test::class, 'test_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'question_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
