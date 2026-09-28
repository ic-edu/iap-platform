<?php

namespace App\Modules\QuestionEngine\Services;

use App\Modules\QuestionEngine\Enums\AssessmentFamily;
use App\Modules\QuestionEngine\Enums\ToeflClaim;
use App\Modules\QuestionEngine\Enums\ToeflSkill;
use App\Modules\QuestionEngine\Enums\ToeflTaskType;
use App\Modules\QuestionEngine\Models\AssessmentStandard;
use InvalidArgumentException;

class ToeflIbtStandardValidator
{
    /**
     * Validate a TOEFL iBT AssessmentStandard definition against ETS 2026 canonical invariants.
     *
     * @param  AssessmentStandard|array<string, mixed>  $standard
     * @return array{is_valid: bool, errors: list<string>}
     */
    public function validate(AssessmentStandard|array $standard): array
    {
        $data = $standard instanceof AssessmentStandard ? $standard->toArray() : $standard;
        $errors = [];

        // 1. Family Verification
        $familyRaw = $data['assessment_family'] ?? null;
        $family = $familyRaw instanceof AssessmentFamily ? $familyRaw->value : (string) $familyRaw;
        if ($family !== AssessmentFamily::ToeflIbt->value) {
            $errors[] = "Assessment family must be 'toefl_ibt', given '{$family}'.";
        }

        // 2. Source Provenance Metadata
        if (empty($data['provider'])) {
            $errors[] = 'Provider is required for TOEFL iBT standard.';
        }
        if (empty($data['source_name'])) {
            $errors[] = 'Source name is required for official ETS standard provenance.';
        }
        if (empty($data['source_url'])) {
            $errors[] = 'Source URL is required for official ETS standard provenance.';
        }
        if (empty($data['source_checked_at'])) {
            $errors[] = 'source_checked_at is required for standard verification provenance.';
        }

        // 3. Structure Definition Checks (Sections, 4 Claims, Skills, 12 Tasks)
        $structure = (array) ($data['structure_definition'] ?? []);
        $sections = $structure['sections'] ?? [];
        $expectedSections = ['reading', 'listening', 'writing', 'speaking'];

        if (count($sections) !== 4 || array_diff($expectedSections, $sections) !== [] || array_diff($sections, $expectedSections) !== []) {
            $errors[] = 'Structure definition must contain exactly the 4 canonical sections: reading, listening, writing, speaking.';
        }

        if (!empty($structure['supports_part_numbers'])) {
            $errors[] = 'TOEFL iBT does not support TOEIC part numbers (supports_part_numbers must be false).';
        }

        // Exactly 4 top-level claims
        $claims = $structure['claims'] ?? [];
        $expectedClaims = ToeflClaim::values();
        $claimsSorted = $claims;
        $expClaimsSorted = $expectedClaims;
        sort($claimsSorted);
        sort($expClaimsSorted);
        if ($claimsSorted !== $expClaimsSorted) {
            $errors[] = 'Structure definition must contain exactly the 4 official top-level TOEFL claims: claim_1_reading, claim_2_listening, claim_3_writing, claim_4_speaking.';
        }

        // Canonical 12 task types
        $taskTypes = $structure['task_types'] ?? [];
        $expectedTaskTypes = ToeflTaskType::values();
        $tasksSorted = $taskTypes;
        $expTasksSorted = $expectedTaskTypes;
        sort($tasksSorted);
        sort($expTasksSorted);
        if ($tasksSorted !== $expTasksSorted) {
            $errors[] = 'Structure definition must contain all 12 canonical TOEFL task types.';
        }

        // Section language use contexts
        $secContexts = (array) ($structure['section_language_use_contexts'] ?? []);
        $expectedSecContexts = [
            'reading' => ['academic', 'social_interpersonal'],
            'listening' => ['academic', 'academic_navigational', 'social_interpersonal'],
            'writing' => ['academic', 'academic_navigational', 'social_interpersonal'],
            'speaking' => ['academic_navigational'],
        ];
        foreach ($expectedSecContexts as $sec => $expectedCtx) {
            $actualCtx = $secContexts[$sec] ?? [];
            $expCtxSorted = $expectedCtx;
            $actCtxSorted = $actualCtx;
            sort($expCtxSorted);
            sort($actCtxSorted);
            if ($expCtxSorted !== $actCtxSorted) {
                $errors[] = "Official language use contexts for section '{$sec}' must be exactly [".implode(', ', $expectedCtx).'].';
            }
        }

        $adaptiveSections = $structure['adaptive_sections'] ?? [];
        if ($adaptiveSections !== ['reading', 'listening']) {
            $errors[] = 'Adaptive sections must be exactly [reading, listening].';
        }

        $linearSections = $structure['linear_sections'] ?? [];
        if ($linearSections !== ['writing', 'speaking']) {
            $errors[] = 'Linear sections must be exactly [writing, speaking].';
        }

        // 4. Section Task Compatibility
        $valDef = (array) ($data['validation_definition'] ?? []);
        if (array_key_exists('part_number_allowed', $valDef) && $valDef['part_number_allowed'] === true) {
            $errors[] = 'part_number_allowed must be false in validation definition.';
        }

        $secTaskMap = (array) ($valDef['section_task_compatibility'] ?? []);
        foreach ($expectedSections as $sec) {
            $expectedTasksForSec = array_map(fn (ToeflTaskType $t) => $t->value, ToeflTaskType::forSection($sec));
            $actualTasksForSec = $secTaskMap[$sec] ?? [];
            $expT = $expectedTasksForSec;
            $actT = $actualTasksForSec;
            sort($expT);
            sort($actT);
            if ($expT !== $actT) {
                $errors[] = "Section task compatibility mismatch for section '{$sec}'.";
            }
        }

        // 5. Claim-Task and Skill-Task Compatibility (Strict Bidirectional Exact Equality)
        $claimTaskMap = (array) ($valDef['claim_task_compatibility'] ?? []);
        $skillTaskMap = (array) ($valDef['skill_task_compatibility'] ?? []);
        foreach (ToeflTaskType::cases() as $task) {
            $expectedClaim = $task->claim()->value;
            $actualClaims = $claimTaskMap[$task->value] ?? [];
            if ($actualClaims !== [$expectedClaim]) {
                $errors[] = "Task '{$task->value}' must map exactly to its section claim '{$expectedClaim}'.";
            }

            $expectedSkills = array_map(fn (ToeflSkill $s) => $s->value, $task->skills());
            $actualSkills = (array) ($skillTaskMap[$task->value] ?? []);

            $expSkillsSorted = $expectedSkills;
            $actSkillsSorted = $actualSkills;
            sort($expSkillsSorted);
            sort($actSkillsSorted);

            if ($expSkillsSorted !== $actSkillsSorted) {
                $missing = array_diff($expectedSkills, $actualSkills);
                $extra = array_diff($actualSkills, $expectedSkills);
                $detail = [];
                if (!empty($missing)) {
                    $detail[] = 'missing expected: '.implode(', ', $missing);
                }
                if (!empty($extra)) {
                    $detail[] = 'unexpected extra: '.implode(', ', $extra);
                }
                $errors[] = "Skill compatibility mismatch for task '{$task->value}' (".implode('; ', $detail).').';
            }
        }

        // 6. Blueprint Definition Checks (No invented min totals for adaptive sections)
        $blueprint = (array) ($data['blueprint_definition'] ?? []);

        // Reading Blueprint
        $readingBp = $blueprint['reading'] ?? [];
        if (($readingBp['mode'] ?? null) !== 'two_stage_adaptive') {
            $errors[] = "Reading blueprint mode must be 'two_stage_adaptive'.";
        }
        if (($readingBp['maximum_target_items'] ?? null) !== 50) {
            $errors[] = 'Reading blueprint maximum target items must be 50.';
        }
        if (!empty($readingBp['total_items_min'])) {
            $errors[] = 'Reading blueprint must not define an invented total_items_min.';
        }
        $rTasks = $readingBp['tasks'] ?? [];
        if (($rTasks['complete_the_words']['target_items'] ?? null) !== 30) {
            $errors[] = 'Complete the Words target items must be 30.';
        }
        if (($rTasks['read_in_daily_life']['target_items_range'] ?? null) !== [5, 15]) {
            $errors[] = 'Read in Daily Life target items range must be [5, 15].';
        }
        if (($rTasks['read_an_academic_passage']['target_items_range'] ?? null) !== [5, 15]) {
            $errors[] = 'Read an Academic Passage target items range must be [5, 15].';
        }

        // Listening Blueprint
        $listeningBp = $blueprint['listening'] ?? [];
        if (($listeningBp['mode'] ?? null) !== 'two_stage_adaptive') {
            $errors[] = "Listening blueprint mode must be 'two_stage_adaptive'.";
        }
        if (($listeningBp['maximum_target_items'] ?? null) !== 47) {
            $errors[] = 'Listening blueprint maximum target items must be 47.';
        }
        if (!empty($listeningBp['total_items_min'])) {
            $errors[] = 'Listening blueprint must not define an invented total_items_min.';
        }
        $lTasks = $listeningBp['tasks'] ?? [];
        if (($lTasks['listen_and_choose_a_response']['target_items_range'] ?? null) !== [15, 19]) {
            $errors[] = 'Listen and Choose a Response target items range must be [15, 19].';
        }
        if (($lTasks['listen_to_a_conversation']['target_items'] ?? null) !== 10) {
            $errors[] = 'Listen to a Conversation target items must be 10.';
        }
        if (($lTasks['listen_to_an_announcement']['target_items_range'] ?? null) !== [6, 10]) {
            $errors[] = 'Listen to an Announcement target items range must be [6, 10].';
        }
        if (($lTasks['listen_to_an_academic_talk']['target_items_range'] ?? null) !== [8, 16]) {
            $errors[] = 'Listen to an Academic Talk target items range must be [8, 16].';
        }

        // Writing Blueprint
        $writingBp = $blueprint['writing'] ?? [];
        if (($writingBp['mode'] ?? null) !== 'linear') {
            $errors[] = "Writing blueprint mode must be 'linear'.";
        }
        if (($writingBp['fixed_total_items'] ?? null) !== 12) {
            $errors[] = 'Writing blueprint fixed total items must be exactly 12.';
        }
        $wTasks = $writingBp['tasks'] ?? [];
        if (($wTasks['build_a_sentence']['fixed_items'] ?? null) !== 10) {
            $errors[] = 'Build a Sentence fixed items must be 10.';
        }
        if (($wTasks['write_an_email']['fixed_items'] ?? null) !== 1) {
            $errors[] = 'Write an Email fixed items must be 1.';
        }
        if (($wTasks['write_for_an_academic_discussion']['fixed_items'] ?? null) !== 1) {
            $errors[] = 'Write for an Academic Discussion fixed items must be 1.';
        }

        // Speaking Blueprint
        $speakingBp = $blueprint['speaking'] ?? [];
        if (($speakingBp['mode'] ?? null) !== 'linear') {
            $errors[] = "Speaking blueprint mode must be 'linear'.";
        }
        if (($speakingBp['fixed_total_items'] ?? null) !== 11) {
            $errors[] = 'Speaking blueprint fixed total items must be exactly 11.';
        }
        $sTasks = $speakingBp['tasks'] ?? [];
        if (($sTasks['listen_and_repeat']['fixed_items'] ?? null) !== 7) {
            $errors[] = 'Listen and Repeat fixed items must be 7.';
        }
        if (($sTasks['take_an_interview']['fixed_items'] ?? null) !== 4) {
            $errors[] = 'Take an Interview fixed items must be 4.';
        }

        // 7. CEFR Target Ranges
        $cefrMap = (array) ($valDef['cefr_target_ranges'] ?? []);
        foreach (ToeflTaskType::cases() as $task) {
            $expectedRange = $task->cefrRange();
            $actualRange = $cefrMap[$task->value] ?? null;
            if ($actualRange !== $expectedRange) {
                $errors[] = "CEFR range mismatch for task '{$task->value}'. Expected min: {$expectedRange['min']}, max: {$expectedRange['max']}.";
            }
        }

        // 8. Scoring Definition Checks
        $scoringDef = (array) ($data['scoring_definition'] ?? []);
        $secScale = $scoringDef['section_score_scale'] ?? [];
        if (($secScale['min'] ?? null) != 1.0 || ($secScale['max'] ?? null) != 6.0 || ($secScale['increment'] ?? null) != 0.5) {
            $errors[] = 'Section score scale must range from 1.0 to 6.0 with increment 0.5.';
        }

        return [
            'is_valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Validate or throw InvalidArgumentException if standard definition is invalid.
     *
     * @param  AssessmentStandard|array<string, mixed>  $standard
     *
     * @throws InvalidArgumentException
     */
    public function validateOrFail(AssessmentStandard|array $standard): void
    {
        $result = $this->validate($standard);

        if (!$result['is_valid']) {
            throw new InvalidArgumentException('TOEFL iBT Standard definition validation failed: '.implode('; ', $result['errors']));
        }
    }
}
