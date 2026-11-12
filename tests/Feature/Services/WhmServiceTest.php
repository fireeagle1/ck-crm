<?php

namespace Tests\Feature\Services;

use App\Exceptions\WhmConnectionException;
use App\Exceptions\WhmProvisioningException;
use App\Models\Setting;
use App\Services\WhmCredentials;
use App\Services\WhmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class WhmServiceTest extends TestCase
{
    use RefreshDatabase;

    private function configure(string $host = 'whm.example.com', string $token = 'SECRETTOKEN', ?string $user = null): void
    {
        Setting::set('whm_hostname', Crypt::encryptString($host));
        Setting::set('whm_api_token', Crypt::encryptString($token));
        if ($user !== null) {
            Setting::set('whm_username', $user);
        }
    }

    public function test_credentials_from_settings_default_username_root(): void
    {
        $this->configure();
        $c = WhmCredentials::fromSettings();

        $this->assertSame('whm.example.com', $c->hostname);
        $this->assertSame('whm root:SECRETTOKEN', $c->authorizationHeader());
    }

    public function test_credentials_use_configured_username(): void
    {
        $this->configure(user: 'reseller');

        $this->assertSame('whm reseller:SECRETTOKEN', WhmCredentials::fromSettings()->authorizationHeader());
    }

    public function test_credentials_throw_when_missing(): void
    {
        $this->expectException(WhmConnectionException::class);
        WhmCredentials::fromSettings();
    }

    public function test_credentials_throw_when_token_missing(): void
    {
        Setting::set('whm_hostname', Crypt::encryptString('whm.example.com'));
        $this->expectException(WhmConnectionException::class);
        WhmCredentials::fromSettings();
    }

    public function test_test_connection_succeeds_and_sends_auth_header_with_verify(): void
    {
        Http::fake(['*' => Http::response(['metadata' => ['result' => 1]])]);

        $this->assertTrue((new WhmService)->testConnection('whm.example.com', 'tok'));

        Http::assertSent(function (Request $r) {
            return $r->hasHeader('Authorization', 'whm root:tok')
                && str_contains($r->url(), 'https://whm.example.com:2087/json-api/listaccts');
        });
    }

    public function test_local_hostnames_still_verify(): void
    {
        foreach (['localhost', '127.0.0.1', '192.168.1.5', '10.0.0.2'] as $host) {
            $captured = [];
            Http::fake(function (Request $r, array $options) use (&$captured) {
                $captured = $options;

                return Http::response(['metadata' => ['result' => 1]]);
            });

            (new WhmService)->testConnection($host, 'tok');

            $this->assertTrue($captured['verify'], "TLS verification must stay on for {$host}");
        }
    }

    public function test_non_2xx_throws(): void
    {
        Http::fake(['*' => Http::response('nope', 500)]);

        $this->expectException(WhmConnectionException::class);
        $this->expectExceptionMessage('HTTP 500');
        (new WhmService)->testConnection('whm.example.com', 'tok');
    }

    public function test_metadata_result_zero_throws_with_reason(): void
    {
        Http::fake(['*' => Http::response(['metadata' => ['result' => 0, 'reason' => 'Access denied']])]);

        $this->expectException(WhmConnectionException::class);
        $this->expectExceptionMessage('Access denied');
        (new WhmService)->testConnection('whm.example.com', 'tok');
    }

    public function test_connection_error_throws_without_leaking_token(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error: could not resolve tok-LEAK'));

        try {
            (new WhmService)->testConnection('whm.example.com', 'tok-LEAK');
            $this->fail('Expected exception');
        } catch (WhmConnectionException $e) {
            $this->assertStringNotContainsString('tok-LEAK', $e->getMessage());
        }
    }

    public function test_create_account_success(): void
    {
        $this->configure();
        Http::fake(['*' => Http::response(['metadata' => ['result' => 1]])]);

        $result = (new WhmService)->createAccount('example.com', 'basic', 'a@b.test');

        $this->assertSame(['username' => 'examplec'], $result);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'createacct')
            && $r->hasHeader('Authorization', 'whm root:SECRETTOKEN'));
    }

    public function test_create_account_failure_is_provisioning_exception_and_never_logs_token(): void
    {
        $this->configure();
        Http::fake(['*' => Http::response(['metadata' => ['result' => 0, 'reason' => 'Bad plan']])]);
        Log::spy();

        try {
            (new WhmService)->createAccount('example.com', 'basic', 'a@b.test');
            $this->fail('Expected exception');
        } catch (WhmProvisioningException $e) {
            $this->assertStringContainsString('Bad plan', $e->getMessage());
        }

        Log::shouldHaveReceived('error')->withArgs(
            fn ($msg, $ctx = []) => ! str_contains(json_encode($ctx), 'SECRETTOKEN')
        );
    }

    public function test_create_account_sends_random_password_in_post_body_and_does_not_return_or_log_it(): void
    {
        $this->configure();
        Http::fake(['*' => Http::response(['metadata' => ['result' => 1]])]);
        Log::spy();

        $result = (new WhmService)->createAccount('example.com', 'basic', 'a@b.test', 'myuser');

        $this->assertSame(['username' => 'myuser'], $result);
        $password = null;
        Http::assertSent(function (Request $r) use (&$password) {
            parse_str($r->body(), $body);
            $password = $body['password'] ?? null;

            return $r->method() === 'POST'
                && ! str_contains($r->url(), 'password')
                && $body['username'] === 'myuser' && $body['plan'] === 'basic'
                && $body['contactemail'] === 'a@b.test' && $body['domain'] === 'example.com';
        });
        $this->assertGreaterThanOrEqual(12, strlen($password));
        Log::shouldHaveReceived('info')->withArgs(
            fn ($msg, $ctx = []) => ! str_contains(json_encode($ctx), $password)
        );
    }

    public function test_create_account_passwords_differ_between_calls(): void
    {
        $this->configure();
        Http::fake(['*' => Http::response(['metadata' => ['result' => 1]])]);

        (new WhmService)->createAccount('a.com', 'basic', 'a@b.test', 'usera');
        (new WhmService)->createAccount('b.com', 'basic', 'a@b.test', 'userb');

        $passwords = [];
        Http::assertSent(function (Request $r) use (&$passwords) {
            parse_str($r->body(), $b);
            $passwords[] = $b['password'];

            return true;
        });
        $this->assertCount(2, array_unique($passwords));
    }

    private function listFixture(array $accts): array
    {
        return ['metadata' => ['result' => 1], 'data' => ['acct' => $accts]];
    }

    private function acct(string $user, string $domain, string $plan = 'basic', int $suspended = 0): array
    {
        return ['user' => $user, 'domain' => $domain, 'plan' => $plan, 'suspended' => $suspended, 'diskused' => '1M'];
    }

    public function test_list_accounts_returns_normalised_shape(): void
    {
        $this->configure();
        Http::fake(['*' => Http::response($this->listFixture([
            $this->acct('alice', 'alice.com'),
            $this->acct('bob', 'bob.com', 'pro', 1),
        ]))]);

        $this->assertSame([
            ['user' => 'alice', 'domain' => 'alice.com', 'plan' => 'basic', 'suspended' => false],
            ['user' => 'bob', 'domain' => 'bob.com', 'plan' => 'pro', 'suspended' => true],
        ], (new WhmService)->listAccounts());
    }

    public function test_list_accounts_empty_when_no_acct_key(): void
    {
        $this->configure();
        Http::fake(['*' => Http::response(['metadata' => ['result' => 1], 'data' => []])]);

        $this->assertSame([], (new WhmService)->listAccounts());
    }

    public function test_list_accounts_failure_throws(): void
    {
        $this->configure();
        Http::fake(['*' => Http::response('x', 503)]);

        $this->expectException(WhmConnectionException::class);
        (new WhmService)->listAccounts();
    }

    public function test_find_account_by_domain_uses_exact_domain_search(): void
    {
        $this->configure();
        Http::fake(['*' => Http::response($this->listFixture([$this->acct('alice', 'alice.com')]))]);

        $found = (new WhmService)->findAccountByDomain('alice.com');

        $this->assertSame('alice', $found['user']);
        Http::assertSent(function (Request $r) {
            parse_str(parse_url($r->url(), PHP_URL_QUERY), $q);

            return $q['searchtype'] === 'domain' && $q['search'] === 'alice.com' && $q['searchmethod'] === 'exact';
        });
    }

    public function test_find_account_by_domain_returns_null_when_none_or_mismatch(): void
    {
        $this->configure();
        Http::fake(['*' => Http::sequence()
            ->push($this->listFixture([]))
            ->push($this->listFixture([$this->acct('other', 'other.com')]))]);

        $this->assertNull((new WhmService)->findAccountByDomain('alice.com'));
        $this->assertNull((new WhmService)->findAccountByDomain('alice.com'));
    }

    public function test_find_account_by_domain_failure_throws(): void
    {
        $this->configure();
        Http::fake(['*' => Http::response(['metadata' => ['result' => 0, 'reason' => 'denied']])]);

        $this->expectException(WhmConnectionException::class);
        (new WhmService)->findAccountByDomain('alice.com');
    }

    public function test_account_exists_true_false_and_failure(): void
    {
        $this->configure();
        Http::fake(['*' => Http::sequence()
            ->push($this->listFixture([$this->acct('alice', 'alice.com')]))
            ->push($this->listFixture([]))
            ->push('boom', 500)]);

        $svc = new WhmService;
        $this->assertTrue($svc->accountExists('alice'));
        $this->assertFalse($svc->accountExists('alice'));
        Http::assertSent(function (Request $r) {
            parse_str(parse_url($r->url(), PHP_URL_QUERY), $q);

            return $q['searchtype'] === 'user' && $q['searchmethod'] === 'exact';
        });

        $this->expectException(WhmConnectionException::class);
        $svc->accountExists('alice');
    }

    public function test_get_account_info_success_and_not_found(): void
    {
        $this->configure();
        Http::fake(['*' => Http::sequence()
            ->push(['metadata' => ['result' => 1], 'data' => ['acct' => [$this->acct('alice', 'alice.com')]]])
            ->push(['metadata' => ['result' => 1], 'data' => ['acct' => []]])
            ->push(['metadata' => ['result' => 0, 'reason' => 'Unable to find user']])]);

        $svc = new WhmService;
        $this->assertSame(
            ['user' => 'alice', 'domain' => 'alice.com', 'plan' => 'basic', 'suspended' => false],
            $svc->getAccountInfo('alice')
        );
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'accountsummary') && str_contains($r->url(), 'user=alice'));

        try {
            $svc->getAccountInfo('ghost');
            $this->fail('Expected exception');
        } catch (WhmConnectionException $e) {
            $this->assertStringContainsString('not found', $e->getMessage());
        }

        $this->expectException(WhmConnectionException::class);
        $svc->getAccountInfo('ghost');
    }

    public function test_create_user_session_returns_url(): void
    {
        $this->configure();
        Http::fake(['*' => Http::response([
            'metadata' => ['result' => 1],
            'data' => ['url' => 'https://whm.example.com:2087/cpsess123/login/?session=abc'],
        ])]);

        $url = (new WhmService)->createUserSession('alice', 'cpaneld');

        $this->assertSame('https://whm.example.com:2087/cpsess123/login/?session=abc', $url);
        Http::assertSent(function (Request $r) {
            parse_str(parse_url($r->url(), PHP_URL_QUERY), $q);

            return str_contains($r->url(), 'create_user_session') && $q['user'] === 'alice' && $q['service'] === 'cpaneld';
        });
    }

    public function test_create_user_session_failure_and_missing_url_throw(): void
    {
        $this->configure();
        Http::fake(['*' => Http::sequence()
            ->push(['metadata' => ['result' => 0, 'reason' => 'No such user']])
            ->push(['metadata' => ['result' => 1], 'data' => []])]);

        $svc = new WhmService;
        try {
            $svc->createUserSession('ghost', 'cpaneld');
            $this->fail('Expected exception');
        } catch (WhmConnectionException $e) {
            $this->assertStringContainsString('No such user', $e->getMessage());
        }

        $this->expectException(WhmConnectionException::class);
        $svc->createUserSession('alice', 'cpaneld');
    }

    public function test_create_account_without_credentials_throws_provisioning_exception(): void
    {
        $this->expectException(WhmProvisioningException::class);
        (new WhmService)->createAccount('example.com', 'basic', 'a@b.test');
    }
}
