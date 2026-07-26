<?php

namespace App\Http\Resources;

use App\Modules\Academic\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Course $resource
 */
class CourseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'title' => $this->resource->title,
            'slug' => $this->resource->slug,
            'code' => $this->resource->code,
            'description' => $this->resource->description,
            'level' => $this->resource->level->value ?? $this->resource->level,
            'is_published' => $this->resource->is_published,
            'created_at' => $this->resource->created_at?->toIso8601String(),
        ];
    }
}
