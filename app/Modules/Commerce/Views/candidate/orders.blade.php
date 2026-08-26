<x-candidate-layout>
    <div class="space-y-6">
        {{-- Header --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-slate-900 dark:text-white">My Orders</h1>
                <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-400 mt-1">Review your placed orders, package purchases, and billing status.</p>
            </div>
            <a href="{{ route('candidate.store') }}" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold transition-all shadow-md shadow-indigo-600/20 flex items-center gap-1.5 self-start sm:self-auto">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                </svg>
                <span>Store Catalog</span>
            </a>
        </div>

        {{-- Flash message --}}
        @if(session('status'))
        <div class="p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-700 dark:text-emerald-400 text-xs font-medium flex items-center gap-2">
            <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            <span>{{ session('status') }}</span>
        </div>
        @endif

        {{-- Orders Table --}}
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead class="bg-slate-50 dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800 text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                        <tr>
                            <th class="px-5 py-4">Order Number</th>
                            <th class="px-5 py-4">Package / Items</th>
                            <th class="px-5 py-4">Grand Total</th>
                            <th class="px-5 py-4">Order Status</th>
                            <th class="px-5 py-4">Invoice</th>
                            <th class="px-5 py-4">Date Placed</th>
                            <th class="px-5 py-4 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
                        @forelse($orders as $order)
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors">
                            <td class="px-5 py-4 font-mono font-bold text-indigo-600 dark:text-indigo-400">{{ $order->order_number }}</td>
                            <td class="px-5 py-4">
                                @foreach($order->items as $item)
                                    <div class="font-bold text-slate-900 dark:text-white">{{ $item->product?->title ?? 'Assessment Item' }}</div>
                                    <span class="text-[11px] text-slate-500 dark:text-slate-400">Qty: {{ $item->quantity }}</span>
                                @endforeach
                            </td>
                            <td class="px-5 py-4 font-extrabold text-slate-900 dark:text-white">IDR {{ number_format($order->grand_total) }}</td>
                            <td class="px-5 py-4">
                                <x-status-badge :status="is_object($order->status) ? $order->status->value : $order->status" />
                            </td>
                            <td class="px-5 py-4 font-mono">
                                @if($order->invoice)
                                    <a href="{{ route('candidate.invoices.show', $order->invoice->id) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline font-semibold">
                                        {{ $order->invoice->invoice_number }}
                                    </a>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-slate-500 dark:text-slate-400">{{ $order->created_at?->format('d M Y, H:i') }}</td>
                            <td class="px-5 py-4 text-right">
                                <a href="{{ route('candidate.orders.show', $order->id) }}" class="px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 text-[11px] font-bold transition-colors">
                                    Details &rarr;
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="p-12 text-center text-slate-500 dark:text-slate-400">
                                <span class="text-3xl block mb-2">📦</span>
                                You have not placed any orders yet.
                                <div class="mt-3">
                                    <a href="{{ route('candidate.store') }}" class="text-indigo-600 dark:text-indigo-400 font-bold hover:underline">Browse Store Catalog &rarr;</a>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($orders->hasPages())
            <div class="p-4 border-t border-slate-100 dark:border-slate-800">
                {{ $orders->links() }}
            </div>
            @endif
        </div>
    </div>
</x-candidate-layout>
