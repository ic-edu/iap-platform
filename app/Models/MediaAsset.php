<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * @property string $id
 * @property string $filename
 * @property string $original_name
 * @property string $mime_type
 * @property string $type         audio|image|pdf|passage|other
 * @property string $path
 * @property int    $size
 * @property string $status       active|archived
 * @property string|null $title
 * @property string|null $description
 * @property int    $uploaded_by
 * @property int|null $archived_by
 * @property \Illuminate\Support\Carbon|null $archived_at
 */
class MediaAsset extends Model
{
    use HasUlids, SoftDeletes;

    protected $table = 'media_assets';

    protected $fillable = [
        'filename',
        'original_name',
        'mime_type',
        'type',
        'category',
        'sub_category',
        'exam_type',
        'difficulty',
        'tags',
        'approval_status',
        'version',
        'content_text',
        'path',
        'size',
        'status',
        'title',
        'description',
        'uploaded_by',
        'archived_by',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'size'        => 'integer',
            'tags'        => 'array',
            'archived_at' => 'datetime',
        ];
    }

    // ──────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeArchived($query)
    {
        return $query->where('status', 'archived');
    }

    // ──────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function archiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by');
    }

    public function deleteRequests(): HasMany
    {
        return $this->hasMany(MediaDeleteRequest::class, 'media_asset_id');
    }

    public function testSections(): BelongsToMany
    {
        return $this->belongsToMany(\App\Modules\Assessment\Models\TestSection::class, 'test_section_media', 'media_asset_id', 'test_section_id')
            ->withPivot(['id', 'caption', 'order'])
            ->withTimestamps();
    }

    public function reviewRequests(): HasMany
    {
        return $this->hasMany(RepositoryReviewRequest::class, 'resource_id')
            ->where('resource_type', 'MediaAsset');
    }

    public function latestReviewRequest()
    {
        return $this->hasOne(RepositoryReviewRequest::class, 'resource_id')
            ->where('resource_type', 'MediaAsset')
            ->latestOfMany();
    }

    // ──────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isArchived(): bool
    {
        return $this->status === 'archived';
    }

    public function isInstitutional(): bool
    {
        return $this->approval_status === 'approved';
    }

    public function isWorking(): bool
    {
        return in_array($this->approval_status, ['draft', 'working', null]);
    }

    public function isPendingReview(): bool
    {
        return $this->approval_status === 'pending_review';
    }

    public function isRevisionRequested(): bool
    {
        return $this->approval_status === 'revision_requested';
    }

    public function isRejected(): bool
    {
        return $this->approval_status === 'rejected';
    }

    public function isUsedInAssessment(): bool
    {
        return \DB::table('questions')->where('media_asset_id', $this->id)->exists()
            || \DB::table('test_section_media')->where('media_asset_id', $this->id)->exists();
    }

    public function assessmentUsageCount(): int
    {
        $qCount = \DB::table('questions')->where('media_asset_id', $this->id)->count();
        $sCount = \DB::table('test_section_media')->where('media_asset_id', $this->id)->count();
        return $qCount + $sCount;
    }

    public function hasPendingDeleteRequest(): bool
    {
        return $this->deleteRequests()->where('status', 'pending')->exists();
    }

    /**
     * Human-readable file size.
     */
    public function humanSize(): string
    {
        $bytes = $this->size;
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1048576) {
            return round($bytes / 1024, 1) . ' KB';
        }
        return round($bytes / 1048576, 1) . ' MB';
    }

    /**
     * Centralized Preview Gateway URL (TASK 2 & TASK 5 & TASK 8).
     * Never exposes raw /storage paths.
     */
    public function previewUrl(): string
    {
        return route('media.preview', $this->id);
    }

    /**
     * Public URL alias pointing exclusively to central preview gateway.
     */
    public function publicUrl(): string
    {
        return $this->previewUrl();
    }

    /**
     * Icon character based on type.
     */
    public function typeIcon(): string
    {
        return match($this->type) {
            'audio'   => '🎵',
            'image'   => '🖼',
            'pdf'     => '📄',
            'passage' => '📝',
            default   => '📎',
        };
    }

    /**
     * Clean format label for UI presentation (e.g. JPG, PNG, WEBP, MP3, M4A, WAV, PDF).
     */
    public function formatLabel(): string
    {
        $ext = strtolower(pathinfo($this->path ?: $this->original_name, PATHINFO_EXTENSION));
        return strtoupper($ext ?: $this->type);
    }
}
