<?php

namespace App\Http\Resources;

use App\Modules\Commerce\Domain\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Order $resource
 */
class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'order_number' => $this->resource->order_number,
            'status' => $this->resource->status->value ?? $this->resource->status,
            'subtotal' => $this->resource->subtotal,
            'discount' => $this->resource->discount,
            'tax' => $this->resource->tax,
            'grand_total' => $this->resource->grand_total,
            'created_at' => $this->resource->created_at?->toIso8601String(),
        ];
    }
}
