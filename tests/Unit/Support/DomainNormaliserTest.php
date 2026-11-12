<?php

namespace Tests\Unit\Support;

use App\Exceptions\InvalidDomainException;
use App\Support\DomainNormaliser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\Gen;

class DomainNormaliserTest extends TestCase
{
    public static function knownExamples(): array
    {
        return [
            ['Example.COM', 'example.com'],
            ['  example.com  ', 'example.com'],
            ['example.com.', 'example.com'],
            ['example.com...', 'example.com'],
            ['www.example.com', 'example.com'],
            ['WWW.www.Example.co.uk.', 'example.co.uk'],
            ['bücher.de', 'xn--bcher-kva.de'],
            ['sub.my-site.io', 'sub.my-site.io'],
        ];
    }

    #[DataProvider('knownExamples')]
    public function test_known_examples(string $in, string $out): void
    {
        $this->assertSame($out, DomainNormaliser::normalise($in));
        $this->assertSame($out, DomainNormaliser::tryNormalise($in));
    }

    public static function invalidExamples(): array
    {
        return [
            'empty' => [''], 'spaces' => ['   '], 'dots' => ['...'], 'www only' => ['www.'],
            'single label' => ['localhost'], 'leading hyphen' => ['-a.com'],
            'trailing hyphen' => ['a-.com'], 'empty label' => ['a..com'],
            'underscore' => ['a_b.com'], 'numeric tld' => ['example.123'],
            'one letter tld' => ['example.c'], 'space inside' => ['exa mple.com'],
            'url' => ['https://example.com'], 'label 64' => [str_repeat('a', 64) . '.com'],
            'too long' => [str_repeat('a.', 127) . 'com'],
        ];
    }

    #[DataProvider('invalidExamples')]
    public function test_rejects_invalid(string $in): void
    {
        $this->assertNull(DomainNormaliser::tryNormalise($in));
        $this->expectException(InvalidDomainException::class);
        DomainNormaliser::normalise($in);
    }

    public function test_exception_is_invalid_argument_exception(): void
    {
        $this->assertInstanceOf(\InvalidArgumentException::class, new InvalidDomainException('x'));
    }

    public function test_try_normalise_null(): void
    {
        $this->assertNull(DomainNormaliser::tryNormalise(null));
    }

    public function test_label_of_63_chars_accepted(): void
    {
        $d = str_repeat('a', 63) . '.com';
        $this->assertSame($d, DomainNormaliser::normalise($d));
    }

    public function test_to_https_url(): void
    {
        $this->assertSame('https://example.com', DomainNormaliser::toHttpsUrl('example.com'));
    }

    public function test_idempotent_on_canonical_input(): void
    {
        foreach (Gen::seeds(100) as $seed) {
            $g = new Gen($seed);
            $c = $g->canonicalDomain();
            $this->assertSame($c, DomainNormaliser::normalise($c), $g->msg());
            $once = DomainNormaliser::normalise($g->domainVariant($c));
            $this->assertSame($once, DomainNormaliser::normalise($once), $g->msg());
        }
    }

    public function test_variants_normalise_to_canonical(): void
    {
        foreach (Gen::seeds(100) as $seed) {
            $g = new Gen($seed);
            $c = $g->canonicalDomain();
            $this->assertSame($c, DomainNormaliser::normalise($g->domainVariant($c)), $g->msg());
        }
    }

    public function test_unicode_converts_to_expected_punycode(): void
    {
        foreach (Gen::seeds(100) as $seed) {
            $g = new Gen($seed);
            $case = $g->unicodeDomain();
            $this->assertSame($case['canonical'], DomainNormaliser::normalise($case['input']), $g->msg());
        }
    }

    public function test_generated_invalid_domains_rejected(): void
    {
        foreach (Gen::seeds(100) as $seed) {
            $g = new Gen($seed);
            $this->assertNull(DomainNormaliser::tryNormalise($g->invalidDomain()), $g->msg());
        }
    }
}
