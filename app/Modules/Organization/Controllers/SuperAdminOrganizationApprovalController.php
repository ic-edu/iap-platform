<?php

namespace App\Modules\Organization\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Organization\Enums\OrganizationStatus;
use App\Modules\Organization\Models\Organization;
use App\Modules\Organization\Models\OrganizationGroup;
use App\Notifications\EnterpriseSystemNotification;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SuperAdminOrganizationApprovalController extends Controller
{
    /**
     * Display Organization Governance & Approval Center for Super Admin.
     */
    public function index(Request $request): View
    {
        $pendingOrganizations = Organization::query()
            ->where('status', OrganizationStatus::Pending)
            ->with(['submitter'])
            ->latest('submitted_at')
            ->get();

        $revisionOrganizations = Organization::query()
            ->where('status', OrganizationStatus::NeedsRevision)
            ->with(['submitter', 'reviewer'])
            ->latest('reviewed_at')
            ->get();

        $rejectedOrganizations = Organization::query()
            ->where('status', OrganizationStatus::Rejected)
            ->with(['submitter', 'reviewer'])
            ->latest('reviewed_at')
            ->get();

        $activeOrganizations = Organization::query()
            ->whereIn('status', [OrganizationStatus::Active, OrganizationStatus::Suspended, OrganizationStatus::Archived])
            ->with(['submitter', 'reviewer'])
            ->withCount(['memberships', 'groups'])
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $pendingGroups = OrganizationGroup::query()
            ->where('approval_status', 'pending')
            ->with(['organization', 'submitter', 'creator'])
            ->latest('submitted_at')
            ->get();

        return view('organization::admin.approvals.organizations', compact(
            'pendingOrganizations',
            'revisionOrganizations',
            'rejectedOrganizations',
            'activeOrganizations',
            'pendingGroups'
        ));
    }

    /**
     * Approve a pending organization.
     */
    public function approveOrganization(Request $request, Organization $organization): RedirectResponse
    {
        $organization->update([
            'status'           => OrganizationStatus::Active,
            'reviewed_by'      => Auth::id(),
            'reviewed_at'      => now(),
            'revision_note'    => null,
            'rejection_reason' => null,
        ]);

        ActivityLogger::log(
            action: 'ORG_APPROVED',
            description: "Super Admin approved organization '{$organization->name}'",
            subject: $organization,
            properties: [
                'organization_id' => $organization->id,
                'reviewed_by'     => Auth::id(),
            ]
        );

        $recipient = $organization->submitter ?? $organization->creator;
        if ($recipient) {
            try {
                $recipient->notify(new EnterpriseSystemNotification(
                    title: 'Organization Approved',
                    message: "Organization '{$organization->name}' has been approved by Super Admin {$request->user()?->name} and is now Active.",
                    type: 'ORGANIZATION_APPROVED',
                    priority: 'HIGH',
                    entityType: 'organization',
                    entityId: (string) $organization->id,
                    targetUrl: route('admin.organizations.index')
                ));
            } catch (\Throwable $e) {
                // Silently handle in dev
            }
        }

        return back()->with('status', "Organization '{$organization->name}' has been approved and is now Active.");
    }

    /**
     * Return an organization for revision.
     */
    public function returnRevision(Request $request, Organization $organization): RedirectResponse
    {
        $validated = $request->validate([
            'revision_note' => ['required', 'string', 'max:1000'],
        ]);

        $organization->update([
            'status'        => OrganizationStatus::NeedsRevision,
            'reviewed_by'   => Auth::id(),
            'reviewed_at'   => now(),
            'revision_note' => $validated['revision_note'],
        ]);

        ActivityLogger::log(
            action: 'ORG_RETURNED_FOR_REVISION',
            description: "Super Admin returned organization '{$organization->name}' for revision: {$validated['revision_note']}",
            subject: $organization,
            properties: [
                'organization_id' => $organization->id,
                'revision_note'   => $validated['revision_note'],
            ]
        );

        $recipient = $organization->submitter ?? $organization->creator;
        if ($recipient) {
            try {
                $recipient->notify(new EnterpriseSystemNotification(
                    title: 'Organization Needs Revision',
                    message: "Super Admin {$request->user()?->name} requested revisions for '{$organization->name}': {$validated['revision_note']}",
                    type: 'ORGANIZATION_NEEDS_REVISION',
                    priority: 'HIGH',
                    entityType: 'organization',
                    entityId: (string) $organization->id,
                    targetUrl: route('admin.organizations.edit', $organization->id)
                ));
            } catch (\Throwable $e) {
                // Silently handle in dev
            }
        }

        return back()->with('status', "Organization '{$organization->name}' returned to Registration Admin for revision.");
    }

    /**
     * Reject an organization.
     */
    public function rejectOrganization(Request $request, Organization $organization): RedirectResponse
    {
        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ]);

        $organization->update([
            'status'           => OrganizationStatus::Rejected,
            'reviewed_by'      => Auth::id(),
            'reviewed_at'      => now(),
            'rejection_reason' => $validated['rejection_reason'],
        ]);

        ActivityLogger::log(
            action: 'ORG_REJECTED',
            description: "Super Admin rejected organization '{$organization->name}': {$validated['rejection_reason']}",
            subject: $organization,
            properties: [
                'organization_id'  => $organization->id,
                'rejection_reason' => $validated['rejection_reason'],
            ]
        );

        $recipient = $organization->submitter ?? $organization->creator;
        if ($recipient) {
            try {
                $recipient->notify(new EnterpriseSystemNotification(
                    title: 'Organization Rejected',
                    message: "Super Admin {$request->user()?->name} rejected organization '{$organization->name}'. Reason: {$validated['rejection_reason']}",
                    type: 'ORGANIZATION_REJECTED',
                    priority: 'HIGH',
                    entityType: 'organization',
                    entityId: (string) $organization->id,
                    targetUrl: route('admin.organizations.index')
                ));
            } catch (\Throwable $e) {
                // Silently handle in dev
            }
        }

        return back()->with('status', "Organization '{$organization->name}' has been rejected.");
    }

    /**
     * Approve archive request.
     */
    public function approveArchive(Request $request, Organization $organization): RedirectResponse
    {
        $organization->update([
            'status'      => OrganizationStatus::Archived,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
            'archived_at' => now(),
        ]);

        ActivityLogger::log(
            action: 'ORG_ARCHIVED',
            description: "Super Admin archived organization '{$organization->name}'",
            subject: $organization,
            properties: [
                'organization_id' => $organization->id,
            ]
        );

        return back()->with('status', "Organization '{$organization->name}' has been archived.");
    }

    /**
     * Approve a pending institutional group.
     */
    public function approveGroup(Request $request, OrganizationGroup $group): RedirectResponse
    {
        $group->update([
            'approval_status' => 'approved',
            'is_active'       => true,
            'reviewed_by'     => Auth::id(),
            'reviewed_at'     => now(),
        ]);

        ActivityLogger::log(
            action: 'ORG_GROUP_APPROVED',
            description: "Super Admin approved group '{$group->name}' for organization '{$group->organization?->name}'",
            subject: $group,
            properties: [
                'group_id'        => $group->id,
                'organization_id' => $group->organization_id,
            ]
        );

        return back()->with('status', "Group '{$group->name}' has been approved and activated.");
    }
}
