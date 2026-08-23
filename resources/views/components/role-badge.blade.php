@props(['role' => 'student'])

@php
    $rawRole = (string)$role;
    $roleSlug = strtolower(trim(str_replace(['_', ' '], '-', $rawRole)));
    
    $badgeClass = match($roleSlug) {
        'super-admin', 'superadmin' => 'role-badge--super-admin',
        'admin', 'administrator' => 'role-badge--admin',
        'teacher', 'author' => 'role-badge--teacher',
        'finance' => 'role-badge--finance',
        'repository-manager', 'repository_manager', 'repomanager' => 'role-badge--repository-manager',
        default => 'role-badge--student',
    };

    $displayLabel = match($roleSlug) {
        'super-admin', 'superadmin' => 'SUPER ADMIN',
        'admin', 'administrator' => 'ADMIN',
        'teacher', 'author' => 'TEACHER',
        'finance' => 'FINANCE',
        'repository-manager', 'repository_manager', 'repomanager' => 'REPOSITORY MANAGER',
        default => strtoupper($rawRole ?: 'STUDENT'),
    };
@endphp

<span {{ $attributes->merge(['class' => 'role-badge ' . $badgeClass]) }}>{{ $slot->isEmpty() ? $displayLabel : $slot }}</span>
