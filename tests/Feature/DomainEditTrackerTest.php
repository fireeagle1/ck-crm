<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Services\DomainEditTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DomainEditTrackerTest extends TestCase
{
    use RefreshDatabase;

    private function domain(): Domain
    {
        return Domain::factory()->create([
            'expiry_date' => '2027-01-15',
            'auto_renew' => true,
            'registrar' => 'eNom',
            'enom_domain_name_id' => '123',
            'domain_admin_notes' => 'old',
            'admin_edited_fields' => null,
        ]);
    }

    public function test_records_only_changed_enom_fields(): void
    {
        $domain = $this->domain();

        (new DomainEditTracker)->record($domain, [
            'expiry_date' => '2028-01-15',
            'auto_renew' => '1',
            'registrar' => 'eNom',
            'enom_domain_name_id' => '123',
        ]);

        $this->assertSame(['expiry_date'], $domain->fresh()->admin_edited_fields);
    }

    public function test_unchanged_values_with_different_types_are_not_flagged(): void
    {
        $domain = $this->domain();

        (new DomainEditTracker)->record($domain, [
            'expiry_date' => '2027-01-15 00:00:00',
            'auto_renew' => 'on',
            'enom_domain_name_id' => 123,
        ]);

        $this->assertNull($domain->fresh()->admin_edited_fields);
    }

    public function test_non_enom_fields_ignored_and_flags_merge(): void
    {
        $domain = $this->domain();
        $tracker = new DomainEditTracker;

        $tracker->record($domain, ['domain_admin_notes' => 'new', 'cost' => 99, 'auto_renew' => false]);
        $tracker->record($domain->fresh(), ['registrar' => 'Other']);

        $this->assertEqualsCanonicalizing(['auto_renew', 'registrar'], $domain->fresh()->admin_edited_fields);
    }

    public function test_reset_clears_all_or_selected_flags(): void
    {
        $domain = $this->domain();
        $domain->update(['admin_edited_fields' => ['expiry_date', 'registrar']]);
        $tracker = new DomainEditTracker;

        $tracker->reset($domain, ['registrar']);
        $this->assertSame(['expiry_date'], $domain->fresh()->admin_edited_fields);

        $tracker->reset($domain);
        $this->assertEmpty($domain->fresh()->admin_edited_fields);
    }
}
