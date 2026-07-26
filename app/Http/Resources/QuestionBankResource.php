<?php

namespace App\Http\Resources;

use App\Modules\QuestionBank\Models\QuestionBank;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property QuestionBank $resource
 */
class QuestionBankResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'title' => $this->resource->title,
            'code' => $this->resource->code,
            'description' => $this->resource->description,
            'is_active' => $this->resource->is_active ?? true,
            'created_at' => $this->resource->created_at?->toIso8601String(),
        ];
    }
}
