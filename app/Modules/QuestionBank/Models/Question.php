<?php

namespace App\Modules\QuestionBank\Models;

use App\Modules\QuestionBank\Enums\DifficultyLevel;
use App\Modules\QuestionBank\Enums\QuestionType;
use App\Modules\QuestionBank\Enums\SectionType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $question_bank_id
 * @property string|null $passage_id
 * @property string|null $passage_text
 * @property string|null $audio_url
 * @property string|null $image_url
 * @property string $prompt
 * @property string|null $question_text
 * @property SectionType $section
 * @property int|null $part_number
 * @property QuestionType $question_type
 * @property DifficultyLevel $difficulty
 * @property int $points
 * @property string|null $explanation
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Question extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $table = 'questions';

    protected $fillable = [
        'question_bank_id',
        'passage_id',
        'passage_text',
        'audio_url',
        'image_url',
        'prompt',
        'section',
        'part_number',
        'question_type',
        'difficulty',
        'points',
        'explanation',
    ];

    protected function casts(): array
    {
        return [
            'section' => SectionType::class,
            'question_type' => QuestionType::class,
            'difficulty' => DifficultyLevel::class,
            'points' => 'integer',
            'part_number' => 'integer',
        ];
    }

    /**
     * Get parent question bank.
     *
     * @return BelongsTo<QuestionBank, $this>
     */
    public function questionBank(): BelongsTo
    {
        return $this->belongsTo(QuestionBank::class, 'question_bank_id');
    }

    /**
     * Get associated passage reading text.
     *
     * @return BelongsTo<Passage, $this>
     */
    public function passage(): BelongsTo
    {
        return $this->belongsTo(Passage::class, 'passage_id');
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

    /**
     * Get associated tags.
     *
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'question_tags', 'question_id', 'tag_id');
    }
}
