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
        ));
    }

    /**
     * Calculate true MRR by normalising each service's charge to a monthly value.
     *
     * For Stripe-managed services: `service_monthly_charge` stores the charge per
     * billing cycle (from Stripe's unit_amount), so we divide by the cycle length.
     *
     * For manually-entered services (no stripe_subscription_id): the charge is
     * already entered as a monthly amount by the admin, so we use it as-is.
     */
    private function calculateMrr(): float
    {
        $services = Service::where('status', 'Active')
            ->whereNotNull('service_monthly_charge')
            ->where('service_monthly_charge', '>', 0)
            ->get(['service_monthly_charge', 'service_payment_frequency', 'stripe_subscription_id']);

        return $services->sum(function ($service) {
            $charge = (float) $service->service_monthly_charge;

            // Manual services are entered as monthly — no conversion needed
            if (empty($service->stripe_subscription_id)) {
                return $charge;
            }

            // Stripe-synced: divide by number of months in the billing cycle
            $months = match ($service->service_payment_frequency) {
                'Weekly'      => 0.25,   // ~1 week ≈ 0.25 months
                'Monthly'     => 1,
                'Quarterly'   => 3,
                'Biannually'  => 6,
                'Annually'    => 12,
                'Biennially'  => 24,
                default       => 1,
            };

            return $charge / $months;
        });
    }
}
