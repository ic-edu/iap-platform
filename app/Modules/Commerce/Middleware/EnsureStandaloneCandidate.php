<?php

namespace App\Modules\Commerce\Middleware;

use App\Modules\Organization\Enums\MembershipRole;
use App\Modules\Organization\Enums\MembershipStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStandaloneCandidate
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            $isInstitutionalCandidate = $user->organizationMemberships()
                ->where('status', MembershipStatus::Active->value)
                ->where('role', MembershipRole::Member->value)
                ->whereHas('organization', fn($q) => $q->where('status', 'active'))
                ->exists();

            if ($isInstitutionalCandidate) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'error' => 'Candidate members affiliated with an active institution receive assessment seats via institutional coordinator. Individual self-purchases are restricted.',
                    ], 403);
                }

                return redirect()->route('candidate.portal')
                    ->with('error', 'Candidate members affiliated with an active institution receive assessment seats via institutional coordinator. Individual self-purchases are restricted.');
            }
        }

        return $next($request);
    }
}
