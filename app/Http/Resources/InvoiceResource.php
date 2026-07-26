<?php

namespace App\Http\Resources;

use App\Modules\Commerce\Domain\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Invoice $resource
 */
class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'invoice_number' => $this->resource->invoice_number,
            'status' => $this->resource->status->value ?? $this->resource->status,
            'amount' => $this->resource->amount,
            'due_date' => $this->resource->due_date?->toIso8601String(),
            'paid_at' => $this->resource->paid_at?->toIso8601String(),
            'created_at' => $this->resource->created_at?->toIso8601String(),
        ];
    }
}
