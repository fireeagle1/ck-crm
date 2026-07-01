<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Domain;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReviewWizardController extends Controller
{
    public function index(Request $request): View
    {
        // Get customers with potential issues (multiple services or unmapped)
        $customers = Customer::withCount('services')
            ->having('services_count', '>', 0)
            ->orderBy('company_name')
            ->get();

        // Current customer being reviewed
        $currentId = $request->get('customer');
        $current = null;
        $services = collect();
        $domains = collect();

        if ($currentId) {
            $current = Customer::find($currentId);
            if ($current) {
                $services = Service::where('company_id', $current->company_id)
                    ->orderByDesc('status')
                    ->orderBy('service_short')
                    ->get();
                $domains = Domain::where('company_id', $current->company_id)->get();
            }
        }

        return view('admin.cleanup.review-wizard', compact('customers', 'current', 'services', 'domains'));
    }

    public function bulkDelete(Request $request)
    {
        $validated = $request->validate([
            'service_ids' => 'required|array',
            'service_ids.*' => 'exists:services,service_id',
        ]);

        $count = Service::whereIn('service_id', $validated['service_ids'])->delete();

        return back()->with('success', "{$count} service(s) deleted.");
    }
}
