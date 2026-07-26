<?php

namespace App\Modules\Certificate\Models;

use App\Models\User;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Certificate\Enums\CertificateStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $certificate_number
 * @property string|null $verification_code
 * @property CertificateStatus $status
 * @property string $template
 * @property string $attempt_id
 * @property int $user_id
 * @property Carbon|null $issued_at
 * @property Carbon|null $revoked_at
 * @property Carbon|null $expires_at
 * @property string|null $pdf_url
 * @property User|null $user
 * @property Attempt|null $attempt
 */
class Certificate extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $table = 'certificates';

    protected $fillable = [
        'certificate_number',
        'verification_code',
        'status',
        'template',
        'attempt_id',
        'user_id',
        'issued_at',
        'revoked_at',
        'expires_at',
        'pdf_url',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'revoked_at' => 'datetime',
            'expires_at' => 'datetime',
            'status' => CertificateStatus::class,
        ];
    }

    /**
     * Get attempt.
     *
     * @return BelongsTo<Attempt, $this>
     */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(Attempt::class, 'attempt_id');
    }

    /**
     * Get user.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
