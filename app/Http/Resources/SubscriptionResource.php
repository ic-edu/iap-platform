<?php

namespace App\Http\Resources;

use App\Modules\Commerce\Domain\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Subscription $resource
 */
class SubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'plan_type' => $this->resource->plan_type,
            'status' => $this->resource->status->value ?? $this->resource->status,
            'starts_at' => $this->resource->starts_at->toIso8601String(),
            'ends_at' => $this->resource->ends_at?->toIso8601String(),
            'created_at' => $this->resource->created_at?->toIso8601String(),
        ];
    }
}
