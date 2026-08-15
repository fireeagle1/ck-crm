<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class InvoiceController extends Controller
{
    /**
     * Paginated list of invoices with optional status filter.
     * Excludes Void and Uncollectible by default.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min($request->integer('per_page', 15), 100);

        $query = Invoice::with('customer');

        // Exclude Void/Uncollectible by default
        $query->whereNotIn('invoice_status', Invoice::EXCLUDED_STATUSES);

        if ($status = $request->input('status')) {
            $query->where('invoice_status', $status);
        }

        $invoices = $query->orderByDesc('invoice_date')->paginate($perPage);

        return response()->json([
            'data' => $invoices->map(fn (Invoice $invoice) => [
                'invoice_id' => $invoice->invoice_id,
                'invoice_status' => $invoice->invoice_status,
                'invoice_amount' => (float) $invoice->invoice_amount,
                'invoice_date' => $invoice->invoice_date?->toDateString(),
                'due_date' => $invoice->due_date?->toDateString(),
                'paid_date' => $invoice->paid_date?->toDateString(),
                'customer_name' => $invoice->customer?->company_name,
            ]),
            'meta' => [
                'current_page' => $invoices->currentPage(),
                'last_page' => $invoices->lastPage(),
                'per_page' => $invoices->perPage(),
                'total' => $invoices->total(),
            ],
        ]);
    }

    /**
     * Create a new one-off invoice.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'company_id' => 'required|integer|exists:customers,company_id',
            'invoice_amount' => 'required|numeric|min:0.01|decimal:0,2',
            'invoice_items' => 'required',
            'due_date' => 'required|date',
        ]);

        $invoice = Invoice::create([
            'company_id' => $validated['company_id'],
            'invoice_amount' => $validated['invoice_amount'],
            'invoice_items' => $validated['invoice_items'],
            'due_date' => $validated['due_date'],
            'invoice_status' => 'Unpaid',
            'invoice_date' => now()->toDateString(),
        ]);

        return response()->json(['data' => $invoice], 201);
    }

    /**
     * Send a payment reminder for an invoice.
     */
    public function remind(Invoice $invoice): JsonResponse
    {
        if ($invoice->invoice_status === 'Paid') {
            return response()->json(['message' => 'Invoice is already paid.'], 422);
        }

        $invoice->load('customer');

        if (! $invoice->customer) {
            return response()->json(['message' => 'No customer linked to this invoice.'], 422);
        }

        // Find the customer's primary contact
        $contact = User::where('company_id', $invoice->company_id)->first();

        if (! $contact?->email) {
            return response()->json(['message' => 'No contact email found for this customer.'], 422);
        }

        Mail::send('emails.invoice-reminder', [
            'invoice' => $invoice,
            'recipientName' => $contact->first_name ?? 'there',
            'customerName' => $invoice->customer->company_name,
        ], function ($message) use ($contact, $invoice) {
            $message->to($contact->email)
                    ->subject('Payment Reminder — Invoice #' . $invoice->invoice_id);
        });

        return response()->json(['message' => 'Payment reminder sent successfully.']);
    }
}
