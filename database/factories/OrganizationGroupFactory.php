<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Organization\Enums\GroupType;
use App\Modules\Organization\Models\Organization;
use App\Modules\Organization\Models\OrganizationGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganizationGroup>
 */
class OrganizationGroupFactory extends Factory
{
    protected $model = OrganizationGroup::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name'            => 'Class ' . fake()->bothify('##-?'),
            'group_type'      => GroupType::ClassGroup->value,
            'description'     => fake()->sentence(),
            'is_active'       => true,
            'created_by'      => User::factory(),
        ];
    }
}
