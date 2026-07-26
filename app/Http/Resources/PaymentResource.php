<?php

namespace App\Http\Resources;

use App\Modules\Commerce\Domain\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Payment $resource
 */
class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'invoice_id' => $this->resource->invoice_id,
            'payment_gateway' => $this->resource->payment_gateway,
            'transaction_id' => $this->resource->transaction_id,
            'status' => $this->resource->status->value ?? $this->resource->status,
            'amount' => $this->resource->amount,
            'confirmed_at' => $this->resource->confirmed_at?->toIso8601String(),
            'created_at' => $this->resource->created_at?->toIso8601String(),
        ];
    }
}
