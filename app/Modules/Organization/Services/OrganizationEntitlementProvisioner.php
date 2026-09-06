<?php

namespace App\Modules\Organization\Services;

use App\Modules\Commerce\Domain\Enums\PaymentStatus;
use App\Modules\Commerce\Domain\Models\Payment;
use App\Modules\Organization\Enums\EntitlementStatus;
use App\Modules\Organization\Models\OrganizationEntitlement;
use App\Services\ActivityLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OrganizationEntitlementProvisioner
{
    /**
     * Provision organization seat entitlements from a confirmed institutional payment.
     *
     * @return Collection<int, OrganizationEntitlement>
     */
    public function provisionFromPayment(Payment $payment): Collection
    {
        $invoice = $payment->invoice;
        if (!$invoice) {
            return collect();
        }

        $order = $invoice->order;
        if (!$order || !$order->organization_id) {
            // Not an institutional order
            return collect();
        }

        if ($payment->status !== PaymentStatus::Success) {
            return collect();
        }

        return DB::transaction(function () use ($payment, $order) {
            $entitlements = collect();

            foreach ($order->items as $item) {
                // Idempotently create entitlement per order_item_id
                $wasRecentlyCreated = false;
                $entitlement = OrganizationEntitlement::where('order_item_id', $item->id)->lockForUpdate()->first();

                if (!$entitlement) {
                    $entitlement = OrganizationEntitlement::create([
                        'organization_id' => $order->organization_id,
                        'order_item_id'   => $item->id,
                        'product_id'      => $item->product_id,
                        'total_seats'     => max(1, (int) $item->quantity),
                        'status'          => EntitlementStatus::Active,
                        'activated_at'    => now(),
                        'valid_from'      => now(),
                        'expires_at'      => null,
                    ]);
                    $wasRecentlyCreated = true;
                }

                if ($wasRecentlyCreated) {
                    ActivityLogger::log(
                        action: 'ORG_ENTITLEMENT_PROVISIONED',
                        description: "Provisioned {$entitlement->total_seats} seats for product '{$item->product?->title}' in organization",
                        subject: $entitlement,
                        properties: [
                            'organization_id' => $order->organization_id,
                            'entitlement_id'  => $entitlement->id,
                            'order_id'        => $order->id,
                            'order_item_id'   => $item->id,
                            'payment_id'      => $payment->id,
                            'product_id'      => $item->product_id,
                            'total_seats'     => $entitlement->total_seats,
                        ]
                    );
                }

                $entitlements->push($entitlement);
            }

            return $entitlements;
        });
    }
}
