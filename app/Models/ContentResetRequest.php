<?php

namespace App\Models;

use App\Services\ContentReset\ContentResetDomains;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $request_number
 * @property string $mode
 * @property string $status
 * @property int $requested_by
 * @property string $reason
 * @property array $scope
 * @property array|null $target_entities
 * @property array|null $excluded_domains
 * @property string $risk_classification
 * @property array|null $audit_report
 * @property array|null $backup_reference
 * @property int|null $ceo_approver_id
 * @property Carbon|null $ceo_approved_at
 * @property string|null $ceo_notes
 * @property int|null $sa_approver_id
 * @property Carbon|null $sa_approved_at
 * @property string|null $sa_notes
 * @property int|null $executed_by
 * @property Carbon|null $executed_at
 * @property array|null $execution_log
 * @property Carbon|null $completed_at
 */
class ContentResetRequest extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'content_reset_requests';

    protected $fillable = [
        'request_number',
        'mode',
        'status',
        'requested_by',
        'reason',
        'scope',
        'target_entities',
        'excluded_domains',
        'risk_classification',
        'audit_report',
        'backup_reference',
        'ceo_approver_id',
        'ceo_approved_at',
        'ceo_notes',
        'sa_approver_id',
        'sa_approved_at',
        'sa_notes',
        'executed_by',
        'executed_at',
        'execution_log',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'scope'            => 'array',
            'target_entities'  => 'array',
            'excluded_domains' => 'array',
            'audit_report'     => 'array',
            'backup_reference' => 'array',
            'execution_log'    => 'array',
            'ceo_approved_at'  => 'datetime',
            'sa_approved_at'   => 'datetime',
            'executed_at'      => 'datetime',
            'completed_at'     => 'datetime',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function ceoApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ceo_approver_id');
    }

    public function saApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sa_approver_id');
    }

    public function executor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executed_by');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(ContentResetAuditLog::class, 'request_id');
    }

    public function isHardReset(): bool
    {
        return $this->mode === ContentResetDomains::MODE_HARD_RESET;
    }

    public function isRefresh(): bool
    {
        return $this->mode === ContentResetDomains::MODE_REFRESH;
    }

    public function isCeoApproved(): bool
    {
        return !empty($this->ceo_approver_id) && !empty($this->ceo_approved_at);
    }

    public function isSaApproved(): bool
    {
        return !empty($this->sa_approver_id) && !empty($this->sa_approved_at);
    }

    /**
     * Check if hard reset has both required distinct approvals.
     */
    public function isFullyApproved(): bool
    {
        if ($this->isRefresh()) {
            return in_array($this->status, ['audit_completed', 'approved'], true);
        }

        return $this->isCeoApproved() 
            && $this->isSaApproved() 
            && $this->ceo_approver_id !== $this->sa_approver_id
            && $this->ceo_approver_id !== $this->requested_by
            && $this->sa_approver_id !== $this->requested_by
            && $this->status === 'approved';
    }
}
