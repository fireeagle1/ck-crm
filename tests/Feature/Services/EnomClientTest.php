<?php

namespace Tests\Feature\Services;

use App\Exceptions\EnomUnavailableException;
use App\Services\EnomClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class EnomClientTest extends TestCase
{
    private const UID = 'resellid-user';
    private const PW = 'S3cr3t-pw-value';

    /** @var array<int, int> */
    private array $delays = [];

    private function fixture(string $name): string
    {
        return file_get_contents(base_path("tests/Fixtures/enom/{$name}.xml"));
    }

    private function client(): EnomClient
    {
        $this->delays = [];

        return new EnomClient(self::UID, self::PW, true, function (int $ms): void {
            $this->delays[] = $ms;
        });
    }

    public function test_paginates_until_total_reached(): void
    {
        Http::fake(function (Request $request) {
            $start = (int) ($request->data()['Start'] ?? 0);

            return Http::response($this->fixture($start === 1 ? 'get_domains_page1' : 'get_domains_page2'));
        });

        $client = $this->client();
        $all = [];
        $start = 1;
        do {
            $page = $client->getDomainsPage($start, 2);
            $this->assertTrue($page->ok);
            $all = array_merge($all, $page->domains);
            $start += 2;
        } while (count($all) < $page->total);

        $this->assertSame(3, $page->total);
        // Names are returned raw; normalisation belongs to the sync (Req 14.5).
        $this->assertSame(['Example.com', 'second.co.uk', 'third.org'], array_column($all, 'domain_name'));
        $this->assertSame('340691704', $all[0]['domain_name_id']);
        $this->assertSame('7/7/2027', $all[0]['expiration_date']);
        $this->assertTrue($all[0]['auto_renew']);
        $this->assertFalse($all[1]['auto_renew']);
        $this->assertSame([], $this->delays);

        Http::assertSentCount(2);
        Http::assertSent(fn (Request $r) => $r['Start'] == 1 && $r['Display'] == 2 && $r['command'] === 'GetDomains');
        Http::assertSent(fn (Request $r) => $r['Start'] == 3);
    }

    public function test_page_size_is_capped_at_100(): void
    {
        Http::fake(['*' => Http::response($this->fixture('get_domains_page1'))]);

        $this->client()->getDomainsPage(1, 500);

        Http::assertSent(fn (Request $r) => $r['Display'] == 100);
    }

    public function test_credentials_are_posted_in_body_not_url(): void
    {
        Http::fake(['*' => Http::response($this->fixture('get_domains_page1'))]);

        $this->client()->getDomainsPage(1, 100);

        Http::assertSent(function (Request $r) {
            return $r->method() === 'POST'
                && $r['uid'] === self::UID
                && $r['pw'] === self::PW
                && ! str_contains($r->url(), self::PW)
                && ! str_contains($r->url(), self::UID)
                && ! str_contains($r->url(), 'pw=')
                && ! str_contains($r->url(), 'uid=');
        });
    }

    public function test_retries_three_times_on_5xx_with_increasing_delays_then_throws(): void
    {
        Http::fake(['*' => Http::response('boom', 503)]);

        try {
            $this->client()->getDomainsPage(1, 100);
            $this->fail('Expected EnomUnavailableException');
        } catch (EnomUnavailableException $e) {
            $this->assertStringNotContainsString(self::PW, $e->getMessage());
            $this->assertStringNotContainsString(self::UID, $e->getMessage());
            $this->assertStringNotContainsString('pw=', $e->getMessage());
        }

        Http::assertSentCount(4);
        $this->assertSame([500, 1500, 4000], $this->delays);
    }

    public function test_retries_on_connection_error_then_succeeds(): void
    {
        $calls = 0;
        Http::fake(function () use (&$calls) {
            if (++$calls < 3) {
                throw new ConnectionException('cURL error 6: could not resolve host');
            }

            return Http::response($this->fixture('get_domains_page2'));
        });

        $page = $this->client()->getDomainsPage(3, 100);

        $this->assertTrue($page->ok);
        $this->assertCount(1, $page->domains);
        $this->assertSame(3, $calls);
        $this->assertSame([500, 1500], $this->delays);
    }

    public function test_connection_failures_throw_without_leaking_credentials_or_logging(): void
    {
        Log::spy();
        Http::fake(function () {
            // Simulate a transport message that echoes request details.
            throw new ConnectionException('cURL error 28 for uid=' . self::UID . '&pw=' . self::PW);
        });

        try {
            $this->client()->getDomainsPage(1, 100);
            $this->fail('Expected EnomUnavailableException');
        } catch (EnomUnavailableException $e) {
            $this->assertStringNotContainsString(self::PW, $e->getMessage());
            $this->assertStringNotContainsString(self::UID, $e->getMessage());
            $this->assertNull($e->getPrevious());
        }

        $this->assertSame([500, 1500, 4000], $this->delays);
        Log::shouldNotHaveReceived('error');
        Log::shouldNotHaveReceived('warning');
        Log::shouldNotHaveReceived('info');
        Log::shouldNotHaveReceived('debug');
    }

    public function test_4xx_is_not_retried_and_returns_not_ok(): void
    {
        Http::fake(['*' => Http::response('nope', 403)]);

        $page = $this->client()->getDomainsPage(1, 100);

        $this->assertFalse($page->ok);
        $this->assertSame([], $this->delays);
        Http::assertSentCount(1);
    }

    public function test_api_error_response_is_not_ok(): void
    {
        Http::fake(['*' => Http::response($this->fixture('get_domains_error'))]);

        $page = $this->client()->getDomainsPage(1, 100);

        $this->assertFalse($page->ok);
        $this->assertSame([], $page->domains);
    }

    public function test_unparseable_body_is_not_ok(): void
    {
        Http::fake(['*' => Http::response('<not-xml')]);

        $this->assertFalse($this->client()->getDomainsPage(1, 100)->ok);
    }

    public function test_missing_credentials_throw(): void
    {
        $client = new EnomClient('', '', false, fn () => null);

        $this->expectException(EnomUnavailableException::class);
        $client->getDomainsPage(1, 100);
    }

    public function test_debug_info_and_redact_hide_credentials(): void
    {
        $client = $this->client();

        $dump = print_r($client, true) . var_export($client->__debugInfo(), true);
        $this->assertStringNotContainsString(self::PW, $dump);
        $this->assertStringNotContainsString(self::UID, $dump);

        $redacted = EnomClient::redact('command=GetDomains&uid=' . self::UID . '&pw=' . self::PW . '&Start=1');
        $this->assertStringNotContainsString(self::PW, $redacted);
        $this->assertStringNotContainsString(self::UID, $redacted);
        $this->assertStringContainsString('Start=1', $redacted);
    }
}
