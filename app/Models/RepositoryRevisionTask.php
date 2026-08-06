<?php

namespace App\Models;

use App\Modules\QuestionBank\Models\QuestionBank;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class RepositoryRevisionTask extends Model
{
    use HasUuids;

    protected $table = 'repository_revision_requests';

    protected $fillable = [
        'question_bank_id',
        'teacher_id',
        'requested_by_id',
        'status',
        'notes',
        'resolved_at',
    ];

    // Alias Attributes for Governance Compatibility
    public function getRepositoryIdAttribute()
    {
        return $this->question_bank_id;
    }

    public function getReviewerIdAttribute()
    {
        return $this->requested_by_id;
    }

    public function getReviewNotesAttribute()
    {
        return $this->notes;
    }

    public function getQualityFindingsAttribute()
    {
        return $this->items;
    }

    public function questionBank()
    {
        return $this->belongsTo(QuestionBank::class, 'question_bank_id');
    }

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }

    public function items()
    {
        return $this->hasMany(RepositoryRevisionItem::class, 'repository_revision_request_id');
    }
}
