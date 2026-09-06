<?php

namespace App\Modules\Organization\Models;

use App\Models\User;
use App\Modules\Organization\Enums\InvitationStatus;
use App\Modules\Organization\Enums\MembershipRole;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $email
 * @property MembershipRole $intended_role
 * @property string|null $member_identifier
 * @property string|null $department
 * @property string $token_hash
 * @property InvitationStatus $status
 * @property Carbon $expires_at
 * @property Carbon|null $accepted_at
 * @property int $invited_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class OrganizationInvitation extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'organization_invitations';

    protected $fillable = [
        'organization_id',
        'email',
        'intended_role',
        'member_identifier',
        'department',
        'token_hash',
        'status',
        'expires_at',
        'accepted_at',
        'invited_by',
    ];

    protected function casts(): array
    {
        return [
            'intended_role' => MembershipRole::class,
            'status'        => InvitationStatus::class,
            'expires_at'    => 'datetime',
            'accepted_at'   => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function isPending(): bool
    {
        return $this->status === InvitationStatus::Pending;
    }

    public function isExpired(): bool
    {
        return $this->status === InvitationStatus::Expired || ($this->isPending() && $this->expires_at && $this->expires_at->isPast());
    }

    public function isRevoked(): bool
    {
        return $this->status === InvitationStatus::Revoked;
    }

    public function isAccepted(): bool
    {
        return $this->status === InvitationStatus::Accepted;
    }

    public function canBeAccepted(): bool
    {
        return $this->isPending() && !$this->isExpired();
    }

    /**
     * Create invitation and return plaintext token for email.
     *
     * @return array{invitation: self, token: string}
     */
    public static function createWithToken(array $attributes): array
    {
        $plainToken = Str::random(40);
        $tokenHash = hash('sha256', $plainToken);

        $attributes['token_hash'] = $tokenHash;
        $attributes['expires_at'] = $attributes['expires_at'] ?? now()->addDays(7);
        $attributes['status'] = InvitationStatus::Pending;

        $invitation = self::create($attributes);

        return [
            'invitation' => $invitation,
            'token'      => $plainToken,
        ];
    }

    /**
     * Find invitation by plaintext token.
     */
    public static function findByPlainToken(string $token): ?self
    {
        $tokenHash = hash('sha256', $token);

        return self::where('token_hash', $tokenHash)->first();
    }
}
