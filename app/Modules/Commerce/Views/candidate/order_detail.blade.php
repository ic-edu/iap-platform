<x-candidate-layout>
    <div class="max-w-4xl mx-auto space-y-6">
        {{-- Breadcrumb --}}
        <div class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
            <a href="{{ route('candidate.orders.index') }}" class="hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">&larr; Back to Orders</a>
            <span>/</span>
            <span class="text-slate-700 dark:text-slate-200 font-mono font-medium truncate">{{ $order->order_number }}</span>
        </div>

        {{-- Order Card --}}
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 sm:p-8 shadow-sm space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-slate-100 dark:border-slate-800">
                <div class="space-y-1">
                    <span class="text-[10px] uppercase font-bold text-slate-400 block tracking-wider">Order Reference</span>
                    <h1 class="text-2xl font-black font-mono text-slate-900 dark:text-white">{{ $order->order_number }}</h1>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Placed on {{ $order->created_at?->format('d F Y, H:i') }}</p>
                </div>
                <div class="flex items-center gap-3">
                    <x-status-badge :status="is_object($order->status) ? $order->status->value : $order->status" />
                </div>
            </div>

            {{-- Items Table --}}
            <div class="space-y-3">
                <h2 class="text-xs font-bold uppercase tracking-wider text-slate-400">Ordered Items</h2>
                <div class="border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 dark:bg-slate-950 text-slate-500 dark:text-slate-400 font-bold border-b border-slate-200 dark:border-slate-800">
                            <tr>
                                <th class="p-4">Item</th>
                                <th class="p-4 text-center">Qty</th>
                                <th class="p-4 text-right">Price</th>
                                <th class="p-4 text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
                            @foreach($order->items as $item)
                            <tr>
                                <td class="p-4">
                                    <span class="font-bold text-slate-900 dark:text-white block">{{ $item->product?->title ?? 'Package Item' }}</span>
                                    <span class="text-[11px] text-slate-500 dark:text-slate-400 font-mono">{{ $item->product?->slug }}</span>
                                </td>
                                <td class="p-4 text-center font-bold text-slate-900 dark:text-white">{{ $item->quantity }}</td>
                                <td class="p-4 text-right text-slate-600 dark:text-slate-300">IDR {{ number_format($item->price) }}</td>
                                <td class="p-4 text-right font-bold text-slate-900 dark:text-white">IDR {{ number_format($item->quantity * $item->price) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Financial Summary --}}
            <div class="p-5 rounded-2xl bg-slate-50 dark:bg-slate-950/60 border border-slate-100 dark:border-slate-800 space-y-2 text-xs">
                <div class="flex justify-between text-slate-600 dark:text-slate-400">
                    <span>Subtotal</span>
                    <span class="font-semibold text-slate-900 dark:text-white">IDR {{ number_format($order->subtotal) }}</span>
                </div>

                @if($order->discount > 0 || $order->coupon)
                    <div class="flex justify-between text-emerald-600 dark:text-emerald-400 font-semibold">
                        <div class="flex items-center gap-1.5">
                            <span>Voucher Discount ({{ $order->coupon?->code }})</span>
                            @if($order->coupon?->campaign)
                                <span class="text-[10px] px-1.5 py-0.5 rounded bg-emerald-100 dark:bg-emerald-950 text-emerald-800 dark:text-emerald-300 font-normal">
                                    {{ $order->coupon->campaign->name }}
                                </span>
                            @endif
                        </div>
                        <span>- IDR {{ number_format($order->discount) }}</span>
                    </div>

                    <div class="flex justify-between text-slate-600 dark:text-slate-400">
                        <span>Taxable Subtotal</span>
                        <span class="font-semibold text-slate-900 dark:text-white">IDR {{ number_format($order->subtotal - $order->discount) }}</span>
                    </div>
                @endif

                <div class="flex justify-between text-slate-600 dark:text-slate-400">
                    <span>Tax (11%)</span>
                    <span class="font-semibold text-slate-900 dark:text-white">IDR {{ number_format($order->tax) }}</span>
                </div>
                <div class="flex justify-between pt-2 border-t border-slate-200 dark:border-slate-800 text-base font-black text-slate-900 dark:text-white">
                    <span>Grand Total</span>
                    <span class="text-indigo-600 dark:text-indigo-400">IDR {{ number_format($order->grand_total) }}</span>
                </div>
            </div>

            {{-- Invoice & Payment Actions --}}
            @if($order->invoice)
            <div class="p-5 rounded-2xl bg-indigo-50/50 dark:bg-indigo-950/20 border border-indigo-100 dark:border-indigo-900/30 flex flex-col sm:flex-row items-center justify-between gap-4">
                <div>
                    <span class="text-[10px] uppercase font-bold text-indigo-700 dark:text-indigo-400 block tracking-wider">Associated Invoice</span>
                    <p class="text-sm font-bold font-mono text-slate-900 dark:text-white mt-0.5">{{ $order->invoice->invoice_number }}</p>
                    <span class="text-xs text-slate-500 dark:text-slate-400">Status: <x-status-badge :status="$order->invoice->status" /></span>
                </div>
                <a href="{{ route('candidate.invoices.show', $order->invoice->id) }}" class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold transition-all shadow-md shadow-indigo-600/20">
                    View Invoice &amp; Payment &rarr;
                </a>
            </div>
            @endif
        </div>
    </div>
</x-candidate-layout>
