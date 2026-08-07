<?php

namespace App\Modules\QuestionBank\Models;

use App\Models\AclAuditTrail;
use App\Models\AclCategory;
use App\Models\AclVersion;
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
 * @property string|null $acl_category_id
 * @property int $created_by
 * @property TestType $test_type
 * @property string $status
 * @property string $current_version
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
        'acl_category_id',
        'created_by',
        'test_type',
        'status',
        'current_version',
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

    public function isSubmitted(): bool
    {
        return $this->status === 'submitted' || $this->status === 'pending_approval';
    }

    public function isReviewed(): bool
    {
        return $this->status === 'reviewed';
    }

    public function isPendingApproval(): bool
    {
        return $this->status === 'pending_approval' || $this->status === 'submitted';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isPublished(): bool
    {
        return $this->status === 'published' || (bool) $this->is_published;
    }

    public function isRevisionRequested(): bool
    {
        return in_array($this->status, ['needs_revision', 'revision_requested', 'rejected']);
    }

    public function isArchived(): bool
    {
        return $this->status === 'archived';
    }

    public function isRestoreRequested(): bool
    {
        return $this->status === 'pending_restore_approval' || $this->status === 'restore_requested';
    }

    public function hasPendingArchiveRequest(): bool
    {
        return $this->archiveRequests()->where('status', 'pending')->exists();
    }

    /**
     * Get the course category of this bank.
     *
     * @return BelongsTo<CourseCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(CourseCategory::class, 'category_id');
    }

    /**
     * Get the ACL taxonomy category of this bank.
     *
     * @return BelongsTo<AclCategory, $this>
     */
    public function aclCategory(): BelongsTo
    {
        return $this->belongsTo(AclCategory::class, 'acl_category_id');
    }

    /**
     * Get the creator user (Teacher/Contributor).
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

    /**
     * Get ACL versions for this question bank.
     *
     * @return HasMany<AclVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(AclVersion::class, 'resource_id')->where('resource_type', 'QuestionBank')->orderBy('created_at', 'desc');
    }

    /**
     * Get ACL audit trails for this question bank.
     *
     * @return HasMany<AclAuditTrail, $this>
     */
    public function auditTrails(): HasMany
    {
        return $this->hasMany(AclAuditTrail::class, 'resource_id')->where('resource_type', 'QuestionBank')->orderBy('created_at', 'desc');
    }
}
