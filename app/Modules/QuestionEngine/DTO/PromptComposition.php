<?php

namespace App\Modules\QuestionEngine\DTO;

final class PromptComposition
{
    /**
     * @param  string  $promptContractVersion  e.g. 'question_generation_v1'
     * @param  string  $systemPrompt  System prompt defining role and high-level rules
     * @param  string  $userPrompt  User prompt defining the specific question task
     * @param  array<string, mixed>  $structuralConstraints  Structural constraints (e.g. part/task constraints, limits)
     * @param  array<string, mixed>  $targetMetadata  Target metadata (family, standard_version, skill, construct, etc.)
     * @param  array<string, mixed>  $schemaDefinition  JSON schema definition expected in output
     * @param  array<int, mixed>  $examples  Optional exemplar inputs/outputs
     * @param  array<string, mixed>  $metadata  Additional metadata
     */
    public function __construct(
        public readonly string $promptContractVersion,
        public readonly string $systemPrompt,
        public readonly string $userPrompt,
        public readonly array $structuralConstraints = [],
        public readonly array $targetMetadata = [],
        public readonly array $schemaDefinition = [],
        public readonly array $examples = [],
        public readonly array $metadata = []
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            promptContractVersion: (string) ($data['prompt_contract_version'] ?? 'question_generation_v1'),
            systemPrompt: (string) ($data['system_prompt'] ?? ''),
            userPrompt: (string) ($data['user_prompt'] ?? ''),
            structuralConstraints: (array) ($data['structural_constraints'] ?? []),
            targetMetadata: (array) ($data['target_metadata'] ?? []),
            schemaDefinition: (array) ($data['schema_definition'] ?? []),
            examples: (array) ($data['examples'] ?? []),
            metadata: (array) ($data['metadata'] ?? []),
        );
    }

    /**
     * Compute a deterministic SHA-256 hash of the canonical prompt representation.
     */
    public function computePromptHash(): string
    {
        $canonicalData = [
            'prompt_contract_version' => $this->promptContractVersion,
            'system_prompt' => $this->systemPrompt,
            'user_prompt' => $this->userPrompt,
            'structural_constraints' => $this->sortArrayRecursively($this->structuralConstraints),
            'target_metadata' => $this->sortArrayRecursively($this->targetMetadata),
            'schema_definition' => $this->sortArrayRecursively($this->schemaDefinition),
            'examples' => $this->sortArrayRecursively($this->examples),
        ];

        return hash('sha256', (string) json_encode($canonicalData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Recursively sort associative arrays by key while preserving sequential list ordering and scalar types.
     *
     * @param  array<mixed>  $array
     * @return array<mixed>
     */
    private function sortArrayRecursively(array $array): array
    {
        if (empty($array)) {
            return [];
        }

        $isAssoc = !array_is_list($array);

        if ($isAssoc) {
            ksort($array, SORT_STRING);
        }

        foreach ($array as $k => $v) {
            if (is_array($v)) {
                $array[$k] = $this->sortArrayRecursively($v);
            }
        }

        return $array;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'prompt_contract_version' => $this->promptContractVersion,
            'prompt_hash' => $this->computePromptHash(),
            'system_prompt' => $this->systemPrompt,
            'user_prompt' => $this->userPrompt,
            'structural_constraints' => $this->structuralConstraints,
            'target_metadata' => $this->targetMetadata,
            'schema_definition' => $this->schemaDefinition,
            'examples' => $this->examples,
            'metadata' => $this->metadata,
        ];
    }
}
