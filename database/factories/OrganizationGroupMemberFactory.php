<?php

namespace Database\Factories;

use App\Modules\Organization\Models\OrganizationGroup;
use App\Modules\Organization\Models\OrganizationGroupMember;
use App\Modules\Organization\Models\OrganizationMembership;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganizationGroupMember>
 */
class OrganizationGroupMemberFactory extends Factory
{
    protected $model = OrganizationGroupMember::class;

    public function definition(): array
    {
        return [
            'group_id'      => OrganizationGroup::factory(),
            'membership_id' => OrganizationMembership::factory(),
        ];
    }
}
