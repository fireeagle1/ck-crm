<?php

namespace App\Notifications\Channels;

use App\Models\DeviceToken;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class ApnChannel
{
    /**
     * APNs production endpoint.
     */
    private const APN_PRODUCTION_URL = 'https://api.push.apple.com';

    /**
     * APNs sandbox endpoint.
     */
    private const APN_SANDBOX_URL = 'https://api.sandbox.push.apple.com';

    /**
     * Send the given notification via APNs.
     */
    public function send(object $notifiable, Notification $notification): void
    {
        $payload = $notification->toApn($notifiable);

        if (empty($payload)) {
            return;
        }

        $tokens = $this->getDeviceTokens($notifiable);

        if ($tokens->isEmpty()) {
            return;
        }

        foreach ($tokens as $deviceToken) {
            $this->sendToDevice($deviceToken, $payload);
        }
    }

    /**
     * Get device tokens for the notifiable entity.
     *
     * If the notifiable is a User, get their tokens.
     * Otherwise, get all admin device tokens (broadcast to all admins).
     */
    private function getDeviceTokens(object $notifiable): \Illuminate\Support\Collection
    {
        if (method_exists($notifiable, 'deviceTokens')) {
            return $notifiable->deviceTokens()->where('platform', 'ios')->get();
        }

        // Broadcast to all admin device tokens
        return DeviceToken::whereHas('user', function ($query) {
            $query->where('is_admin', true);
        })->where('platform', 'ios')->get();
    }

    /**
     * Send a push notification to a single device token via APNs HTTP/2.
     */
    private function sendToDevice(DeviceToken $deviceToken, array $payload): void
    {
        $url = $this->getApnUrl() . '/3/device/' . $deviceToken->token;

        $apnPayload = json_encode(['aps' => $payload]);

        try {
            $headers = $this->buildHeaders();

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_2_0);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $apnPayload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $this->handleResponse($httpCode, $response, $deviceToken);
        } catch (\Exception $e) {
            Log::error('APNs push notification failed', [
                'token' => substr($deviceToken->token, 0, 10) . '...',
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the APNs response and clean up invalid tokens.
     */
    private function handleResponse(int $httpCode, ?string $response, DeviceToken $deviceToken): void
    {
        if ($httpCode === 200) {
            return;
        }

        $responseData = json_decode($response, true);
        $reason = $responseData['reason'] ?? 'Unknown';

        // Remove invalid device tokens per Requirement 17.5
        $invalidReasons = [
            'BadDeviceToken',
            'Unregistered',
            'ExpiredToken',
            'DeviceTokenNotForTopic',
        ];

        if (in_array($reason, $invalidReasons)) {
            $deviceToken->delete();

            Log::info('Removed invalid APNs device token', [
                'token' => substr($deviceToken->token, 0, 10) . '...',
                'reason' => $reason,
            ]);
        } else {
            Log::warning('APNs push notification error', [
                'http_code' => $httpCode,
                'reason' => $reason,
                'token' => substr($deviceToken->token, 0, 10) . '...',
            ]);
        }
    }

    /**
     * Build HTTP headers for the APNs request including JWT auth.
     */
    private function buildHeaders(): array
    {
        $headers = [
            'Content-Type: application/json',
            'apns-topic: ' . config('services.apn.bundle_id'),
            'apns-push-type: alert',
            'apns-priority: 10',
        ];

        $jwt = $this->generateJwt();
        if ($jwt) {
            $headers[] = 'Authorization: Bearer ' . $jwt;
        }

        return $headers;
    }

    /**
     * Generate a JWT token for APNs authentication.
     *
     * Uses the ES256 algorithm with the APNs auth key.
     */
    private function generateJwt(): ?string
    {
        $keyId = config('services.apn.key_id');
        $teamId = config('services.apn.team_id');
        $keyPath = config('services.apn.key_path');

        if (! $keyId || ! $teamId || ! $keyPath) {
            Log::warning('APNs configuration incomplete — push notifications will not be delivered.');
            return null;
        }

        if (! file_exists($keyPath)) {
            Log::error('APNs key file not found', ['path' => $keyPath]);
            return null;
        }

        $key = file_get_contents($keyPath);

        $header = $this->base64UrlEncode(json_encode([
            'alg' => 'ES256',
            'kid' => $keyId,
        ]));

        $claims = $this->base64UrlEncode(json_encode([
            'iss' => $teamId,
            'iat' => time(),
        ]));

        $signingInput = $header . '.' . $claims;

        $privateKey = openssl_pkey_get_private($key);
        if (! $privateKey) {
            Log::error('Failed to load APNs private key');
            return null;
        }

        $signature = '';
        $success = openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        if (! $success) {
            Log::error('Failed to sign APNs JWT');
            return null;
        }

        // Convert DER signature to raw R+S format for ES256
        $signature = $this->derToRaw($signature);

        return $signingInput . '.' . $this->base64UrlEncode($signature);
    }

    /**
     * Get the appropriate APNs URL based on environment.
     */
    private function getApnUrl(): string
    {
        if (config('services.apn.sandbox', false)) {
            return self::APN_SANDBOX_URL;
        }

        return self::APN_PRODUCTION_URL;
    }

    /**
     * Base64 URL-safe encoding without padding.
     */
    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Convert a DER-encoded ECDSA signature to raw R+S concatenation.
     */
    private function derToRaw(string $der): string
    {
        $pos = 0;
        $pos++; // skip SEQUENCE tag (0x30)
        $pos++; // skip SEQUENCE length

        // Read R
        $pos++; // skip INTEGER tag (0x02)
        $rLen = ord($der[$pos]);
        $pos++;
        $r = substr($der, $pos, $rLen);
        $pos += $rLen;

        // Read S
        $pos++; // skip INTEGER tag (0x02)
        $sLen = ord($der[$pos]);
        $pos++;
        $s = substr($der, $pos, $sLen);

        // Pad or trim R and S to 32 bytes each
        $r = ltrim($r, "\x00");
        $s = ltrim($s, "\x00");
        $r = str_pad($r, 32, "\x00", STR_PAD_LEFT);
        $s = str_pad($s, 32, "\x00", STR_PAD_LEFT);

        return $r . $s;
    }
}
