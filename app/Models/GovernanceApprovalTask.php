<?php

namespace App\Models;

use App\Modules\QuestionBank\Models\QuestionBank;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class GovernanceApprovalTask extends Model
{
    use HasUuids;

    protected $table = 'governance_approval_tasks';

    protected $fillable = [
        'question_bank_id',
        'teacher_id',
        'assigned_repository_manager_id',
        'workflow',
        'status',
        'submitted_at',
        'completed_at',
    ];

    // Alias Attributes for Governance Compatibility
    public function getTaskIdAttribute()
    {
        return $this->id;
    }

    public function getRepositoryIdAttribute()
    {
        return $this->question_bank_id;
    }

    public function questionBank()
    {
        return $this->belongsTo(QuestionBank::class, 'question_bank_id');
    }

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function assignedRepositoryManager()
    {
        return $this->belongsTo(User::class, 'assigned_repository_manager_id');
    }
}
