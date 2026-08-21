<?php

namespace App\Modules\Assessment\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $attempt_id
 * @property string $question_id
 * @property string|null $media_asset_id
 * @property int $play_count
 * @property Carbon $started_at
 * @property Carbon|null $completed_at
 * @property string|null $ip_address
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class AttemptAudioPlay extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'attempt_audio_plays';

    protected $fillable = [
        'attempt_id',
        'question_id',
        'media_asset_id',
        'play_count',
        'started_at',
        'completed_at',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'play_count'   => 'integer',
            'started_at'   => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(Attempt::class, 'attempt_id');
    }
}
