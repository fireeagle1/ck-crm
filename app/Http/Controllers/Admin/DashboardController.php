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

        // Service stats
        $activeServices = Service::where('status', 'Active')->count();
        $totalCustomers = Customer::count();

        // Revenue KPIs — normalise charges to monthly based on billing frequency
        $mrr = $this->calculateMrr();
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

    /**
     * Calculate true MRR by normalising each service's charge to a monthly value
     * based on its payment frequency.
     */
    private function calculateMrr(): float
    {
        $services = Service::where('status', 'Active')
            ->whereNotNull('service_monthly_charge')
            ->where('service_monthly_charge', '>', 0)
            ->get(['service_monthly_charge', 'service_payment_frequency']);

        return $services->sum(function ($service) {
            return match ($service->service_payment_frequency) {
                'Quarterly' => (float) $service->service_monthly_charge / 3,
                'Annually'  => (float) $service->service_monthly_charge / 12,
                default     => (float) $service->service_monthly_charge, // Monthly or null
            };
        });
    }
}
