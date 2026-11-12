<?php

namespace App\Services;

use App\Exceptions\EnomUnavailableException;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * HTTP and XML handling for the eNom reseller API (GetDomains only).
 *
 * Credentials travel in the POST form body, never in the URL. The client never
 * logs and never puts the request body in exception messages.
 *
 * eNom GetDomains (https://api.enom.com/docs/get-domains): `Start` is the 1-based
 * position in the sorted list, `Display` is the page size (0 to 100), and the
 * response carries the account total in `TotalDomainCount`.
 */
class EnomClient
{
    public const MAX_PAGE_SIZE = 100;

    /** Delays in milliseconds before retry 1, 2 and 3. */
    public const RETRY_DELAYS_MS = [500, 1500, 4000];

    private const LIVE_URL = 'https://reseller.enom.com/interface.asp';
    private const SANDBOX_URL = 'https://resellertest.enom.com/interface.asp';

    private string $username;
    private string $password;
    private bool $sandbox;

    /** @var Closure(int): void */
    private Closure $sleeper;

    /**
     * @param  ?Closure(int): void  $sleeper  Receives the delay in milliseconds. Defaults to usleep.
     */
    public function __construct(
        ?string $username = null,
        ?string $password = null,
        ?bool $sandbox = null,
        ?Closure $sleeper = null,
    ) {
        $this->username = (string) ($username ?? config('services.enom.username'));
        $this->password = (string) ($password ?? config('services.enom.password'));
        $this->sandbox = (bool) ($sandbox ?? config('services.enom.sandbox', false));
        $this->sleeper = $sleeper ?? static function (int $ms): void {
            usleep($ms * 1000);
        };
    }

    public function isConfigured(): bool
    {
        return $this->username !== '' && $this->password !== '';
    }

    /**
     * Fetch one page of domains.
     *
     * @param  int  $start     1-based position in eNom's sorted list
     * @param  int  $pageSize  1 to 100
     *
     * @throws EnomUnavailableException when eNom is unreachable or keeps returning 5xx
     */
    public function getDomainsPage(int $start, int $pageSize): EnomPage
    {
        if (! $this->isConfigured()) {
            throw new EnomUnavailableException('eNom credentials are not configured.');
        }

        $start = max(1, $start);
        $pageSize = min(self::MAX_PAGE_SIZE, max(1, $pageSize));

        $body = $this->post([
            'command' => 'GetDomains',
            'responsetype' => 'xml',
            'Tab' => 'IOwn',
            'Display' => $pageSize,
            'Start' => $start,
        ]);

        if ($body === null) {
            return new EnomPage([], 0, false);
        }

        return $this->parse($body);
    }

    /**
     * Strip uid and pw values from arbitrary text (query strings, form bodies,
     * XML echoes) before it is shown in debug output.
     */
    public static function redact(string $text): string
    {
        $text = preg_replace('/(?<![A-Za-z0-9_])(uid|pw)=[^&\s"\']*/i', '$1=[redacted]', $text) ?? '';
        $text = preg_replace('/(<(uid|pw)>)[^<]*(<\/\2>)/i', '$1[redacted]$3', $text) ?? '';

        return $text;
    }

    /**
     * Never expose credentials through var_dump, dd or similar.
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'username' => '[redacted]',
            'password' => '[redacted]',
            'sandbox' => $this->sandbox,
        ];
    }

    /**
     * POST with retries. Returns the body, or null for a non-retryable 4xx.
     */
    private function post(array $params): ?string
    {
        $url = $this->sandbox ? self::SANDBOX_URL : self::LIVE_URL;
        $form = $params + ['uid' => $this->username, 'pw' => $this->password];

        $attempts = count(self::RETRY_DELAYS_MS) + 1;
        $reason = 'unknown error';

        for ($attempt = 0; $attempt < $attempts; $attempt++) {
            if ($attempt > 0) {
                ($this->sleeper)(self::RETRY_DELAYS_MS[$attempt - 1]);
            }

            try {
                $response = Http::timeout(30)->asForm()->post($url, $form);
            } catch (ConnectionException) {
                // The cURL message can embed the request URL, so only a fixed text is kept.
                $reason = 'connection error';
                continue;
            }

            if ($response->serverError()) {
                $reason = 'HTTP ' . $response->status();
                continue;
            }

            if (! $response->successful()) {
                return null;
            }

            return $response->body();
        }

        throw new EnomUnavailableException(
            'eNom request failed after ' . (count(self::RETRY_DELAYS_MS)) . ' retries (' . $reason . ').'
        );
    }

    private function parse(string $body): EnomPage
    {
        $previous = libxml_use_internal_errors(true);
        try {
            $xml = simplexml_load_string($body);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if ($xml === false || (int) ($xml->ErrCount ?? 0) > 0) {
            return new EnomPage([], 0, false);
        }

        $getDomains = $xml->GetDomains ?? null;
        if ($getDomains === null || ! isset($getDomains->TotalDomainCount)) {
            return new EnomPage([], 0, false);
        }

        $total = (int) $getDomains->TotalDomainCount;
        $domains = [];

        foreach ($getDomains->{'domain-list'}->domain ?? [] as $node) {
            $sld = trim((string) $node->sld);
            $tld = trim((string) $node->tld);
            if ($sld === '' || $tld === '') {
                continue;
            }

            $autoRenew = strtolower(trim((string) $node->{'auto-renew'}));

            $domains[] = [
                'domain_name_id' => trim((string) $node->DomainNameID),
                'sld' => $sld,
                'tld' => $tld,
                'domain_name' => $sld . '.' . $tld,
                'expiration_date' => trim((string) $node->{'expiration-date'}),
                'auto_renew' => in_array($autoRenew, ['yes', '1', 'true'], true),
            ];
        }

        return new EnomPage($domains, $total, true);
    }
}
