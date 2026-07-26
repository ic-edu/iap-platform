<?php

namespace App\Http\Resources;

use App\Modules\Assessment\Models\Test;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Test $resource
 */
class TestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'title' => $this->resource->title,
            'slug' => $this->resource->slug,
            'test_type' => $this->resource->test_type->value ?? $this->resource->test_type,
            'duration_minutes' => $this->resource->duration_minutes,
            'pass_score' => $this->resource->pass_score,
            'is_published' => $this->resource->is_published,
            'created_at' => $this->resource->created_at?->toIso8601String(),
        ];
    }
}
