<?php

namespace App\Services;

use App\Exceptions\WhmConnectionException;
use App\Exceptions\WhmProvisioningException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhmService
{
    /**
     * Test connectivity to a WHM server using the provided credentials.
     *
     * @throws WhmConnectionException on connectivity or authentication failure
     */
    public function testConnection(string $hostname, string $apiToken, string $user = 'root'): bool
    {
        $this->request(new WhmCredentials($hostname, $apiToken, $user), 'listaccts', [], 15);

        return true;
    }

    /**
     * Create a hosting account on the WHM server using stored credentials.
     *
     * @return array{username: string} The cPanel username created
     * @throws WhmProvisioningException on account creation failure
     */
    public function createAccount(string $domain, string $package, string $contactEmail, ?string $username = null): array
    {
        $username ??= $this->generateUsername($domain);

        try {
            // WHM generates a password when none is sent, but we set our own random one so
            // the account never has a predictable credential. It is never returned or logged;
            // sent in the POST body so it stays out of URLs and access logs.
            $this->request(WhmCredentials::fromSettings(), 'createacct', [
                'username' => $username,
                'domain' => $domain,
                'plan' => $package,
                'contactemail' => $contactEmail,
                'password' => $this->randomPassword(),
            ], 30, true);
        } catch (WhmConnectionException $e) {
            Log::error('WHM: Account creation failed', [
                'domain' => $domain,
                'username' => $username,
                'error' => $e->getMessage(),
            ]);
            throw new WhmProvisioningException('WHM account creation failed: ' . $e->getMessage(), 0, $e);
        }

        Log::info('WHM: Account created successfully', [
            'domain' => $domain,
            'username' => $username,
            'package' => $package,
        ]);

        return ['username' => $username];
    }

    /**
     * Find the account whose main domain matches exactly, or null.
     *
     * @return array{user: string, domain: string, plan: string, suspended: bool}|null
     * @throws WhmConnectionException
     */
    public function findAccountByDomain(string $domain): ?array
    {
        $accounts = $this->queryAccounts(['searchtype' => 'domain', 'search' => $domain, 'searchmethod' => 'exact']);

        foreach ($accounts as $a) {
            if (strcasecmp($a['domain'], $domain) === 0) {
                return $a;
            }
        }

        return null;
    }

    /**
     * @throws WhmConnectionException
     */
    public function accountExists(string $username): bool
    {
        $accounts = $this->queryAccounts(['searchtype' => 'user', 'search' => $username, 'searchmethod' => 'exact']);

        foreach ($accounts as $a) {
            if ($a['user'] === $username) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array{user: string, domain: string, plan: string, suspended: bool}>
     * @throws WhmConnectionException
     */
    public function listAccounts(): array
    {
        return $this->queryAccounts();
    }

    /**
     * @return array{user: string, domain: string, plan: string, suspended: bool}
     * @throws WhmConnectionException when the account is missing or WHM fails
     */
    public function getAccountInfo(string $username): array
    {
        $data = $this->request(WhmCredentials::fromSettings(), 'accountsummary', ['user' => $username]);
        $acct = $data['data']['acct'][0] ?? null;

        if (! is_array($acct)) {
            throw new WhmConnectionException("WHM account {$username} not found");
        }

        return $this->normaliseAccount($acct);
    }

    /**
     * Create a single-use login URL for a cPanel user.
     *
     * @throws WhmConnectionException
     */
    public function createUserSession(string $username, string $service): string
    {
        $data = $this->request(WhmCredentials::fromSettings(), 'create_user_session', [
            'user' => $username,
            'service' => $service,
        ]);

        $url = $data['data']['url'] ?? null;
        if (! is_string($url) || $url === '') {
            throw new WhmConnectionException('WHM request failed: no session URL returned');
        }

        return $url;
    }

    /**
     * @return list<array{user: string, domain: string, plan: string, suspended: bool}>
     */
    private function queryAccounts(array $query = []): array
    {
        $data = $this->request(WhmCredentials::fromSettings(), 'listaccts', $query, 30);
        $acct = $data['data']['acct'] ?? [];

        return is_array($acct)
            ? array_values(array_map(fn ($a) => $this->normaliseAccount($a), array_filter($acct, 'is_array')))
            : [];
    }

    private function normaliseAccount(array $a): array
    {
        return [
            'user' => (string) ($a['user'] ?? ''),
            'domain' => (string) ($a['domain'] ?? ''),
            'plan' => (string) ($a['plan'] ?? ''),
            'suspended' => filter_var($a['suspended'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ];
    }

    private function randomPassword(): string
    {
        // 32 hex chars + fixed mixed-class suffix to satisfy password strength rules.
        return bin2hex(random_bytes(16)) . 'Aa#1';
    }

    /**
     * Single entry point for WHM JSON API calls. Always verifies TLS.
     *
     * @return array<string, mixed> decoded response
     * @throws WhmConnectionException
     */
    private function request(WhmCredentials $credentials, string $function, array $query = [], int $timeout = 15, bool $post = false): array
    {
        $url = "https://{$credentials->hostname}:2087/json-api/{$function}";
        $params = ['api.version' => 1] + $query;

        try {
            $pending = Http::withOptions(['verify' => true])
                ->withHeaders(['Authorization' => $credentials->authorizationHeader()])
                ->timeout($timeout);
            $response = $post ? $pending->asForm()->post($url, $params) : $pending->get($url, $params);
        } catch (\Throwable $e) {
            throw new WhmConnectionException('WHM connection failed: ' . $this->redact($e->getMessage(), $credentials), 0, $e);
        }

        if (! $response->successful()) {
            throw new WhmConnectionException("WHM request failed: HTTP {$response->status()}");
        }

        $data = $response->json();
        $result = is_array($data) ? ($data['metadata']['result'] ?? null) : null;
        if ($result != 1) {
            $reason = is_array($data) ? ($data['metadata']['reason'] ?? null) : null;
            throw new WhmConnectionException('WHM request failed: ' . ($reason ?: 'Unexpected response'));
        }

        return $data;
    }

    private function redact(string $message, WhmCredentials $credentials): string
    {
        return str_replace($credentials->token(), '***', $message);
    }

    /**
     * Generate a cPanel username from the domain.
     *
     * Takes the first 8 characters of the domain (without dots), lowercase.
     * cPanel usernames must be max 8 characters, lowercase alphanumeric.
     */
    private function generateUsername(string $domain): string
    {
        // Remove dots and special characters, take first 8 chars, lowercase
        $clean = preg_replace('/[^a-z0-9]/', '', strtolower($domain));

        return substr($clean, 0, 8);
    }
}
