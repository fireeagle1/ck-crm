<?php

namespace Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use Tests\Support\Gen;

class GenTest extends TestCase
{
    private function sample(Gen $g): array
    {
        $canonical = $g->canonicalDomain();

        return [
            $g->string(0, 30),
            $canonical,
            $g->domainVariant($canonical),
            $g->unicodeDomain(),
            $g->invalidDomain(),
            $g->serviceType(),
            $g->serviceStatus(),
            $g->legacyLabel(),
            $g->quantities(),
            $g->stock(),
            $g->findingsPayload(),
        ];
    }

    public function test_same_seed_gives_same_output(): void
    {
        foreach (Gen::seeds(50) as $seed) {
            $this->assertSame($this->sample(new Gen($seed)), $this->sample(new Gen($seed)), "seed={$seed}");
        }
    }

    public function test_different_seeds_give_different_output(): void
    {
        $this->assertNotSame($this->sample(new Gen(1)), $this->sample(new Gen(2)));
    }

    public function test_message_contains_seed(): void
    {
        $g = new Gen(1234);
        $g->int(1, 5);

        $this->assertStringContainsString('seed=1234', $g->msg('extra'));
        $this->assertStringContainsString('extra', $g->msg('extra'));
    }

    public function test_generators_respect_their_contracts(): void
    {
        foreach (Gen::seeds(200) as $seed) {
            $g = new Gen($seed);
            $m = $g->msg();

            $this->assertGreaterThanOrEqual(1, $g->quantity(), $m);
            $this->assertMatchesRegularExpression('/^[a-z0-9-]+(\.[a-z0-9-]+)+$/', $g->canonicalDomain(), $m);
            $this->assertStringNotContainsString('www.', substr($g->canonicalDomain(), 0, 4), $m);

            $variant = $g->domainVariant('example.com');
            $this->assertSame('example.com', rtrim(preg_replace('/^(www\.)+/', '', strtolower(trim($variant))), '.'), $m);

            $uni = $g->unicodeDomain();
            $this->assertNotSame('', $uni['canonical'], $m);
            $this->assertMatchesRegularExpression('/^xn--/', $uni['canonical'], $m);

            $payload = $g->findingsPayload(1, 5);
            $this->assertNotEmpty($payload['findings'], $m);
            $this->assertContains($payload['findings'][0]['status'], ['open', 'resolved', 'dismissed'], $m);

            $this->assertGreaterThan(1000, count($g->oversizedFindingsPayload()['findings']), $m);
        }
    }
}
