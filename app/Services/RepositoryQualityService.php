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

                // Check choices for choice-based questions (prevent false positives for oral/essay/speaking prompts)
                $qTypeVal = is_object($q->question_type) ? $q->question_type->value : (string) $q->question_type;
                $nonChoiceTypes = ['essay', 'speaking', 'writing', 'short_answer'];

                if (in_array($qTypeVal, ['multiple_choice', 'multiple_response', 'true_false']) || (!in_array($qTypeVal, $nonChoiceTypes) && $q->choices->count() > 0)) {
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
     * Synchronize and reconcile IRQA Findings for a QuestionBank repository.
     * Prevents duplicate OPEN findings during re-scans and resolves findings when issues disappear.
     */
    public function syncRepositoryFindings(QuestionBank $bank): array
    {
        $audit = $this->validateRepository($bank);
        $currentWarnings = $audit['warnings'] ?? [];

        if (!\Illuminate\Support\Facades\Schema::hasTable('repository_findings')) {
            return $audit;
        }

        // 1. Get existing OPEN findings for this repository
        $existingOpenFindings = \App\Models\RepositoryFinding::where('question_bank_id', $bank->id)
            ->where('status', 'OPEN')
            ->get();

        $activeFindingIds = [];

        // 2. Process current validation warnings
        foreach ($currentWarnings as $warning) {
            $findingCode = 'IRQA_WARN';
            $severity = 'high';

            // Check if an equivalent OPEN finding already exists (deduplication)
            $existingFinding = $existingOpenFindings->first(function ($f) use ($warning) {
                return $f->title === $warning || $f->description === $warning;
            });

            if ($existingFinding) {
                // Retain existing finding (do not create duplicate), touch updated_at
                $existingFinding->touch();
                $activeFindingIds[] = $existingFinding->id;
            } else {
                // Check if a FIXED or CLOSED finding exists that can be re-opened
                $resolvedFinding = \App\Models\RepositoryFinding::where('question_bank_id', $bank->id)
                    ->whereIn('status', ['FIXED', 'CLOSED', 'VERIFIED', 'DISMISSED'])
                    ->where(function ($q) use ($warning) {
                        $q->where('title', $warning)->orWhere('description', $warning);
                    })
                    ->first();

                if ($resolvedFinding) {
                    $resolvedFinding->status = 'OPEN';
                    $resolvedFinding->save();
                    $activeFindingIds[] = $resolvedFinding->id;
                } else {
                    // Create new OPEN finding
                    $newFinding = \App\Models\RepositoryFinding::create([
                        'question_bank_id' => $bank->id,
                        'finding_code'     => $findingCode,
                        'title'            => $warning,
                        'description'      => $warning,
                        'severity'         => $severity,
                        'status'           => 'OPEN',
                    ]);
                    $activeFindingIds[] = $newFinding->id;
                }
            }
        }

        // 3. Mark previous OPEN findings that are NO LONGER present as FIXED
        foreach ($existingOpenFindings as $openFinding) {
            if (!in_array($openFinding->id, $activeFindingIds)) {
                $openFinding->status = 'FIXED';
                $openFinding->save();
            }
        }

        // 4. Deduplicate any remaining OPEN findings for identical warnings
        $openFindingsGrouped = \App\Models\RepositoryFinding::where('question_bank_id', $bank->id)
            ->where('status', 'OPEN')
            ->get()
            ->groupBy('title');

        foreach ($openFindingsGrouped as $title => $group) {
            if ($group->count() > 1) {
                $keep = $group->sortByDesc('created_at')->first();
                foreach ($group as $item) {
                    if ($item->id !== $keep->id) {
                        $item->status = 'FIXED';
                        $item->save();
                    }
                }
            }
        }

        return $audit;
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
        $needsImprovementUnreviewedCount = 0;
        $reviewedIssuesCount = 0;
        $pendingApprovalCount = 0;
        $sumHealthScore = 0;
        $allWarnings = [];

        $governanceActions = [
            'approved',
            'revision_requested',
            'rejected',
            'repository_manager_approved_repository',
            'repository_manager_requested_revision',
        ];

        foreach ($banks as $bank) {
            $audit = $this->validateRepository($bank);
            $audits[] = $audit;

            $sumHealthScore += $audit['health_score'];
            $bankStatus = is_object($bank->status) ? $bank->status->value : (string)($bank->status ?? 'draft');

            $latestGovernanceLog = \App\Models\RepositoryActivityLog::where('resource_type', 'QuestionBank')
                ->where('resource_id', $bank->id)
                ->whereIn('action', $governanceActions)
                ->latest()
                ->first();

            $hasCompletedGovernanceTask = \App\Models\GovernanceApprovalTask::where('question_bank_id', $bank->id)
                ->where('status', 'COMPLETED')
                ->exists();

            $isCanonicalReviewedState = in_array($bankStatus, ['published', 'approved', 'needs_revision', 'rejected', 'archived']);
            $isReviewed = $isCanonicalReviewedState || $hasCompletedGovernanceTask || ($latestGovernanceLog !== null);
            $hasIssues = $audit['needs_improvement'] || count($audit['warnings']) > 0;

            $findingRecordsSummary = \Illuminate\Support\Facades\Schema::hasTable('repository_findings')
                ? \App\Models\RepositoryFinding::where('question_bank_id', $bank->id)
                    ->whereIn('status', ['OPEN', 'FIXED', 'VERIFIED', 'CLOSED'])
                    ->get()
                    ->reject(function ($f) {
                        return str_contains(strtolower($f->title), 'has no answer choices attached');
                    })
                : collect([]);

            $hasFindingHistory = $findingRecordsSummary->count() > 0;
            $hasHistoricalOrActiveFindings = $hasIssues || $hasFindingHistory;

            if (!$hasIssues) {
                $healthyCount++;
            } elseif (!$isReviewed) {
                $needsImprovementUnreviewedCount++;
            }

            if ($isReviewed && $hasHistoricalOrActiveFindings) {
                $reviewedIssuesCount++;
            }

            if (in_array($bankStatus, ['pending_approval', 'submitted'])) {
                $pendingApprovalCount++;
            }

            foreach ($audit['warnings'] as $w) {
                $allWarnings[] = "[{$bank->title}] {$w}";
            }
        }

        $avgHealthScore = $totalRepositories > 0 ? round($sumHealthScore / $totalRepositories) : 100;
        $duplicates = $this->detectDuplicates($banks);

        return [
            'total_repositories'           => $totalRepositories,
            'healthy_count'                => $healthyCount,
            'needs_improvement_count'      => $needsImprovementUnreviewedCount,
            'reviewed_issues_count'        => $reviewedIssuesCount,
            'pending_approval_count'       => $pendingApprovalCount,
            'avg_health_score'             => $avgHealthScore,
            'audits'                       => $audits,
            'all_warnings'                 => $allWarnings,
            'duplicates'                   => $duplicates,
        ];
    }

    /**
     * Get Audited Repositories filtered, searched, and sorted for Repository Explorer.
     */
    public function getExplorerAudits(array $options = []): array
    {
        $filter   = $options['filter'] ?? 'all';
        $decision = $options['decision'] ?? 'all';
        $search   = strtolower(trim($options['search'] ?? ''));
        $sort     = $options['sort'] ?? ($filter === 'needs_improvement' ? 'health_asc' : 'health_desc');

        $banks = QuestionBank::with(['questions.choices', 'aclCategory', 'creator'])->get();
        $audits = [];

        foreach ($banks as $bank) {
            $audit = $this->validateRepository($bank);

            $bankStatus = is_object($bank->status) ? $bank->status->value : (string)($bank->status ?? 'draft');

            // Governance decision actions in RepositoryActivityLog
            $governanceActions = [
                'approved',
                'revision_requested',
                'rejected',
                'repository_manager_approved_repository',
                'repository_manager_requested_revision',
            ];

            $latestGovernanceLog = \App\Models\RepositoryActivityLog::where('resource_type', 'QuestionBank')
                ->where('resource_id', $bank->id)
                ->whereIn('action', $governanceActions)
                ->with(['actor', 'reviewer'])
                ->latest()
                ->first();

            $hasCompletedGovernanceTask = \App\Models\GovernanceApprovalTask::where('question_bank_id', $bank->id)
                ->where('status', 'COMPLETED')
                ->exists();

            $isCanonicalReviewedState = in_array($bankStatus, ['published', 'approved', 'needs_revision', 'rejected', 'archived']);

            $isReviewed = $isCanonicalReviewedState || $hasCompletedGovernanceTask || ($latestGovernanceLog !== null);

            $displayLog = $latestGovernanceLog ?? \App\Models\RepositoryActivityLog::where('resource_type', 'QuestionBank')
                ->where('resource_id', $bank->id)
                ->with(['actor', 'reviewer'])
                ->latest()
                ->first();

            $findingRecords = \Illuminate\Support\Facades\Schema::hasTable('repository_findings')
                ? \App\Models\RepositoryFinding::where('question_bank_id', $bank->id)
                    ->whereIn('status', ['OPEN', 'FIXED', 'VERIFIED', 'CLOSED'])
                    ->get()
                    ->reject(function ($f) {
                        return str_contains(strtolower($f->title), 'has no answer choices attached');
                    })
                : collect([]);

            $hasFindingHistory = $findingRecords->count() > 0;
            $hasIssues = $audit['needs_improvement'] || count($audit['warnings']) > 0;
            $hasHistoricalOrActiveFindings = $hasIssues || $hasFindingHistory;

            $audit['is_reviewed'] = $isReviewed;
            $audit['finding_history'] = $findingRecords;
            $audit['review_details'] = [
                'reviewer_name' => $displayLog?->reviewer?->name ?? $displayLog?->actor?->name ?? 'Repository Manager',
                'reviewed_at'   => $displayLog?->created_at ? $displayLog->created_at->format('d M Y, H:i') : ($bank->updated_at ? $bank->updated_at->format('d M Y') : 'N/A'),
                'decision'      => strtoupper(str_replace('_', ' ', $bankStatus)),
                'approval_note' => $displayLog?->approval_note ?? 'Governance review completed.',
            ];

            // 1. Search Filter
            if (!empty($search)) {
                $titleMatch = str_contains(strtolower($audit['title']), $search);
                $typeMatch  = str_contains(strtolower($audit['test_type']), $search);
                $catMatch   = str_contains(strtolower($bank->aclCategory?->name ?? ''), $search);
                if (!$titleMatch && !$typeMatch && !$catMatch) {
                    continue;
                }
            }

            // 2. Status / Lifecycle Filter
            $passFilter = match ($filter) {
                'healthy'           => !$audit['needs_improvement'] && count($audit['warnings']) === 0,
                'needs_improvement' => $hasIssues && !$isReviewed,
                'awaiting_approval' => in_array($bankStatus, ['pending_approval', 'submitted']),
                'reviewed_issues'   => $isReviewed && $hasHistoricalOrActiveFindings,
                'archived'          => $bankStatus === 'archived' || $bankStatus === 'rejected',
                default             => true,
            };

            // 3. Decision sub-filter for reviewed_issues
            if ($filter === 'reviewed_issues' && !empty($decision) && $decision !== 'all') {
                if ($decision === 'published' && !in_array($bankStatus, ['published', 'approved'])) {
                    $passFilter = false;
                } elseif ($decision === 'needs_revision' && $bankStatus !== 'needs_revision') {
                    $passFilter = false;
                } elseif ($decision === 'rejected' && !in_array($bankStatus, ['rejected', 'archived'])) {
                    $passFilter = false;
                }
            }

            if ($passFilter) {
                $audits[] = $audit;
            }
        }

        // 4. Sorting
        usort($audits, function ($a, $b) use ($sort) {
            return match ($sort) {
                'health_asc'     => $a['health_score'] <=> $b['health_score'],
                'health_desc'    => $b['health_score'] <=> $a['health_score'],
                'title_asc'      => strcmp($a['title'], $b['title']),
                'questions_desc' => $b['total_questions'] <=> $a['total_questions'],
                default          => $b['health_score'] <=> $a['health_score'],
            };
        });

        return [
            'filter'       => $filter,
            'decision'     => $decision,
            'search'       => $search,
            'sort'         => $sort,
            'total_found'  => count($audits),
            'audits'       => $audits,
        ];
    }

    /**
     * Get Read-Only Analytics Data for IRQA Analytics (TASK 1.5).
     */
    public function getAnalyticsData(): array
    {
        $globalSummary = $this->getGlobalQualitySummary();
        $audits = $globalSummary['audits'];

        $distribution = [
            'excellent'         => 0,
            'good'              => 0,
            'needs_improvement' => 0,
        ];

        $sumMetadata    = 0;
        $sumQuestions   = 0;
        $sumExplanation = 0;
        $sumDifficulty  = 0;

        foreach ($audits as $audit) {
            if ($audit['health_score'] >= 90) {
                $distribution['excellent']++;
            } elseif ($audit['health_score'] >= 75) {
                $distribution['good']++;
            } else {
                $distribution['needs_improvement']++;
            }

            $sumMetadata    += $audit['scores']['metadata'];
            $sumQuestions   += $audit['scores']['questions'];
            $sumExplanation += $audit['scores']['explanation'];
            $sumDifficulty  += $audit['scores']['difficulty'];
        }

        $totalCount = max(1, count($audits));

        // Top healthy repository
        $sortedByHealthDesc = $audits;
        usort($sortedByHealthDesc, fn($a, $b) => $b['health_score'] <=> $a['health_score']);
        $topHealthy = $sortedByHealthDesc[0] ?? null;

        // Lowest health repository
        $sortedByHealthAsc = $audits;
        usort($sortedByHealthAsc, fn($a, $b) => $a['health_score'] <=> $b['health_score']);
        $lowestRepo = $sortedByHealthAsc[0] ?? null;

        $coverageService = app(\App\Services\AclCoverageService::class);
        $coverageReport  = $coverageService->getCategoryCoverageReport();
        $overallCoverage = 0;
        if (is_array($coverageReport) && isset($coverageReport['overall_percentage'])) {
            $overallCoverage = $coverageReport['overall_percentage'];
        }

        return [
            'global_summary'        => $globalSummary,
            'avg_health_score'      => $globalSummary['avg_health_score'],
            'distribution'          => $distribution,
            'metadata_completion'   => round($sumMetadata / $totalCount),
            'question_completeness' => round($sumQuestions / $totalCount),
            'explanation_coverage'  => round($sumExplanation / $totalCount),
            'difficulty_balance'    => round($sumDifficulty / $totalCount),
            'overall_coverage'      => $overallCoverage,
            'top_healthy_repo'      => $topHealthy,
            'lowest_repo'           => $lowestRepo,
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
