<?php

namespace App\Http\Resources;

use App\Modules\Commerce\Domain\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Product $resource
 */
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'title' => $this->resource->title,
            'slug' => $this->resource->slug,
            'product_type' => $this->resource->product_type,
            'price' => $this->resource->price,
            'is_active' => $this->resource->is_active,
            'is_featured' => $this->resource->is_featured,
            'created_at' => $this->resource->created_at?->toIso8601String(),
        ];
    }
}
