<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\Ticket;
use App\Models\TicketReply;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ScorecardController extends Controller
{
    public function index(Request $request): View
    {
        $companyId = $request->user()->company_id;

        // Check they have support plan
        $hasSupportPlan = Service::where('company_id', $companyId)
            ->where('service_type', 'Technical Support')
            ->where('status', 'Active')
            ->exists();

        if (!$hasSupportPlan) {
            abort(403);
        }

        return $this->buildScorecard($companyId);
    }

    public function adminScorecard(\App\Models\Customer $customer): View
    {
        return $this->buildScorecard($customer->company_id);
    }

    private function buildScorecard(int $companyId): View
    {

        // This month stats
        $thisMonth = now()->startOfMonth();
        $lastMonth = now()->subMonth()->startOfMonth();
        $lastMonthEnd = now()->subMonth()->endOfMonth();

        $thisMonthTickets = Ticket::where('company_id', $companyId)
            ->where('created_at', '>=', $thisMonth)->get();
        $lastMonthTickets = Ticket::where('company_id', $companyId)
            ->whereBetween('created_at', [$lastMonth, $lastMonthEnd])->get();

        // Ticket counts
        $thisTotal = $thisMonthTickets->count();
        $lastTotal = $lastMonthTickets->count();

        $thisOpen = $thisMonthTickets->whereIn('status', ['Open', 'Pending', 'In Progress'])->count();
        $thisClosed = $thisMonthTickets->where('status', 'Closed')->count();
        $lastClosed = $lastMonthTickets->where('status', 'Closed')->count();

        // By type
        $thisIncidents = $thisMonthTickets->where('ticket_type', 'Incident')->count();
        $thisRequests = $thisMonthTickets->where('ticket_type', 'Service Request')->count();
        $lastIncidents = $lastMonthTickets->where('ticket_type', 'Incident')->count();
        $lastRequests = $lastMonthTickets->where('ticket_type', 'Service Request')->count();

        // Average first response time (hours) — time between ticket creation and first admin reply
        $thisResponseTimes = $this->avgResponseTime($thisMonthTickets);
        $lastResponseTimes = $this->avgResponseTime($lastMonthTickets);

        // By category
        $categories = $thisMonthTickets->whereNotNull('request_category')
            ->groupBy('request_category')
            ->map->count()
            ->sortDesc();

        return view('portal.scorecard', compact(
            'thisTotal', 'lastTotal',
            'thisOpen', 'thisClosed', 'lastClosed',
            'thisIncidents', 'thisRequests',
            'lastIncidents', 'lastRequests',
            'thisResponseTimes', 'lastResponseTimes',
            'categories',
        ));
    }

    private function avgResponseTime($tickets): ?float
    {
        if ($tickets->isEmpty()) return null;

        $totalHours = 0;
        $count = 0;

        foreach ($tickets as $ticket) {
            $firstReply = TicketReply::where('ticket_id', $ticket->ticket_id)
                ->whereHas('user', fn($q) => $q->where('is_admin', true))
                ->where('is_internal', false)
                ->orderBy('created_at')
                ->first();

            if ($firstReply) {
                $totalHours += $ticket->created_at->diffInMinutes($firstReply->created_at) / 60;
                $count++;
            }
        }

        return $count > 0 ? round($totalHours / $count, 1) : null;
    }
}
