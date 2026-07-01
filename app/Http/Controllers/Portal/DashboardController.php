<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\Invoice;
use App\Models\Service;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $companyId = $user->company_id;

        $activeServices = Service::where('company_id', $companyId)
            ->where('status', 'Active')
            ->where('service_short', '!=', 'Technical Support Package')
            ->count();

        $openTickets = Ticket::where('company_id', $companyId)
            ->where('status', '!=', 'Closed')
            ->count();

        $expiringDomains = Domain::where('company_id', $companyId)
            ->whereDate('expiry_date', '<=', now()->addDays(30))
            ->whereDate('expiry_date', '>=', now())
            ->count();

        $hasSupportPlan = Service::where('company_id', $companyId)
            ->where('service_short', 'Technical Support Package')
            ->where('status', 'Active')
            ->exists();

        // Recent tickets
        $recentTickets = Ticket::where('company_id', $companyId)
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get();

        // Upcoming renewals (services with next payment in 14 days)
        $upcomingRenewals = Service::where('company_id', $companyId)
            ->where('status', 'Active')
            ->whereDate('next_payment_date', '<=', now()->addDays(14))
            ->whereDate('next_payment_date', '>=', now())
            ->get();

        // Recent invoices
        $recentInvoices = Invoice::where('company_id', $companyId)
            ->orderByDesc('invoice_date')
            ->limit(5)
            ->get();

        // Expiring domains list
        $expiringDomainsList = Domain::where('company_id', $companyId)
            ->whereDate('expiry_date', '<=', now()->addDays(30))
            ->whereDate('expiry_date', '>=', now())
            ->orderBy('expiry_date')
            ->get();

        return view('portal.dashboard', compact(
            'activeServices',
            'openTickets',
            'expiringDomains',
            'hasSupportPlan',
            'recentTickets',
            'upcomingRenewals',
            'recentInvoices',
            'expiringDomainsList',
        ));
    }
}
