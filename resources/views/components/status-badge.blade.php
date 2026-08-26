@props(['status' => 'pending'])

@php
    $rawStatus = is_object($status) ? ($status->value ?? (string)$status) : (string)$status;
    $statusSlug = strtolower(trim(str_replace(['_', ' '], '-', $rawStatus)));

    $badgeClass = match($statusSlug) {
        'ready-for-assignment', 'ready_for_assignment', 'ready-assignment' => 'ra-status--ready-for-assignment',
        'assigned' => 'ra-status--assigned',
        'placement-required', 'placement_required' => 'ra-status--placement-required',
        'waiting-review', 'waiting_review', 'waiting-approval', 'waiting_approval', 'pending-approval', 'pending_approval' => 'ra-status--waiting-review',
        'active', 'approved' => 'ra-status--active',
        'completed', 'passed' => 'ra-status--completed',
        'rejected', 'failed' => 'ra-status--rejected',
        'inactive' => 'ra-status--inactive',
        'waived' => 'ra-status--waived',
        default => 'ra-status--awaiting-assignment',
    };

    $displayLabel = match($statusSlug) {
        'ready-for-assignment', 'ready_for_assignment', 'ready-assignment' => 'READY FOR ASSIGNMENT',
        'assigned' => 'ASSIGNED',
        'placement-required', 'placement_required' => 'PLACEMENT REQUIRED',
        'waiting-review', 'waiting_review' => 'WAITING REVIEW',
        'waiting-approval', 'waiting_approval' => 'WAITING APPROVAL',
        'pending-approval', 'pending_approval' => 'PENDING APPROVAL',
        'active' => 'ACTIVE',
        'approved' => 'APPROVED',
        'completed' => 'COMPLETED',
        'passed' => 'PASSED',
        'rejected' => 'REJECTED',
        'failed' => 'FAILED',
        'inactive' => 'INACTIVE',
        'waived' => 'WAIVED',
        default => strtoupper(str_replace(['_', '-'], ' ', $rawStatus ?: 'PENDING')),
    };
@endphp

<span {{ $attributes->merge(['class' => 'ra-status-badge ' . $badgeClass]) }}>{{ $slot->isEmpty() ? $displayLabel : $slot }}</span>
