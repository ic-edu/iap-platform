<?php

namespace App\Modules\Certificate\Events;

use App\Modules\Certificate\Models\Certificate;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CertificateIssued
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Certificate $certificate
    ) {}
}
