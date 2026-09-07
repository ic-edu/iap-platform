<?php

namespace App\Modules\Commerce\Application;

use App\Models\User;
use App\Modules\Commerce\Domain\Enums\OrderStatus;
use App\Modules\Commerce\Domain\Models\Coupon;
use App\Modules\Commerce\Domain\Models\CouponRedemption;
use App\Modules\Commerce\Domain\Models\Invoice;
use App\Modules\Commerce\Domain\Models\Order;
use App\Modules\Commerce\Domain\Models\OrderItem;
use App\Modules\Commerce\Domain\Models\Product;
use App\Modules\Commerce\Events\CheckoutCompleted;
use App\Modules\Organization\Models\Organization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CheckoutEngine
{
    public function __construct(
        protected PricingEngine $pricingEngine,
        protected InvoiceEngine $invoiceEngine
    ) {}

    /**
     * Process checkout for user, product, optional coupon, and optional owning organization.
     *
     * @return array{order: Order, invoice: Invoice}
     */
    public function checkout(
        User $user,
        Product $product,
        int $quantity = 1,
        ?Coupon $coupon = null,
        ?Organization $organization = null
    ): array {
        return DB::transaction(function () use ($user, $product, $quantity, $coupon, $organization) {
            // Idempotency: Check if an active pending order already exists for this customer and product
            $query = Order::where('status', OrderStatus::Pending)
                ->whereHas('items', fn($q) => $q->where('product_id', $product->id));

            if ($organization) {
                $query->where('organization_id', $organization->id);
            } else {
                $query->where('user_id', $user->id)->whereNull('organization_id');
            }

            $existingOrder = $query->with(['invoice', 'items', 'redemption'])->first();

            if ($existingOrder && $existingOrder->invoice) {
                return [
                    'order'   => $existingOrder,
                    'invoice' => $existingOrder->invoice,
                ];
            }

            // Concurrency lock and capacity check on coupon if provided
            $lockedCoupon = null;
            if ($coupon) {
                $lockedCoupon = Coupon::where('id', $coupon->id)->lockForUpdate()->first();
                if (!$lockedCoupon || $lockedCoupon->getAvailableUses() <= 0) {
                    throw new InvalidArgumentException('Voucher usage limit reached or voucher is unavailable.');
                }
            }

            $pricing = $this->pricingEngine->calculate($product, $quantity, $lockedCoupon);

            $orderNumber = 'ORD-'.now()->format('Ymd').'-'.strtoupper(Str::random(4));

            $order = Order::create([
                'user_id'         => $user->id,
                'organization_id' => $organization?->id,
                'coupon_id'       => $lockedCoupon?->id,
                'order_number'    => $orderNumber,
                'status'          => OrderStatus::Pending,
                'subtotal'        => $pricing['base_price'],
                'discount'        => $pricing['discount'],
                'tax'             => $pricing['tax'],
                'grand_total'     => $pricing['grand_total'],
            ]);

            OrderItem::create([
                'order_id'   => $order->id,
                'product_id' => $product->id,
                'quantity'   => $quantity,
                'price'      => $product->price,
                'total'      => $pricing['grand_total'],
            ]);

            // Create reserved coupon redemption record
            if ($lockedCoupon) {
                CouponRedemption::create([
                    'coupon_id'       => $lockedCoupon->id,
                    'order_id'        => $order->id,
                    'user_id'         => $user->id,
                    'organization_id' => $organization?->id,
                    'status'          => 'reserved',
                    'discount_amount' => $pricing['discount'],
                    'reserved_at'     => now(),
                ]);
            }

            $invoice = $this->invoiceEngine->generateInvoice($order);

            event(new CheckoutCompleted($order, $invoice));

            return [
                'order'   => $order,
                'invoice' => $invoice,
            ];
        });
    }
}
