<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Domain;
use App\Models\Invoice;
use App\Models\Ticket;
use App\Models\User;
use Carbon\Carbon;

class DashboardService
{
    public function __construct(
        private MrrCalculator $mrrCalculator,
    ) {}

    /**
     * Aggregate all dashboard metrics into the API response structure.
     */
    public function getMetrics(): array
    {
        return [
            'tickets' => $this->getTicketStats(),
            'financials' => $this->getFinancials(),
            'recent_tickets' => $this->getRecentTickets(),
            'recent_logins' => $this->getRecentLogins(),
            'expiring_domains' => $this->getExpiringDomains(),
            'rentals' => $this->getRentalMetrics(),
        ];
    }

    private function getTicketStats(): array
    {
        $openStatuses = ['Open', 'Pending', 'In Progress'];

        $openCount = Ticket::whereIn('status', $openStatuses)->count();

        $criticalCount = Ticket::whereIn('status', $openStatuses)
            ->where('priority', 'Critical')
            ->count();

        $highCount = Ticket::whereIn('status', $openStatuses)
            ->where('priority', 'High')
            ->count();

        $overdueCount = Ticket::whereIn('status', $openStatuses)
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->count();

        $avgResponseTimeMinutes = Ticket::whereNotNull('first_replied_at')
            ->whereRaw('first_replied_at > created_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, created_at, first_replied_at)) as avg_minutes')
            ->value('avg_minutes');

        return [
            'open_count' => $openCount,
            'critical_count' => $criticalCount,
            'high_count' => $highCount,
            'overdue_count' => $overdueCount,
            'avg_response_time_minutes' => $avgResponseTimeMinutes ? round((float) $avgResponseTimeMinutes, 1) : null,
        ];
    }

    private function getFinancials(): array
    {
        $mrr = $this->mrrCalculator->calculate();
        $arr = $mrr * 12;

        $overdueInvoicesQuery = Invoice::where('invoice_status', 'Unpaid')
            ->whereNotIn('invoice_status', Invoice::EXCLUDED_STATUSES)
            ->where('due_date', '<', now());

        $overdueInvoicesCount = (clone $overdueInvoicesQuery)->count();
        $overdueInvoicesAmount = (clone $overdueInvoicesQuery)->sum('invoice_amount');

        $revenueThisMonth = Invoice::where('invoice_status', 'Paid')
            ->whereNotIn('invoice_status', Invoice::EXCLUDED_STATUSES)
            ->whereMonth('paid_date', now()->month)
            ->whereYear('paid_date', now()->year)
            ->sum('invoice_amount');

        return [
            'mrr' => round($mrr, 2),
            'arr' => round($arr, 2),
            'overdue_invoices_count' => $overdueInvoicesCount,
            'overdue_invoices_amount' => round((float) $overdueInvoicesAmount, 2),
            'revenue_this_month' => round((float) $revenueThisMonth, 2),
        ];
    }

    private function getRecentTickets(): array
    {
        return Ticket::with(['customer', 'user'])
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn ($ticket) => [
                'ticket_id' => $ticket->ticket_id,
                'subject' => $ticket->subject,
                'customer_name' => $ticket->customer?->customer_name,
                'assigned_user_name' => $ticket->user?->name,
                'status' => $ticket->status,
                'priority' => $ticket->priority,
            ])
            ->all();
    }

    private function getRecentLogins(): array
    {
        return User::where('is_admin', true)
            ->whereNotNull('last_login')
            ->orderByDesc('last_login')
            ->limit(5)
            ->get()
            ->map(fn ($user) => [
                'user_name' => $user->name,
                'last_login' => $user->last_login->toIso8601String(),
            ])
            ->all();
    }

    private function getExpiringDomains(): array
    {
        $now = now();

        return Domain::whereDate('expiry_date', '>=', $now)
            ->whereDate('expiry_date', '<=', $now->copy()->addDays(30))
            ->with('customer')
            ->orderBy('expiry_date')
            ->limit(5)
            ->get()
            ->map(fn ($domain) => [
                'domain_name' => $domain->domain_name,
                'customer_name' => $domain->customer?->customer_name,
                'expiry_date' => $domain->expiry_date->toDateString(),
                'days_until_expiry' => (int) $now->diffInDays($domain->expiry_date, false),
            ])
            ->all();
    }

    private function getRentalMetrics(): array
    {
        $activeRentalsCount = Booking::where(function ($q) {
            $q->where('status', 'active')
              ->orWhere('fulfilment_stage', 'checked_out');
        })->count();

        $upcomingReturnsCount = Booking::where('status', 'active')
            ->where('end_date', '<=', now()->addDays(7)->toDateString())
            ->where('end_date', '>=', now()->toDateString())
            ->count();

        $recentlyReturnedCount = Booking::whereNotNull('returned_at')
            ->where('returned_at', '>=', now()->subDays(7))
            ->count();

        return [
            'active_rentals_count' => $activeRentalsCount,
            'upcoming_returns_count' => $upcomingReturnsCount,
            'recently_returned_count' => $recentlyReturnedCount,
        ];
    }
}
