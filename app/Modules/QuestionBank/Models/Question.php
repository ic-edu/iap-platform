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
        'media_asset_id',
        'passage_group_id',
        'passage_id',
        'passage_text',
        'audio_group_id',
        'audio_url',
        'image_url',
        'prompt',
        'section',
        'part_number',
        'question_type',
        'difficulty',
        'difficulty_score',
        'difficulty_status',
        'difficulty_source',
        'difficulty_factors',
        'difficulty_detected_at',
        'points',
        'explanation',
    ];

    protected function casts(): array
    {
        return [
            'section' => SectionType::class,
            'question_type' => QuestionType::class,
            'difficulty' => DifficultyLevel::class,
            'difficulty_score' => 'integer',
            'difficulty_factors' => 'array',
            'difficulty_detected_at' => 'datetime',
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
     * Get associated shared passage group (Part 6 / Part 7 Single, Double, Triple).
     *
     * @return BelongsTo<PassageGroup, $this>
     */
    public function passageGroup(): BelongsTo
    {
        return $this->belongsTo(PassageGroup::class, 'passage_group_id');
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
     * Get all effective passages associated with this question (from group or direct passage).
     *
     * @return \Illuminate\Support\Collection<int, Passage>
     */
    public function getEffectivePassages(): \Illuminate\Support\Collection
    {
        if ($this->passageGroup) {
            $passages = $this->passageGroup->passages;
            if ($passages && $passages->isNotEmpty()) {
                return $passages;
            }
        }

        if ($this->passage) {
            return collect([$this->passage]);
        }

        if (!empty($this->passage_text)) {
            $virtualPassage = new Passage([
                'title'         => 'Reading Passage',
                'content'       => $this->passage_text,
                'document_type' => 'article',
            ]);
            return collect([$virtualPassage]);
        }

        return collect();
    }

    /**
     * Get associated shared audio group (Part 3 Conversations / Part 4 Talks).
     *
     * @return BelongsTo<AudioGroup, $this>
     */
    public function audioGroup(): BelongsTo
    {
        return $this->belongsTo(AudioGroup::class, 'audio_group_id');
    }

    /**
     * Resolve effective audio URL, checking group first then mediaAsset then legacy audio_url.
     */
    public function getEffectiveAudioUrl(): ?string
    {
        if ($this->audioGroup) {
            $groupUrl = $this->audioGroup->getEffectiveAudioUrl();
            if (!empty($groupUrl)) {
                return $groupUrl;
            }
        }

        if ($this->mediaAsset) {
            $type = $this->mediaAsset->type ?? '';
            $mime = $this->mediaAsset->mime_type ?? '';
            if ($type === 'audio' || str_starts_with($mime, 'audio/')) {
                return $this->mediaAsset->publicUrl() ?: $this->mediaAsset->path;
            }
        }

        if (!empty($this->audio_url)) {
            return $this->audio_url;
        }

        return null;
    }

    /**
     * Resolve effective image URL, checking attached media asset first then legacy image_url.
     */
    public function getEffectiveImageUrl(): ?string
    {
        if ($this->mediaAsset) {
            $type = $this->mediaAsset->type ?? '';
            $mime = $this->mediaAsset->mime_type ?? '';
            if ($type === 'image' || str_starts_with($mime, 'image/')) {
                return $this->mediaAsset->publicUrl() ?: $this->mediaAsset->path;
            }
        }

        if (!empty($this->image_url)) {
            return $this->image_url;
        }

        return null;
    }

    /**
     * Get associated institutional media asset.
     *
     * @return BelongsTo<\App\Models\MediaAsset, $this>
     */
    public function mediaAsset(): BelongsTo
    {
        return $this->belongsTo(\App\Models\MediaAsset::class, 'media_asset_id');
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
     * Get test question bindings across sections.
     *
     * @return HasMany<\App\Modules\Assessment\Models\TestQuestion, $this>
     */
    public function testQuestions(): HasMany
    {
        return $this->hasMany(\App\Modules\Assessment\Models\TestQuestion::class, 'question_id');
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

    /**
     * Check if child question meets completeness criteria for TOEIC Audio/Passage group.
     */
    public function isCompleteChild(): bool
    {
        $partNumber = (int) ($this->part_number ?? 0);
        if (!in_array($partNumber, [2, 6], true) && empty(trim((string) $this->prompt))) {
            return false;
        }

        $choices = $this->relationLoaded('choices') ? $this->choices : $this->choices()->get();
        if ($choices->count() !== 4) {
            return false;
        }

        $nonEmpty = $choices->filter(fn($c) => !empty(trim((string) ($c->content ?? $c->choice_text ?? ''))));
        if ($nonEmpty->count() !== 4) {
            return false;
        }

        return $choices->contains(fn($c) => (bool) $c->is_correct);
    }
}
