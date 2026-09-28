<?php

namespace App\Modules\QuestionEngine\Services;

use App\Modules\QuestionEngine\DTO\ToeflSectionSpecification;
use App\Modules\QuestionEngine\Enums\AssessmentFamily;
use App\Modules\QuestionEngine\Enums\ToeflClaim;
use App\Modules\QuestionEngine\Enums\ToeflLanguageUseContext;
use App\Modules\QuestionEngine\Enums\ToeflResponseMode;
use App\Modules\QuestionEngine\Enums\ToeflScoringMode;
use App\Modules\QuestionEngine\Enums\ToeflSkill;
use App\Modules\QuestionEngine\Enums\ToeflTaskType;

class ToeflIbt2026StandardDefinition
{
    public const VERSION = '2026.1';

    public const STANDARD_CODE = 'toefl_ibt';

    public const PROVIDER = 'ETS';

    public const SOURCE_NAME = 'TOEFL iBT Test: 2026 Update Test Blueprint and Specifications Document';

    public const SOURCE_URL = 'https://www.ets.org/content/dam/ets-org/pdfs/toefl/toefl-ibt-test-specifications-2026.pdf';

    public const SECONDARY_SOURCE_URL = 'https://www.ets.org/toefl/test-takers/ibt/about/content.html';

    public const EFFECTIVE_FROM = '2026-01-21 00:00:00';

    public const SOURCE_CHECKED_AT = '2026-09-28 16:30:00';

    /**
     * Get the authoritative canonical 2026.1 TOEFL iBT AssessmentStandard registration payload.
     *
     * @return array<string, mixed>
     */
    public static function getDefinition(): array
    {
        $sections = ['reading', 'listening', 'writing', 'speaking'];

        $sectionSpecs = [];
        $sectionContexts = [];
        foreach ($sections as $section) {
            $spec = ToeflSectionSpecification::forSection($section);
            $sectionSpecs[$section] = $spec->toArray();
            $sectionContexts[$section] = array_map(fn (ToeflLanguageUseContext $c) => $c->value, $spec->supportedLanguageUseContexts);
        }

        $sectionTaskMap = [];
        foreach ($sections as $section) {
            $sectionTaskMap[$section] = array_map(
                fn (ToeflTaskType $t) => $t->value,
                ToeflTaskType::forSection($section)
            );
        }

        $claimTaskMap = [];
        $skillTaskMap = [];
        $cefrRanges = [];
        $taskScoringCategories = [];
        foreach (ToeflTaskType::cases() as $task) {
            $claimTaskMap[$task->value] = [$task->claim()->value];
            $skillTaskMap[$task->value] = array_map(fn (ToeflSkill $s) => $s->value, $task->skills());
            $cefrRanges[$task->value] = $task->cefrRange();
            $taskScoringCategories[$task->value] = $task->scoringMode()->value;
        }

        $claimsMetadata = [];
        foreach (ToeflClaim::cases() as $claim) {
            $claimsMetadata[$claim->value] = [
                'machine_id' => $claim->value,
                'short_label' => $claim->shortLabel(),
                'official_text' => $claim->officialText(),
                'provenance' => $claim->provenance(),
                'section' => $claim->section(),
            ];
        }

        $skillsMetadata = [];
        foreach (ToeflSkill::cases() as $skill) {
            $skillsMetadata[$skill->value] = [
                'machine_id' => $skill->value,
                'short_label' => $skill->shortLabel(),
                'official_text' => $skill->officialText(),
                'provenance' => $skill->provenance(),
                'section' => $skill->section(),
                'claim' => $skill->claim()->value,
            ];
        }

        return [
            'assessment_family' => AssessmentFamily::ToeflIbt,
            'standard_code' => self::STANDARD_CODE,
            'version' => self::VERSION,
            'title' => 'TOEFL iBT (2026.1 - Official 2026 Update)',
            'provider' => self::PROVIDER,
            'effective_from' => self::EFFECTIVE_FROM,
            'source_name' => self::SOURCE_NAME,
            'source_url' => self::SOURCE_URL,
            'source_checked_at' => self::SOURCE_CHECKED_AT,
            'structure_definition' => [
                'sections' => $sections,
                'claims' => ToeflClaim::values(),
                'claims_metadata' => $claimsMetadata,
                'skills' => ToeflSkill::values(),
                'skills_metadata' => $skillsMetadata,
                'task_types' => ToeflTaskType::values(),
                'adaptive_sections' => ['reading', 'listening'],
                'linear_sections' => ['writing', 'speaking'],
                'language_use_contexts' => ToeflLanguageUseContext::values(),
                'section_language_use_contexts' => $sectionContexts,
                'response_modes' => ToeflResponseMode::values(),
                'scoring_modes' => ToeflScoringMode::values(),
                'supports_part_numbers' => false,
                'supports_task_types' => true,
                'supports_claims' => true,
                'supports_skills' => true,
            ],
            'blueprint_definition' => [
                'sections' => $sectionSpecs,
                'reading' => [
                    'mode' => 'two_stage_adaptive',
                    'maximum_target_items' => 50,
                    'includes_pretest' => true,
                    'total_items_min' => null,
                    'tasks' => [
                        'complete_the_words' => ['target_items' => 30, 'cefr' => 'B1-C1+'],
                        'read_in_daily_life' => ['target_items_range' => [5, 15], 'cefr' => 'A1-C1'],
                        'read_an_academic_passage' => ['target_items_range' => [5, 15], 'cefr' => 'B1-C2'],
                    ],
                ],
                'listening' => [
                    'mode' => 'two_stage_adaptive',
                    'maximum_target_items' => 47,
                    'includes_pretest' => true,
                    'total_items_min' => null,
                    'tasks' => [
                        'listen_and_choose_a_response' => ['target_items_range' => [15, 19], 'cefr' => 'A1-B2'],
                        'listen_to_a_conversation' => ['target_items' => 10, 'cefr' => 'A2-C1'],
                        'listen_to_an_announcement' => ['target_items_range' => [6, 10], 'cefr' => 'A2-C1'],
                        'listen_to_an_academic_talk' => ['target_items_range' => [8, 16], 'cefr' => 'A2-C2'],
                    ],
                ],
                'writing' => [
                    'mode' => 'linear',
                    'fixed_total_items' => 12,
                    'includes_pretest' => false,
                    'tasks' => [
                        'build_a_sentence' => ['fixed_items' => 10, 'cefr' => 'A1-C2'],
                        'write_an_email' => ['fixed_items' => 1, 'cefr' => 'B1-C2'],
                        'write_for_an_academic_discussion' => ['fixed_items' => 1, 'cefr' => 'B1-C2'],
                    ],
                ],
                'speaking' => [
                    'mode' => 'linear',
                    'fixed_total_items' => 11,
                    'includes_pretest' => false,
                    'tasks' => [
                        'listen_and_repeat' => ['fixed_items' => 7, 'cefr' => 'A1-C2'],
                        'take_an_interview' => ['fixed_items' => 4, 'cefr' => 'A1-C2'],
                    ],
                ],
            ],
            'validation_definition' => [
                'part_number_allowed' => false,
                'section_task_compatibility' => $sectionTaskMap,
                'claim_task_compatibility' => $claimTaskMap,
                'skill_task_compatibility' => $skillTaskMap,
                'cefr_target_ranges' => $cefrRanges,
                'section_language_use_contexts' => $sectionContexts,
                'language_use_contexts' => ToeflLanguageUseContext::values(),
            ],
            'scoring_definition' => [
                'section_score_scale' => [
                    'min' => 1.0,
                    'max' => 6.0,
                    'increment' => 0.5,
                    'reporting_bands' => [1.0, 1.5, 2.0, 2.5, 3.0, 3.5, 4.0, 4.5, 5.0, 5.5, 6.0],
                ],
                'overall_score_scale' => [
                    'min' => 1.0,
                    'max' => 6.0,
                    'increment' => 0.5,
                    'calculation_rule' => 'average_of_four_sections_rounded_to_nearest_half_band',
                ],
                'transition_concordance_scale' => [
                    'min' => 0,
                    'max' => 120,
                    'reporting_mode' => 'comparable_overall_transition_score',
                ],
                'task_scoring_categories' => $taskScoringCategories,
            ],
            'metadata' => [
                'source_type' => 'official_specification',
                'official' => true,
                'effective_date' => '2026-01-21',
                'retrieved_version' => self::VERSION,
                'primary_source' => [
                    'name' => self::SOURCE_NAME,
                    'url' => self::SOURCE_URL,
                    'type' => 'official_specification_pdf',
                ],
                'secondary_sources' => [
                    [
                        'name' => 'ETS TOEFL iBT Test Content & Structure Page',
                        'url' => self::SECONDARY_SOURCE_URL,
                        'type' => 'operational_confirmation',
                    ],
                ],
                'verification_metadata' => [
                    'effective_from' => self::EFFECTIVE_FROM,
                    'source_checked_at' => self::SOURCE_CHECKED_AT,
                    'timezone' => 'UTC+07:00 (Asia/Jakarta)',
                    'verification_note' => 'Standard specifications verified against official ETS 2026 PDF document.',
                ],
                'provenance_separation' => [
                    'official_properties' => ['section', 'task_type', 'claim', 'skills', 'cefr_range', 'language_use_contexts', 'response_mode', 'scoring_mode'],
                    'iap_derived_properties' => ['stimulus_type', 'stimulus_type_provenance', 'generation_metadata'],
                ],
                'notes' => 'Official ETS TOEFL iBT 2026 Update Blueprint & Specification standard.',
            ],
        ];
    }
}
