<?php

namespace App\Modules\Organization\Listeners;

use App\Modules\Commerce\Events\PaymentConfirmed;
use App\Modules\Organization\Services\OrganizationEntitlementProvisioner;
use Illuminate\Support\Facades\Log;

class ProvisionOrganizationEntitlementsOnPayment
{
    public function __construct(
        protected OrganizationEntitlementProvisioner $provisioner
    ) {}

    /**
     * Handle the PaymentConfirmed event to provision organization seat entitlements.
     */
    public function handle(PaymentConfirmed $event): void
    {
        try {
            $this->provisioner->provisionFromPayment($event->payment);
        } catch (\Throwable $e) {
            Log::error('ProvisionOrganizationEntitlementsOnPayment failed: ' . $e->getMessage(), [
                'payment_id' => $event->payment->id ?? null,
                'exception'  => $e,
            ]);
            throw $e;
        }
    }
}
