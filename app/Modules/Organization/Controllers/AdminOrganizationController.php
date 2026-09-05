<?php

namespace App\Modules\Organization\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Organization\Enums\InvitationStatus;
use App\Modules\Organization\Enums\MembershipRole;
use App\Modules\Organization\Enums\OrganizationStatus;
use App\Modules\Organization\Enums\OrganizationType;
use App\Modules\Organization\Models\Organization;
use App\Modules\Organization\Models\OrganizationInvitation;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminOrganizationController extends Controller
{
    /**
     * Display Organization Operations Directory for Registration Admin / Admin.
     */
    public function index(Request $request): View
    {
        $search = $request->query('search');
        $statusFilter = $request->query('status', 'all');
        $typeFilter = $request->query('type', 'all');

        $organizations = Organization::query()
            ->withCount(['memberships', 'activeMemberships', 'groups'])
            ->with(['submitter', 'reviewer'])
            ->when($statusFilter && $statusFilter !== 'all', fn($q) => $q->where('status', $statusFilter))
            ->when($typeFilter && $typeFilter !== 'all', fn($q) => $q->where('organization_type', $typeFilter))
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $types = OrganizationType::cases();
        $statuses = OrganizationStatus::cases();

        return view('organization::admin.organizations.index', compact(
            'organizations',
            'search',
            'statusFilter',
            'typeFilter',
            'types',
            'statuses'
        ));
    }

    /**
     * Show Organization Creation Form (Operational RA).
     */
    public function create(): View
    {
        $types = OrganizationType::cases();

        return view('organization::admin.organizations.create', compact('types'));
    }

    /**
     * Store new Organization in Pending status awaiting Super Admin approval.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'              => ['required', 'string', 'max:255'],
            'organization_type' => ['required', Rule::in(OrganizationType::values())],
            'email'             => ['nullable', 'email', 'max:255'],
            'phone'             => ['nullable', 'string', 'max:50'],
            'website'           => ['nullable', 'url', 'max:255'],
            'address'           => ['nullable', 'string', 'max:500'],
            'city'              => ['nullable', 'string', 'max:100'],
            'province'          => ['nullable', 'string', 'max:100'],
            'country'           => ['nullable', 'string', 'max:100'],
            'postal_code'       => ['nullable', 'string', 'max:20'],
        ]);

        $baseSlug = Str::slug($validated['name']);
        $slug = $baseSlug;
        $counter = 1;
        while (Organization::where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        $organization = Organization::create([
            'name'              => $validated['name'],
            'slug'              => $slug,
            'organization_type' => $validated['organization_type'],
            'email'             => $validated['email'] ?? null,
            'phone'             => $validated['phone'] ?? null,
            'website'           => $validated['website'] ?? null,
            'address'           => $validated['address'] ?? null,
            'city'              => $validated['city'] ?? null,
            'province'          => $validated['province'] ?? null,
            'country'           => $validated['country'] ?? 'Indonesia',
            'postal_code'       => $validated['postal_code'] ?? null,
            'status'            => OrganizationStatus::Pending,
            'created_by'        => Auth::id(),
            'submitted_by'      => Auth::id(),
            'submitted_at'      => now(),
        ]);

        ActivityLogger::log(
            action: 'ORG_CREATED',
            description: "Registration Admin submitted organization '{$organization->name}' for Super Admin approval",
            subject: $organization,
            properties: [
                'organization_id' => $organization->id,
                'name'            => $organization->name,
                'slug'            => $organization->slug,
                'type'            => $organization->organization_type->value,
                'status'          => $organization->status->value,
            ]
        );

        return redirect()->route('admin.organizations.index')
            ->with('status', "Organization '{$organization->name}' created and submitted for Super Admin approval.");
    }

    /**
     * Show Organization Edit Form.
     */
    public function edit(Organization $organization): View
    {
        $types = OrganizationType::cases();
        $statuses = OrganizationStatus::cases();

        return view('organization::admin.organizations.edit', compact('organization', 'types', 'statuses'));
    }

    /**
     * Update Organization metadata.
     */
    public function update(Request $request, Organization $organization): RedirectResponse
    {
        $validated = $request->validate([
            'name'              => ['required', 'string', 'max:255'],
            'organization_type' => ['required', Rule::in(OrganizationType::values())],
            'email'             => ['nullable', 'email', 'max:255'],
            'phone'             => ['nullable', 'string', 'max:50'],
            'website'           => ['nullable', 'url', 'max:255'],
            'address'           => ['nullable', 'string', 'max:500'],
            'city'              => ['nullable', 'string', 'max:100'],
            'province'          => ['nullable', 'string', 'max:100'],
            'country'           => ['nullable', 'string', 'max:100'],
            'postal_code'       => ['nullable', 'string', 'max:20'],
            'resubmit'          => ['nullable', 'boolean'],
        ]);

        $resubmit = $request->boolean('resubmit') || $organization->needsRevision();

        $updateData = [
            'name'              => $validated['name'],
            'organization_type' => $validated['organization_type'],
            'email'             => $validated['email'] ?? null,
            'phone'             => $validated['phone'] ?? null,
            'website'           => $validated['website'] ?? null,
            'address'           => $validated['address'] ?? null,
            'city'              => $validated['city'] ?? null,
            'province'          => $validated['province'] ?? null,
            'country'           => $validated['country'] ?? 'Indonesia',
            'postal_code'       => $validated['postal_code'] ?? null,
        ];

        if ($resubmit) {
            $updateData['status'] = OrganizationStatus::Pending;
            $updateData['submitted_by'] = Auth::id();
            $updateData['submitted_at'] = now();
            $updateData['revision_note'] = null;
        }

        $organization->update($updateData);

        ActivityLogger::log(
            action: 'ORG_UPDATED',
            description: "Registration Admin updated organization '{$organization->name}'" . ($resubmit ? " and resubmitted for approval" : ""),
            subject: $organization,
            properties: [
                'organization_id' => $organization->id,
                'resubmitted'     => $resubmit,
            ]
        );

        $msg = $resubmit
            ? "Organization '{$organization->name}' updated and resubmitted for Super Admin approval."
            : "Organization '{$organization->name}' updated successfully.";

        return redirect()->route('admin.organizations.index')->with('status', $msg);
    }

    /**
     * Submit / Resubmit an Organization for Super Admin Approval.
     */
    public function submitForApproval(Request $request, Organization $organization): RedirectResponse
    {
        $organization->update([
            'status'        => OrganizationStatus::Pending,
            'submitted_by'  => Auth::id(),
            'submitted_at'  => now(),
            'revision_note' => null,
        ]);

        ActivityLogger::log(
            action: 'ORG_SUBMITTED_FOR_APPROVAL',
            description: "Registration Admin submitted organization '{$organization->name}' for approval",
            subject: $organization,
            properties: ['organization_id' => $organization->id]
        );

        return back()->with('status', "Organization '{$organization->name}' submitted for Super Admin approval.");
    }

    /**
     * Invite Primary Coordinator/Owner (Available ONLY when Organization is Active/Approved).
     */
    public function inviteCoordinator(Request $request, Organization $organization): RedirectResponse
    {
        if (! $organization->isActive()) {
            return back()->withErrors(['error' => 'Coordinator invitations can only be issued for Approved / Active organizations.']);
        }

        $validated = $request->validate([
            'coordinator_email' => ['required', 'email', 'max:255'],
        ]);

        $invitationData = OrganizationInvitation::createWithToken([
            'organization_id' => $organization->id,
            'email'           => $validated['coordinator_email'],
            'intended_role'   => MembershipRole::Owner,
            'invited_by'      => Auth::id(),
            'expires_at'      => now()->addDays(7),
        ]);

        $plainToken = $invitationData['token'];
        $acceptUrl = route('invitations.accept', ['token' => $plainToken]);

        ActivityLogger::log(
            action: 'ORG_COORDINATOR_INVITED',
            description: "Invited primary coordinator '{$validated['coordinator_email']}' for '{$organization->name}'",
            subject: $organization,
            properties: [
                'organization_id' => $organization->id,
                'email'           => $validated['coordinator_email'],
            ]
        );

        return back()->with('status', "Primary coordinator invitation link generated: {$acceptUrl}");
    }

    /**
     * Suspend an active organization.
     */
    public function suspend(Request $request, Organization $organization): RedirectResponse
    {
        if (! $organization->isActive()) {
            return back()->withErrors(['error' => 'Only active organizations can be suspended.']);
        }

        $organization->update(['status' => OrganizationStatus::Suspended]);

        ActivityLogger::log(
            action: 'ORG_SUSPENDED',
            description: "Registration Admin suspended organization '{$organization->name}'",
            subject: $organization,
            properties: ['organization_id' => $organization->id]
        );

        return back()->with('status', "Organization '{$organization->name}' has been suspended.");
    }

    /**
     * Reactivate a suspended organization.
     */
    public function activate(Request $request, Organization $organization): RedirectResponse
    {
        if (! $organization->isSuspended()) {
            return back()->withErrors(['error' => 'Only suspended organizations can be reactivated directly.']);
        }

        $organization->update(['status' => OrganizationStatus::Active]);

        ActivityLogger::log(
            action: 'ORG_ACTIVATED',
            description: "Registration Admin reactivated organization '{$organization->name}'",
            subject: $organization,
            properties: ['organization_id' => $organization->id]
        );

        return back()->with('status', "Organization '{$organization->name}' has been reactivated.");
    }

    /**
     * Toggle Organization status (backward compatibility / quick toggle).
     */
    public function toggleStatus(Request $request, Organization $organization): RedirectResponse
    {
        if ($organization->isActive()) {
            return $this->suspend($request, $organization);
        }

        if ($organization->isSuspended()) {
            return $this->activate($request, $organization);
        }

        return back()->withErrors(['error' => 'Status cannot be toggled while in ' . $organization->status->label() . ' state.']);
    }
}
