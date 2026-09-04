<?php

namespace Database\Factories;

use App\Modules\Organization\Enums\OrganizationStatus;
use App\Modules\Organization\Enums\OrganizationType;
use App\Modules\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    public function definition(): array
    {
        $name = fake()->company() . ' ' . fake()->randomElement(['University', 'Institute', 'Academy', 'Corp']);
        return [
            'name'              => $name,
            'slug'              => Str::slug($name) . '-' . Str::random(5),
            'organization_type' => fake()->randomElement(OrganizationType::cases()),
            'email'             => fake()->companyEmail(),
            'phone'             => fake()->phoneNumber(),
            'website'           => fake()->url(),
            'address'           => fake()->streetAddress(),
            'city'              => fake()->city(),
            'province'          => fake()->state(),
            'country'           => 'Indonesia',
            'postal_code'       => fake()->postcode(),
            'status'            => OrganizationStatus::Active,
        ];
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrganizationStatus::Suspended,
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'      => OrganizationStatus::Archived,
            'archived_at' => now(),
        ]);
    }
}
