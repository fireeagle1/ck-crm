<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Domain;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServiceController extends Controller
{
    public function index(Request $request): View
    {
        $services = Service::with('customer')
            ->orderByDesc('service_id')
            ->paginate(20);

        return view('admin.services.index', compact('services'));
    }

    public function create(): View
    {
        $customers = Customer::orderBy('company_name')->get();

        // Fetch Stripe prices if configured
        $stripePrices = [];
        if (config('services.stripe.secret')) {
            try {
                $result = \Stripe\Price::all([
                    'active' => true,
                    'limit' => 100,
                    'expand' => ['data.product'],
                ]);

                foreach ($result->data as $price) {
                    if (empty($price->recurring)) continue;

                    $product = $price->product;
                    $productName = is_object($product) ? ($product->name ?? $price->id) : $price->id;
                    $amount = number_format($price->unit_amount / 100, 2);
                    $interval = $price->recurring->interval;
                    $intervalCount = $price->recurring->interval_count;

                    $frequency = match (true) {
                        $interval === 'month' && $intervalCount === 1 => 'Monthly',
                        $interval === 'month' && $intervalCount === 3 => 'Quarterly',
                        $interval === 'year' && $intervalCount === 1 => 'Annually',
                        default => ucfirst($interval) . 'ly',
                    };

                    $stripePrices[] = [
                        'id' => $price->id,
                        'label' => "{$productName} — £{$amount}/{$frequency}",
                        'product_name' => $productName,
                        'amount' => $amount,
                        'frequency' => $frequency,
                    ];
                }

                usort($stripePrices, fn($a, $b) => strcasecmp($a['label'], $b['label']));
            } catch (\Exception $e) {
                // Stripe not available, just show manual form
            }
        }

        return view('admin.services.create', compact('customers', 'stripePrices'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'company_id' => 'required|exists:customers,company_id',
            'service_short' => 'required|string|max:255',
            'service_type' => 'required|in:Technical Support,Web Hosting,Other',
            'status' => 'in:Active,Suspended,Cancelled',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'service_monthly_charge' => 'nullable|numeric|min:0',
            'service_payment_frequency' => 'nullable|string',
            'stripe_price_id' => 'nullable|string',
            'domain_name' => 'required_if:service_type,Web Hosting|nullable|string|max:255',
            'cpanel_username' => 'nullable|string|max:255',
        ]);

        // Duplicate prevention: check if a service with the same domain already exists for this customer
        if (!empty($validated['domain_name'])) {
            $existing = Service::where('company_id', $validated['company_id'])
                ->where('domain_name', $validated['domain_name'])
                ->where('status', '!=', 'Cancelled')
                ->first();

            if ($existing) {
                return back()->withInput()->with('error',
                    "A service for '{$validated['domain_name']}' already exists for this customer: {$existing->service_short} (ID: {$existing->service_id}). Edit the existing service instead of creating a duplicate."
                );
            }
        }

        $service = Service::create([
            'company_id' => $validated['company_id'],
            'service_short' => $validated['service_short'],
            'service_type' => $validated['service_type'],
            'domain_name' => $validated['domain_name'] ?? null,
            'cpanel_username' => $validated['cpanel_username'] ?? null,
            'status' => $validated['status'] ?? 'Active',
            'start_date' => $validated['start_date'] ?? now(),
            'end_date' => $validated['end_date'] ?? null,
            'service_monthly_charge' => $validated['service_monthly_charge'],
            'service_payment_frequency' => $validated['service_payment_frequency'],
        ]);

        // Create Stripe subscription if a price was selected
        if (!empty($validated['stripe_price_id']) && config('services.stripe.secret')) {
            try {
                $customer = Customer::find($validated['company_id']);
                $stripeCustomerId = $this->ensureStripeCustomer($customer);

                $subscription = \Stripe\Subscription::create([
                    'customer' => $stripeCustomerId,
                    'items' => [['price' => $validated['stripe_price_id']]],
                    'collection_method' => 'send_invoice',
                    'days_until_due' => 7,
                    'metadata' => [
                        'service_id' => $service->service_id,
                        'company_id' => $validated['company_id'],
                    ],
                ]);

                $service->update(['stripe_subscription_id' => $subscription->id]);
            } catch (\Exception $e) {
                return redirect()->route('admin.services.index')
                    ->with('success', 'Service created but Stripe subscription failed: ' . $e->getMessage());
            }
        }

        return redirect()->route('admin.services.index')
            ->with('success', 'Service created.');
    }

    private function ensureStripeCustomer(Customer $customer): string
    {
        if ($customer->stripe_customer_id) {
            return $customer->stripe_customer_id;
        }

        $email = $customer->users()->first()?->email;

        $stripeCustomer = \Stripe\Customer::create([
            'name' => $customer->company_name,
            'email' => $email,
            'phone' => $customer->phone_number,
            'metadata' => ['company_id' => $customer->company_id],
        ]);

        $customer->update(['stripe_customer_id' => $stripeCustomer->id]);

        return $stripeCustomer->id;
    }

    public function show(Service $service): View
    {
        $service->load('customer');

        // Try to fetch WHM account info if this is a hosting service
        $whmInfo = null;
        if ($service->cpanel_username && config('services.whm.host')) {
            $whmInfo = $this->fetchWhmAccountInfo($service->cpanel_username);
        }

        return view('admin.services.show', compact('service', 'whmInfo'));
    }

    public function edit(Service $service): View
    {
        $service->load('customer');
        $customers = Customer::orderBy('company_name')->get();

        return view('admin.services.edit', compact('service', 'customers'));
    }

    public function update(Request $request, Service $service)
    {
        $validated = $request->validate([
            'company_id' => 'required|exists:customers,company_id',
            'service_short' => 'required|string|max:255',
            'service_type' => 'nullable|string|max:50',
            'domain_name' => 'nullable|string|max:255',
            'cpanel_username' => 'nullable|string|max:255',
            'status' => 'in:Active,Suspended,Cancelled',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'service_monthly_charge' => 'nullable|numeric|min:0',
            'service_payment_frequency' => 'nullable|string',
        ]);

        $service->update($validated);

        return redirect()->route('admin.services.show', $service)
            ->with('success', 'Service updated.');
    }

    public function destroy(Service $service)
    {
        $name = $service->service_short;
        $service->delete();

        return redirect()->route('admin.services.index')
            ->with('success', "Service '{$name}' deleted.");
    }

    private function fetchWhmAccountInfo(string $username): ?array
    {
        $host = config('services.whm.host');
        $whmUser = config('services.whm.username');
        $token = config('services.whm.token');

        if (!$host || !$whmUser || !$token) {
            return null;
        }

        try {
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'Authorization' => "WHM {$whmUser}:{$token}",
            ])->withOptions(['verify' => false, 'timeout' => 10])
              ->get("https://{$host}:2087/json-api/accountsummary", [
                  'api.version' => 1,
                  'user' => $username,
              ]);

            $data = $response->json();

            if (($data['metadata']['result'] ?? 0) == 1 && !empty($data['data']['acct'][0])) {
                $acct = $data['data']['acct'][0];
                return [
                    'domain' => $acct['domain'] ?? null,
                    'plan' => $acct['plan'] ?? null,
                    'disk_used' => $acct['diskused'] ?? null,
                    'disk_limit' => $acct['disklimit'] ?? null,
                    'ip' => $acct['ip'] ?? null,
                    'start_date' => $acct['startdate'] ?? null,
                    'suspended' => ($acct['suspended'] ?? 0) == 1,
                    'email' => $acct['email'] ?? null,
                ];
            }
        } catch (\Exception) {
            // silently fail
        }

        return null;
    }
}
