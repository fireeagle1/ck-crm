<?php

namespace App\Services;

use App\Exceptions\WhmConnectionException;
use App\Exceptions\WhmProvisioningException;
use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhmService
{
    /**
     * Test connectivity to a WHM server using the provided credentials.
     *
     * Makes a listaccts API call to verify the hostname and token are valid.
     *
     * @throws WhmConnectionException on connectivity or authentication failure
     */
    public function testConnection(string $hostname, string $apiToken): bool
    {
        $url = "https://{$hostname}:2087/json-api/listaccts?api.version=1";

        try {
            $response = $this->buildRequest($hostname)
                ->withHeaders([
                    'Authorization' => "WHM root:{$apiToken}",
                ])
                ->timeout(15)
                ->get($url);

            if ($response->failed()) {
                throw new WhmConnectionException(
                    "WHM connection failed: HTTP {$response->status()} - " . ($response->body() ?: 'No response body')
                );
            }

            $data = $response->json();

            // WHM API returns metadata.result = 1 on success
            if (isset($data['metadata']['result']) && $data['metadata']['result'] != 1) {
                throw new WhmConnectionException(
                    'WHM authentication failed: ' . ($data['metadata']['reason'] ?? 'Invalid credentials')
                );
            }

            return true;
        } catch (WhmConnectionException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new WhmConnectionException(
                'WHM connection failed: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Create a hosting account on the WHM server.
     *
     * Uses the configured WHM hostname and API token from Settings (encrypted).
     *
     * @return array{username: string} The cPanel username created
     * @throws WhmProvisioningException on account creation failure
     */
    public function createAccount(string $domain, string $package, string $contactEmail): array
    {
        $hostname = $this->getDecryptedSetting('whm_hostname');
        $apiToken = $this->getDecryptedSetting('whm_api_token');

        if (!$hostname || !$apiToken) {
            throw new WhmProvisioningException('WHM credentials are not configured. Please configure WHM settings first.');
        }

        $username = $this->generateUsername($domain);

        $url = "https://{$hostname}:2087/json-api/createacct";

        try {
            $response = $this->buildRequest($hostname)
                ->withHeaders([
                    'Authorization' => "WHM root:{$apiToken}",
                ])
                ->timeout(30)
                ->get($url, [
                    'api.version' => 1,
                    'username' => $username,
                    'domain' => $domain,
                    'plan' => $package,
                    'contactemail' => $contactEmail,
                ]);

            if ($response->failed()) {
                Log::error('WHM: Account creation HTTP failure', [
                    'domain' => $domain,
                    'username' => $username,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                throw new WhmProvisioningException(
                    "WHM account creation failed: HTTP {$response->status()}"
                );
            }

            $data = $response->json();

            // WHM createacct returns metadata.result = 1 on success
            if (isset($data['metadata']['result']) && $data['metadata']['result'] != 1) {
                $reason = $data['metadata']['reason'] ?? 'Unknown error';

                Log::error('WHM: Account creation API failure', [
                    'domain' => $domain,
                    'username' => $username,
                    'reason' => $reason,
                ]);

                throw new WhmProvisioningException(
                    "WHM account creation failed: {$reason}"
                );
            }

            Log::info('WHM: Account created successfully', [
                'domain' => $domain,
                'username' => $username,
                'package' => $package,
            ]);

            return ['username' => $username];
        } catch (WhmProvisioningException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('WHM: Account creation exception', [
                'domain' => $domain,
                'username' => $username,
                'error' => $e->getMessage(),
            ]);

            throw new WhmProvisioningException(
                'WHM account creation failed: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Build an HTTP request, disabling SSL verification only for local connections.
     *
     * Remote WHM connections use standard certificate verification.
     * Local connections (localhost/127.0.0.1) skip verification since there's no network to intercept.
     */
    private function buildRequest(string $hostname): \Illuminate\Http\Client\PendingRequest
    {
        $isLocal = in_array($hostname, ['localhost', '127.0.0.1', '::1'], true)
            || str_starts_with($hostname, '192.168.')
            || str_starts_with($hostname, '10.');

        return $isLocal ? Http::withoutVerifying() : Http::withOptions(['verify' => true]);
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

    /**
     * Retrieve and decrypt a WHM setting from the database.
     */
    private function getDecryptedSetting(string $key): ?string
    {
        $value = Setting::get($key);

        if (!$value) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Exception $e) {
            Log::warning("WHM: Failed to decrypt setting '{$key}'", [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
