<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepositoryFindingHistory extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'repository_finding_id',
        'actor_id',
        'previous_status',
        'new_status',
        'note',
    ];

    public function finding(): BelongsTo
    {
        return $this->belongsTo(RepositoryFinding::class, 'repository_finding_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
