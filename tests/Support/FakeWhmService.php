<?php

namespace Tests\Support;

use App\Exceptions\WhmConnectionException;
use App\Exceptions\WhmProvisioningException;
use App\Services\WhmService;

/**
 * Test double for WhmService. Makes no HTTP calls.
 *
 * Holds an in-memory account list keyed by username, counts calls per method,
 * and can be told to fail a method. Implements the method set the design gives
 * WhmService (findAccountByDomain, accountExists, listAccounts, getAccountInfo,
 * createUserSession) as well as the current testConnection/createAccount.
 */
class FakeWhmService extends WhmService
{
    /** @var array<string, array{user: string, domain: string, plan: string, suspended: bool, email?: string}> */
    public array $accounts = [];

    /** @var array<string, int> */
    public array $callCounts = [];

    /** @var list<array{method: string, args: array}> */
    public array $calls = [];

    /** @var array<string, array{remaining: int, exception: \Throwable}> */
    private array $failures = [];

    /** Set to make createAccount fail after the account is created (simulates a crash after WHM success). */
    public bool $failAfterCreate = false;

    public function __construct()
    {
    }

    /** @param list<array{user: string, domain: string, plan?: string, suspended?: bool}> $accounts */
    public function withAccounts(array $accounts): static
    {
        foreach ($accounts as $a) {
            $this->addAccount($a['user'], $a['domain'], $a['plan'] ?? 'default', $a['suspended'] ?? false);
        }

        return $this;
    }

    public function addAccount(string $user, string $domain, string $plan = 'default', bool $suspended = false): static
    {
        $this->accounts[$user] = [
            'user' => $user,
            'domain' => $domain,
            'plan' => $plan,
            'suspended' => $suspended,
        ];

        return $this;
    }

    /**
     * Make a method fail. $afterSuccesses lets that many calls succeed first.
     * Default exception is WhmProvisioningException for createAccount, else WhmConnectionException.
     */
    public function failAt(string $method, ?\Throwable $e = null, int $afterSuccesses = 0): static
    {
        $this->failures[$method] = [
            'remaining' => $afterSuccesses,
            'exception' => $e ?? ($method === 'createAccount'
                ? new WhmProvisioningException('Fake WHM failure at createAccount')
                : new WhmConnectionException("Fake WHM failure at {$method}")),
        ];

        return $this;
    }

    public function clearFailures(): static
    {
        $this->failures = [];
        $this->failAfterCreate = false;

        return $this;
    }

    public function callCount(string $method): int
    {
        return $this->callCounts[$method] ?? 0;
    }

    public function totalCalls(): int
    {
        return array_sum($this->callCounts);
    }

    // ------------------------------------------------------------------
    // WhmService API
    // ------------------------------------------------------------------

    public function testConnection(string $hostname, string $apiToken, string $user = 'root'): bool
    {
        $this->enter(__FUNCTION__, compact('hostname', 'user'));

        return true;
    }

    public function createAccount(string $domain, string $package, string $contactEmail, ?string $username = null): array
    {
        $this->enter(__FUNCTION__, compact('domain', 'package', 'contactEmail', 'username'));

        $username ??= $this->generateFakeUsername($domain);

        if (isset($this->accounts[$username])) {
            throw new WhmProvisioningException("WHM account creation failed: username {$username} already exists");
        }
        foreach ($this->accounts as $a) {
            if ($a['domain'] === $domain) {
                throw new WhmProvisioningException("WHM account creation failed: domain {$domain} already exists");
            }
        }

        $this->accounts[$username] = [
            'user' => $username,
            'domain' => $domain,
            'plan' => $package,
            'suspended' => false,
            'email' => $contactEmail,
        ];

        if ($this->failAfterCreate) {
            throw new WhmProvisioningException('Fake WHM failure after account creation');
        }

        return ['username' => $username];
    }

    /** @return array{user: string, domain: string, plan: string, suspended: bool}|null */
    public function findAccountByDomain(string $domain): ?array
    {
        $this->enter(__FUNCTION__, compact('domain'));

        foreach ($this->accounts as $a) {
            if ($a['domain'] === $domain) {
                return $this->publicShape($a);
            }
        }

        return null;
    }

    public function accountExists(string $username): bool
    {
        $this->enter(__FUNCTION__, compact('username'));

        return isset($this->accounts[$username]);
    }

    /** @return list<array{user: string, domain: string, plan: string, suspended: bool}> */
    public function listAccounts(): array
    {
        $this->enter(__FUNCTION__, []);

        return array_values(array_map(fn ($a) => $this->publicShape($a), $this->accounts));
    }

    /** @return array{user: string, domain: string, plan: string, suspended: bool} */
    public function getAccountInfo(string $username): array
    {
        $this->enter(__FUNCTION__, compact('username'));

        if (! isset($this->accounts[$username])) {
            throw new WhmConnectionException("Account {$username} not found");
        }

        return $this->publicShape($this->accounts[$username]);
    }

    public function createUserSession(string $username, string $service): string
    {
        $this->enter(__FUNCTION__, compact('username', 'service'));

        if (! isset($this->accounts[$username])) {
            throw new WhmConnectionException("Account {$username} not found");
        }

        return "https://whm.fake.test/cpsess0000000000/login/?user={$username}&service={$service}";
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    private function enter(string $method, array $args): void
    {
        $this->callCounts[$method] = ($this->callCounts[$method] ?? 0) + 1;
        $this->calls[] = ['method' => $method, 'args' => $args];

        if (isset($this->failures[$method])) {
            if ($this->failures[$method]['remaining'] > 0) {
                $this->failures[$method]['remaining']--;

                return;
            }

            throw $this->failures[$method]['exception'];
        }
    }

    private function publicShape(array $a): array
    {
        return [
            'user' => $a['user'],
            'domain' => $a['domain'],
            'plan' => $a['plan'],
            'suspended' => $a['suspended'],
        ];
    }

    private function generateFakeUsername(string $domain): string
    {
        $base = substr(preg_replace('/[^a-z0-9]/', '', strtolower($domain)), 0, 8);
        $base = ltrim($base, '0123456789') ?: 'acct';

        return $base;
    }
}
