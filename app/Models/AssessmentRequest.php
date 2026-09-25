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
            if ($this->test->status === 'archived') {
                return false;
            }
            return ! $this->test->isPublished();
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

        if ($this->status === 'archived' || ($this->test && $this->test->status === 'archived')) {
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
     * Shared requirement matcher over a collection of active AssessmentRequests.
     */
    public static function findMatchingInCollection(
        \Illuminate\Support\Collection $requests,
        ?int $candidateId,
        string $testType,
        ?string $programContext = null
    ): ?self {
        $normalizedType = strtolower(trim($testType));
        $normalizedContext = self::normalizeContext($programContext);

        $filtered = $requests->filter(function (self $req) use ($candidateId, $normalizedType) {
            if ($candidateId !== null) {
                if ((int) $req->candidate_id !== (int) $candidateId) {
                    return false;
                }
            } else {
                if ($req->candidate_id !== null) {
                    return false;
                }
            }

            if (strtolower(trim((string) $req->test_type)) !== $normalizedType) {
                return false;
            }

            return $req->isActive();
        });

        if ($filtered->isEmpty()) {
            return null;
        }

        // 1. Exact normalized context match (strictly highest precedence)
        $exact = $filtered->first(function (self $req) use ($normalizedContext) {
            return self::normalizeContext($req->program_context) === $normalizedContext;
        });

        if ($exact) {
            return $exact;
        }

        // 2. Legacy / Candidate-Level (Empty Context) Match
        // If an active request exists with empty context, it covers general candidate requirements
        $emptyContextReq = $filtered->first(function (self $req) {
            return empty(self::normalizeContext($req->program_context));
        });

        if ($emptyContextReq) {
            return $emptyContextReq;
        }

        // 3. If target context is empty, match any active candidate request
        if (empty($normalizedContext) && $candidateId !== null) {
            return $filtered->first();
        }

        return null;
    }

    /**
     * Resolve an active assessment request for a candidate requirement from the database.
     */
    public static function resolveActiveRequirement(
        ?int $candidateId,
        string $testType,
        ?string $programContext = null,
        bool $lockForUpdate = false
    ): ?self {
        $normalizedType = strtolower(trim($testType));

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

        return self::findMatchingInCollection($candidates, $candidateId, $testType, $programContext);
    }
}
