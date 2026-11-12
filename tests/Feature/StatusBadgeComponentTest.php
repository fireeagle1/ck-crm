<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StatusBadgeComponentTest extends TestCase
{
    /** @return array<string, array{string, string|null, string, string, string}> */
    public static function cases(): array
    {
        return [
            'service pending' => ['service', 'Pending', 'Status', 'Pending', 'bg-amber-100'],
            'service active' => ['service', 'Active', 'Status', 'Active', 'bg-green-100'],
            'service suspended' => ['service', 'Suspended', 'Status', 'Suspended', 'bg-red-100'],
            'service cancelled' => ['service', 'Cancelled', 'Status', 'Cancelled', 'bg-red-100'],
            'prov queued' => ['provisioning', 'queued', 'Provisioning status', 'Queued', 'bg-blue-100'],
            'prov running' => ['provisioning', 'running', 'Provisioning status', 'Running', 'bg-amber-100'],
            'prov succeeded' => ['provisioning', 'succeeded', 'Provisioning status', 'Succeeded', 'bg-green-100'],
            'prov failed' => ['provisioning', 'failed', 'Provisioning status', 'Failed', 'bg-red-100'],
            'prov null' => ['provisioning', null, 'Provisioning status', 'Not tracked', 'bg-gray-100'],
            'domain active' => ['domain-sync', 'active', 'Sync status', 'Active', 'bg-green-100'],
            'domain missing' => ['domain-sync', 'missing_from_enom', 'Sync status', 'Missing from eNom', 'bg-amber-100'],
        ];
    }

    #[DataProvider('cases')]
    public function test_renders_label_and_accessible_name(string $type, ?string $status, string $prefix, string $label, string $colour): void
    {
        $html = Blade::render('<x-status-badge :type="$type" :status="$status" />', compact('type', 'status'));

        $this->assertStringContainsString('<span class="sr-only">'.$prefix.': </span>'.$label, $html);
        $this->assertStringContainsString($colour, $html);
    }

    public function test_unknown_status_falls_back_to_neutral_text_label(): void
    {
        $html = Blade::render('<x-status-badge type="service" status="Weird" />');

        $this->assertStringContainsString('Weird', $html);
        $this->assertStringContainsString('bg-gray-100', $html);
    }
}
