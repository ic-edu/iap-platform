<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Student Application model for managing student registrations and placement status.
 *
 * @property string $id
 * @property string $registration_id
 * @property int|null $user_id
 * @property string $student_name
 * @property string $selected_program
 * @property string $english_level
 * @property string $placement_test_status
 * @property int $target_score
 * @property string $application_status
 * @property string|null $notes
 */
class StudentApplication extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'student_applications';

    protected $fillable = [
        'registration_id',
        'user_id',
        'student_name',
        'selected_program',
        'english_level',
        'placement_test_status',
        'target_score',
        'application_status',
        'notes',
    ];

    /**
     * Get student user account.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
