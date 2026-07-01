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

        // Services that need subscription mapping
        $services = \App\Models\Service::with('customer')
            ->where('status', 'Active')
            ->orderBy('service_short')
            ->get();

        // Fetch Stripe customers and subscriptions for reference
        $stripeCustomers = $this->fetchStripeCustomers();
        $stripeSubscriptions = $this->fetchStripeSubscriptions();

        return view('admin.services.stripe-mapping', compact('mapped', 'unmapped', 'services', 'stripeCustomers', 'stripeSubscriptions'));
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

    public function updateSubscriptions(Request $request)
    {
        $validated = $request->validate([
            'subscriptions' => 'required|array',
            'subscriptions.*.service_id' => 'required|exists:services,service_id',
            'subscriptions.*.stripe_subscription_id' => 'nullable|string|max:255',
        ]);

        $updated = 0;
        foreach ($validated['subscriptions'] as $mapping) {
            $service = \App\Models\Service::find($mapping['service_id']);
            $newId = !empty($mapping['stripe_subscription_id']) ? $mapping['stripe_subscription_id'] : null;

            if ($service && $service->stripe_subscription_id !== $newId) {
                $service->update(['stripe_subscription_id' => $newId]);
                $updated++;
            }
        }

        return back()->with('success', "{$updated} service(s) updated.");
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

    private function fetchStripeSubscriptions(): array
    {
        if (!config('services.stripe.secret')) {
            return [];
        }

        try {
            $subscriptions = [];
            $result = \Stripe\Subscription::all([
                'limit' => 100,
                'status' => 'all',
                'expand' => ['data.customer'],
            ]);

            foreach ($result->data as $sub) {
                $productName = 'Subscription';
                if (!empty($sub->items->data[0]->price->nickname)) {
                    $productName = $sub->items->data[0]->price->nickname;
                }

                $customerName = '';
                if (is_object($sub->customer)) {
                    $customerName = $sub->customer->name ?? $sub->customer->email ?? '';
                }

                $subscriptions[] = [
                    'id' => $sub->id,
                    'customer_id' => is_object($sub->customer) ? $sub->customer->id : $sub->customer,
                    'customer_name' => $customerName,
                    'product' => $productName,
                    'status' => $sub->status,
                    'amount' => !empty($sub->items->data[0]) ? number_format($sub->items->data[0]->price->unit_amount / 100, 2) : '0.00',
                    'interval' => $sub->items->data[0]->price->recurring->interval ?? 'month',
                ];
            }

            return $subscriptions;
        } catch (\Exception) {
            return [];
        }
    }
}
