<?php

namespace App\Modules\Assessment\Models;

use App\Modules\QuestionBank\Enums\SectionType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $test_id
 * @property string $title
 * @property SectionType $section_type
 * @property int $duration_minutes
 * @property int $order
 */
class TestSection extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'test_sections';

    protected $fillable = [
        'test_id',
        'title',
        'section_type',
        'instructions',
        'duration_minutes',
        'order',
    ];

    protected function casts(): array
    {
        return [
            'section_type' => SectionType::class,
            'duration_minutes' => 'integer',
            'order' => 'integer',
        ];
    }

    /**
     * Get parent test.
     *
     * @return BelongsTo<Test, $this>
     */
    public function test(): BelongsTo
    {
        return $this->belongsTo(Test::class, 'test_id');
    }

    /**
     * Get questions associated with this section.
     *
     * @return HasMany<TestQuestion, $this>
     */
    public function testQuestions(): HasMany
    {
        return $this->hasMany(TestQuestion::class, 'test_section_id')->orderBy('order');
    }

    /**
     * Get media assets attached to this test section.
     *
     * @return BelongsToMany<\App\Models\MediaAsset, $this>
     */
    public function mediaAssets(): BelongsToMany
    {
        return $this->belongsToMany(\App\Models\MediaAsset::class, 'test_section_media', 'test_section_id', 'media_asset_id')
            ->withPivot(['id', 'caption', 'order'])
            ->withTimestamps()
            ->orderBy('test_section_media.order');
    }
}
