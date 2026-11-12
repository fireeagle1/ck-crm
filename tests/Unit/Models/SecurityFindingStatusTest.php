<?php

namespace Tests\Unit\Models;

use App\Models\CustomerApp;
use App\Models\SecurityFinding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Validates: Requirements 17.4 */
class SecurityFindingStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_statuses_include_not_reported(): void
    {
        $this->assertSame('not_reported', SecurityFinding::STATUS_NOT_REPORTED);
        $this->assertContains('not_reported', SecurityFinding::STATUSES);
    }

    public function test_payload_statuses_exclude_not_reported(): void
    {
        $this->assertSame(['open', 'resolved', 'dismissed'], SecurityFinding::PAYLOAD_STATUSES);
    }

    public function test_open_findings_counts_only_open(): void
    {
        $app = CustomerApp::factory()->create();

        foreach (SecurityFinding::STATUSES as $status) {
            SecurityFinding::factory()->create(['customer_app_id' => $app->id, 'status' => $status]);
        }

        $this->assertSame(1, $app->openFindings()->count());
        $this->assertSame(count(SecurityFinding::STATUSES), $app->findings()->count());
    }
}
