<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ServiceRequest;
use App\Models\Customer;
use App\Models\Domain;
use App\Models\Service;
use App\Exceptions\WhmConnectionException;
use App\Services\DomainLinker;
use App\Services\ProvisioningDispatcher;
use App\Services\ServiceBillingService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;
use App\Services\WhmService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
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
        $customers = Customer::with('users')->orderBy('company_name')->get();

        // Build a map of customer_id => email for JS autofill
        $customerEmails = $customers->mapWithKeys(function ($c) {
            $email = $c->users->first()?->email ?? '';
            return [$c->company_id => $email];
        });

        // Get existing cpanel usernames so we don't generate duplicates
        $existingUsernames = Service::whereNotNull('cpanel_username')->pluck('cpanel_username')->toArray();

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

        return view('admin.services.create', compact('customers', 'stripePrices', 'customerEmails', 'existingUsernames'));
    }

    public function store(ServiceRequest $request, ProvisioningDispatcher $dispatcher, DomainLinker $linker, ServiceBillingService $billing)
    {
        // Duplicate domains (including variants, which are normalised to the canonical form
        // before validation) are rejected by ServiceRequest's global unique rule.
        $validated = $request->validated();

        // When provisioning is requested, the form username is only the requested
        // username for the queued job; it is not persisted on the service up front.
        $provisionRequested = $request->boolean('provision_whm')
            && !empty($validated['domain_name']);
        $requestedUsername = $provisionRequested && !empty($validated['cpanel_username'])
            ? $validated['cpanel_username']
            : null;

        $domainName = $validated['domain_name'] ?? null;

        // Duplicate check, Service creation, and Domain find-or-create/link share one
        // transaction. The unique index is the final guard against races.
        try {
            $service = DB::transaction(function () use (
                $validated, $domainName, $provisionRequested, $requestedUsername, $dispatcher, $linker
            ) {
                $domain = null;
                if ($domainName !== null && $domainName !== '') {
                    $domain = $linker->findOrCreate($domainName, (int) $validated['company_id']);

                    $holder = Service::where(function ($q) use ($domain, $domainName) {
                        $q->where('domain_id', $domain->getKey())->orWhere('domain_name', $domainName);
                    })->first();

                    if ($holder !== null) {
                        throw $this->duplicateDomainError($domain->domain_name, $holder);
                    }
                }

                $service = Service::create([
                    'company_id' => $validated['company_id'],
                    'service_short' => $validated['service_short'],
                    'service_type' => $validated['service_type'],
                    'domain_name' => $domainName,
                    'domain_id' => $domain?->getKey(),
                    'cpanel_username' => $provisionRequested ? null : ($validated['cpanel_username'] ?? null),
                    'whm_package' => $provisionRequested ? ($validated['whm_package'] ?? null) : null,
                    'status' => $validated['status'] ?? 'Active',
                    'start_date' => $validated['start_date'] ?? now(),
                    'end_date' => $validated['end_date'] ?? null,
                    'service_monthly_charge' => $validated['service_monthly_charge'] ?? null,
                    'service_payment_frequency' => $validated['service_payment_frequency'] ?? null,
                ]);

                // Queue provisioning once the service row is committed.
                if ($provisionRequested) {
                    DB::afterCommit(fn () => $dispatcher->dispatch(
                        $service,
                        $requestedUsername,
                        ProvisioningDispatcher::CONTEXT_ADMIN,
                    ));
                }

                return $service;
            });
        } catch (UniqueConstraintViolationException $e) {
            // Lost a race: another request took the domain between check and insert.
            throw ValidationException::withMessages([
                'domain_name' => "The domain {$domainName} is already in use by another service.",
            ]);
        }

        $whmMessage = $provisionRequested ? 'WHM provisioning queued.' : '';

        // Create Stripe subscription if a price was selected
        if (!empty($validated['stripe_price_id']) && config('services.stripe.secret')) {
            if (!$billing->setup($service, $validated)) {
                return redirect()->route('admin.services.index')
                    ->with('success', 'Service created but Stripe subscription failed: ' . $service->billing_error);
            }
        }

        return redirect()->route('admin.services.index')
            ->with('success', 'Service created.' . ($whmMessage ? ' ' . $whmMessage : ''));
    }

    /** Validation error naming the duplicate domain and the service holding it (even if Cancelled). */
    private function duplicateDomainError(string $domain, Service $holder): ValidationException
    {
        $status = $holder->status instanceof \BackedEnum ? $holder->status->value : (string) $holder->status;
        $label = $holder->service_short ?: 'service';

        return ValidationException::withMessages([
            'domain_name' => "The domain {$domain} is already held by service #{$holder->service_id} ({$label}, {$status}).",
        ]);
    }

    public function show(Service $service): View
    {
        $service->load(['customer', 'customerApp']);

        return view('admin.services.show', compact('service'));
    }

    /**
     * On-demand WHM lookup. The show page itself never calls WHM.
     */
    public function refreshWhm(Service $service, WhmService $whm): RedirectResponse
    {
        if (! $service->cpanel_username) {
            return redirect()->route('admin.services.show', $service)
                ->with('whmError', 'This service has no cPanel username to look up.');
        }

        try {
            $info = $whm->getAccountInfo($service->cpanel_username);
        } catch (WhmConnectionException $e) {
            return redirect()->route('admin.services.show', $service)
                ->with('whmError', $e->getMessage());
        }

        return redirect()->route('admin.services.show', $service)
            ->with('whmInfo', $info);
    }

    /**
     * Retry Stripe billing setup for a service flagged billing_failed. The price is not
     * persisted on the service, so the admin supplies it (and the contact email) again.
     */
    public function retryBilling(Request $request, Service $service, ServiceBillingService $billing): RedirectResponse
    {
        $back = redirect()->route('admin.services.show', $service);

        if ($service->billing_state !== ServiceBillingService::STATE_FAILED) {
            return $back->with('error', 'Billing retry is only available for services with failed billing.');
        }

        $data = $request->validate([
            'stripe_price_id' => ['required', 'string', 'max:255'],
            'contact_email' => ['required', 'email'],
        ]);

        if ($billing->setup($service, $data)) {
            return $back->with('success', 'Billing set up successfully.');
        }

        return $back->with('error', 'Billing retry failed: ' . $service->billing_error);
    }

    public function edit(Service $service): View
    {
        $service->load('customer');
        $customers = Customer::orderBy('company_name')->get();

        return view('admin.services.edit', compact('service', 'customers'));
    }

    public function update(ServiceRequest $request, Service $service)
    {
        // Only persist service columns; web-only create fields (WHM, Stripe price) are ignored here.
        $validated = Arr::only($request->validated(), [
            'company_id',
            'service_short',
            'service_type',
            'domain_name',
            'cpanel_username',
            'status',
            'start_date',
            'end_date',
            'service_monthly_charge',
            'service_payment_frequency',
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
}
