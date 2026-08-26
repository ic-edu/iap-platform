<?php

namespace App\Modules\Commerce\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Commerce\Application\BillingEngine;
use App\Modules\Commerce\Application\CheckoutEngine;
use App\Modules\Commerce\Application\PricingEngine;
use App\Modules\Commerce\Domain\Models\Invoice;
use App\Modules\Commerce\Domain\Models\Order;
use App\Modules\Commerce\Domain\Models\Payment;
use App\Modules\Commerce\Domain\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class CandidateCommerceController extends Controller
{
    public function __construct(
        protected CheckoutEngine $checkoutEngine,
        protected BillingEngine $billingEngine,
        protected PricingEngine $pricingEngine
    ) {}

    /**
     * Check if a product is purchasable by candidates.
     */
    protected function isProductPurchasable(Product $product): bool
    {
        if (!$product->is_active) {
            return false;
        }

        if ($product->product_type === 'assessment' && $product->test_id) {
            $test = $product->test;
            if (!$test) {
                return false;
            }

            // Must be real_test and published/approved, not in draft/pending
            $isPublished = (bool) $test->is_published || in_array($test->status, ['published', 'approved'], true);
            $isNotDraft = !in_array($test->status, ['draft', 'pending', 'pending_approval', 'needs_revision'], true);

            return $test->isRealTest() && $isPublished && $isNotDraft;
        }

        return true;
    }

    /**
     * Display Candidate Store / Package Catalog.
     */
    public function store(Request $request): View
    {
        $products = Product::where('is_active', true)
            ->where(function ($query) {
                $query->where('product_type', '!=', 'assessment')
                    ->orWhereNull('test_id')
                    ->orWhereHas('test', function ($q) {
                        $q->where('assessment_mode', 'real_test')
                          ->where(function ($sq) {
                              $sq->where('is_published', true)
                                ->orWhereIn('status', ['published', 'approved']);
                          })
                          ->whereNotIn('status', ['draft', 'pending', 'pending_approval', 'needs_revision']);
                    });
            })
            ->with(['test', 'course', 'category'])
            ->latest()
            ->paginate(12);

        /** @var view-string $viewName */
        $viewName = 'commerce::candidate.store';

        return view($viewName, compact('products'));
    }

    /**
     * Display Product Detail.
     */
    public function showProduct(Request $request, Product $product): View
    {
        if (!$this->isProductPurchasable($product)) {
            abort(404, 'The requested package or assessment is currently not available for purchase.');
        }

        $pricing = $this->pricingEngine->calculate($product);

        /** @var view-string $viewName */
        $viewName = 'commerce::candidate.product_detail';

        return view($viewName, compact('product', 'pricing'));
    }

    /**
     * Display Candidate Checkout Confirmation Form.
     */
    public function checkoutForm(Request $request, Product $product): View
    {
        if (!$this->isProductPurchasable($product)) {
            abort(404, 'This product is not available for purchase.');
        }

        $user = $request->user();
        $pricing = $this->pricingEngine->calculate($product);

        // Check if there is already an existing pending order
        $existingOrder = Order::where('user_id', $user->id)
            ->where('status', 'pending')
            ->whereHas('items', fn($q) => $q->where('product_id', $product->id))
            ->with(['invoice'])
            ->first();

        /** @var view-string $viewName */
        $viewName = 'commerce::candidate.checkout';

        return view($viewName, compact('product', 'pricing', 'existingOrder'));
    }

    /**
     * Process Candidate Checkout.
     */
    public function processCheckout(Request $request, Product $product): RedirectResponse
    {
        if (!$this->isProductPurchasable($product)) {
            abort(404, 'This product is not available for checkout.');
        }

        $user = $request->user();

        // 1. Execute Checkout via CheckoutEngine
        $result = $this->checkoutEngine->checkout($user, $product);
        $order = $result['order'];
        $invoice = $result['invoice'];

        // 2. Automatically create initial pending payment record
        $payment = $this->billingEngine->createPayment($invoice, 'manual_transfer');

        return redirect()->route('candidate.invoices.show', $invoice->id)
            ->with('status', "Order {$order->order_number} created successfully. Please follow the instructions below to complete your payment.");
    }

    /**
     * Display Candidate Orders List.
     */
    public function myOrders(Request $request): View
    {
        $user = $request->user();
        $orders = Order::where('user_id', $user->id)
            ->with(['items.product', 'invoice.payments'])
            ->latest()
            ->paginate(10);

        /** @var view-string $viewName */
        $viewName = 'commerce::candidate.orders';

        return view($viewName, compact('orders'));
    }

    /**
     * Display Single Order Detail.
     */
    public function showOrder(Request $request, Order $order): View
    {
        if ((int) $order->user_id !== (int) $request->user()->id) {
            abort(403, 'Unauthorized access to order.');
        }

        $order->loadMissing(['items.product.test', 'invoice.payments']);

        /** @var view-string $viewName */
        $viewName = 'commerce::candidate.order_detail';

        return view($viewName, compact('order'));
    }

    /**
     * Display Candidate Invoices List.
     */
    public function myInvoices(Request $request): View
    {
        $user = $request->user();
        $invoices = Invoice::where('user_id', $user->id)
            ->with(['order.items.product', 'payments'])
            ->latest()
            ->paginate(10);

        /** @var view-string $viewName */
        $viewName = 'commerce::candidate.invoices';

        return view($viewName, compact('invoices'));
    }

    /**
     * Display Single Invoice with Payment Instructions.
     */
    public function showInvoice(Request $request, Invoice $invoice): View
    {
        if ((int) $invoice->user_id !== (int) $request->user()->id) {
            abort(403, 'Unauthorized access to invoice.');
        }

        $invoice->loadMissing(['order.items.product.test', 'payments']);

        // Ensure a pending payment record exists for candidate action
        $payment = $invoice->payments()->latest()->first();
        if (!$payment && $invoice->status === 'unpaid') {
            $payment = $this->billingEngine->createPayment($invoice, 'manual_transfer');
            $invoice->load('payments');
        }

        /** @var view-string $viewName */
        $viewName = 'commerce::candidate.invoice_detail';

        return view($viewName, compact('invoice', 'payment'));
    }

    /**
     * Initiate or re-trigger payment creation for an invoice.
     */
    public function initiatePayment(Request $request, Invoice $invoice): RedirectResponse
    {
        if ((int) $invoice->user_id !== (int) $request->user()->id) {
            abort(403, 'Unauthorized access to invoice.');
        }

        $payment = $this->billingEngine->createPayment($invoice, 'manual_transfer');

        return redirect()->route('candidate.payments.show', $payment->id)
            ->with('status', "Payment reference {$payment->reference_number} generated.");
    }

    /**
     * Display Candidate Payments List.
     */
    public function myPayments(Request $request): View
    {
        $user = $request->user();
        $payments = Payment::where('user_id', $user->id)
            ->with(['invoice.order.items.product'])
            ->latest()
            ->paginate(10);

        /** @var view-string $viewName */
        $viewName = 'commerce::candidate.payments';

        return view($viewName, compact('payments'));
    }

    /**
     * Display Single Payment Detail and Evidence Upload.
     */
    public function showPayment(Request $request, Payment $payment): View
    {
        if ((int) $payment->user_id !== (int) $request->user()->id) {
            abort(403, 'Unauthorized access to payment.');
        }

        $payment->loadMissing(['invoice.order.items.product.test']);

        /** @var view-string $viewName */
        $viewName = 'commerce::candidate.payment_detail';

        return view($viewName, compact('payment'));
    }

    /**
     * Upload Payment Evidence / Proof of Transfer.
     */
    public function uploadProof(Request $request, Payment $payment): RedirectResponse
    {
        if ((int) $payment->user_id !== (int) $request->user()->id) {
            abort(403, 'Unauthorized access to payment.');
        }

        if (is_object($payment->status) ? $payment->status->value !== 'pending' : $payment->status !== 'pending') {
            return back()->withErrors(['proof' => 'Payment proof can only be uploaded for pending payments.']);
        }

        $validated = $request->validate([
            'proof' => ['required', 'file', 'mimes:jpeg,png,jpg,pdf', 'max:5120'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $file = $request->file('proof');
        $storedPath = $file->store('payment_proofs', 'local');

        $payment->update([
            'proof_path'          => $storedPath,
            'proof_original_name' => $file->getClientOriginalName(),
            'proof_uploaded_at'   => now(),
            'proof_notes'         => $validated['notes'] ?? null,
        ]);

        return back()->with('status', 'Payment proof uploaded successfully. Awaiting Finance Officer verification.');
    }
}
