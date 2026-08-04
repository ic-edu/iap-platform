<?php

namespace App\Services;

use App\Models\AclCategory;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Modules\QuestionBank\Models\QuestionChoice;
use Illuminate\Support\Collection;

class RepositoryQualityService
{
    /**
     * Audit and evaluate a single QuestionBank repository.
     */
    public function validateRepository(QuestionBank $bank): array
    {
        $bank->loadMissing(['questions.choices', 'aclCategory', 'creator']);

        $warnings = [];
        $metadataScore = 100;
        $questionScore = 100;
        $explanationScore = 100;
        $difficultyScore = 100;

        // 1. Metadata Validation (PART 1)
        $requiredMetadata = [
            'title'           => 'Repository Name',
            'test_type'       => 'Program (TOEFL/TOEIC/IELTS/General)',
            'description'     => 'Repository Description',
            'current_version' => 'Version',
            'status'          => 'Workflow Status',
            'created_by'      => 'Created By',
        ];

        foreach ($requiredMetadata as $field => $label) {
            if (empty($bank->{$field})) {
                $metadataScore -= 15;
                $warnings[] = "Missing metadata: {$label}";
            }
        }

        if (!$bank->acl_category_id) {
            $metadataScore -= 20;
            $warnings[] = "Missing Category association";
        }

        // 2. Question Quality & Completeness Validation (PART 2)
        $questions = $bank->questions;
        $totalQuestions = $questions->count();
        $questionsWithoutExplanation = 0;
        $incompleteQuestions = 0;

        $difficultyCounts = [
            'easy'   => 0,
            'medium' => 0,
            'hard'   => 0,
        ];

        if ($totalQuestions === 0) {
            $questionScore = 0;
            $explanationScore = 0;
            $difficultyScore = 0;
            $warnings[] = "Insufficient question count (0 questions in repository)";
        } else {
            foreach ($questions as $q) {
                // Check explanation
                if (empty(trim($q->explanation ?? ''))) {
                    $questionsWithoutExplanation++;
                }

                // Check prompt
                if (empty(trim($q->prompt ?? ''))) {
                    $incompleteQuestions++;
                    $warnings[] = "Incomplete question found (ID: {$q->id}) - missing prompt";
                }

                // Check choices for MCQs
                if (in_array($q->question_type->value ?? $q->question_type, ['multiple_choice', 'listening', 'reading'])) {
                    if ($q->choices->count() === 0) {
                        $incompleteQuestions++;
                        $warnings[] = "Question '{$q->prompt}' has no answer choices attached";
                    } else {
                        $hasCorrect = $q->choices->where('is_correct', true)->count() > 0;
                        if (!$hasCorrect) {
                            $incompleteQuestions++;
                            $warnings[] = "Question '{$q->prompt}' is missing a designated correct answer choice";
                        }
                    }
                }

                // Difficulty counting
                $diff = strtolower(is_object($q->difficulty) ? $q->difficulty->value : ($q->difficulty ?? 'medium'));
                if (isset($difficultyCounts[$diff])) {
                    $difficultyCounts[$diff]++;
                } else {
                    $difficultyCounts['medium']++;
                }
            }

            // Explanation score
            $explanationCoverage = round((($totalQuestions - $questionsWithoutExplanation) / $totalQuestions) * 100);
            $explanationScore = $explanationCoverage;
            if ($questionsWithoutExplanation > 0) {
                $warnings[] = "{$questionsWithoutExplanation} of {$totalQuestions} questions are missing detailed explanations";
            }

            // Question completeness score
            if ($incompleteQuestions > 0) {
                $questionScore = max(0, 100 - ($incompleteQuestions * 20));
            }
        }

        // 3. Difficulty Distribution Analysis (PART 3)
        $activeDifficulties = array_filter($difficultyCounts, fn($c) => $c > 0);
        $diffTypesCount = count($activeDifficulties);

        if ($totalQuestions > 0 && $diffTypesCount <= 1) {
            $difficultyScore = 40;
            $warnings[] = "Repository needs more balanced question difficulty (currently 100% " . array_key_first($activeDifficulties) . ")";
        } elseif ($diffTypesCount === 2) {
            $difficultyScore = 75;
        } else {
            $difficultyScore = 100;
        }

        // 4. Calculate Overall Health Score (PART 4)
        $metadataScore    = max(0, $metadataScore);
        $questionScore    = max(0, $questionScore);
        $explanationScore = max(0, $explanationScore);
        $difficultyScore  = max(0, $difficultyScore);

        $healthScore = round(
            ($metadataScore * 0.25) +
            ($questionScore * 0.30) +
            ($explanationScore * 0.25) +
            ($difficultyScore * 0.20)
        );

        $healthGrade = match (true) {
            $healthScore >= 90 => 'Excellent',
            $healthScore >= 75 => 'Good',
            default            => 'Needs Improvement',
        };

        $needsImprovement = ($healthScore < 75) || (count($warnings) > 0);

        // 5. Institutional Governance Metadata (PART 9)
        $governance = [
            'institution_owner' => 'iC.edu Language Ecosystem',
            'contributor'       => $bank->creator?->name ?? 'System Seeder',
            'reviewer'          => 'Academic Admin Team',
            'approver'          => 'Super Admin',
            'current_version'   => 'v' . ($bank->current_version ?? '1.0'),
            'status'            => ucfirst(str_replace('_', ' ', $bank->status ?? 'draft')),
        ];

        return [
            'bank_id'            => $bank->id,
            'title'              => $bank->title,
            'slug'               => $bank->slug,
            'test_type'          => is_object($bank->test_type) ? $bank->test_type->value : (string) ($bank->test_type ?? 'general'),
            'test_type_label'    => is_object($bank->test_type) ? $bank->test_type->label() : strtoupper((string) ($bank->test_type ?? 'General')),
            'status'             => is_object($bank->status) ? $bank->status->value : (string) ($bank->status ?? 'draft'),
            'health_score'       => $healthScore,
            'health_grade'       => $healthGrade,
            'needs_improvement'  => $needsImprovement,
            'warnings'           => array_unique($warnings),
            'difficulty_counts'  => $difficultyCounts,
            'total_questions'    => $totalQuestions,
            'explanation_pct'    => $totalQuestions > 0 ? round((($totalQuestions - $questionsWithoutExplanation) / $totalQuestions) * 100) : 0,
            'scores'             => [
                'metadata'    => $metadataScore,
                'questions'   => $questionScore,
                'explanation' => $explanationScore,
                'difficulty'  => $difficultyScore,
            ],
            'governance'         => $governance,
        ];
    }

    /**
     * Generate global Quality Audit Summary for all repositories (PART 11).
     */
    public function getGlobalQualitySummary(): array
    {
        $banks = QuestionBank::with(['questions.choices', 'aclCategory', 'creator'])->get();

        $audits = [];
        $totalRepositories = $banks->count();
        $healthyCount = 0;
        $needsImprovementCount = 0;
        $pendingApprovalCount = 0;
        $sumHealthScore = 0;
        $allWarnings = [];

        foreach ($banks as $bank) {
            $audit = $this->validateRepository($bank);
            $audits[] = $audit;

            $sumHealthScore += $audit['health_score'];

            if ($audit['health_score'] >= 75 && count($audit['warnings']) === 0) {
                $healthyCount++;
            } else {
                $needsImprovementCount++;
            }

            if (in_array($bank->status, ['pending_approval', 'submitted'])) {
                $pendingApprovalCount++;
            }

            foreach ($audit['warnings'] as $w) {
                $allWarnings[] = "[{$bank->title}] {$w}";
            }
        }

        $avgHealthScore = $totalRepositories > 0 ? round($sumHealthScore / $totalRepositories) : 100;
        $duplicates = $this->detectDuplicates($banks);

        return [
            'total_repositories'      => $totalRepositories,
            'healthy_count'           => $healthyCount,
            'needs_improvement_count' => $needsImprovementCount,
            'pending_approval_count'  => $pendingApprovalCount,
            'avg_health_score'        => $avgHealthScore,
            'audits'                  => $audits,
            'all_warnings'            => $allWarnings,
            'duplicates'              => $duplicates,
        ];
    }

    /**
     * Detect duplicate question prompts, choices, or bank titles (PART 6).
     */
    public function detectDuplicates(Collection $banks): array
    {
        $duplicatePrompts = [];
        $duplicateTitles  = [];

        // Check duplicate titles
        $titles = $banks->pluck('title')->toArray();
        $titleCounts = array_count_values($titles);
        foreach ($titleCounts as $t => $count) {
            if ($count > 1) {
                $duplicateTitles[] = "Duplicate Repository Title found: '{$t}' ({$count} instances)";
            }
        }

        // Check duplicate question prompts
        $allQuestions = Question::with('questionBank')->get();
        $prompts = [];

        foreach ($allQuestions as $q) {
            $trimmed = strtolower(trim($q->prompt));
            if (empty($trimmed)) continue;

            if (isset($prompts[$trimmed])) {
                $duplicatePrompts[] = "Duplicate Question Prompt: '{$q->prompt}' (Found in repository: " . ($q->questionBank?->title ?? 'Unknown') . ")";
            } else {
                $prompts[$trimmed] = true;
            }
        }

        return [
            'duplicate_titles'  => $duplicateTitles,
            'duplicate_prompts' => array_slice($duplicatePrompts, 0, 10), // Limit top 10
        ];
    }
}
