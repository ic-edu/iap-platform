<?php

namespace App\Modules\Organization\Services;

use App\Models\User;
use App\Modules\Organization\Models\Organization;
use App\Modules\Organization\Models\OrganizationMembership;

class OrganizationContext
{
    protected ?Organization $currentOrganization = null;
    protected ?OrganizationMembership $currentMembership = null;

    public function setContext(Organization $organization, ?OrganizationMembership $membership = null): void
    {
        $this->currentOrganization = $organization;
        $this->currentMembership = $membership;
    }

    public function getOrganization(): ?Organization
    {
        return $this->currentOrganization;
    }

    public function getMembership(): ?OrganizationMembership
    {
        return $this->currentMembership;
    }

    public function hasContext(): bool
    {
        return $this->currentOrganization !== null;
    }

    public function clear(): void
    {
        $this->currentOrganization = null;
        $this->currentMembership = null;
    }
}
