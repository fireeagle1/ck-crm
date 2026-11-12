<?php

use App\Models\ServiceDataCleanupChange;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * One-off production fix-ups agreed with the business (domain owns its subscription):
 *
 *  1. theproductcorner.co.uk / pickleandpetal.co.uk: move the domain-fee subscription from the
 *     duplicate "Domain Name - Annual Registration Fee" services (139, 140) onto the domains,
 *     then delete those services (nothing references them).
 *  2. Giant's Seat: the £80 hosting subscription goes back to hosting service 142, the £10.99
 *     domain subscription goes onto the real domain giantsseat.org.uk (35), service 142 is
 *     pointed at domain 35, and the typo duplicate giantseat.org.uk (36) is soft deleted.
 *  3. greenmount.org.uk (9): note that it is not managed by CK Enterprises.
 *
 * Each fix only runs when every record matches the state seen in production; otherwise it is
 * skipped and logged, so fresh and test databases are untouched. Every change is written to
 * service_data_cleanup_changes and down() restores the original values and deleted rows.
 * No Stripe API calls are made.
 */
return new class extends Migration
{
    private const TAG = 'domain-billing-fixups';

    private const GREENMOUNT_NOTE = 'Not managed by CK Enterprises (registered with Freeola). No subscription required.';

    public function up(): void
    {
        if (! Schema::hasTable('service_data_cleanup_changes')
            || ! Schema::hasColumn('domains', 'deleted_at')
            || ServiceDataCleanupChange::where('migration', self::TAG)->exists()) {
            return;
        }

        DB::transaction(function () {
            $this->foldDomainFeeService(139, 32, 'theproductcorner.co.uk', 'sub_1UI6KQKOtRMQ2ybuDr9AM7Nq');
            $this->foldDomainFeeService(140, 34, 'pickleandpetal.co.uk', 'sub_1USH98KOtRMQ2ybuGpdH5kBi');
            $this->fixGiantsSeat();
            $this->noteGreenmount();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('service_data_cleanup_changes')) {
            return;
        }

        DB::transaction(function () {
            ServiceDataCleanupChange::query()
                ->where('migration', self::TAG)
                ->orderByDesc('id')
                ->each(function (ServiceDataCleanupChange $log) {
                    $key = $log->table_name === 'services' ? 'service_id' : 'id';

                    if ($log->column_name === '__row__') {
                        // A deleted row: re-insert it exactly as it was.
                        if (! DB::table($log->table_name)->where($key, $log->row_id)->exists()) {
                            DB::table($log->table_name)->insert(json_decode($log->old_value, true));
                        }

                        return;
                    }

                    DB::table($log->table_name)
                        ->where($key, $log->row_id)
                        ->update([$log->column_name => $log->old_value]);
                });

            ServiceDataCleanupChange::query()->where('migration', self::TAG)->delete();
        });
    }

    private function foldDomainFeeService(int $serviceId, int $domainId, string $domainName, string $subscriptionId): void
    {
        $service = DB::table('services')->where('service_id', $serviceId)->lockForUpdate()->first();
        $domain = DB::table('domains')->where('id', $domainId)->lockForUpdate()->first();

        if (! $service || ! $domain
            || $service->stripe_subscription_id !== $subscriptionId
            || (int) $service->domain_id !== $domainId
            || $domain->domain_name !== $domainName
            || $domain->deleted_at !== null
            || filled($domain->stripe_subscription_id)) {
            $this->skip("domain-fee fold for {$domainName}");

            return;
        }

        $this->set('domains', 'id', $domainId, 'stripe_subscription_id', $domain->stripe_subscription_id, $subscriptionId);

        ServiceDataCleanupChange::record(self::TAG, 'services', $serviceId, '__row__', json_encode((array) $service), null);
        DB::table('services')->where('service_id', $serviceId)->delete();
    }

    private function fixGiantsSeat(): void
    {
        $real = DB::table('domains')->where('id', 35)->lockForUpdate()->first();
        $typo = DB::table('domains')->where('id', 36)->lockForUpdate()->first();
        $hosting = DB::table('services')->where('service_id', 142)->lockForUpdate()->first();
        $hostingSub = 'sub_1UZzJqKOtRMQ2ybupKDl6BAs';
        $domainSub = 'sub_1UZzIGKOtRMQ2ybuTljfxRoL';

        $nameTaken = DB::table('services')
            ->where('service_id', '!=', 142)
            ->where(fn ($q) => $q->where('domain_name', 'giantsseat.org.uk')->orWhere('domain_id', 35))
            ->exists();

        if (! $real || ! $typo || ! $hosting || $nameTaken
            || $real->domain_name !== 'giantsseat.org.uk' || $real->deleted_at !== null || filled($real->stripe_subscription_id)
            || $typo->domain_name !== 'giantseat.org.uk' || $typo->deleted_at !== null || $typo->stripe_subscription_id !== $hostingSub
            || (int) $hosting->domain_id !== 36 || filled($hosting->stripe_subscription_id)
            || (int) $real->company_id !== (int) $hosting->company_id) {
            $this->skip("Giant's Seat fix");

            return;
        }

        // Free the hosting subscription from the typo domain first, then hand it to the service.
        $this->set('domains', 'id', 36, 'stripe_subscription_id', $typo->stripe_subscription_id, null);
        $this->set('services', 'service_id', 142, 'stripe_subscription_id', $hosting->stripe_subscription_id, $hostingSub);
        $this->set('services', 'service_id', 142, 'domain_id', $hosting->domain_id, 35);
        $this->set('services', 'service_id', 142, 'domain_name', $hosting->domain_name, 'giantsseat.org.uk');
        $this->set('domains', 'id', 36, 'deleted_at', null, now()->toDateTimeString());
        $this->set('domains', 'id', 35, 'stripe_subscription_id', $real->stripe_subscription_id, $domainSub);
    }

    private function noteGreenmount(): void
    {
        $domain = DB::table('domains')->where('id', 9)->lockForUpdate()->first();

        if (! $domain || $domain->domain_name !== 'greenmount.org.uk'
            || str_contains((string) $domain->domain_admin_notes, self::GREENMOUNT_NOTE)) {
            $this->skip('Greenmount note');

            return;
        }

        $notes = trim(($domain->domain_admin_notes ? $domain->domain_admin_notes."\n" : '').self::GREENMOUNT_NOTE);
        $this->set('domains', 'id', 9, 'domain_admin_notes', $domain->domain_admin_notes, $notes);
    }

    private function set(string $table, string $key, int $id, string $column, mixed $old, mixed $new): void
    {
        ServiceDataCleanupChange::record(self::TAG, $table, $id, $column, $old, $new);
        DB::table($table)->where($key, $id)->update([$column => $new]);
    }

    private function skip(string $what): void
    {
        Log::info('domain-billing-fixups: skipped '.$what.' (records not in the expected state)');
    }
};
