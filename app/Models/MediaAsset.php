<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
     * Public URL for the stored file.
     */
    public function publicUrl(): string
    {
        return Storage::url($this->path);
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
}
