<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->get('status', 'all');

        $query = Invoice::with('customer');

        if ($status !== 'all') {
            $query->where('invoice_status', ucfirst($status));
        }

        $invoices = $query->orderByDesc('invoice_date')->paginate(20);

        // Summary stats
        $totalUnpaid = Invoice::where('invoice_status', 'Unpaid')->sum('invoice_amount');
        $totalPaidThisMonth = Invoice::where('invoice_status', 'Paid')
            ->whereMonth('paid_date', now()->month)
            ->whereYear('paid_date', now()->year)
            ->sum('invoice_amount');

        return view('admin.invoices.index', compact('invoices', 'status', 'totalUnpaid', 'totalPaidThisMonth'));
    }
}
