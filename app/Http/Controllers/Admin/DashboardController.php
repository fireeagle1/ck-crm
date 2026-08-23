<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Domain;
use App\Models\Invoice;
use App\Models\Service;
use App\Models\Ticket;
use App\Models\User;
use App\Services\MrrCalculator;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Statuses that should be excluded from all financial reporting.
     */
    private const EXCLUDED_STATUSES = ['Void', 'Uncollectible'];

    public function index(): View
    {
        // Ticket stats
        $openTickets = Ticket::whereIn('status', ['Open', 'Pending', 'In Progress'])->count();
        $criticalTickets = Ticket::whereIn('status', ['Open', 'Pending', 'In Progress'])
            ->where('priority', 'Critical')->count();
        $highTickets = Ticket::whereIn('status', ['Open', 'Pending', 'In Progress'])
            ->where('priority', 'High')->count();
        $overdueTickets = Ticket::whereIn('status', ['Open', 'Pending', 'In Progress'])
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->count();
        $avgResponseTime = Ticket::whereNotNull('first_replied_at')
            ->whereRaw('first_replied_at > created_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, created_at, first_replied_at)) as avg_minutes')
            ->value('avg_minutes');

        // Service stats
        $activeServices = Service::where('status', 'Active')->count();
        $totalCustomers = Customer::count();

        // Revenue KPIs — normalise charges to monthly based on billing frequency
        $mrr = app(MrrCalculator::class)->calculate();
        $arr = $mrr * 12;

        // Overdue invoices — exclude void/uncollectible
        $overdueInvoices = Invoice::where('invoice_status', 'Unpaid')
            ->whereNotIn('invoice_status', self::EXCLUDED_STATUSES)
            ->where('due_date', '<', now())
            ->count();
        $overdueAmount = Invoice::where('invoice_status', 'Unpaid')
            ->whereNotIn('invoice_status', self::EXCLUDED_STATUSES)
            ->where('due_date', '<', now())
            ->sum('invoice_amount');

        // Revenue this month — exclude void/uncollectible
        $revenueThisMonth = Invoice::where('invoice_status', 'Paid')
            ->whereNotIn('invoice_status', self::EXCLUDED_STATUSES)
            ->whereMonth('paid_date', now()->month)
            ->whereYear('paid_date', now()->year)
            ->sum('invoice_amount');

        // Domains expiring within 30 days
        $expiringDomains = Domain::whereDate('expiry_date', '<=', now()->addDays(30))
            ->whereDate('expiry_date', '>=', now())
            ->with('customer')
            ->orderBy('expiry_date')
            ->limit(5)
            ->get();

        // Recent tickets
        $recentTickets = Ticket::with(['customer', 'user'])
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        // Recent logins
        $recentLogins = User::whereNotNull('last_login')
            ->orderByDesc('last_login')
            ->limit(5)
            ->get();

        // Upcoming rentals — confirmed/active bookings starting soon or currently active
        $upcomingRentals = Booking::with(['product', 'customer'])
            ->whereNotNull('company_id') // exclude blocked dates
            ->whereIn('status', ['confirmed', 'active'])
            ->where('end_date', '>=', now()->toDateString())
            ->orderBy('start_date')
            ->limit(8)
            ->get();

        return view('admin.dashboard', compact(
            'openTickets',
            'criticalTickets',
            'highTickets',
            'overdueTickets',
            'avgResponseTime',
            'activeServices',
            'totalCustomers',
            'mrr',
            'arr',
            'overdueInvoices',
            'overdueAmount',
            'revenueThisMonth',
            'expiringDomains',
            'recentTickets',
            'recentLogins',
            'upcomingRentals',
        ));
    }

}
