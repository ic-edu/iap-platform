<?php

namespace App\Http\Resources;

use App\Modules\Assessment\Models\Attempt;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Attempt
 */
class ResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $testTitle = $this->test ? $this->test->title : '';
        $candidateName = $this->user ? $this->user->name : '';
        $passScore = $this->test ? $this->test->pass_score : 70;
        $score = $this->score ?? 0;

        return [
            'attempt_id' => $this->id,
            'test_title' => $testTitle,
            'candidate_name' => $candidateName,
            'score' => $score,
            'is_passed' => $score >= $passScore,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
        ];
    }
}
