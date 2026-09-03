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
        'image_url',
        'media_asset_id',
    ];

    /**
     * Get attached media asset.
     *
     * @return BelongsTo<\App\Models\MediaAsset, $this>
     */
    public function mediaAsset(): BelongsTo
    {
        return $this->belongsTo(\App\Models\MediaAsset::class, 'media_asset_id');
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
                return route('media.preview', $this->mediaAsset->id);
            }
        }

        if (!empty($this->image_url) && preg_match('#/media/([0-9a-z]+)#i', $this->image_url, $m)) {
            return route('media.preview', $m[1]);
        }

        if (!empty($this->image_url)) {
            return $this->image_url;
        }

        return null;
    }

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
