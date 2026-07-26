<?php

namespace App\Modules\Assessment\Models;

use App\Models\User;
use App\Modules\Assessment\Enums\AttemptStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $test_id
 * @property int $user_id
 * @property Carbon|null $started_at
 * @property Carbon|null $submitted_at
 * @property float|null $total_score
 * @property array<string, mixed>|null $section_scores
 * @property AttemptStatus $status
 * @property Carbon|null $updated_at
 * @property User|null $user
 * @property Test|null $test
 */
class Attempt extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'attempts';

    protected $fillable = [
        'test_id',
        'user_id',
        'started_at',
        'submitted_at',
        'total_score',
        'section_scores',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'total_score' => 'float',
            'section_scores' => 'array',
            'status' => AttemptStatus::class,
        ];
    }

    /**
     * Get test.
     *
     * @return BelongsTo<Test, $this>
     */
    public function test(): BelongsTo
    {
        return $this->belongsTo(Test::class, 'test_id');
    }

    /**
     * Get student user.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get answers given in this attempt.
     *
     * @return HasMany<Answer, $this>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class, 'attempt_id');
    }
}
