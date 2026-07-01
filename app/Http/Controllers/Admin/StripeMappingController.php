<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StripeMappingController extends Controller
{
    public function index(): View
    {
        // Customers with Stripe IDs
        $mapped = Customer::whereNotNull('stripe_customer_id')
            ->where('stripe_customer_id', '!=', '')
            ->orderBy('company_name')
            ->get();

        // Customers without Stripe IDs
        $unmapped = Customer::where(function ($q) {
            $q->whereNull('stripe_customer_id')
              ->orWhere('stripe_customer_id', '');
        })->orderBy('company_name')->get();

        // Fetch Stripe customers for reference
        $stripeCustomers = $this->fetchStripeCustomers();

        return view('admin.services.stripe-mapping', compact('mapped', 'unmapped', 'stripeCustomers'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'mappings' => 'required|array',
            'mappings.*.company_id' => 'required|exists:customers,company_id',
            'mappings.*.stripe_customer_id' => 'nullable|string|max:255',
        ]);

        $updated = 0;
        foreach ($validated['mappings'] as $mapping) {
            $customer = Customer::find($mapping['company_id']);
            $newId = !empty($mapping['stripe_customer_id']) ? $mapping['stripe_customer_id'] : null;

            if ($customer && $customer->stripe_customer_id !== $newId) {
                $customer->update(['stripe_customer_id' => $newId]);
                $updated++;
            }
        }

        return back()->with('success', "{$updated} customer(s) updated.");
    }

    private function fetchStripeCustomers(): array
    {
        if (!config('services.stripe.secret')) {
            return [];
        }

        try {
            $customers = [];
            $hasMore = true;
            $startingAfter = null;

            while ($hasMore && count($customers) < 200) {
                $params = ['limit' => 100];
                if ($startingAfter) {
                    $params['starting_after'] = $startingAfter;
                }

                $result = \Stripe\Customer::all($params);

                foreach ($result->data as $sc) {
                    $customers[] = [
                        'id' => $sc->id,
                        'name' => $sc->name ?? $sc->email ?? $sc->id,
                        'email' => $sc->email ?? '',
                    ];
                    $startingAfter = $sc->id;
                }

                $hasMore = $result->has_more;
            }

            usort($customers, fn($a, $b) => strcasecmp($a['name'], $b['name']));
            return $customers;
        } catch (\Exception) {
            return [];
        }
    }
}
