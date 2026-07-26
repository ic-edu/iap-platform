<?php

namespace App\Http\Resources;

use App\Modules\QuestionBank\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Question $resource
 */
class QuestionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'question_bank_id' => $this->resource->question_bank_id,
            'question_type' => $this->resource->question_type->value ?? $this->resource->question_type,
            'question_text' => $this->resource->question_text ?? '',
            'difficulty' => $this->resource->difficulty,
            'points' => $this->resource->points,
            'created_at' => $this->resource->created_at?->toIso8601String(),
        ];
    }
}
