<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->get('status', 'all');
        $search = $request->get('q', '');

        $query = Invoice::with('customer');

        if ($status !== 'all') {
            $query->where('invoice_status', ucfirst($status));
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('stripe_invoice_id', 'LIKE', "%{$search}%")
                  ->orWhere('invoice_id', $search)
                  ->orWhereHas('customer', function ($cq) use ($search) {
                      $cq->where('company_name', 'LIKE', "%{$search}%");
                  });
            });
        }

        $invoices = $query->orderByDesc('invoice_date')->paginate(20);

        $totalUnpaid = Invoice::where('invoice_status', 'Unpaid')->sum('invoice_amount');
        $overdueCount = Invoice::where('invoice_status', 'Unpaid')
            ->whereDate('due_date', '<', now())->count();
        $totalPaidThisMonth = Invoice::where('invoice_status', 'Paid')
            ->whereMonth('paid_date', now()->month)
            ->whereYear('paid_date', now()->year)
            ->sum('invoice_amount');

        return view('admin.invoices.index', compact('invoices', 'status', 'search', 'totalUnpaid', 'overdueCount', 'totalPaidThisMonth'));
    }

    public function remind(Request $request, Invoice $invoice)
    {
        $invoice->load('customer');

        if (!$invoice->customer) {
            return back()->with('error', 'No customer linked to this invoice.');
        }

        // Find the customer's primary contact
        $contact = User::where('company_id', $invoice->company_id)->first();

        if (!$contact?->email) {
            return back()->with('error', 'No contact email found for this customer.');
        }

        try {
            Mail::send('emails.invoice-reminder', [
                'invoice' => $invoice,
                'recipientName' => $contact->first_name ?? 'there',
                'customerName' => $invoice->customer->company_name,
            ], function ($message) use ($contact, $invoice) {
                $message->to($contact->email)
                        ->subject('Payment Reminder — Invoice #' . $invoice->invoice_id);
            });
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to send reminder: ' . $e->getMessage());
        }

        return back()->with('success', "Payment reminder sent to {$contact->email}.");
    }
}
