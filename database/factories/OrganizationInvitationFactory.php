<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Organization\Enums\InvitationStatus;
use App\Modules\Organization\Enums\MembershipRole;
use App\Modules\Organization\Models\Organization;
use App\Modules\Organization\Models\OrganizationInvitation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<OrganizationInvitation>
 */
class OrganizationInvitationFactory extends Factory
{
    protected $model = OrganizationInvitation::class;

    public function definition(): array
    {
        $token = Str::random(40);
        return [
            'organization_id'   => Organization::factory(),
            'email'             => fake()->unique()->safeEmail(),
            'intended_role'     => MembershipRole::Member,
            'member_identifier' => 'INV-' . fake()->numerify('####'),
            'department'        => 'Academic Division',
            'token_hash'        => hash('sha256', $token),
            'status'            => InvitationStatus::Pending,
            'expires_at'        => now()->addDays(7),
            'invited_by'        => User::factory(),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'     => InvitationStatus::Expired,
            'expires_at' => now()->subDay(),
        ]);
    }

    public function revoked(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => InvitationStatus::Revoked,
        ]);
    }
}
