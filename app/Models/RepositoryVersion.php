<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepositoryVersion extends Model
{
    use HasUlids;

    protected $table = 'repository_versions';

    protected $fillable = [
        'resource_type',
        'resource_id',
        'version_number',
        'title',
        'snapshot_data',
        'created_by',
        'change_reason',
        'is_current',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_data' => 'array',
            'is_current'    => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
