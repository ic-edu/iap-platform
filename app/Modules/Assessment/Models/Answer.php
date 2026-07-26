<?php

namespace App\Modules\Assessment\Models;

use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionChoice;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Answer extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'answers';

    protected $fillable = [
        'attempt_id',
        'question_id',
        'selected_choice_id',
        'text_response',
        'audio_response_url',
        'is_correct',
        'score_earned',
        'feedback',
    ];

    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
            'score_earned' => 'float',
        ];
    }

    /**
     * Get parent attempt.
     *
     * @return BelongsTo<Attempt, $this>
     */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(Attempt::class, 'attempt_id');
    }

    /**
     * Get question.
     *
     * @return BelongsTo<Question, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'question_id');
    }

    /**
     * Get selected choice.
     *
     * @return BelongsTo<QuestionChoice, $this>
     */
    public function selectedChoice(): BelongsTo
    {
        return $this->belongsTo(QuestionChoice::class, 'selected_choice_id');
    }
}
