<?php

namespace App\Modules\QuestionEngine\DTO;

final class GenerationValidationResult
{
    /**
     * @param  list<array<string, mixed>>  $violations
     * @param  list<string>  $warnings
     * @param  array<string, mixed>  $metrics
     */
    public function __construct(
        public readonly bool $isValid,
        public readonly array $violations = [],
        public readonly array $warnings = [],
        public readonly array $metrics = []
    ) {}

    /**
     * Factory for valid result.
     *
     * @param  array<string, mixed>  $metrics
     * @param  list<string>  $warnings
     */
    public static function valid(array $metrics = [], array $warnings = []): self
    {
        return new self(
            isValid: true,
            violations: [],
            warnings: $warnings,
            metrics: $metrics,
        );
    }

    /**
     * Factory for invalid result.
     *
     * @param  list<array<string, mixed>>  $violations
     * @param  array<string, mixed>  $metrics
     * @param  list<string>  $warnings
     */
    public static function invalid(array $violations, array $metrics = [], array $warnings = []): self
    {
        return new self(
            isValid: false,
            violations: $violations,
            warnings: $warnings,
            metrics: $metrics,
        );
    }

    public function hasViolations(): bool
    {
        return !empty($this->violations);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            isValid: (bool) ($data['is_valid'] ?? false),
            violations: (array) ($data['violations'] ?? []),
            warnings: (array) ($data['warnings'] ?? []),
            metrics: (array) ($data['metrics'] ?? []),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'is_valid' => $this->isValid,
            'violations' => $this->violations,
            'warnings' => $this->warnings,
            'metrics' => $this->metrics,
        ];
    }
}
