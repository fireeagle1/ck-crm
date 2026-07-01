<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    public function portal(Request $request)
    {
        $customer = Customer::find($request->user()->company_id);

        if (! $customer?->stripe_customer_id) {
            return redirect()->route('portal.dashboard')
                ->with('error', 'No billing account linked.');
        }

        $session = \Stripe\BillingPortal\Session::create([
            'customer' => $customer->stripe_customer_id,
            'return_url' => route('portal.dashboard'),
        ]);

        return redirect($session->url);
    }
}
