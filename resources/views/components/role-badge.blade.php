@props(['role' => null])

@php
    $rawRole = (string)($role ?? '');
    $roleSlug = strtolower(trim(str_replace(['_', ' '], '-', $rawRole)));
    
    $badgeClass = match($roleSlug) {
        'super-admin', 'superadmin' => 'role-badge--super-admin',
        'admin', 'administrator' => 'role-badge--admin',
        'teacher', 'author' => 'role-badge--teacher',
        'finance' => 'role-badge--finance',
        'repository-manager', 'repository_manager', 'repomanager' => 'role-badge--repository-manager',
        'student', 'candidate' => 'role-badge--student',
        'organization-coordinator', 'coordinator' => 'role-badge--organization-coordinator',
        'unassigned', 'none', '' => 'role-badge--unassigned',
        default => 'role-badge--unassigned',
    };

    $displayLabel = match($roleSlug) {
        'super-admin', 'superadmin' => 'SUPER ADMIN',
        'admin', 'administrator' => 'ADMIN',
        'teacher', 'author' => 'TEACHER',
        'finance' => 'FINANCE',
        'repository-manager', 'repository_manager', 'repomanager' => 'REPOSITORY MANAGER',
        'student', 'candidate' => 'STUDENT',
        'organization-coordinator', 'coordinator' => 'ORGANIZATION COORDINATOR',
        'unassigned', 'none', '' => 'UNASSIGNED',
        default => strtoupper($rawRole),
    };
@endphp

<span {{ $attributes->merge(['class' => 'role-badge ' . $badgeClass]) }}>{{ $slot->isEmpty() ? $displayLabel : $slot }}</span>
