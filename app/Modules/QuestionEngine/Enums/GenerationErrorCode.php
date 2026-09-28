<?php

namespace App\Modules\QuestionEngine\Enums;

enum GenerationErrorCode: string
{
    case ProviderUnavailable = 'provider_unavailable';
    case ProviderError = 'provider_error';
    case ProviderTimeout = 'provider_timeout';
    case InvalidProviderResponse = 'invalid_provider_response';
    case NormalizationFailed = 'normalization_failed';
    case SchemaValidationFailed = 'schema_validation_failed';
    case QualityGateFailed = 'quality_gate_failed';
    case MaterializationFailed = 'materialization_failed';
    case StandardMismatch = 'standard_mismatch';
    case SlotMismatch = 'slot_mismatch';
    case ConcurrencyLockFailed = 'concurrency_lock_failed';
    case MaxAttemptsExceeded = 'max_attempts_exceeded';

    public function label(): string
    {
        return match ($this) {
            self::ProviderUnavailable => 'Provider Unavailable',
            self::ProviderError => 'Provider Error',
            self::ProviderTimeout => 'Provider Timeout',
            self::InvalidProviderResponse => 'Invalid Provider Response',
            self::NormalizationFailed => 'Normalization Failed',
            self::SchemaValidationFailed => 'Schema Validation Failed',
            self::QualityGateFailed => 'Quality Gate Failed',
            self::MaterializationFailed => 'Materialization Failed',
            self::StandardMismatch => 'Standard Mismatch',
            self::SlotMismatch => 'Slot Mismatch',
            self::ConcurrencyLockFailed => 'Concurrency Lock Failed',
            self::MaxAttemptsExceeded => 'Maximum Attempts Exceeded',
        };
    }

    public function isRetryable(): bool
    {
        return in_array($this, [
            self::ProviderUnavailable,
            self::ProviderError,
            self::ProviderTimeout,
            self::InvalidProviderResponse,
            self::NormalizationFailed,
            self::QualityGateFailed,
            self::ConcurrencyLockFailed,
        ], true);
    }
}
