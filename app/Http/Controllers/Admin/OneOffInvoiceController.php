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
        // Debug: if items are empty, it's a form submission issue
        if (empty($request->input('items'))) {
            return back()->withInput()->with('error', 'No line items received. Please add at least one item.');
        }

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
            // Create the invoice as a draft first
            $stripeInvoice = \Stripe\Invoice::create([
                'customer' => $customer->stripe_customer_id,
                'collection_method' => 'send_invoice',
                'days_until_due' => (int) $validated['days_until_due'],
                'pending_invoice_items_behavior' => 'exclude',
            ]);

            // Create invoice items attached to the draft invoice
            foreach ($validated['items'] as $item) {
                \Stripe\InvoiceItem::create([
                    'customer' => $customer->stripe_customer_id,
                    'invoice' => $stripeInvoice->id,
                    'amount' => (int) round(floatval($item['amount']) * 100),
                    'currency' => 'gbp',
                    'description' => $item['description'],
                ]);
            }

            // Finalize (locks the line items onto the invoice)
            $stripeInvoice = \Stripe\Invoice::retrieve($stripeInvoice->id);
            $stripeInvoice->finalizeInvoice();

            // Re-retrieve to get the hosted URL
            $stripeInvoice = \Stripe\Invoice::retrieve($stripeInvoice->id);

            // Send it to the customer
            $stripeInvoice->sendInvoice();

            // Save locally
            \App\Models\Invoice::create([
                'company_id' => $customer->company_id,
                'stripe_invoice_id' => $stripeInvoice->id,
                'stripe_hosted_url' => $stripeInvoice->hosted_invoice_url ?? null,
                'invoice_status' => 'Unpaid',
                'invoice_amount' => $stripeInvoice->amount_due / 100,
                'invoice_date' => now()->format('Y-m-d'),
                'due_date' => $stripeInvoice->due_date ? date('Y-m-d', $stripeInvoice->due_date) : now()->addDays($validated['days_until_due'])->format('Y-m-d'),
                'invoice_items' => collect($validated['items'])->map(fn($i) => [
                    'description' => $i['description'],
                    'amount' => floatval($i['amount']),
                ])->toArray(),
            ]);

            return redirect()->route('admin.invoices.index')
                ->with('success', "Invoice #{$stripeInvoice->number} created and sent to {$customer->company_name}.");
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Stripe error: ' . $e->getMessage());
        }
    }
}
