<?php

namespace App\Modules\QuestionEngine\DTO;

final class GenerationProviderRequest
{
    /**
     * @param  array<string, mixed>  $generationParameters  (temperature, max_tokens, etc.)
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $batchId,
        public readonly string $itemId,
        public readonly int $slotSequence,
        public readonly PromptComposition $promptComposition,
        public readonly array $generationParameters = [],
        public readonly array $metadata = []
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            batchId: (string) $data['batch_id'],
            itemId: (string) $data['item_id'],
            slotSequence: (int) $data['slot_sequence'],
            promptComposition: $data['prompt_composition'] instanceof PromptComposition
                ? $data['prompt_composition']
                : PromptComposition::fromArray((array) $data['prompt_composition']),
            generationParameters: (array) ($data['generation_parameters'] ?? []),
            metadata: (array) ($data['metadata'] ?? []),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'batch_id' => $this->batchId,
            'item_id' => $this->itemId,
            'slot_sequence' => $this->slotSequence,
            'prompt_composition' => $this->promptComposition->toArray(),
            'generation_parameters' => $this->generationParameters,
            'metadata' => $this->metadata,
        ];
    }
}
