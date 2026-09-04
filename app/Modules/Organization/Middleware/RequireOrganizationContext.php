<?php

namespace App\Modules\Organization\Middleware;

use App\Models\User;
use App\Modules\Organization\Enums\MembershipRole;
use App\Modules\Organization\Enums\MembershipStatus;
use App\Modules\Organization\Enums\OrganizationStatus;
use App\Modules\Organization\Models\Organization;
use App\Modules\Organization\Models\OrganizationMembership;
use App\Modules\Organization\Services\OrganizationContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireOrganizationContext
{
    public function __construct(
        protected OrganizationContext $context
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, ?string $requiredRole = null): Response
    {
        $user = $request->user();
        if (!$user) {
            return redirect()->route('login');
        }

        $orgParam = $request->route('organization');
        if (!$orgParam) {
            abort(404, 'Organization context is missing from the request.');
        }

        /** @var Organization|null $organization */
        if ($orgParam instanceof Organization) {
            $organization = $orgParam;
        } else {
            $organization = Organization::where('slug', $orgParam)
                ->orWhere('id', $orgParam)
                ->first();
        }

        if (!$organization) {
            abort(404, 'The requested organization was not found.');
        }

        // 1. Super Admin Override
        if ($user->hasRole('super-admin')) {
            $membership = $organization->getMembership($user);
            $this->context->setContext($organization, $membership);
            view()->share('currentOrganization', $organization);
            view()->share('currentMembership', $membership);

            return $next($request);
        }

        // 2. Resolve Authenticated User Membership
        /** @var OrganizationMembership|null $membership */
        $membership = OrganizationMembership::where('organization_id', $organization->id)
            ->where('user_id', $user->id)
            ->first();

        if (!$membership) {
            abort(403, 'Unauthorized. You are not a member of this organization.');
        }

        if ($membership->status !== MembershipStatus::Active) {
            $statusLabel = is_object($membership->status) ? $membership->status->value : $membership->status;
            abort(403, "Your membership in this organization is currently {$statusLabel}.");
        }

        // 3. Organization Portal Admin Access Rule (Must be Owner, Admin, or Coordinator)
        if (!$membership->hasManagementAuthority()) {
            abort(403, 'Access denied. Candidate members do not have management access to the Organization Portal.');
        }

        // 4. Role Hierarchy Check if specific role required
        if ($requiredRole === 'owner' && !$membership->isOwner()) {
            abort(403, 'This action requires Organization Owner authority.');
        }

        if ($requiredRole === 'admin' && !in_array($membership->role, [MembershipRole::Owner, MembershipRole::Admin], true)) {
            abort(403, 'This action requires Organization Admin or Owner authority.');
        }

        // 5. Active Organization Lifecycle Check for State Mutations (POST/PUT/DELETE)
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            if ($organization->status !== OrganizationStatus::Active) {
                $orgStatus = is_object($organization->status) ? $organization->status->value : $organization->status;
                abort(403, "Cannot perform mutations. Organization is currently {$orgStatus}.");
            }
        }

        $this->context->setContext($organization, $membership);
        view()->share('currentOrganization', $organization);
        view()->share('currentMembership', $membership);

        return $next($request);
    }
}
