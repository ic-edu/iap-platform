<?php

namespace App\Modules\QuestionBank\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $question_bank_id
 * @property string $title
 * @property string $content
 * @property string|null $audio_url
 */
class Passage extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $table = 'passages';

    protected $fillable = [
        'passage_group_id',
        'question_bank_id',
        'test_id',
        'order_in_group',
        'document_type',
        'title',
        'content',
        'audio_url',
    ];

    /**
     * Get parent passage group.
     *
     * @return BelongsTo<PassageGroup, $this>
     */
    public function passageGroup(): BelongsTo
    {
        return $this->belongsTo(PassageGroup::class, 'passage_group_id');
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
     * Get parent assessment test.
     *
     * @return BelongsTo<\App\Modules\Assessment\Models\Test, $this>
     */
    public function test(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Assessment\Models\Test::class, 'test_id');
    }

    /**
     * Get questions utilizing this passage.
     *
     * @return HasMany<Question, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class, 'passage_id');
    }
}
