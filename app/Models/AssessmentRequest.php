<?php

namespace App\Models;

use App\Modules\Assessment\Models\Test;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssessmentRequest extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $table = 'assessment_requests';

    protected $fillable = [
        'title',
        'test_type',
        'program_context',
        'required_sections',
        'notes',
        'requested_deadline',
        'requested_by',
        'candidate_id',
        'status',
        'test_id',
    ];

    protected function casts(): array
    {
        return [
            'requested_deadline' => 'date',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'candidate_id');
    }

    public function test(): BelongsTo
    {
        return $this->belongsTo(Test::class, 'test_id');
    }
}
