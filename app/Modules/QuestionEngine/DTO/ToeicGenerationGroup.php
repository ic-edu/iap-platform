<?php

namespace App\Modules\QuestionEngine\DTO;

use InvalidArgumentException;

final class ToeicGenerationGroup
{
    /**
     * @param  string  $groupType  Group type ('conversation', 'talk', 'single', 'double', 'triple', 'passage')
     * @param  int  $partNumber  Part number (3, 4, 6, 7)
     * @param  int  $groupIndex  1-indexed group index within part
     * @param  int|null  $documentCount  Number of passage stimulus documents (null for audio groups)
     * @param  int  $questionCount  Number of questions in group
     * @param  list<int>  $questionNumbers  Canonical question numbers (e.g. [32, 33, 34])
     * @param  list<int>  $slotSequences  Plan slot sequence numbers (e.g. [32, 33, 34])
     * @param  array<string, mixed>  $metadata  Additional metadata
     */
    public function __construct(
        public readonly string $groupType,
        public readonly int $partNumber,
        public readonly int $groupIndex,
        public readonly ?int $documentCount,
        public readonly int $questionCount,
        public readonly array $questionNumbers,
        public readonly array $slotSequences,
        public readonly array $metadata = [],
    ) {
        if ($this->groupIndex < 1) {
            throw new InvalidArgumentException("Group index must be positive, got {$this->groupIndex}.");
        }
        if ($this->questionCount < 1) {
            throw new InvalidArgumentException("Group question count must be positive, got {$this->questionCount}.");
        }
        if (!in_array($this->partNumber, [3, 4, 6, 7], true)) {
            throw new InvalidArgumentException("Invalid grouped part number [{$this->partNumber}]. Grouped parts are 3, 4, 6, 7.");
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            groupType: (string) $data['group_type'],
            partNumber: (int) $data['part_number'],
            groupIndex: (int) $data['group_index'],
            documentCount: isset($data['document_count']) && $data['document_count'] !== null ? (int) $data['document_count'] : null,
            questionCount: (int) $data['question_count'],
            questionNumbers: array_values(array_map('intval', (array) ($data['question_numbers'] ?? []))),
            slotSequences: array_values(array_map('intval', (array) ($data['slot_sequences'] ?? []))),
            metadata: isset($data['metadata']) && is_array($data['metadata']) ? $data['metadata'] : [],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'group_type' => $this->groupType,
            'part_number' => $this->partNumber,
            'group_index' => $this->groupIndex,
            'document_count' => $this->documentCount,
            'question_count' => $this->questionCount,
            'question_numbers' => $this->questionNumbers,
            'slot_sequences' => $this->slotSequences,
            'metadata' => $this->metadata,
        ];
    }
}
