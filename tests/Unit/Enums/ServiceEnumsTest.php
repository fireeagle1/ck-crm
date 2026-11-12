<?php

namespace Tests\Unit\Enums;

use App\Enums\DomainSyncStatus;
use App\Enums\ProvisioningStatus;
use App\Enums\ServiceStatus;
use App\Enums\ServiceType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ServiceEnumsTest extends TestCase
{
    public function test_exact_labels(): void
    {
        $this->assertSame(['Technical Support', 'Web Hosting', 'Other'], ServiceType::values());
        $this->assertSame(['Pending', 'Active', 'Suspended', 'Cancelled'], ServiceStatus::values());
        $this->assertSame(['queued', 'running', 'succeeded', 'failed'], ProvisioningStatus::values());
        $this->assertSame(['active', 'missing_from_enom'], DomainSyncStatus::values());
    }

    public function test_alias_constants(): void
    {
        $this->assertSame(['hosting' => 'Web Hosting'], ServiceType::ALIASES);
        $this->assertSame(
            ['pending' => 'Pending', 'payment_failed' => 'Suspended', 'cancelled' => 'Cancelled'],
            ServiceStatus::ALIASES
        );
    }

    public function test_type_alias_mapping(): void
    {
        $this->assertSame(ServiceType::WebHosting, ServiceType::fromLoose('hosting'));
    }

    public static function statusAliases(): array
    {
        return [
            ['pending', ServiceStatus::Pending],
            ['payment_failed', ServiceStatus::Suspended],
            ['cancelled', ServiceStatus::Cancelled],
        ];
    }

    #[DataProvider('statusAliases')]
    public function test_status_alias_mapping(string $raw, ServiceStatus $expected): void
    {
        $this->assertSame($expected, ServiceStatus::fromLoose($raw));
    }

    public function test_whitespace_and_case_handling(): void
    {
        $this->assertSame(ServiceType::WebHosting, ServiceType::fromLoose("  HOSTING \n"));
        $this->assertSame(ServiceType::WebHosting, ServiceType::fromLoose(' web hosting '));
        $this->assertSame(ServiceType::TechnicalSupport, ServiceType::fromLoose("\ttechnical SUPPORT"));
        $this->assertSame(ServiceStatus::Active, ServiceStatus::fromLoose(' aCtIvE '));
        $this->assertSame(ServiceStatus::Suspended, ServiceStatus::fromLoose(' Payment_Failed '));
    }

    public function test_unknown_returns_null(): void
    {
        foreach ([null, '', '   ', 'Domain Registration', 'web  hosting'] as $raw) {
            $this->assertNull(ServiceType::fromLoose($raw));
        }
        foreach ([null, '', 'paused', 'active!'] as $raw) {
            $this->assertNull(ServiceStatus::fromLoose($raw));
        }
    }
}
