<?php

namespace App\Modules\Certificate\Models;

use App\Models\User;
use App\Modules\Assessment\Models\Attempt;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Certificate extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $table = 'certificates';

    protected $fillable = [
        'certificate_number',
        'attempt_id',
        'user_id',
        'issued_at',
        'expiry_date',
        'template_name',
        'pdf_url',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'expiry_date' => 'date',
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
