<?php

namespace App\Modules\QuestionBank\Models;

use App\Modules\QuestionBank\Enums\QuestionType;
use App\Modules\QuestionBank\Enums\SectionType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $question_bank_id
 * @property string|null $passage_text
 * @property string|null $audio_url
 * @property string|null $image_url
 * @property string $prompt
 * @property SectionType $section
 * @property int|null $part_number
 * @property QuestionType $question_type
 * @property int $points
 * @property string|null $explanation
 */
class Question extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $table = 'questions';

    protected $fillable = [
        'question_bank_id',
        'passage_text',
        'audio_url',
        'image_url',
        'prompt',
        'section',
        'part_number',
        'question_type',
        'points',
        'explanation',
    ];

    protected function casts(): array
    {
        return [
            'section' => SectionType::class,
            'question_type' => QuestionType::class,
            'points' => 'integer',
            'part_number' => 'integer',
        ];
    }

    /**
     * Get the question bank.
     *
     * @return BelongsTo<QuestionBank, $this>
     */
    public function questionBank(): BelongsTo
    {
        return $this->belongsTo(QuestionBank::class, 'question_bank_id');
    }

    /**
     * Get multiple choice options for this question.
     *
     * @return HasMany<QuestionChoice, $this>
     */
    public function choices(): HasMany
    {
        return $this->hasMany(QuestionChoice::class, 'question_id');
    }
}
