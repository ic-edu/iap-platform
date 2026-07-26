<?php

namespace App\Http\Resources;

use App\Modules\Certificate\Models\Certificate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Certificate
 */
class CertificateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'certificate_number' => $this->certificate_number,
            'verification_code' => $this->verification_code,
            'recipient_name' => $this->recipient_name ?? '',
            'status' => $this->status->value ?? $this->status,
            'issued_at' => $this->issued_at?->toIso8601String(),
        ];
    }
}
