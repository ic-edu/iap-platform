# iC.edu Assessment Platform (IAP) — Visual Theme Baseline & Architecture Freeze

## 1. Purpose & Freeze Directive

This document defines the **authoritative visual design system, semantic token contract, and theme standards** for the iC.edu Assessment Platform (IAP).

> [!IMPORTANT]
> **VISUAL BASELINE FREEZE**:  
> No future feature or patch sprint may silently introduce hardcoded colors, bypass semantic theme variables, or alter the approved contrast contracts in Light or Dark Mode. All UI modifications must adhere to the semantic token architecture and pass the automated theme regression test suite (`tests/Feature/GlobalThemeConsistencyTest.php`, `tests/Feature/RegularAdminThemeConsistencyTest.php`, and `tests/Feature/TeacherLightThemeConsistencyTest.php`).

---

## 2. Global Semantic Token Architecture

All UI components across IAP reference CSS custom properties defined in `resources/css/app.css` under `:root`, `html.light` / `html[data-theme="light"]`, and `html.dark` / `html[data-theme="dark"]`.

### Standardized Token Reference

| Category | Token Name | Light Theme Value | Dark Theme Value |
| :--- | :--- | :--- | :--- |
| **Surfaces** | `--app-bg` | `#f4f7fb` | `#0b0f19` |
| | `--surface-base` | `#ffffff` | `#0f172a` |
| | `--surface-raised` | `#ffffff` | `#1e293b` |
| | `--surface-elevated` | `#ffffff` | `#1e293b` |
| | `--surface-muted` | `#eef2f6` | `#020617` |
| | `--surface-sidebar` | `#fcfdfe` | `#0b0f19` |
| **Text** | `--text-primary` | `#0f172a` (Deep Slate) | `#f8fafc` (Near White) |
| | `--text-secondary` | `#334155` (Slate) | `#94a3b8` (Slate 400) |
| | `--text-muted` | `#64748b` (Slate 500) | `#64748b` (Slate 500) |
| | `--text-disabled` | `#94a3b8` | `#475569` |
| | `--text-inverse` | `#ffffff` | `#0f172a` |
| **Borders** | `--border-default` | `#e2e8f0` (Slate 200) | `#1e293b` (Slate 800) |
| | `--border-subtle` | `#f1f5f9` (Slate 100) | `#1e293b` |
| | `--border-strong` | `#cbd5e1` (Slate 300) | `#334155` (Slate 700) |
| **Interaction** | `--primary` | `#4f46e5` (Indigo 600) | `#6366f1` (Indigo 500) |
| | `--primary-hover` | `#4338ca` (Indigo 700) | `#4f46e5` (Indigo 600) |
| | `--primary-foreground` | `#ffffff` | `#ffffff` |
| | `--secondary` | `#f1f5f9` | `#1e293b` |
| | `--focus-ring` | `rgba(79, 70, 229, 0.3)` | `rgba(99, 102, 241, 0.4)` |
| **Status** | `--success` | `#047857` (Emerald 700) | `#10b981` (Emerald 500) |
| | `--success-surface` | `#ecfdf5` (Emerald 50) | `rgba(16, 185, 129, 0.15)` |
| | `--success-foreground` | `#047857` | `#34d399` |
| | `--warning` | `#92400e` (Amber 800) | `#f59e0b` (Amber 500) |
| | `--warning-surface` | `#fffbeb` (Amber 50) | `rgba(245, 158, 11, 0.15)` |
| | `--warning-foreground` | `#92400e` | `#fbbf24` |
| | `--danger` | `#be123c` (Rose 700) | `#f43f5e` (Rose 500) |
| | `--danger-surface` | `#fff1f2` (Rose 50) | `rgba(244, 63, 94, 0.15)` |
| | `--danger-foreground` | `#be123c` | `#fb7185` |
| | `--info` | `#0369a1` (Sky 700) | `#38bdf8` (Sky 400) |
| | `--info-surface` | `#f0f9ff` (Sky 50) | `rgba(56, 189, 248, 0.15)` |
| | `--info-foreground` | `#0369a1` | `#38bdf8` |
| **Metrics / KPI** | `--metric-primary` | `#4338ca` (Deep Indigo) | `#818cf8` (Bright Indigo) |
| | `--metric-success` | `#047857` (Forest Emerald) | `#34d399` (Bright Emerald) |
| | `--metric-warning` | `#b45309` (Warm Amber) | `#fbbf24` (Bright Amber) |
| | `--metric-danger` | `#be123c` (Deep Rose) | `#fb7185` (Bright Rose) |
| | `--metric-info` | `#0369a1` (Deep Sky) | `#38bdf8` (Bright Sky) |
| | `--metric-neutral` | `#0f172a` (Deep Slate) | `#ffffff` (White) |

---

## 3. KPI & Metric Value Contract

Every dashboard metric adheres to the following contract:

1. **Card Container**: Uses `.kpi-card`, `.metric-card`, or `.gov-card` with `background-color: var(--surface-base)` and explicit border `var(--border-default)`.
2. **Numeric Values**: Must use `.metric-value`, `.kpi-value`, `.stat-value`, or responsive Tailwind utilities (`text-slate-900 dark:text-white` or `text-*-600 dark:text-*-400`).
3. **Labels & Subtitles**: Must use `.metric-label` / `.metric-sub` (`color: var(--text-muted)`) or `text-slate-500 dark:text-slate-400`.
4. **Safety Rule**: Numeric KPI values MUST NEVER use un-scoped `text-white` inside card surfaces, preventing invisible text in Light Theme.

---

## 4. Status & Role Badge Contract

### Reusable Blade Components
* **Security Roles**: `<x-role-badge :role="$role" />`
  * Renders `.role-badge.role-badge--{slug}`.
  * Standardized across `super-admin`, `admin`, `teacher`, `finance`, `student`, and `repository-manager`.
* **Workflow & Status Chips**: `<x-status-badge :status="$status" />`
  * Renders `.ra-status-badge.ra-status--{slug}`.
  * Standardized across `ready-for-assignment`, `assigned`, `placement-required`, `waiting-review`, `waiting-approval`, `active`, `approved`, `completed`, `passed`, `rejected`, `failed`, `inactive`, and `waived`.

### Badge Color Specifications
* **Light Theme**: Solid pastel background (`#ecfdf5`, `#eef2ff`, `#fffbeb`, `#fff7ed`, `#f1f5f9`, `#fff1f2`, `#f0f9ff`) with deep 700/800-shade readable text (`#047857`, `#4338ca`, `#92400e`, `#c2410c`, `#334155`, `#be123c`, `#0369a1`) and matching 200/300-shade borders.
* **Dark Theme**: Translucent dark background (`rgba(..., 0.15)`) with bright 300/400-shade text (`#34d399`, `#818cf8`, `#fbbf24`, `#fb923c`, `#cbd5e1`, `#fb7185`, `#38bdf8`) and matching border (`rgba(..., 0.35)`).

---

## 5. CTA, Hero, & Card Contracts

1. **Teacher Workspace**:
   * Hero uses `.tw-hero` with dual-mode gradients and high-contrast typography.
   * Action banners and draft cards use `.tw-banner`, `.tw-card`, and `.tw-kpi` — NEVER hardcoded dark navy rectangles inside light layouts.
2. **Governance Workspaces**:
   * Hero uses `.gov-hero`, `.gov-hero-indigo`, `.gov-hero-purple`, or `.gov-hero-cyan`.
   * Cards use `.gov-card`, `.gov-stats-strip`, and `.gov-btn-secondary`.

---

## 6. Prohibited Visual Patterns

Developers and AI agents are STRICTLY PROHIBITED from introducing:
1. **Unscoped `text-white`**: Placing `text-white` on surfaces without declaring dark-theme conditional scope (i.e. use `text-slate-900 dark:text-white` instead).
2. **Neon Colors in Light Theme**: Using 300-shade or 400-shade Tailwind text colors (e.g. `text-emerald-400`, `text-amber-300`, `text-rose-400`) directly on light surfaces. Always use `text-{color}-600 dark:text-{color}-400`.
3. **Hardcoded Inline Hex Colors**: Writing `style="color: #ffffff"` or `style="background: #1e293b"` without theme variables or matching light-theme overrides.
4. **Accidental White Containers in Dark Theme**: Creating components with static `bg-white` or `bg-slate-50` that lack `dark:bg-slate-900` or `var(--surface-base)`.
5. **Fallback Gray-on-Gray Badges**: Relying on unhandled `@else bg-slate-800 text-slate-400` which washes out in Light Theme.

---

## 7. Protected Dashboard Surfaces

The following views are explicitly protected by automated theme regression tests:
1. **Teacher Dashboard**: `resources/views/teacher/dashboard.blade.php`
2. **Regular Admin Operational Dashboard**: `resources/views/admin/operational_dashboard.blade.php`
3. **Super Admin Executive Dashboard**: `resources/views/admin/dashboard.blade.php`
4. **User & Access Control Management**: `resources/views/admin/users/index.blade.php`
5. **Academic Operations (Applications, Courses, Monitoring, Enrollments, Teacher Assignments)**: `resources/views/admin/academic_operations/*.blade.php`
6. **Repository Manager Command Center & IRQA Analytics**: `resources/views/admin/repository_manager/*.blade.php`, `resources/views/admin/academic_library/*.blade.php`
7. **Finance Dashboard**: `resources/views/finance/dashboard.blade.php`
8. **Student Portal & Exam Review**: `app/Modules/Assessment/Views/candidate/*.blade.php`
9. **Operational Reporting & Candidate Status**: `app/Modules/Reporting/Views/index.blade.php`
10. **Governance Approval Centers**: `resources/views/admin/approvals/*.blade.php`

---

## 8. Regression Prevention Workflow

Before committing any future UI changes:
1. Run `./vendor/bin/pest tests/Feature/GlobalThemeConsistencyTest.php tests/Feature/RegularAdminThemeConsistencyTest.php tests/Feature/TeacherLightThemeConsistencyTest.php`
2. Run `npm run build` to ensure stylesheet transformations succeed.
3. Run the full `./vendor/bin/pest` test suite.
