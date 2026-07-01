<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Domain;
use App\Models\Invoice;
use App\Models\Service;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        // Ticket stats
        $openTickets = Ticket::whereIn('status', ['Open', 'Pending', 'In Progress'])->count();
        $criticalTickets = Ticket::whereIn('status', ['Open', 'Pending', 'In Progress'])
            ->where('priority', 'Critical')->count();

        // Service stats
        $activeServices = Service::where('status', 'Active')->count();
        $totalCustomers = Customer::count();

        // Revenue KPIs
        $mrr = Service::where('status', 'Active')
            ->whereNotNull('service_monthly_charge')
            ->sum('service_monthly_charge');
        $arr = $mrr * 12;

        // Overdue invoices
        $overdueInvoices = Invoice::where('invoice_status', 'Unpaid')
            ->where('due_date', '<', now())
            ->count();
        $overdueAmount = Invoice::where('invoice_status', 'Unpaid')
            ->where('due_date', '<', now())
            ->sum('invoice_amount');

        // Revenue this month
        $revenueThisMonth = Invoice::where('invoice_status', 'Paid')
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

        return view('admin.dashboard', compact(
            'openTickets',
            'criticalTickets',
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
        ));
    }
}
