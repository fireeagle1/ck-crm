<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\View\View;

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

        $order->load('items');

        return view('portal.orders.show', compact('order'));
    }
}
