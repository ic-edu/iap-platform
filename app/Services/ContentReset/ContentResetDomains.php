<?php

namespace App\Services\ContentReset;

class ContentResetDomains
{
    // Reset Modes
    public const MODE_REFRESH = 'refresh';
    public const MODE_HARD_RESET = 'hard_reset';

    // Resettable Content Domains (Allowlist)
    public const DRAFT_ASSESSMENTS = 'draft_assessments';
    public const ARCHIVED_ASSESSMENTS = 'archived_assessments';
    public const SOFT_DELETED_ASSESSMENT_CONTENT = 'soft_deleted_assessment_content';
    public const ASSESSMENT_TEST_SECTIONS = 'assessment_test_sections';
    public const ASSESSMENT_TEST_QUESTION_BINDINGS = 'assessment_test_question_bindings';
    public const QUESTION_CONTENT = 'question_content';
    public const QUESTION_CHOICES = 'question_choices';
    public const QUESTION_FILES = 'question_files';
    public const AUDIO_GROUP_CONTENT = 'audio_group_content';
    public const PASSAGE_GROUP_CONTENT = 'passage_group_content';
    public const TRANSIENT_UAT_ATTEMPTS = 'transient_uat_attempts';
    public const TRANSIENT_UAT_ANSWERS = 'transient_uat_answers';

    // Absolutely Protected Domains (Deny-List)
    public const FINANCE = 'finance';
    public const HR_PEOPLE = 'hr_people';
    public const USERS = 'users';
    public const ROLES = 'roles';
    public const PERMISSIONS = 'permissions';
    public const AUDIT_LOGS = 'audit_logs';
    public const ACTIVITY_LOGS = 'activity_logs';
    public const PLATFORM_ENGINE = 'platform_engine';
    public const UI_UX = 'ui_ux';
    public const ROUTES = 'routes';

    /**
     * Get all valid resettable domains.
     *
     * @return array<string>
     */
    public static function allResettableDomains(): array
    {
        return [
            self::DRAFT_ASSESSMENTS,
            self::ARCHIVED_ASSESSMENTS,
            self::SOFT_DELETED_ASSESSMENT_CONTENT,
            self::ASSESSMENT_TEST_SECTIONS,
            self::ASSESSMENT_TEST_QUESTION_BINDINGS,
            self::QUESTION_CONTENT,
            self::QUESTION_CHOICES,
            self::QUESTION_FILES,
            self::AUDIO_GROUP_CONTENT,
            self::PASSAGE_GROUP_CONTENT,
            self::TRANSIENT_UAT_ATTEMPTS,
            self::TRANSIENT_UAT_ANSWERS,
        ];
    }

    /**
     * Get all absolutely protected domains.
     *
     * @return array<string>
     */
    public static function allProtectedDomains(): array
    {
        return [
            self::FINANCE,
            self::HR_PEOPLE,
            self::USERS,
            self::ROLES,
            self::PERMISSIONS,
            self::AUDIT_LOGS,
            self::ACTIVITY_LOGS,
            self::PLATFORM_ENGINE,
            self::UI_UX,
            self::ROUTES,
        ];
    }

    /**
     * Check if a given domain is valid and resettable.
     */
    public static function isResettable(string $domain): bool
    {
        return in_array($domain, self::allResettableDomains(), true);
    }

    /**
     * Check if a domain is hard-protected.
     */
    public static function isProtected(string $domain): bool
    {
        return in_array($domain, self::allProtectedDomains(), true);
    }
}
