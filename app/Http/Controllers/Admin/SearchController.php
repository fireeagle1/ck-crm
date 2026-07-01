<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Customer;
use App\Models\Domain;
use App\Models\Invoice;
use App\Models\Service;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SearchController extends Controller
{
    public function __invoke(Request $request): View
    {
        $q = $request->get('q', '');

        $results = [];

        if (strlen($q) >= 2) {
            $results['customers'] = Customer::where('company_name', 'LIKE', "%{$q}%")
                ->orWhere('customer_name', 'LIKE', "%{$q}%")
                ->limit(10)->get();

            $results['tickets'] = Ticket::where('subject', 'LIKE', "%{$q}%")
                ->orWhere('ticket_id', $q)
                ->with('customer')
                ->limit(10)->get();

            $results['services'] = Service::where('service_short', 'LIKE', "%{$q}%")
                ->with('customer')
                ->limit(10)->get();

            $results['domains'] = Domain::where('domain_name', 'LIKE', "%{$q}%")
                ->limit(10)->get();

            $results['users'] = User::where('email', 'LIKE', "%{$q}%")
                ->orWhere('first_name', 'LIKE', "%{$q}%")
                ->orWhere('last_name', 'LIKE', "%{$q}%")
                ->limit(10)->get();

            $results['invoices'] = Invoice::where('stripe_invoice_id', 'LIKE', "%{$q}%")
                ->orWhere('invoice_id', $q)
                ->with('customer')
                ->limit(10)->get();

            $results['assets'] = Asset::where('device_name', 'LIKE', "%{$q}%")
                ->orWhere('serial_number', 'LIKE', "%{$q}%")
                ->with('customer')
                ->limit(10)->get();
        }

        return view('admin.search', compact('q', 'results'));
    }
}
