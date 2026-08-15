<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $orders = Order::where('company_id', $request->user()->company_id)
            ->with('items')
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('portal.orders.index', compact('orders'));
    }

    public function show(Request $request, Order $order): View
    {
        abort_unless($order->company_id === $request->user()->company_id, 404);

        $order->load('items.booking', 'items.product');

        return view('portal.orders.show', compact('order'));
    }

    /**
     * Download the generated PDF invoice for an order.
     * Only allows download if the order belongs to the authenticated customer.
     *
     * Requirements: 18.2, 18.4
     */
    public function downloadInvoice(Request $request, Order $order): StreamedResponse|RedirectResponse
    {
        abort_unless($order->company_id === $request->user()->company_id, 404);

        if (!$order->invoice_pdf_path || !Storage::disk('local')->exists($order->invoice_pdf_path)) {
            return redirect()->route('portal.orders.show', $order)
                ->with('error', 'No invoice PDF available for this order.');
        }

        return Storage::disk('local')->download(
            $order->invoice_pdf_path,
            'invoice-order-' . $order->id . '.pdf'
        );
    }
}
