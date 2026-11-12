<?php

namespace Tests\Feature;

use App\Exceptions\EnomUnavailableException;
use App\Models\Customer;
use App\Models\Domain;
use App\Services\EnomClient;
use App\Services\EnomDomainSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EnomDomainSyncTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(string $name): string
    {
        return file_get_contents(base_path("tests/Fixtures/enom/{$name}.xml"));
    }

    /** Fake eNom: page 1 at Start=1, page 2 at Start=3 (or an error for page 2). */
    private function fakeEnom(bool $page2Fails = false): void
    {
        Http::fake(function (Request $request) use ($page2Fails) {
            $start = (int) ($request->data()['Start'] ?? 1);
            if ($start === 1) {
                return Http::response($this->fixture('get_domains_page1'));
            }
            return $page2Fails
                ? Http::response($this->fixture('get_domains_error'))
                : Http::response($this->fixture('get_domains_page2'));
        });
    }

    private function sync(): array
    {
        $client = new EnomClient('user', 'pass', true, fn () => null);

        return (new EnomDomainSync($client))->sync();
    }

    public function test_creates_new_domains_unassigned_and_active(): void
    {
        $this->fakeEnom();

        $result = $this->sync();

        $this->assertSame(3, $result['created']);
        $domain = Domain::where('domain_name', 'example.com')->first();
        $this->assertNull($domain->company_id);
        $this->assertSame('active', $domain->status);
        $this->assertSame('eNom', $domain->registrar);
        $this->assertSame('340691704', $domain->enom_domain_name_id);
        $this->assertTrue($domain->auto_renew);
        $this->assertSame('2027-07-07', $domain->expiry_date->toDateString());
        $this->assertFalse(Domain::where('domain_name', 'second.co.uk')->first()->auto_renew);
        $this->assertNotNull($domain->last_synced_at);
    }

    public function test_existing_ownership_and_non_enom_fields_are_kept_and_edited_fields_skipped(): void
    {
        $this->fakeEnom();
        $customer = Customer::factory()->create();
        $domain = Domain::factory()->create([
            'company_id' => $customer->company_id,
            'domain_name' => 'example.com',
            'cost' => 12.50,
            'domain_admin_notes' => 'keep me',
            'expiry_date' => '2030-01-01',
            'auto_renew' => false,
            'admin_edited_fields' => ['expiry_date'],
        ]);

        $this->sync();

        $fresh = $domain->fresh();
        $this->assertSame($customer->company_id, $fresh->company_id);
        $this->assertSame('keep me', $fresh->domain_admin_notes);
        $this->assertSame('12.50', $fresh->cost);
        $this->assertSame('2030-01-01', $fresh->expiry_date->toDateString()); // edited, skipped
        $this->assertTrue($fresh->auto_renew);                                // not edited, synced
        $this->assertSame('340691704', $fresh->enom_domain_name_id);
        $this->assertSame(1, Domain::where('domain_name', 'example.com')->count());
    }

    public function test_trashed_rows_are_matched_not_duplicated(): void
    {
        $this->fakeEnom();
        $domain = Domain::factory()->create(['domain_name' => 'second.co.uk']);
        $domain->delete();

        $this->sync();

        $this->assertSame(1, Domain::withTrashed()->where('domain_name', 'second.co.uk')->count());
        $this->assertTrue(Domain::withTrashed()->where('domain_name', 'second.co.uk')->first()->trashed());
        $this->assertSame('152708845', Domain::withTrashed()->where('domain_name', 'second.co.uk')->first()->enom_domain_name_id);
    }

    public function test_flags_missing_and_reactivates_returned_domains(): void
    {
        $this->fakeEnom();
        $gone = Domain::factory()->enomSourced()->create(['last_synced_at' => now()->subDay()]);
        $manual = Domain::factory()->create(['registrar' => 'Other', 'last_synced_at' => null]);
        $back = Domain::factory()->missingFromEnom()->create([
            'domain_name' => 'third.org',
            'last_synced_at' => now()->subDay(),
        ]);

        $result = $this->sync();

        $this->assertSame('missing_from_enom', $gone->fresh()->status);
        $this->assertSame('active', $manual->fresh()->status);
        $this->assertSame('active', $back->fresh()->status);
        $this->assertSame(1, $result['flagged']);
        $this->assertSame(1, $result['reactivated']);
        $this->assertNotNull(Domain::find($gone->id)); // never deleted
    }

    public function test_failed_page_writes_and_flags_nothing(): void
    {
        $this->fakeEnom(page2Fails: true);
        $gone = Domain::factory()->enomSourced()->create(['last_synced_at' => now()->subDay()]);

        try {
            $this->sync();
            $this->fail('Expected EnomUnavailableException');
        } catch (EnomUnavailableException) {
        }

        $this->assertSame(1, Domain::withTrashed()->count());
        $this->assertSame('active', $gone->fresh()->status);
    }

    public function test_unparseable_expiry_keeps_existing_and_unparseable_names_are_skipped(): void
    {
        $xml = str_replace('7/7/2027', 'not-a-date', $this->fixture('get_domains_page1'));
        $xml = str_replace('<sld>second</sld>', '<sld>bad_name!</sld>', $xml);
        $xml = str_replace('<TotalDomainCount>3', '<TotalDomainCount>2', $xml);
        Http::fake(fn () => Http::response($xml));
        $domain = Domain::factory()->create(['domain_name' => 'example.com', 'expiry_date' => '2031-05-05']);

        $result = $this->sync();

        $this->assertSame('2031-05-05', $domain->fresh()->expiry_date->toDateString());
        $this->assertSame(1, $result['skipped']);
        $this->assertSame(0, Domain::where('domain_name', 'like', 'bad%')->count());
    }
}
