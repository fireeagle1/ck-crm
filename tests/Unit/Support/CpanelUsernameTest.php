<?php

namespace Tests\Unit\Support;

use App\Support\CpanelUsername;
use Tests\TestCase;

class CpanelUsernameTest extends TestCase
{
    private function take(string $domain, int $n): array
    {
        $out = [];
        foreach (CpanelUsername::candidates($domain) as $c) {
            $out[] = $c;
            if (count($out) >= $n) {
                break;
            }
        }

        return $out;
    }

    public function test_simple_domain(): void
    {
        $this->assertSame(['example', 'example2', 'example3'], $this->take('example.com', 3));
    }

    public function test_long_domain_suffix_replaces_tail(): void
    {
        $c = $this->take('averylongdomainname.com', 12);
        $this->assertSame('averylon', $c[0]);
        $this->assertSame('averylo2', $c[1]);
        $this->assertSame('averyl10', $c[9]);
    }

    public function test_digits_only_gets_letter_prefix(): void
    {
        $this->assertSame('c1234567', $this->take('12345678901.com', 1)[0]);
    }

    public function test_reserved_word_is_skipped(): void
    {
        $c = $this->take('root.com', 2);
        $this->assertSame(['root2', 'root3'], $c);
        $this->assertFalse(CpanelUsername::isValid('root'));
    }

    public function test_unicode_and_hyphens(): void
    {
        foreach (['bücher.de', 'münchen-café.com', '例え.jp', 'my-site.co.uk', 'a.com'] as $d) {
            foreach ($this->take($d, 5) as $c) {
                $this->assertTrue(CpanelUsername::isValid($c), "$d -> $c");
            }
        }
        $this->assertSame('mysite', $this->take('my-site.co.uk', 1)[0]);
        $this->assertSame('a00', $this->take('a.com', 1)[0]);
    }

    public function test_respects_configured_max_length(): void
    {
        config(['hosting.username_max_length' => 6]);
        foreach ($this->take('averylongdomainname.com', 50) as $c) {
            $this->assertLessThanOrEqual(6, strlen($c));
            $this->assertTrue(CpanelUsername::isValid($c));
        }
    }

    public function test_is_valid_rules(): void
    {
        $this->assertTrue(CpanelUsername::isValid('abc'));
        $this->assertFalse(CpanelUsername::isValid('ab'));
        $this->assertFalse(CpanelUsername::isValid('abcdefghi'));
        $this->assertFalse(CpanelUsername::isValid('1abc'));
        $this->assertFalse(CpanelUsername::isValid('Abc'));
        $this->assertFalse(CpanelUsername::isValid('ab-c'));
    }
}
