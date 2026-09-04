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
use App\Modules\Organization\Models\OrganizationMembership;
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
     * Display Super Admin Organizations Directory.
     */
    public function index(Request $request): View
    {
        $search = $request->query('search');
        $statusFilter = $request->query('status', 'all');
        $typeFilter = $request->query('type', 'all');

        $organizations = Organization::query()
            ->withCount(['memberships', 'activeMemberships', 'groups'])
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
     * Show Organization Creation Form.
     */
    public function create(): View
    {
        $types = OrganizationType::cases();

        return view('organization::admin.organizations.create', compact('types'));
    }

    /**
     * Store new Organization and optionally invite initial owner/coordinator.
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
            'coordinator_email' => ['nullable', 'email', 'max:255'],
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
            'status'            => OrganizationStatus::Active,
            'created_by'        => Auth::id(),
        ]);

        ActivityLogger::log(
            action: 'ORG_CREATED',
            description: "Super Admin created organization '{$organization->name}'",
            subject: $organization,
            properties: [
                'organization_id' => $organization->id,
                'name'            => $organization->name,
                'slug'            => $organization->slug,
                'type'            => $organization->organization_type->value,
            ]
        );

        $invitationNotice = '';
        if (!empty($validated['coordinator_email'])) {
            $invitationData = OrganizationInvitation::createWithToken([
                'organization_id' => $organization->id,
                'email'           => $validated['coordinator_email'],
                'intended_role'   => MembershipRole::Owner,
                'invited_by'      => Auth::id(),
                'expires_at'      => now()->addDays(7),
            ]);

            $plainToken = $invitationData['token'];
            $acceptUrl = route('invitations.accept', ['token' => $plainToken]);
            $invitationNotice = " Invitation link generated for primary coordinator: {$acceptUrl}";
        }

        return redirect()->route('admin.organizations.index')
            ->with('status', "Organization '{$organization->name}' created successfully.{$invitationNotice}");
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
     * Update Organization metadata and status.
     */
    public function update(Request $request, Organization $organization): RedirectResponse
    {
        $validated = $request->validate([
            'name'              => ['required', 'string', 'max:255'],
            'organization_type' => ['required', Rule::in(OrganizationType::values())],
            'status'            => ['required', Rule::in(OrganizationStatus::values())],
            'email'             => ['nullable', 'email', 'max:255'],
            'phone'             => ['nullable', 'string', 'max:50'],
            'website'           => ['nullable', 'url', 'max:255'],
            'address'           => ['nullable', 'string', 'max:500'],
            'city'              => ['nullable', 'string', 'max:100'],
            'province'          => ['nullable', 'string', 'max:100'],
            'country'           => ['nullable', 'string', 'max:100'],
            'postal_code'       => ['nullable', 'string', 'max:20'],
        ]);

        $oldStatus = $organization->status;
        $organization->update($validated);

        if ($oldStatus !== $organization->status) {
            $action = match ($organization->status) {
                OrganizationStatus::Suspended => 'ORG_SUSPENDED',
                OrganizationStatus::Archived => 'ORG_ARCHIVED',
                default => 'ORG_UPDATED',
            };

            ActivityLogger::log(
                action: $action,
                description: "Organization '{$organization->name}' status changed from {$oldStatus->value} to {$organization->status->value}",
                subject: $organization,
                properties: [
                    'organization_id' => $organization->id,
                    'old_status'      => $oldStatus->value,
                    'new_status'      => $organization->status->value,
                ]
            );
        } else {
            ActivityLogger::log(
                action: 'ORG_UPDATED',
                description: "Super Admin updated organization '{$organization->name}'",
                subject: $organization,
                properties: ['organization_id' => $organization->id]
            );
        }

        return redirect()->route('admin.organizations.index')
            ->with('status', "Organization '{$organization->name}' updated successfully.");
    }

    /**
     * Toggle Organization status (Quick active/suspended).
     */
    public function toggleStatus(Request $request, Organization $organization): RedirectResponse
    {
        $newStatus = $organization->isActive() ? OrganizationStatus::Suspended : OrganizationStatus::Active;
        $organization->update(['status' => $newStatus]);

        $action = $newStatus === OrganizationStatus::Suspended ? 'ORG_SUSPENDED' : 'ORG_UPDATED';

        ActivityLogger::log(
            action: $action,
            description: "Organization '{$organization->name}' status toggled to {$newStatus->value}",
            subject: $organization,
            properties: [
                'organization_id' => $organization->id,
                'status'          => $newStatus->value,
            ]
        );

        return back()->with('status', "Organization '{$organization->name}' is now {$newStatus->value}.");
    }
}
