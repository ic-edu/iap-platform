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

    /**
     * Determine if this request is currently active (pending, in authoring, review, revision, or publication approval).
     */
    public function isActive(): bool
    {
        if (in_array($this->status, ['completed', 'cancelled', 'rejected', 'archived'], true)) {
            return false;
        }

        if ($this->test_id && $this->test) {
            return ! $this->test->isPublished() && $this->test->status !== 'published';
        }

        return in_array($this->status, ['pending', 'draft_created'], true);
    }

    /**
     * Get the current workflow stage identifier.
     */
    public function getWorkflowStage(): string
    {
        if ($this->status === 'completed' || ($this->test && $this->test->isPublished())) {
            return 'completed';
        }

        if ($this->status === 'archived') {
            return 'archived';
        }

        if (!$this->test_id || !$this->test) {
            return 'awaiting_rm';
        }

        $testStatus = strtolower(trim((string) $this->test->status));

        if ($testStatus === 'approved') {
            return 'awaiting_publication';
        }

        if (in_array($testStatus, ['needs_revision', 'revision_requested', 'rejected'], true)) {
            return 'revision_in_progress';
        }

        if (in_array($testStatus, ['pending', 'pending_approval', 'pending_review', 'submitted'], true)) {
            return 'awaiting_review';
        }

        if ($testStatus === 'draft' || empty($testStatus)) {
            return 'in_authoring';
        }

        return 'in_authoring';
    }

    /**
     * Get the human-readable workflow stage label.
     */
    public function getWorkflowStageLabel(): string
    {
        return match ($this->getWorkflowStage()) {
            'awaiting_rm'          => 'Awaiting Repository Manager',
            'in_authoring'         => 'In Authoring',
            'awaiting_review'      => 'Awaiting RM Review',
            'revision_in_progress' => 'Revision in Progress',
            'awaiting_publication' => 'Awaiting Publication',
            'completed'            => 'Completed',
            'archived'             => 'Archived',
            default                => 'In Authoring',
        };
    }

    /**
     * Normalize program context string for robust matching.
     */
    public static function normalizeContext(?string $context): string
    {
        if ($context === null) {
            return '';
        }
        $cleaned = preg_replace('/\s+/', ' ', trim($context));
        return strtolower($cleaned ?? '');
    }

    /**
     * Resolve an active assessment request for a candidate requirement.
     */
    public static function resolveActiveRequirement(
        ?int $candidateId,
        string $testType,
        ?string $programContext = null,
        bool $lockForUpdate = false
    ): ?self {
        $normalizedType = strtolower(trim($testType));
        $normalizedContext = self::normalizeContext($programContext);

        $query = self::with(['test.assignedTeacher', 'candidate', 'requester'])
            ->whereIn('status', ['pending', 'draft_created'])
            ->where('test_type', $normalizedType);

        if ($candidateId !== null) {
            $query->where('candidate_id', $candidateId);
        } else {
            $query->whereNull('candidate_id');
        }

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $candidates = $query->latest()->get();

        // 1. Exact normalized context match
        $exact = $candidates->first(function (self $req) use ($normalizedContext) {
            return self::normalizeContext($req->program_context) === $normalizedContext && $req->isActive();
        });

        if ($exact) {
            return $exact;
        }

        // 2. If no exact match and candidate is specified, find active requirement if context is empty
        if ($candidateId !== null && empty($normalizedContext)) {
            $anyActive = $candidates->first(fn(self $req) => $req->isActive());
            if ($anyActive) {
                return $anyActive;
            }
        }

        return null;
    }
}
