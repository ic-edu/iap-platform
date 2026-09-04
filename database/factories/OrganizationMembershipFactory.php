<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Organization\Enums\MembershipRole;
use App\Modules\Organization\Enums\MembershipStatus;
use App\Modules\Organization\Models\Organization;
use App\Modules\Organization\Models\OrganizationMembership;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganizationMembership>
 */
class OrganizationMembershipFactory extends Factory
{
    protected $model = OrganizationMembership::class;

    public function definition(): array
    {
        return [
            'organization_id'   => Organization::factory(),
            'user_id'           => User::factory(),
            'role'              => MembershipRole::Member,
            'member_identifier' => 'ID-' . fake()->numerify('######'),
            'department'        => fake()->randomElement(['Engineering', 'Business', 'Languages', 'Human Resources']),
            'status'            => MembershipStatus::Active,
            'joined_at'         => now(),
        ];
    }

    public function coordinator(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => MembershipRole::Coordinator,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => MembershipRole::Admin,
        ]);
    }

    public function owner(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => MembershipRole::Owner,
        ]);
    }
}
