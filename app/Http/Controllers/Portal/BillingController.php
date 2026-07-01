<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BillingController extends Controller
{
    public function portal(Request $request)
    {
        $customer = Customer::find($request->user()->company_id);

        if (!$customer?->stripe_customer_id) {
            return redirect()->route('portal.dashboard')
                ->with('error', 'No billing account linked.');
        }

        $session = \Stripe\BillingPortal\Session::create([
            'customer' => $customer->stripe_customer_id,
            'return_url' => route('portal.dashboard'),
        ]);

        return redirect($session->url);
    }

    public function invoices(Request $request): View
    {
        $invoices = Invoice::where('company_id', $request->user()->company_id)
            ->orderByDesc('invoice_date')
            ->paginate(15);

        return view('portal.invoices.index', compact('invoices'));
    }
}
