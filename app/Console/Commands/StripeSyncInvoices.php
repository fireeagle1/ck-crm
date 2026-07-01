<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\ScheduledTaskLog;
use App\Models\Service;
use Illuminate\Console\Command;

class StripeSyncInvoices extends Command
{
    protected $signature = 'stripe:sync {--customer= : Sync a specific customer by company_id}';

    protected $description = 'Sync invoices and subscription data from Stripe';

    public function handle(): int
    {
        $log = ScheduledTaskLog::begin('stripe:sync');

        if (!config('services.stripe.secret')) {
            $log->fail('Stripe API key not configured.');
            $this->error('Stripe API key not configured. Set STRIPE_SECRET in .env');
            return self::FAILURE;
        }

        $query = Customer::whereNotNull('stripe_customer_id');

        if ($customerId = $this->option('customer')) {
            $query->where('company_id', $customerId);
        }

        $customers = $query->get();

        if ($customers->isEmpty()) {
            $log->complete('No customers with Stripe IDs found.', ['customers' => 0]);
            $this->warn('No customers with Stripe IDs found.');
            return self::SUCCESS;
        }

        $this->info("Syncing {$customers->count()} customer(s) from Stripe...");
        $bar = $this->output->createProgressBar($customers->count());

        $invoiceCount = 0;
        $subscriptionCount = 0;
        $errors = [];

        foreach ($customers as $customer) {
            try {
                $invoiceCount += $this->syncInvoices($customer);
                $subscriptionCount += $this->syncSubscriptions($customer);
            } catch (\Exception $e) {
                $errors[] = "{$customer->company_name}: {$e->getMessage()}";
                $this->newLine();
                $this->warn("  Error for {$customer->company_name}: {$e->getMessage()}");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("✓ Synced {$invoiceCount} invoices, {$subscriptionCount} subscriptions.");

        $summary = "Synced {$invoiceCount} invoices, {$subscriptionCount} subscriptions.";
        if ($errors) {
            $summary .= ' Errors: ' . count($errors);
        }

        $log->complete($summary, [
            'customers_processed' => $customers->count(),
            'invoices_synced' => $invoiceCount,
            'subscriptions_synced' => $subscriptionCount,
            'errors' => $errors,
        ]);

        return self::SUCCESS;
    }

    private function syncInvoices(Customer $customer): int
    {
        $count = 0;
        $hasMore = true;
        $startingAfter = null;

        while ($hasMore) {
            $params = [
                'customer' => $customer->stripe_customer_id,
                'limit' => 100,
            ];

            if ($startingAfter) {
                $params['starting_after'] = $startingAfter;
            }

            $response = \Stripe\Invoice::all($params);

            foreach ($response->data as $stripeInvoice) {
                $status = match ($stripeInvoice->status) {
                    'paid' => 'Paid',
                    'open' => 'Unpaid',
                    'void' => 'Void',
                    'uncollectible' => 'Uncollectible',
                    default => 'Draft',
                };

                // Build line items
                $items = [];
                foreach ($stripeInvoice->lines->data as $line) {
                    $items[] = [
                        'description' => $line->description ?? 'Line item',
                        'amount' => $line->amount / 100,
                    ];
                }

                Invoice::updateOrCreate(
                    ['stripe_invoice_id' => $stripeInvoice->id],
                    [
                        'company_id' => $customer->company_id,
                        'invoice_status' => $status,
                        'invoice_amount' => $stripeInvoice->amount_due / 100,
                        'amount_after_fees' => $stripeInvoice->amount_paid / 100,
                        'invoice_date' => $stripeInvoice->created ? date('Y-m-d', $stripeInvoice->created) : null,
                        'due_date' => $stripeInvoice->due_date ? date('Y-m-d', $stripeInvoice->due_date) : null,
                        'paid_date' => $stripeInvoice->status_transitions?->paid_at
                            ? date('Y-m-d', $stripeInvoice->status_transitions->paid_at)
                            : null,
                        'stripe_hosted_url' => $stripeInvoice->hosted_invoice_url ?? null,
                        'invoice_items' => $items,
                    ]
                );

                $count++;
                $startingAfter = $stripeInvoice->id;
            }

            $hasMore = $response->has_more;
        }

        return $count;
    }

    private function syncSubscriptions(Customer $customer): int
    {
        $count = 0;

        $subscriptions = \Stripe\Subscription::all([
            'customer' => $customer->stripe_customer_id,
            'limit' => 100,
            'status' => 'all',
        ]);

        foreach ($subscriptions->data as $sub) {
            $status = match ($sub->status) {
                'active', 'trialing' => 'Active',
                'past_due' => 'Active', // still running, just overdue
                'canceled', 'incomplete_expired' => 'Cancelled',
                default => 'Suspended',
            };

            // Get a descriptive name from the first line item
            $name = 'Subscription';
            if (!empty($sub->items->data)) {
                $item = $sub->items->data[0];
                $name = $item->price->nickname ?? $item->price->product ?? 'Subscription';

                // Try to get product name
                if (is_string($item->price->product)) {
                    try {
                        $product = \Stripe\Product::retrieve($item->price->product);
                        $name = $product->name ?? $name;
                    } catch (\Exception) {
                        // keep the fallback name
                    }
                }
            }

            // Store the actual charge amount (not a monthly conversion)
            $actualCharge = 0;
            $frequency = null;
            if (!empty($sub->items->data)) {
                $price = $sub->items->data[0]->price;
                $actualCharge = $price->unit_amount / 100;
                $interval = $price->recurring?->interval ?? 'month';
                $intervalCount = $price->recurring?->interval_count ?? 1;

                $frequency = match (true) {
                    $interval === 'month' && $intervalCount === 1 => 'Monthly',
                    $interval === 'month' && $intervalCount === 3 => 'Quarterly',
                    $interval === 'month' && $intervalCount === 6 => 'Biannually',
                    $interval === 'year' && $intervalCount === 1 => 'Annually',
                    $interval === 'year' && $intervalCount === 2 => 'Biennially',
                    $interval === 'week' => 'Weekly',
                    default => 'Monthly',
                };
            }

            Service::updateOrCreate(
                ['stripe_subscription_id' => $sub->id],
                [
                    'company_id' => $customer->company_id,
                    'service_short' => $name,
                    'status' => $status,
                    'start_date' => date('Y-m-d', $sub->start_date),
                    'end_date' => $sub->canceled_at ? date('Y-m-d', $sub->canceled_at) : null,
                    'service_monthly_charge' => $actualCharge,
                    'service_payment_frequency' => $frequency,
                    'next_payment_date' => $sub->current_period_end ? date('Y-m-d', $sub->current_period_end) : null,
                ]
            );

            $count++;
        }

        return $count;
    }
}
