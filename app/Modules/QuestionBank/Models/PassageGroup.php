<?php

namespace App\Modules\QuestionBank\Models;

use App\Models\User;
use App\Modules\Assessment\Models\Test;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string|null $test_id
 * @property string|null $question_bank_id
 * @property string|null $title
 * @property int $part_number
 * @property string $passage_type
 * @property array|null $context_metadata
 * @property int $order
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class PassageGroup extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $table = 'passage_groups';

    protected $fillable = [
        'test_id',
        'question_bank_id',
        'title',
        'part_number',
        'passage_type',
        'context_metadata',
        'order',
        'created_by',
    ];

    protected $casts = [
        'part_number'      => 'integer',
        'order'            => 'integer',
        'context_metadata' => 'array',
    ];

    /**
     * Get passages belonging to this passage group.
     *
     * @return HasMany<Passage, $this>
     */
    public function passages(): HasMany
    {
        return $this->hasMany(Passage::class, 'passage_group_id')->orderBy('order_in_group', 'asc');
    }

    /**
     * Get questions linked to this passage group.
     *
     * @return HasMany<Question, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class, 'passage_group_id');
    }

    /**
     * Get associated Question Bank (if authored in repository).
     *
     * @return BelongsTo<QuestionBank, $this>
     */
    public function questionBank(): BelongsTo
    {
        return $this->belongsTo(QuestionBank::class, 'question_bank_id');
    }

    /**
     * Get associated Assessment Test (if authored in assessment builder).
     *
     * @return BelongsTo<Test, $this>
     */
    public function test(): BelongsTo
    {
        return $this->belongsTo(Test::class, 'test_id');
    }

    /**
     * Get the creator user.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isSingle(): bool
    {
        return $this->passage_type === 'single';
    }

    public function isDouble(): bool
    {
        return $this->passage_type === 'double';
    }

    public function isTriple(): bool
    {
        return $this->passage_type === 'triple';
    }

    public function getRequiredPassageCount(): int
    {
        if ($this->part_number === 6) {
            return 1;
        }

        return match ($this->passage_type) {
            'double' => 2,
            'triple' => 3,
            default  => 1,
        };
    }
}
