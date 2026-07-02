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
            // Create pending invoice items first
            foreach ($validated['items'] as $item) {
                \Stripe\InvoiceItem::create([
                    'customer' => $customer->stripe_customer_id,
                    'amount' => (int) round($item['amount'] * 100),
                    'currency' => 'gbp',
                    'description' => $item['description'],
                ]);
            }

            // Create the invoice (it automatically picks up pending items for this customer)
            $invoice = \Stripe\Invoice::create([
                'customer' => $customer->stripe_customer_id,
                'collection_method' => 'send_invoice',
                'days_until_due' => $validated['days_until_due'],
            ]);

            // Finalize it (locks the line items)
            $invoice = $invoice->finalizeInvoice();

            // Send it
            $invoice->sendInvoice();

            // Sync this invoice to our local DB immediately
            \App\Models\Invoice::updateOrCreate(
                ['stripe_invoice_id' => $invoice->id],
                [
                    'company_id' => $customer->company_id,
                    'invoice_status' => 'Unpaid',
                    'invoice_amount' => $invoice->amount_due / 100,
                    'invoice_date' => date('Y-m-d', $invoice->created),
                    'due_date' => $invoice->due_date ? date('Y-m-d', $invoice->due_date) : null,
                    'stripe_hosted_url' => $invoice->hosted_invoice_url ?? null,
                    'invoice_items' => collect($validated['items'])->map(fn($i) => [
                        'description' => $i['description'],
                        'amount' => $i['amount'],
                    ])->toArray(),
                ]
            );

            return redirect()->route('admin.invoices.index')
                ->with('success', "Invoice created and sent to {$customer->company_name}. Total: £" . number_format(collect($validated['items'])->sum('amount'), 2));
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Stripe error: ' . $e->getMessage());
        }
    }
}
