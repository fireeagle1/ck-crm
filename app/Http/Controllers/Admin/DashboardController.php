<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Service;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $openTickets = Ticket::whereIn('status', ['Open', 'Pending', 'In Progress'])->count();
        $activeServices = Service::where('status', 'Active')->count();
        $recentLogins = User::whereNotNull('last_login')
            ->orderByDesc('last_login')
            ->limit(5)
            ->get();

        return view('admin.dashboard', compact(
            'openTickets',
            'activeServices',
            'recentLogins',
        ));
    }
}
