<?php

namespace App\Services;

use App\Exceptions\WhmConnectionException;
use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;

/**
 * Immutable WHM connection credentials. The token is hidden from
 * serialisation and debug output so it cannot leak into logs.
 */
final class WhmCredentials
{
    public function __construct(
        public readonly string $hostname,
        private readonly string $apiToken,
        public readonly string $username = 'root',
    ) {
    }

    /**
     * @throws WhmConnectionException when hostname or token is missing/undecryptable
     */
    public static function fromSettings(): self
    {
        $hostname = self::decrypt('whm_hostname');
        $token = self::decrypt('whm_api_token');

        if ($hostname === null || $token === null) {
            throw new WhmConnectionException('WHM credentials are not configured. Please configure WHM settings first.');
        }

        $username = Setting::get('whm_username');
        $username = (is_string($username) && trim($username) !== '') ? trim($username) : 'root';

        return new self($hostname, $token, $username);
    }

    public function token(): string
    {
        return $this->apiToken;
    }

    public function authorizationHeader(): string
    {
        return "whm {$this->username}:{$this->apiToken}";
    }

    public function __debugInfo(): array
    {
        return ['hostname' => $this->hostname, 'username' => $this->username, 'apiToken' => '***'];
    }

    private static function decrypt(string $key): ?string
    {
        $value = Setting::get($key);
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            $plain = Crypt::decryptString($value);
        } catch (\Throwable) {
            return null;
        }

        return $plain === '' ? null : $plain;
    }
}
