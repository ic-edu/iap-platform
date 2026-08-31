<?php

namespace App\Modules\QuestionBank\Models;

use App\Models\MediaAsset;
use App\Models\User;
use App\Modules\Assessment\Models\Test;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string|null $test_id
 * @property string|null $question_bank_id
 * @property string|null $title
 * @property string $group_type 'conversation' | 'talk'
 * @property int $part_number 3 | 4
 * @property string|null $media_asset_id
 * @property string|null $audio_url
 * @property string|null $audio_script
 * @property int $order
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class AudioGroup extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'audio_groups';

    protected $fillable = [
        'test_id',
        'question_bank_id',
        'title',
        'group_type',
        'part_number',
        'media_asset_id',
        'audio_url',
        'audio_script',
        'order',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'part_number' => 'integer',
            'order'       => 'integer',
            'created_by'  => 'integer',
        ];
    }

    /**
     * Questions belonging to this audio group.
     *
     * @return HasMany<Question, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class, 'audio_group_id');
    }

    /**
     * Associated institutional media asset.
     *
     * @return BelongsTo<MediaAsset, $this>
     */
    public function mediaAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'media_asset_id');
    }

    /**
     * Parent Question Bank if authored within repository.
     *
     * @return BelongsTo<QuestionBank, $this>
     */
    public function questionBank(): BelongsTo
    {
        return $this->belongsTo(QuestionBank::class, 'question_bank_id');
    }

    /**
     * Parent Assessment Test if authored directly within assessment builder.
     *
     * @return BelongsTo<Test, $this>
     */
    public function test(): BelongsTo
    {
        return $this->belongsTo(Test::class, 'test_id');
    }

    /**
     * Authoring User / Creator.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Resolve effective audio URL (from direct URL or media asset).
     */
    public function getEffectiveAudioUrl(): ?string
    {
        if (!empty($this->audio_url)) {
            return $this->audio_url;
        }

        if ($this->mediaAsset) {
            return $this->mediaAsset->path;
        }

        return null;
    }

    /**
     * Check if group is Part 3 Conversation.
     */
    public function isConversation(): bool
    {
        return $this->group_type === 'conversation' || $this->part_number === 3;
    }

    /**
     * Check if group is Part 4 Talk.
     */
    public function isTalk(): bool
    {
        return $this->group_type === 'talk' || $this->part_number === 4;
    }

    /**
     * Get count of complete child questions.
     */
    public function getCompleteQuestionsCountAttribute(): int
    {
        return $this->questions->filter(fn($q) => $q->isCompleteChild())->count();
    }

    /**
     * Check if group authoring is complete (has audio + exactly 3 complete child questions).
     */
    public function isComplete(): bool
    {
        $hasAudio = !empty($this->audio_url) || !empty($this->media_asset_id);
        return $hasAudio && $this->complete_questions_count === 3;
    }

    /**
     * Authoring completeness status: 'complete' | 'draft'.
     */
    public function getAuthoringStatusAttribute(): string
    {
        return $this->isComplete() ? 'complete' : 'draft';
    }

    /**
     * Check if group satisfies the strict TOEIC requirement: exactly 3 questions + audio asset attached.
     */
    public function isValidGroup(): bool
    {
        return $this->isComplete();
    }
}
