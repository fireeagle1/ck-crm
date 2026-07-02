<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OneOffInvoiceController extends Controller
{
    public function create(): View
    {
        $customers = Customer::whereNotNull('stripe_customer_id')
            ->orderBy('company_name')
            ->get();

        return view('admin.invoices.create', compact('customers'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'company_id' => 'required|exists:customers,company_id',
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string|max:255',
            'items.*.amount' => 'required|numeric|min:0.01',
            'days_until_due' => 'required|integer|min:1|max:90',
        ]);

        $customer = Customer::find($validated['company_id']);

        if (!$customer->stripe_customer_id) {
            return back()->with('error', 'This customer does not have a Stripe account linked.');
        }

        try {
            // Create invoice items
            foreach ($validated['items'] as $item) {
                \Stripe\InvoiceItem::create([
                    'customer' => $customer->stripe_customer_id,
                    'amount' => (int) round($item['amount'] * 100), // Convert to pence
                    'currency' => 'gbp',
                    'description' => $item['description'],
                ]);
            }

            // Create and send the invoice
            $invoice = \Stripe\Invoice::create([
                'customer' => $customer->stripe_customer_id,
                'collection_method' => 'send_invoice',
                'days_until_due' => $validated['days_until_due'],
                'auto_advance' => true,
            ]);

            // Finalize and send
            $invoice->finalizeInvoice();
            $invoice->sendInvoice();

            return redirect()->route('admin.invoices.index')
                ->with('success', "Invoice created and sent to {$customer->company_name}. Total: £" . number_format(collect($validated['items'])->sum('amount'), 2));
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Stripe error: ' . $e->getMessage());
        }
    }
}
