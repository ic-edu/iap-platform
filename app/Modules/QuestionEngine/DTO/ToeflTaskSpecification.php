<?php

namespace App\Modules\QuestionEngine\DTO;

use App\Modules\QuestionEngine\Enums\ToeflClaim;
use App\Modules\QuestionEngine\Enums\ToeflLanguageUseContext;
use App\Modules\QuestionEngine\Enums\ToeflResponseMode;
use App\Modules\QuestionEngine\Enums\ToeflScoringMode;
use App\Modules\QuestionEngine\Enums\ToeflSkill;
use App\Modules\QuestionEngine\Enums\ToeflTaskType;
use InvalidArgumentException;

class ToeflTaskSpecification
{
    /**
     * @param  list<ToeflSkill>  $skills
     * @param  list<ToeflClaim>  $claims
     * @param  list<ToeflLanguageUseContext>  $languageUseContexts
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public ToeflTaskType $taskType,
        public string $section,
        public ToeflClaim $claim,
        public array $skills,
        public string $cefrMin,
        public string $cefrMax,
        public ?int $itemCountFixed = null,
        public ?int $itemCountMin = null,
        public ?int $itemCountMax = null,
        public ToeflResponseMode $responseMode = ToeflResponseMode::SelectedResponse,
        public ToeflScoringMode $scoringMode = ToeflScoringMode::Machine,
        public ?string $stimulusType = null,
        public string $stimulusTypeProvenance = 'iap_derived',
        public array $languageUseContexts = [],
        public ?string $adaptiveRole = null,
        public array $claims = [],
        public array $metadata = []
    ) {
        if (empty($this->claims)) {
            $this->claims = [$this->claim];
        }
    }

    /**
     * Generate canonical specification for a given ToeflTaskType.
     */
    public static function forTaskType(ToeflTaskType|string $taskType): self
    {
        $resolved = $taskType instanceof ToeflTaskType ? $taskType : ToeflTaskType::tryFrom((string) $taskType);

        if ($resolved === null) {
            throw new InvalidArgumentException("Unknown TOEFL task type '{$taskType}'.");
        }

        $fixed = $resolved->itemCountFixed();
        $range = $resolved->itemCountRange();
        $claim = $resolved->claim();

        return new self(
            taskType: $resolved,
            section: $resolved->section(),
            claim: $claim,
            skills: $resolved->skills(),
            cefrMin: $resolved->cefrMin(),
            cefrMax: $resolved->cefrMax(),
            itemCountFixed: $fixed,
            itemCountMin: $range['min'] ?? null,
            itemCountMax: $range['max'] ?? null,
            responseMode: $resolved->responseMode(),
            scoringMode: $resolved->scoringMode(),
            stimulusType: self::defaultStimulusType($resolved),
            stimulusTypeProvenance: 'iap_derived',
            languageUseContexts: $resolved->allowedLanguageUseContexts(),
            adaptiveRole: in_array($resolved->section(), ['reading', 'listening'], true) ? 'adaptive_candidate' : null,
            claims: [$claim],
            metadata: [
                'official_label' => $resolved->label(),
                'official_provenance' => 'ETS 2026 Update Blueprint',
                'provenance_distinction' => [
                    'official' => ['section', 'task_type', 'claim', 'skills', 'cefr_range', 'language_use_contexts', 'response_mode', 'scoring_mode'],
                    'iap_derived' => ['stimulus_type', 'stimulus_type_provenance'],
                ],
            ]
        );
    }

    private static function defaultStimulusType(ToeflTaskType $taskType): string
    {
        return match ($taskType) {
            ToeflTaskType::CompleteTheWords => 'cloze_paragraph',
            ToeflTaskType::ReadInDailyLife => 'daily_life_document',
            ToeflTaskType::ReadAnAcademicPassage => 'academic_passage',
            ToeflTaskType::ListenAndChooseAResponse => 'short_dialogue_audio',
            ToeflTaskType::ListenToAConversation => 'dialogue_audio',
            ToeflTaskType::ListenToAnAnnouncement => 'monologic_announcement_audio',
            ToeflTaskType::ListenToAnAcademicTalk => 'academic_lecture_audio',
            ToeflTaskType::BuildASentence => 'jumbled_sentence_words',
            ToeflTaskType::WriteAnEmail => 'situational_prompt',
            ToeflTaskType::WriteForAnAcademicDiscussion => 'discussion_board_threads',
            ToeflTaskType::ListenAndRepeat => 'spoken_sentence_audio',
            ToeflTaskType::TakeAnInterview => 'interactive_interview_prompts',
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'task_type' => $this->taskType->value,
            'section' => $this->section,
            'claim' => $this->claim->value,
            'claims' => array_map(fn (ToeflClaim $c) => $c->value, $this->claims),
            'skills' => array_map(fn (ToeflSkill $s) => $s->value, $this->skills),
            'cefr_min' => $this->cefrMin,
            'cefr_max' => $this->cefrMax,
            'item_count_fixed' => $this->itemCountFixed,
            'item_count_min' => $this->itemCountMin,
            'item_count_max' => $this->itemCountMax,
            'response_mode' => $this->responseMode->value,
            'scoring_mode' => $this->scoringMode->value,
            'stimulus_type' => $this->stimulusType,
            'stimulus_type_provenance' => $this->stimulusTypeProvenance,
            'language_use_contexts' => array_map(fn (ToeflLanguageUseContext $ctx) => $ctx->value, $this->languageUseContexts),
            'adaptive_role' => $this->adaptiveRole,
            'metadata' => $this->metadata,
        ];
    }
}
