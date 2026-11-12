<?php

namespace Tests\Support;

use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Seeded generator for property-style PHPUnit loops (no PBT library in this repo).
 *
 * Same seed gives the same sequence on every PHP version (Mt19937 via Random\\Randomizer).
 * Tests should pass `$gen->msg()` into assertion messages so a failure shows the
 * seed and iteration, and can be replayed with `new Gen($seed)`.
 *
 *   foreach (Gen::seeds(100) as $seed) {
 *       $g = new Gen($seed);
 *       $this->assertSame($x, f($g->domainVariant('a.com')), $g->msg());
 *   }
 *
 * Self-contained: depends on no app classes, so it works before enums/normaliser exist.
 */
final class Gen
{
    /** Domain values used across the legacy and canonical sets (see ServiceType / ServiceStatus). */
    public const SERVICE_TYPES = ['Technical Support', 'Web Hosting', 'Other'];
    public const SERVICE_STATUSES = ['Pending', 'Active', 'Suspended', 'Cancelled'];
    public const PROVISIONING_STATUSES = ['queued', 'running', 'succeeded', 'failed'];
    public const FINDING_STATUSES = ['open', 'resolved', 'dismissed', 'not_reported'];
    public const SEVERITIES = ['low', 'medium', 'high', 'critical'];

    private const TLDS = ['com', 'co.uk', 'org', 'net', 'io', 'uk', 'shop'];

    /** Unicode labels paired with their expected IDNA (UTS46) ASCII form. */
    private const UNICODE_LABELS = [
        'bücher' => 'xn--bcher-kva',
        'münchen' => 'xn--mnchen-3ya',
        'café' => 'xn--caf-dma',
        'straße' => 'xn--strae-oqa',
        'пример' => 'xn--e1afmkfd',
        '例え' => 'xn--r8jz45g',
    ];

    private Randomizer $rng;
    private int $step = 0;

    public function __construct(public readonly int $seed)
    {
        // Mt19937 is seed-stable across PHP versions (PHP 8.2+).
        $this->rng = new Randomizer(new Mt19937($seed));
    }

    /** @return list<int> seeds 1..$count, for use in loops. */
    public static function seeds(int $count, int $start = 1): array
    {
        return range($start, $start + $count - 1);
    }

    /** Assertion message fragment: include in every assertion in a property loop. */
    public function msg(string $extra = ''): string
    {
        return trim("[seed={$this->seed} draw={$this->step}] {$extra}");
    }

    // ---------------------------------------------------------------- primitives

    /** Uniform integer in [$min, $max]. */
    public function int(int $min, int $max): int
    {
        if ($max < $min) {
            [$min, $max] = [$max, $min];
        }
        $this->step++;
        $range = $max - $min + 1;

        return $min + (int) ($this->next() % $range);
    }

    public function bool(int $percentTrue = 50): bool
    {
        return $this->int(1, 100) <= $percentTrue;
    }

    /**
     * @template T
     * @param  non-empty-list<T> $items
     * @return T
     */
    public function pick(array $items): mixed
    {
        return $items[$this->int(0, count($items) - 1)];
    }

    /**
     * @template T
     * @param  list<T> $items
     * @return list<T>
     */
    public function shuffle(array $items): array
    {
        for ($i = count($items) - 1; $i > 0; $i--) {
            $j = $this->int(0, $i);
            [$items[$i], $items[$j]] = [$items[$j], $items[$i]];
        }

        return $items;
    }

    // ------------------------------------------------------------------- strings

    /** Random string from an alphabet (default lowercase letters and digits). */
    public function string(int $minLen = 0, int $maxLen = 20, string $alphabet = 'abcdefghijklmnopqrstuvwxyz0123456789'): string
    {
        $len = $this->int($minLen, $maxLen);
        $out = '';
        $max = strlen($alphabet) - 1;
        for ($i = 0; $i < $len; $i++) {
            $out .= $alphabet[$this->int(0, $max)];
        }

        return $out;
    }

    /** Non-empty alphanumeric string. */
    public function word(int $minLen = 1, int $maxLen = 12): string
    {
        return $this->string(max(1, $minLen), $maxLen, 'abcdefghijklmnopqrstuvwxyz0123456789');
    }

    /** Leading-letter label of 1 to $maxLen chars, valid as a DNS label (may include inner hyphens). */
    public function label(int $maxLen = 15): string
    {
        $label = $this->string(1, 1, 'abcdefghijklmnopqrstuvwxyz');
        $rest = $this->string(0, max(0, $maxLen - 1), 'abcdefghijklmnopqrstuvwxyz0123456789-');

        return rtrim($label . $rest, '-');
    }

    /** Random-case version of $s. */
    public function mixedCase(string $s): string
    {
        $out = '';
        foreach (mb_str_split($s) as $ch) {
            $out .= $this->bool() ? mb_strtoupper($ch) : mb_strtolower($ch);
        }

        return $out;
    }

    /** $s with random leading and trailing whitespace. */
    public function padded(string $s): string
    {
        $ws = [' ', "\t", "\n", '  '];

        return ($this->bool() ? $this->pick($ws) : '') . $s . ($this->bool() ? $this->pick($ws) : '');
    }

    // ------------------------------------------------------------------- domains

    /** Canonical (lowercase, ASCII, no www, no trailing dot) domain such as `shop-1.example.co.uk`. */
    public function canonicalDomain(): string
    {
        $labels = [];
        for ($i = $this->int(0, 2); $i > 0; $i--) {
            $labels[] = $this->label(10);
        }
        $labels[] = $this->label(12);

        $domain = implode('.', $labels) . '.' . $this->pick(self::TLDS);

        // Never start with "www": that prefix is stripped by the normaliser and would break canonical-ness.
        return str_starts_with($domain, 'www.') ? 'a' . $domain : $domain;
    }

    /**
     * A messy variant of a canonical domain: random casing, whitespace, repeated `www.`
     * prefixes and trailing dots. Normalising the result must equal $canonical.
     */
    public function domainVariant(string $canonical): string
    {
        $d = $canonical;

        for ($i = $this->int(0, 2); $i > 0; $i--) {
            $d = 'www.' . $d;
        }
        for ($i = $this->int(0, 2); $i > 0; $i--) {
            $d .= '.';
        }
        if ($this->bool()) {
            $d = $this->mixedCase($d);
        }

        return $this->bool() ? $this->padded($d) : $d;
    }

    /**
     * Unicode domain with its expected canonical (punycode) form.
     *
     * @return array{input:string, canonical:string}
     */
    public function unicodeDomain(): array
    {
        $unicode = $this->pick(array_keys(self::UNICODE_LABELS));
        $tld = $this->pick(self::TLDS);
        $canonical = self::UNICODE_LABELS[$unicode] . '.' . $tld;
        $input = $unicode . '.' . $tld;

        if ($this->bool()) {
            $input = 'www.' . $input;
        }
        if ($this->bool()) {
            $input .= '.';
        }
        if ($this->bool()) {
            // Simple mapping: full mapping would turn ß into SS.
            $input = mb_convert_case($input, MB_CASE_UPPER_SIMPLE);
        }


        return ['input' => $input, 'canonical' => $canonical];
    }

    /**
     * Input that must be rejected by the normaliser.
     */
    public function invalidDomain(): string
    {
        return match ($this->int(0, 9)) {
            0 => '',
            1 => '   ',
            2 => $this->label(10),                                   // single label
            3 => $this->label(8) . '..' . $this->pick(self::TLDS),   // empty label
            4 => '-' . $this->label(8) . '.' . $this->pick(self::TLDS),
            5 => $this->label(8) . '-.' . $this->pick(self::TLDS),
            6 => $this->label(8) . '.com/' . $this->word(),          // path
            7 => $this->label(8) . ' ' . $this->label(5) . '.com',   // inner space
            8 => str_repeat('a', 64) . '.com',                       // label > 63
            default => implode('.', array_fill(0, 60, 'abcd')) . '.com', // total > 253
        };
    }

    // ----------------------------------------------------- statuses and quantities

    public function serviceType(): string
    {
        return $this->pick(self::SERVICE_TYPES);
    }

    public function serviceStatus(): string
    {
        return $this->pick(self::SERVICE_STATUSES);
    }

    public function provisioningStatus(): string
    {
        return $this->pick(self::PROVISIONING_STATUSES);
    }

    public function findingStatus(): string
    {
        return $this->pick(self::FINDING_STATUSES);
    }

    /**
     * A legacy or messy spelling of a known label, with its canonical label.
     * Aliases follow the design: 'hosting', 'pending', 'payment_failed', 'cancelled'.
     *
     * @return array{input:string, expected:string}
     */
    public function legacyLabel(): array
    {
        $aliases = [
            ['hosting', 'Web Hosting'],
            ['pending', 'Pending'],
            ['payment_failed', 'Suspended'],
            ['cancelled', 'Cancelled'],
        ];
        if ($this->bool(30)) {
            [$input, $expected] = $this->pick($aliases);
        } else {
            $expected = $this->pick([...self::SERVICE_TYPES, ...self::SERVICE_STATUSES]);
            $input = $expected;
        }

        return ['input' => $this->padded($this->mixedCase($input)), 'expected' => $expected];
    }

    /** A string that is not any known label or alias. */
    public function unknownLabel(): string
    {
        return 'zz_' . $this->word(3, 10);
    }

    /** Purchase quantity, at least 1. */
    public function quantity(int $max = 20): int
    {
        return $this->int(1, $max);
    }

    /** Stock level; null means unlimited. */
    public function stock(int $max = 50, int $percentUnlimited = 10): ?int
    {
        return $this->bool($percentUnlimited) ? null : $this->int(0, $max);
    }

    /**
     * Cart-like list of quantities.
     *
     * @return list<int>
     */
    public function quantities(int $maxItems = 5, int $maxQty = 10): array
    {
        $out = [];
        for ($i = $this->int(1, $maxItems); $i > 0; $i--) {
            $out[] = $this->quantity($maxQty);
        }

        return $out;
    }

    // ------------------------------------------------------------------ findings

    /**
     * One finding in the ingest payload shape: severity, description, status.
     *
     * @return array{severity:string, description:string, status:string}
     */
    public function finding(?string $status = null): array
    {
        return [
            'severity' => $this->pick(self::SEVERITIES),
            'description' => $this->word(3, 10) . ' ' . $this->word(3, 12) . ' ' . $this->word(1, 8),
            'status' => $status ?? $this->pick(['open', 'resolved', 'dismissed']),
        ];
    }

    /**
     * @return list<array{severity:string, description:string, status:string}>
     */
    public function findings(int $min = 0, int $max = 10, ?string $status = null): array
    {
        $out = [];
        for ($i = $this->int($min, $max); $i > 0; $i--) {
            $out[] = $this->finding($status);
        }

        return $out;
    }

    /**
     * @return array{name:string, version:string}
     */
    public function package(): array
    {
        return [
            'name' => $this->word(3, 10) . '/' . $this->word(3, 10),
            'version' => $this->int(0, 9) . '.' . $this->int(0, 20) . '.' . $this->int(0, 20),
        ];
    }

    /**
     * @return list<array{name:string, version:string}>
     */
    public function packages(int $min = 0, int $max = 5): array
    {
        $out = [];
        for ($i = $this->int($min, $max); $i > 0; $i--) {
            $out[] = $this->package();
        }

        return $out;
    }

    /**
     * Full ingest payload.
     *
     * @return array{packages:list<array{name:string,version:string}>, findings:list<array{severity:string,description:string,status:string}>}
     */
    public function findingsPayload(int $minFindings = 0, int $maxFindings = 10): array
    {
        return [
            'packages' => $this->packages(),
            'findings' => $this->findings($minFindings, $maxFindings),
        ];
    }

    /**
     * Payload whose findings count exceeds the 1000 limit by 1 to $over.
     *
     * @return array{packages:list<array>, findings:list<array>}
     */
    public function oversizedFindingsPayload(int $over = 5): array
    {
        $finding = $this->finding('open');
        $count = 1000 + $this->int(1, $over);

        return ['packages' => [], 'findings' => array_fill(0, $count, $finding)];
    }

    // ------------------------------------------------------------------ internals

    private function next(): int
    {
        return $this->rng->getInt(0, PHP_INT_MAX);
    }
}
