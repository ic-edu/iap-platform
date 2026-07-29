<?php

namespace App\Modules\QuestionBank\Models;

use App\Models\QuestionBankArchiveRequest;
use App\Models\User;
use App\Modules\Academic\Models\CourseCategory;
use App\Modules\QuestionBank\Enums\TestType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $title
 * @property string $slug
 * @property string|null $code
 * @property string|null $category_id
 * @property int $created_by
 * @property TestType $test_type
 * @property string $status
 * @property bool $is_published
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class QuestionBank extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $table = 'question_banks';

    protected $fillable = [
        'title',
        'slug',
        'category_id',
        'created_by',
        'test_type',
        'status',
        'is_published',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'test_type' => TestType::class,
            'is_published' => 'boolean',
        ];
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isPendingApproval(): bool
    {
        return $this->status === 'pending_approval';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isPublished(): bool
    {
        return $this->status === 'published' || (bool) $this->is_published;
    }

    public function isArchived(): bool
    {
        return $this->status === 'archived';
    }

    public function hasPendingArchiveRequest(): bool
    {
        return $this->archiveRequests()->where('status', 'pending')->exists();
    }

    /**
     * Get the category of this bank.
     *
     * @return BelongsTo<CourseCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(CourseCategory::class, 'category_id');
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

    /**
     * Get questions in this bank.
     *
     * @return HasMany<Question, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class, 'question_bank_id');
    }

    /**
     * Get archive requests for this question bank.
     *
     * @return HasMany<QuestionBankArchiveRequest, $this>
     */
    public function archiveRequests(): HasMany
    {
        return $this->hasMany(QuestionBankArchiveRequest::class, 'question_bank_id');
    }
}
