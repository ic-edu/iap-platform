<?php

namespace App\Models;

use App\Modules\Academic\Models\CourseEnrollment;
use App\Modules\Certificate\Models\Certificate;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $status
 * @property string|null $phone_number
 * @property string $theme_preference
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'status', 'phone_number', 'theme_preference'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    /**
     * Get user theme preference ('dark', 'light', 'system').
     */
    public function getThemePreference(): string
    {
        return $this->theme_preference ?? 'dark';
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Creation requests for this user.
     *
     * @return HasMany<UserCreationRequest, $this>
     */
    public function creationRequests(): HasMany
    {
        return $this->hasMany(UserCreationRequest::class, 'user_id');
    }

    /**
     * Deletion requests for this user.
     *
     * @return HasMany<UserDeletionRequest, $this>
     */
    public function deletionRequests(): HasMany
    {
        return $this->hasMany(UserDeletionRequest::class, 'user_id');
    }

    /**
     * Check if user has a pending creation approval request.
     */
    public function hasPendingCreationRequest(): bool
    {
        return $this->status === 'pending_approval' || $this->creationRequests()->where('status', 'pending')->exists();
    }

    /**
     * Check if user has a pending deletion approval request.
     */
    public function hasPendingDeletionRequest(): bool
    {
        return $this->deletionRequests()->where('status', 'pending')->exists();
    }

    /**
     * Get enrollments for user.
     *
     * @return HasMany<CourseEnrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(CourseEnrollment::class, 'user_id');
    }

    /**
     * Get certificates issued to user.
     *
     * @return HasMany<Certificate, $this>
     */
    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class, 'user_id');
    }

    /**
     * Get orders placed by user.
     *
     * @return HasMany<\App\Modules\Commerce\Domain\Models\Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(\App\Modules\Commerce\Domain\Models\Order::class, 'user_id');
    }

    /**
     * Get test assignments for candidate user.
     *
     * @return HasMany<\App\Modules\Assessment\Models\CandidateTestAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(\App\Modules\Assessment\Models\CandidateTestAssignment::class, 'user_id');
    }

    /**
     * Get assessment attempts for candidate user.
     *
     * @return HasMany<\App\Modules\Assessment\Models\Attempt, $this>
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(\App\Modules\Assessment\Models\Attempt::class, 'user_id');
    }

    /**
     * Defensive permission check for downloading repository assets.
     * Never throws PermissionDoesNotExist exception even if permissions are unseeded.
     */
    public function canDownloadRepositoryAsset(): bool
    {
        if ($this->hasRole('super-admin')) {
            return true;
        }

        try {
            return $this->hasPermissionTo('repository.download.asset');
        } catch (\Throwable $e) {
            return false;
        }
    }
}
