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
            'service_type' => 'required|in:Technical Support,Web Hosting,Domain Registration,Other',
            'status' => 'in:Active,Suspended,Cancelled',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'service_monthly_charge' => 'nullable|numeric|min:0',
            'service_payment_frequency' => 'nullable|string',
            'stripe_price_id' => 'nullable|string',
            'domain_name' => 'nullable|string|max:255',
            'domain_registrar' => 'nullable|string|max:255',
        ]);

        $service = Service::create([
            'company_id' => $validated['company_id'],
            'service_short' => $validated['service_short'],
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

        // Add domain record if provided
        if (!empty($validated['domain_name'])) {
            Domain::updateOrCreate(
                ['domain_name' => strtolower($validated['domain_name'])],
                [
                    'company_id' => $validated['company_id'],
                    'registrar' => $validated['domain_registrar'] ?? 'eNom',
                ]
            );
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
}
