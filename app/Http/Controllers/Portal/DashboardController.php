<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Domain;
use App\Models\Invoice;
use App\Models\Order;
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
            ->where('service_type', 'Technical Support')
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

        // Website services (hosting) — shown as cards on dashboard
        $websites = Service::where('company_id', $companyId)
            ->where('status', 'Active')
            ->where(function ($q) {
                $q->where('service_type', 'Web Hosting')
                  ->orWhereNotNull('cpanel_username');
            })
            ->orderBy('service_short')
            ->get();

        // Customer's domains (for showing alongside services)
        $customerDomains = Domain::where('company_id', $companyId)
            ->orderBy('expiry_date')
            ->get();

        // Overdue invoices
        $overdueInvoices = Invoice::where('company_id', $companyId)
            ->where('invoice_status', 'Unpaid')
            ->whereNotIn('invoice_status', Invoice::EXCLUDED_STATUSES)
            ->whereDate('due_date', '<', now())
            ->get();

        // Active rental bookings (start_date <= today, end_date >= today, status confirmed or active)
        $activeBookings = Booking::where('company_id', $companyId)
            ->whereIn('status', ['confirmed', 'active'])
            ->whereDate('start_date', '<=', now())
            ->whereDate('end_date', '>=', now())
            ->with('product', 'orderItem.order')
            ->orderBy('end_date')
            ->get();

        // Upcoming rental bookings (start_date > today, status confirmed or active)
        $upcomingBookings = Booking::where('company_id', $companyId)
            ->whereIn('status', ['confirmed', 'active'])
            ->whereDate('start_date', '>', now())
            ->with('product', 'orderItem.order')
            ->orderBy('start_date')
            ->get();

        // Check if customer has no services or orders — prompt them to visit the shop
        $hasNoProducts = $activeServices === 0
            && !Order::where('company_id', $companyId)->exists()
            && !Service::where('company_id', $companyId)->exists();

        return view('portal.dashboard', compact(
            'activeServices',
            'openTickets',
            'expiringDomains',
            'hasSupportPlan',
            'recentTickets',
            'upcomingRenewals',
            'recentInvoices',
            'expiringDomainsList',
            'websites',
            'customerDomains',
            'overdueInvoices',
            'activeBookings',
            'upcomingBookings',
            'hasNoProducts',
        ));
    }
}
