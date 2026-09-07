<?php

namespace App\Modules\Organization\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Commerce\Application\BillingEngine;
use App\Modules\Commerce\Application\CheckoutEngine;
use App\Modules\Commerce\Application\CommerceQuoteService;
use App\Modules\Commerce\Application\CouponEngine;
use App\Modules\Commerce\Domain\Enums\InvoiceStatus;
use App\Modules\Commerce\Domain\Enums\OrderStatus;
use App\Modules\Commerce\Domain\Enums\PaymentStatus;
use App\Modules\Commerce\Domain\Models\Invoice;
use App\Modules\Commerce\Domain\Models\Order;
use App\Modules\Commerce\Domain\Models\Payment;
use App\Modules\Commerce\Domain\Models\Product;
use App\Modules\Organization\Enums\MembershipRole;
use App\Modules\Organization\Enums\MembershipStatus;
use App\Modules\Organization\Enums\OrganizationStatus;
use App\Modules\Organization\Models\Organization;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class OrganizationCommerceController extends Controller
{
    public function __construct(
        protected CheckoutEngine $checkoutEngine,
        protected BillingEngine $billingEngine,
        protected CommerceQuoteService $quoteService,
        protected CouponEngine $couponEngine
    ) {}

    /**
     * Display Organization Purchases & Billing History.
     */
    public function purchases(Organization $organization): View
    {
        $orders = $organization->orders()
            ->with(['items.product', 'invoice.payments', 'coupon.campaign'])
            ->latest()
            ->paginate(15);

        return view('organization::purchases.index', compact('organization', 'orders'));
    }

    /**
     * Show New Purchase / Order Creation Form.
     */
    public function createPurchase(Organization $organization): View
    {
        if ($organization->status !== OrganizationStatus::Active) {
            abort(403, 'Purchases are only permitted for active organizations.');
        }

        $products = Product::where('is_active', true)->orderBy('price')->get();

        return view('organization::purchases.create', compact('organization', 'products'));
    }

    /**
     * Authoritative JSON quote calculation endpoint for Organization checkout.
     */
    public function quote(Request $request, Organization $organization): JsonResponse
    {
        if ($organization->status !== OrganizationStatus::Active) {
            return response()->json(['error' => 'Purchases are only permitted for active organizations.'], 403);
        }

        $validated = $request->validate([
            'product_id'   => ['required', 'exists:products,id'],
            'quantity'     => ['required', 'integer', 'min:1', 'max:10000'],
            'voucher_code' => ['nullable', 'string', 'max:50'],
        ]);

        $product = Product::findOrFail($validated['product_id']);
        $quantity = (int) $validated['quantity'];
        $voucherCode = $validated['voucher_code'] ?? null;

        $quote = $this->quoteService->getQuote(
            product: $product,
            quantity: $quantity,
            voucherCode: $voucherCode,
            user: $request->user(),
            organization: $organization
        );

        return response()->json($quote);
    }

    /**
     * Store New Organization Order and generate Invoice & Payment entry.
     */
    public function storePurchase(Request $request, Organization $organization): RedirectResponse
    {
        if ($organization->status !== OrganizationStatus::Active) {
            abort(403, 'Purchases are only permitted for active organizations.');
        }

        $user = $request->user();
        $membership = $organization->getMembership($user);

        if (!$membership || $membership->status !== MembershipStatus::Active) {
            abort(403, 'Unauthorized. Active organization membership required.');
        }

        if ($membership->role === MembershipRole::Member) {
            abort(403, 'Candidate Members are not authorized to create organization orders.');
        }

        $validated = $request->validate([
            'product_id'   => ['required', 'exists:products,id'],
            'quantity'     => ['required', 'integer', 'min:1', 'max:10000'],
            'voucher_code' => ['nullable', 'string', 'max:50'],
        ]);

        $product = Product::findOrFail($validated['product_id']);
        $quantity = (int) $validated['quantity'];
        $voucherCode = !empty($validated['voucher_code']) ? trim($validated['voucher_code']) : null;

        $coupon = null;
        if ($voucherCode) {
            $validation = $this->couponEngine->validateCoupon($voucherCode, $product, $user, $organization);
            if (!$validation['valid']) {
                return back()->withErrors(['voucher_code' => $validation['reason'] ?? 'Invalid voucher code.'])->withInput();
            }
            $coupon = $validation['coupon'];
        }

        $result = $this->checkoutEngine->checkout(
            user: $user,
            product: $product,
            quantity: $quantity,
            coupon: $coupon,
            organization: $organization
        );

        $order = $result['order'];
        $invoice = $result['invoice'];

        // Automatically create pending payment entry for invoice
        $payment = $this->billingEngine->createPayment($invoice, 'manual_transfer');

        ActivityLogger::log(
            action: 'ORG_ORDER_CREATED',
            description: "Created institutional order #{$order->order_number} for {$quantity} seats of '{$product->title}'" . ($coupon ? " (Voucher: {$coupon->code})" : ''),
            subject: $order,
            properties: [
                'organization_id' => $organization->id,
                'order_id'        => $order->id,
                'order_number'    => $order->order_number,
                'product_id'      => $product->id,
                'quantity'        => $quantity,
                'coupon_id'       => $coupon?->id,
                'subtotal'        => $order->subtotal,
                'discount'        => $order->discount,
                'tax'             => $order->tax,
                'grand_total'     => $order->grand_total,
                'initiated_by'    => $user->id,
            ]
        );

        return redirect()->route('organization.purchases.show', [$organization->slug, $order->id])
            ->with('status', "Order #{$order->order_number} created successfully. Please upload payment proof to proceed.");
    }

    /**
     * Display Single Order & Invoice Details with Payment Upload.
     */
    public function showOrder(Organization $organization, Order $order): View
    {
        if ((string) $order->organization_id !== (string) $organization->id) {
            abort(403, 'Order does not belong to this organization.');
        }

        $order->loadMissing(['items.product', 'invoice.payments', 'coupon.campaign']);
        $invoice = $order->invoice;
        $payment = $invoice?->payments->first();

        return view('organization::purchases.show', compact('organization', 'order', 'invoice', 'payment'));
    }

    /**
     * Upload Bank Transfer Payment Proof for Institutional Order.
     */
    public function uploadProof(Request $request, Organization $organization, Order $order): RedirectResponse
    {
        if ((string) $order->organization_id !== (string) $organization->id) {
            abort(403, 'Order does not belong to this organization.');
        }

        if ($order->status !== OrderStatus::Pending) {
            return back()->withErrors(['error' => 'Payment proof can only be uploaded for pending orders.']);
        }

        $request->validate([
            'payment_proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'proof_file'    => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'sender_bank'   => ['nullable', 'string', 'max:100'],
            'sender_name'   => ['nullable', 'string', 'max:255'],
            'payment_reference' => ['nullable', 'string', 'max:255'],
            'notes'         => ['nullable', 'string', 'max:500'],
        ]);

        $file = $request->file('payment_proof') ?? $request->file('proof_file');
        if (!$file) {
            return back()->withErrors(['payment_proof' => 'Please provide a valid payment proof file (JPG, PNG, PDF).']);
        }

        $invoice = $order->invoice;
        if (!$invoice) {
            $invoice = Invoice::create([
                'order_id'       => $order->id,
                'user_id'        => $order->user_id,
                'invoice_number' => 'INV-' . strtoupper(uniqid()),
                'amount'         => $order->grand_total,
                'status'         => InvoiceStatus::Unpaid,
            ]);
        }

        $payment = $invoice->payments()->where('status', PaymentStatus::Pending)->first();
        if (!$payment) {
            $payment = Payment::create([
                'invoice_id'       => $invoice->id,
                'user_id'          => $order->user_id,
                'reference_number' => 'PAY-' . strtoupper(uniqid()),
                'payment_method'   => 'bank_transfer',
                'payment_gateway'  => 'manual',
                'amount'           => $order->grand_total,
                'status'           => PaymentStatus::Pending,
            ]);
        }

        $filename = 'proof_' . $payment->id . '_' . time() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('payment-proofs', $filename, 'public');

        $notesParts = array_filter([
            $request->input('notes'),
            $request->filled('sender_bank') ? "Bank: {$request->input('sender_bank')}" : null,
            $request->filled('sender_name') ? "Sender: {$request->input('sender_name')}" : null,
        ]);
        $notes = !empty($notesParts) ? implode(' | ', $notesParts) : null;

        $payment->update([
            'proof_path'          => $path,
            'proof_original_name' => $file->getClientOriginalName(),
            'proof_uploaded_at'   => now(),
            'proof_notes'         => $notes,
            'transaction_id'      => $request->input('payment_reference') ?: $payment->transaction_id,
        ]);

        ActivityLogger::log(
            action: 'ORG_PAYMENT_SUBMITTED',
            description: "Submitted payment proof for order #{$order->order_number} (Ref: {$payment->reference_number})",
            subject: $payment,
            properties: [
                'organization_id'  => $organization->id,
                'payment_id'       => $payment->id,
                'reference_number' => $payment->reference_number,
                'amount'           => $payment->amount,
                'uploaded_by'      => $request->user()->id,
            ]
        );

        return back()->with('status', 'Payment proof submitted successfully. Awaiting Finance verification and approval.');
    }
}
